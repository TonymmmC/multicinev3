<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para realizar pedidos', 'warning');
    redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setMensaje('Acceso inválido', 'warning');
    redirect('/multicinev3/');
}

// Get form data
$productosSeleccionados = isset($_POST['productos_seleccionados']) ? $_POST['productos_seleccionados'] : '';
$cineId = isset($_POST['cine_id']) ? intval($_POST['cine_id']) : 0;
$total = isset($_POST['total']) ? floatval($_POST['total']) : 0;
$metodoPago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : '';
$paymentDetails = isset($_POST['payment_details']) ? $_POST['payment_details'] : '';

if (empty($productosSeleccionados) || $total <= 0 || empty($metodoPago)) {
    setMensaje('Datos incompletos para procesar el pedido', 'warning');
    redirect('/multicinev3/candybar.php');
}

// Decode selected products
$carrito = json_decode($productosSeleccionados, true);
if (empty($carrito)) {
    setMensaje('Carrito de compras vacío', 'warning');
    redirect('/multicinev3/candybar.php');
}

// Get user info
$userId = $_SESSION['user_id'];

// Begin transaction
$conn->begin_transaction();

try {
    // Create pedido record without reserva_id (it's nullable in the database)
    $queryPedido = "INSERT INTO pedidos_candy_bar (user_id, fecha_pedido, total, estado, reserva_id) 
                    VALUES (?, NOW(), ?, 'pendiente', NULL)";
    $stmtPedido = $conn->prepare($queryPedido);
    $stmtPedido->bind_param("id", $userId, $total);
    $stmtPedido->execute();
    $pedidoId = $conn->insert_id;
    
    // Process each item in cart
    foreach ($carrito as $item) {
        if ($item['type'] === 'producto') {
            // Add product to pedido detail
            $queryDetalle = "INSERT INTO detalle_pedidos_candy (pedido_id, producto_id, cantidad, precio_unitario, descuento) 
                            VALUES (?, ?, ?, ?, 0)";
            $stmtDetalle = $conn->prepare($queryDetalle);
            $stmtDetalle->bind_param("iiid", $pedidoId, $item['id'], $item['quantity'], $item['price']);
            $stmtDetalle->execute();
            
            // Update inventory
            $queryInventario = "UPDATE inventario_cine SET stock = stock - ? 
                               WHERE cine_id = ? AND producto_id = ? AND stock >= ?";
            $stmtInventario = $conn->prepare($queryInventario);
            $stmtInventario->bind_param("iiii", $item['quantity'], $cineId, $item['id'], $item['quantity']);
            $stmtInventario->execute();
            
            if ($stmtInventario->affected_rows === 0) {
                // Not enough stock
                throw new Exception("No hay suficiente stock para " . $item['name']);
            }
        } elseif ($item['type'] === 'combo') {
            // For combos, get a valid product_id from the combo's first product
            $queryFirstProduct = "SELECT cp.producto_id FROM combo_producto cp WHERE cp.combo_id = ? LIMIT 1";
            $stmtFirstProduct = $conn->prepare($queryFirstProduct);
            $comboIdPositive = intval($item['id']);
            $stmtFirstProduct->bind_param("i", $comboIdPositive);
            $stmtFirstProduct->execute();
            $resultFirstProduct = $stmtFirstProduct->get_result();
            $firstProduct = $resultFirstProduct->fetch_assoc();
            
            if (!$firstProduct) {
                throw new Exception("No se encontraron productos asociados al combo: " . $item['name']);
            }
            
            // Use the first product's ID as producto_id for the detalle record
            $productoId = $firstProduct['producto_id'];
            
            // Add combo to pedido detail using a real product_id
            $queryDetalle = "INSERT INTO detalle_pedidos_candy (pedido_id, producto_id, cantidad, precio_unitario, descuento) 
                            VALUES (?, ?, ?, ?, 0)";
            $stmtDetalle = $conn->prepare($queryDetalle);
            $stmtDetalle->bind_param("iiid", $pedidoId, $productoId, $item['quantity'], $item['price']);
            $stmtDetalle->execute();
            
            // Store the combo association in a separate table or field
            // (This is a placeholder - ideally you'd add a combo_id field to detalle_pedidos_candy)
            
            // Get combo products and update inventory
            $queryComboProductos = "SELECT cp.producto_id, cp.cantidad 
                                   FROM combo_producto cp
                                   WHERE cp.combo_id = ?";
            $stmtComboProductos = $conn->prepare($queryComboProductos);
            $stmtComboProductos->bind_param("i", $comboIdPositive);
            $stmtComboProductos->execute();
            $resultComboProductos = $stmtComboProductos->get_result();
            
            while ($producto = $resultComboProductos->fetch_assoc()) {
                // Calculate total quantity needed
                $cantidadNecesaria = $producto['cantidad'] * $item['quantity'];
                
                // Update inventory
                $queryInventario = "UPDATE inventario_cine SET stock = stock - ? 
                                   WHERE cine_id = ? AND producto_id = ? AND stock >= ?";
                $stmtInventario = $conn->prepare($queryInventario);
                $stmtInventario->bind_param("iiii", $cantidadNecesaria, $cineId, $producto['producto_id'], $cantidadNecesaria);
                $stmtInventario->execute();
                
                if ($stmtInventario->affected_rows === 0) {
                    // Not enough stock
                    throw new Exception("No hay suficiente stock para los productos del combo");
                }
            }
        }
    }
    
    // Process payment
    $paymentInfo = json_decode($paymentDetails, true);
    $metodoId = null;
    
    if ($metodoPago === 'tarjeta' && isset($paymentInfo['saved_card_id'])) {
        // Using saved card
        $metodoId = $paymentInfo['saved_card_id'];
    } elseif ($metodoPago === 'tarjeta' && isset($paymentInfo['save_card']) && $paymentInfo['save_card']) {
        // Save new card
        $queryMetodo = "INSERT INTO metodos_pago (user_id, tipo, alias, token_secure, ultimos_digitos, marca, predeterminado) 
                        VALUES (?, 'tarjeta', ?, ?, ?, ?, 0)";
        $cardAlias = "Tarjeta " . substr($paymentInfo['card_number'], -4);
        $tokenSecure = md5(time() . $userId . rand(1000, 9999)); // This should be replaced with proper encryption
        $ultimosDigitos = substr($paymentInfo['card_number'], -4);
        $marca = 'visa'; // This should be determined based on card number
        
        $stmtMetodo = $conn->prepare($queryMetodo);
        $stmtMetodo->bind_param("issss", $userId, $cardAlias, $tokenSecure, $ultimosDigitos, $marca);
        $stmtMetodo->execute();
        $metodoId = $conn->insert_id;
    }
    
    // Update pedido with payment method
    if ($metodoId) {
        $queryUpdatePedido = "UPDATE pedidos_candy_bar SET metodo_pago_id = ? WHERE id = ?";
        $stmtUpdatePedido = $conn->prepare($queryUpdatePedido);
        $stmtUpdatePedido->bind_param("ii", $metodoId, $pedidoId);
        $stmtUpdatePedido->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Clear cart from session
    unset($_SESSION['cart']);
    
    // Redirect to success page
    setMensaje('Pedido realizado con éxito', 'success');
    redirect("ticket_candybar.php?pedido=$pedidoId");
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    setMensaje($e->getMessage(), 'danger');
    redirect('/multicinev3/candybar.php');
}
?>