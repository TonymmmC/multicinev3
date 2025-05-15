<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Verificar sesión
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para continuar con la reserva', 'warning');
    redirect('auth/login.php');
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setMensaje('Acceso inválido', 'warning');
    redirect('/multicinev3/');
}

// Obtener datos del formulario
$funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
$asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';
$productosSeleccionados = isset($_POST['productos_seleccionados']) ? $_POST['productos_seleccionados'] : '';
$codigoPromocional = isset($_POST['codigo_promocional']) ? $_POST['codigo_promocional'] : '';
$descuentoAplicado = isset($_POST['descuento_aplicado']) ? floatval($_POST['descuento_aplicado']) : 0;
$metodoPago = isset($_POST['metodo_pago']) ? $_POST['metodo_pago'] : 'qr';
$paymentDetails = isset($_POST['payment_details']) ? $_POST['payment_details'] : '';

// Validar datos básicos
if (!$funcionId || empty($asientosSeleccionados)) {
    setMensaje('Datos de reserva incompletos', 'warning');
    redirect('/multicinev3/');
}

// Comenzar transacción
$conn->begin_transaction();

try {
    // Obtener detalles de la función
    $queryFuncion = "SELECT f.*, s.id as sala_id, p.id as pelicula_id 
                     FROM funciones f 
                     JOIN salas s ON f.sala_id = s.id 
                     JOIN peliculas p ON f.pelicula_id = p.id 
                     WHERE f.id = ?";
    $stmtFuncion = $conn->prepare($queryFuncion);
    $stmtFuncion->bind_param("i", $funcionId);
    $stmtFuncion->execute();
    $resultFuncion = $stmtFuncion->get_result();

    if ($resultFuncion->num_rows === 0) {
        throw new Exception("Función no encontrada");
    }

    $funcion = $resultFuncion->fetch_assoc();

    // Procesar ID de asientos
    $asientosIds = explode(',', $asientosSeleccionados);
    $asientosIds = array_map('intval', $asientosIds);
    $numAsientos = count($asientosIds);

    // Verificar disponibilidad de asientos
    $placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
    $queryVerificarAsientos = "SELECT id FROM asientos WHERE id IN ($placeholders) AND disponible = 0";
    
    $stmtVerificarAsientos = $conn->prepare($queryVerificarAsientos);
    $types = str_repeat('i', count($asientosIds));
    $stmtVerificarAsientos->bind_param($types, ...$asientosIds);
    $stmtVerificarAsientos->execute();
    $resultVerificarAsientos = $stmtVerificarAsientos->get_result();

    if ($resultVerificarAsientos->num_rows > 0) {
        throw new Exception("Algunos asientos seleccionados ya no están disponibles");
    }

    // Calcular costos
    $costoAsientos = $funcion['precio_base'] * $numAsientos;
    $costoProductos = 0;
    
    // Procesar productos si los hay
    $productosData = [];
    $costoTotalProductos = 0; // I'd keep this as costoTotalProductos since it's used elsewhere

    if (!empty($productosSeleccionados)) {
        try {
            $productosData = json_decode($productosSeleccionados, true);
            
            // Verificar que la decodificación no falló
            if ($productosData === null && json_last_error() !== JSON_ERROR_NONE) {
                // Log el error
                error_log('Error decodificando productos: ' . json_last_error_msg());
                $productosData = [];
            } else {
                // Calcular productos total - solo si la decodificación fue exitosa
                foreach ($productosData as $producto) {
                    // Verificar que tiene las propiedades requeridas
                    if (isset($producto['price']) && isset($producto['quantity'])) {
                        $costoTotalProductos += $producto['price'] * $producto['quantity'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Excepción procesando productos: ' . $e->getMessage());
            $productosData = [];
            $costoTotalProductos = 0;
        }
    }
    
    // Calcular total antes de descuento
    $totalSinDescuento = $costoAsientos + $costoProductos;
    $totalConDescuento = $totalSinDescuento;
    
    // Aplicar descuento si hay
    $promocionId = null;
    if ($descuentoAplicado > 0 && !empty($codigoPromocional)) {
        // Obtener información del código promocional
        $queryPromo = "SELECT id, tipo FROM promociones WHERE codigo_promocional = ? AND activa = 1 LIMIT 1";
        $stmtPromo = $conn->prepare($queryPromo);
        $stmtPromo->bind_param("s", $codigoPromocional);
        $stmtPromo->execute();
        $resultPromo = $stmtPromo->get_result();
        
        if ($resultPromo->num_rows > 0) {
            $promo = $resultPromo->fetch_assoc();
            $promocionId = $promo['id'];
            
            if ($promo['tipo'] === 'porcentaje') {
                $totalConDescuento = $totalSinDescuento * (1 - ($descuentoAplicado / 100));
            } else {
                $totalConDescuento = $totalSinDescuento - $descuentoAplicado;
            }
            
            // Asegurar que el total no sea negativo
            $totalConDescuento = max(0, $totalConDescuento);
            
            // Actualizar usos del código promocional
            $queryUpdatePromo = "UPDATE promociones SET usos_actuales = usos_actuales + 1 WHERE id = ?";
            $stmtUpdatePromo = $conn->prepare($queryUpdatePromo);
            $stmtUpdatePromo->bind_param("i", $promocionId);
            $stmtUpdatePromo->execute();
        }
    }
    
    // Procesar método de pago
    $metodoPagoId = null;
    
    if ($metodoPago === 'tarjeta') {
        // Procesar detalles de pago
        $paymentData = json_decode($paymentDetails, true);
        
        if (isset($paymentData['saved_card_id'])) {
            // Usar tarjeta guardada
            $metodoPagoId = $paymentData['saved_card_id'];
        } else if (isset($paymentData['save_card']) && $paymentData['save_card']) {
            // Guardar nueva tarjeta
            $userId = $_SESSION['user_id'];
            $alias = "Tarjeta " . substr($paymentData['card_number'], -4);
            $ultimosDigitos = substr($paymentData['card_number'], -4);
            
            // Determinar marca de tarjeta basada en primeros dígitos
            $cardNumber = str_replace(' ', '', $paymentData['card_number']);
            $marca = '';
            
            if (substr($cardNumber, 0, 1) === '4') {
                $marca = 'visa';
            } else if (substr($cardNumber, 0, 1) === '5') {
                $marca = 'mastercard';
            } else {
                $marca = 'otro';
            }
            
            // Crear token seguro (en producción, usar encriptación adecuada)
            $tokenSecure = password_hash($cardNumber, PASSWORD_DEFAULT);
            
            $queryInsertTarjeta = "INSERT INTO metodos_pago (user_id, tipo, alias, token_secure, ultimos_digitos, marca, predeterminado)
                                  VALUES (?, 'tarjeta', ?, ?, ?, ?, 0)";
            $stmtInsertTarjeta = $conn->prepare($queryInsertTarjeta);
            $stmtInsertTarjeta->bind_param("issss", $userId, $alias, $tokenSecure, $ultimosDigitos, $marca);
            $stmtInsertTarjeta->execute();
            
            $metodoPagoId = $conn->insert_id;
        }
    } else if ($metodoPago === 'qr' || $metodoPago === 'tigo_money') {
        // En un sistema real, aquí se procesaría el pago con el proveedor correspondiente
        // Para este ejemplo, creamos un registro de método de pago temporal
        $userId = $_SESSION['user_id'];
        $alias = $metodoPago === 'qr' ? "Pago QR" : "Tigo Money";
        $tokenSecure = bin2hex(random_bytes(16)); // Generar token aleatorio

        $queryInsertMetodo = "INSERT INTO metodos_pago (user_id, tipo, alias, token_secure, predeterminado)
                            VALUES (?, ?, ?, ?, 0)";
        $stmtInsertMetodo = $conn->prepare($queryInsertMetodo);
        $stmtInsertMetodo->bind_param("isss", $userId, $metodoPago, $alias, $tokenSecure);
        $stmtInsertMetodo->execute();
        
        $metodoPagoId = $conn->insert_id;
    }
    
    // Crear reserva
    $userId = $_SESSION['user_id'];
    $estado = 'aprobado'; // En un sistema real, sería 'pendiente' hasta confirmar el pago
    
    $queryInsertReserva = "INSERT INTO reservas (user_id, funcion_id, metodo_pago_id, fecha_reserva, total_pagado, estado, codigo_promocional, descuento_aplicado, promocion_id)
                          VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
    $stmtInsertReserva = $conn->prepare($queryInsertReserva);
    $stmtInsertReserva->bind_param("iiidssis", $userId, $funcionId, $metodoPagoId, $totalConDescuento, $estado, $codigoPromocional, $descuentoAplicado, $promocionId);
    $stmtInsertReserva->execute();
    
    $reservaId = $conn->insert_id;
    
    // Registrar asientos reservados
    $queryInsertAsientos = "INSERT INTO asientos_reservados (reserva_id, asiento_id, sala_id, precio_final)
                           VALUES (?, ?, ?, ?)";
    $stmtInsertAsientos = $conn->prepare($queryInsertAsientos);
    
    foreach ($asientosIds as $asientoId) {
        $stmtInsertAsientos->bind_param("iiid", $reservaId, $asientoId, $funcion['sala_id'], $funcion['precio_base']);
        $stmtInsertAsientos->execute();
    }
    
    // Marcar asientos como no disponibles
    $queryUpdateAsientos = "UPDATE asientos SET disponible = 0 WHERE id IN ($placeholders)";
    $stmtUpdateAsientos = $conn->prepare($queryUpdateAsientos);
    $stmtUpdateAsientos->bind_param($types, ...$asientosIds);
    $stmtUpdateAsientos->execute();
    
    // Registrar productos si hay
    if (!empty($productosData)) {
        // Crear pedido de candy bar
        $queryInsertPedido = "INSERT INTO pedidos_candy_bar (reserva_id, user_id, fecha_pedido, total, estado, promocion_id)
                             VALUES (?, ?, NOW(), ?, 'pendiente', ?)";
        $stmtInsertPedido = $conn->prepare($queryInsertPedido);
        $stmtInsertPedido->bind_param("iidi", $reservaId, $userId, $costoProductos, $promocionId);
        $stmtInsertPedido->execute();
        
        $pedidoId = $conn->insert_id;
        
        // Registrar detalle de productos
        $queryInsertDetalle = "INSERT INTO detalle_pedidos_candy (pedido_id, producto_id, cantidad, precio_unitario, descuento)
                              VALUES (?, ?, ?, ?, 0)";
        $stmtInsertDetalle = $conn->prepare($queryInsertDetalle);
        
        foreach ($productosData as $producto) {
            $productoId = $producto['id'];
            $cantidad = $producto['quantity'];
            $precio = $producto['price'];
            
            $stmtInsertDetalle->bind_param("iiid", $pedidoId, $productoId, $cantidad, $precio);
            $stmtInsertDetalle->execute();
        }
    }
    
    // Generar tickets
    $queryInsertTicket = "INSERT INTO tickets (reserva_id, codigo_barras, ci_usuario, usado)
                         VALUES (?, ?, ?, 0)";
    $stmtInsertTicket = $conn->prepare($queryInsertTicket);
    
    // Obtener NIT/CI del usuario
    $queryUsuario = "SELECT nit_ci FROM perfiles_usuario WHERE user_id = ? LIMIT 1";
    $stmtUsuario = $conn->prepare($queryUsuario);
    $stmtUsuario->bind_param("i", $userId);
    $stmtUsuario->execute();
    $resultUsuario = $stmtUsuario->get_result();
    $datosUsuario = $resultUsuario->fetch_assoc();
    $ciUsuario = $datosUsuario['nit_ci'] ?? 'SIN CI';
    
    // Generar un ticket por cada asiento
    for ($i = 0; $i < $numAsientos; $i++) {
        // Generar código de barras único
        $codigoBarras = 'MC' . date('ymd') . str_pad($reservaId, 6, '0', STR_PAD_LEFT) . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
        
        $stmtInsertTicket->bind_param("iss", $reservaId, $codigoBarras, $ciUsuario);
        $stmtInsertTicket->execute();
    }
    
    // Si todo está bien, confirmar la transacción
    $conn->commit();
    
    // Redirigir a página de confirmación
    setMensaje('¡Reserva realizada con éxito!', 'success');
    redirect('ticket.php?reserva=' . $reservaId);
    
} catch (Exception $e) {
    // Si hay error, revertir los cambios
    $conn->rollback();
    
    setMensaje('Error al procesar la reserva: ' . $e->getMessage(), 'danger');
    redirect('confirmar_compra.php');
}
?>