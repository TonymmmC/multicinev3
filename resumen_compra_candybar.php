<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
   // If not logged in, store the form data in session to recover after login
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $_SESSION['temp_candybar_cart'] = $_POST;
   }
   
   setMensaje('Debe iniciar sesión para continuar con la compra', 'warning');
   redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   // Check if there's a stored reservation
   if (isset($_SESSION['temp_candybar_cart'])) {
       $_POST = $_SESSION['temp_candybar_cart'];
       unset($_SESSION['temp_candybar_cart']);
   } else {
       setMensaje('Acceso inválido', 'warning');
       redirect('/multicinev3/candybar.php');
   }
}

// Get form data
$productosSeleccionados = isset($_POST['productos_seleccionados']) ? $_POST['productos_seleccionados'] : '';
$cineId = isset($_POST['cine_id']) ? intval($_POST['cine_id']) : 1;

if (empty($productosSeleccionados)) {
   setMensaje('Debe seleccionar al menos un producto', 'warning');
   redirect('candybar.php');
}

// Decode productos seleccionados
$carrito = json_decode($productosSeleccionados, true);

if (empty($carrito)) {
   setMensaje('El carrito está vacío', 'warning');
   redirect('candybar.php');
}

// Get cine details
$queryCine = "SELECT nombre, direccion FROM cines WHERE id = ?";
$stmtCine = $conn->prepare($queryCine);
$stmtCine->bind_param("i", $cineId);
$stmtCine->execute();
$resultCine = $stmtCine->get_result();

if ($resultCine->num_rows === 0) {
   setMensaje('Cine no encontrado', 'warning');
   redirect('candybar.php');
}

$cine = $resultCine->fetch_assoc();

// Process products and combos in cart
$productos = [];
$combos = [];
$totalCarrito = 0;

foreach ($carrito as $item) {
    if ($item['type'] === 'producto') {
        // Get product details
        $queryProducto = "SELECT p.id, p.nombre, p.precio_unitario, c.nombre as categoria,
                         CONCAT('assets/img/candybar/productos/', p.id, '.jpg') as imagen_url,
                         ic.stock
                         FROM productos p
                         JOIN categorias_producto c ON p.categoria_id = c.id
                         JOIN inventario_cine ic ON p.id = ic.producto_id AND ic.cine_id = ?
                         WHERE p.id = ? AND p.activo = 1";
        $stmtProducto = $conn->prepare($queryProducto);
        $stmtProducto->bind_param("ii", $cineId, $item['id']);
        $stmtProducto->execute();
        $resultProducto = $stmtProducto->get_result();
        
        if ($resultProducto->num_rows > 0) {
            $producto = $resultProducto->fetch_assoc();
            
            // Check stock
            if ($producto['stock'] >= $item['quantity']) {
                $producto['cantidad'] = $item['quantity'];
                $producto['subtotal'] = $producto['precio_unitario'] * $item['quantity'];
                $totalCarrito += $producto['subtotal'];
                $productos[] = $producto;
            } else {
                // Not enough stock
                if ($producto['stock'] > 0) {
                    // Add with available stock
                    $producto['cantidad'] = $producto['stock'];
                    $producto['subtotal'] = $producto['precio_unitario'] * $producto['stock'];
                    $totalCarrito += $producto['subtotal'];
                    $productos[] = $producto;
                    
                    setMensaje("Solo hay {$producto['stock']} unidades disponibles de {$producto['nombre']}", 'warning');
                } else {
                    setMensaje("{$producto['nombre']} no está disponible actualmente", 'warning');
                }
            }
        }
        $stmtProducto->close();
    } else if ($item['type'] === 'combo') {
        // Get combo details
        $queryCombo = "SELECT c.id, c.nombre, c.descripcion, c.precio,
                      CONCAT('assets/img/candybar/combos/', c.id, '.jpg') as imagen_url
                      FROM combos c
                      WHERE c.id = ? AND c.activo = 1
                      AND (c.fecha_fin IS NULL OR c.fecha_fin > NOW())";
        $stmtCombo = $conn->prepare($queryCombo);
        $stmtCombo->bind_param("i", $item['id']);
        $stmtCombo->execute();
        $resultCombo = $stmtCombo->get_result();
        
        if ($resultCombo->num_rows > 0) {
            $combo = $resultCombo->fetch_assoc();
            
            // Check availability of products in combo
            $queryComboProductos = "SELECT cp.producto_id, cp.cantidad, p.nombre, ic.stock 
                                   FROM combo_producto cp
                                   JOIN productos p ON cp.producto_id = p.id
                                   JOIN inventario_cine ic ON cp.producto_id = ic.producto_id AND ic.cine_id = ?
                                   WHERE cp.combo_id = ?";
            $stmtComboProductos = $conn->prepare($queryComboProductos);
            $stmtComboProductos->bind_param("ii", $cineId, $item['id']);
            $stmtComboProductos->execute();
            $resultComboProductos = $stmtComboProductos->get_result();
            
            $disponible = true;
            $productosInsuficientes = [];
            
            while ($producto = $resultComboProductos->fetch_assoc()) {
                // Check if enough stock for this combo item
                $stockRequerido = $producto['cantidad'] * $item['quantity'];
                if ($producto['stock'] < $stockRequerido) {
                    $disponible = false;
                    $productosInsuficientes[] = $producto['nombre'];
                }
            }
            
            $stmtComboProductos->close();
            
            if ($disponible) {
                // Combo products are available
                $combo['cantidad'] = $item['quantity'];
                $combo['subtotal'] = $combo['precio'] * $item['quantity'];
                $totalCarrito += $combo['subtotal'];
                $combos[] = $combo;
            } else {
                // Not enough stock for some products
                setMensaje("No hay suficiente stock para el combo {$combo['nombre']}: " . implode(", ", $productosInsuficientes), 'warning');
            }
        }
        $stmtCombo->close();
    }
}

if (empty($productos) && empty($combos)) {
    setMensaje('No hay productos disponibles en su carrito', 'warning');
    redirect('candybar.php');
}

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/resumen.css" rel="stylesheet">
<link href="assets/css/resumen_compra.css" rel="stylesheet">
<link href="assets/css/resumen_compra_candybar.css" rel="stylesheet">

<div class="res-comp-container">
   <div class="res-comp-sidebar">
       <div class="res-comp-movie-info">
           <a href="/multicinev3/" class="res-comp-home-btn">
               <i class="fas fa-home"></i>
           </a>
           <img src="assets/img/logo.jpg" alt="Multicine" class="res-comp-logo">
           <h2 class="res-comp-candybar-title">CandyBar</h2>
           <p class="res-comp-cinema-name"><?php echo $cine['nombre']; ?></p>
       </div>
       
       <div class="res-comp-cinema-info">
           <h3>Cine</h3>
           <p class="res-comp-info-text"><?php echo $cine['nombre']; ?></p>
           
           <h3>Dirección</h3>
           <p class="res-comp-info-text"><?php echo $cine['direccion']; ?></p>
       </div>
       
       <div class="res-comp-tickets-info">
           <h3>Mi pedido</h3>
           
           <div class="res-comp-sidebar-items">
               <?php if (!empty($combos)): ?>
                   <?php foreach($combos as $combo): ?>
                       <div class="res-comp-sidebar-item">
                           <div class="res-comp-sidebar-item-name"><?php echo $combo['nombre']; ?></div>
                           <div class="res-comp-sidebar-item-details">
                               <span><?php echo $combo['cantidad']; ?> x Bs. <?php echo number_format($combo['precio'], 2); ?></span>
                               <span>Bs. <?php echo number_format($combo['subtotal'], 2); ?></span>
                           </div>
                       </div>
                   <?php endforeach; ?>
               <?php endif; ?>
               
               <?php if (!empty($productos)): ?>
                   <?php foreach($productos as $producto): ?>
                       <div class="res-comp-sidebar-item">
                           <div class="res-comp-sidebar-item-name"><?php echo $producto['nombre']; ?></div>
                           <div class="res-comp-sidebar-item-details">
                               <span><?php echo $producto['cantidad']; ?> x Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></span>
                               <span>Bs. <?php echo number_format($producto['subtotal'], 2); ?></span>
                           </div>
                       </div>
                   <?php endforeach; ?>
               <?php endif; ?>
           </div>
           
           <div class="res-comp-total">
               <span>Total</span>
               <span id="totalPriceDisplay">Bs. <?php echo number_format($totalCarrito, 2); ?></span>
           </div>
       </div>
   </div>
   
   <div class="res-comp-main-content">
       <h2>Resumen de compra - CandyBar</h2>
       
       <?php if (!empty($combos)): ?>
       <div class="res-comp-section">
           <h3>Combos</h3>
           
           <?php foreach($combos as $combo): ?>
               <div class="res-comp-item">
                   <div class="res-comp-item-image">
                       <img src="<?php echo $combo['imagen_url']; ?>" alt="<?php echo $combo['nombre']; ?>">
                   </div>
                   <div class="res-comp-item-info">
                       <h4><?php echo $combo['nombre']; ?></h4>
                       <p class="res-comp-item-description"><?php echo $combo['descripcion']; ?></p>
                       <div class="res-comp-item-pricing">
                           <div class="res-comp-price-quantity">
                               <span class="res-comp-price">Bs. <?php echo number_format($combo['precio'], 2); ?></span>
                               <span class="res-comp-quantity">x <?php echo $combo['cantidad']; ?></span>
                           </div>
                           <span class="res-comp-subtotal">Bs. <?php echo number_format($combo['subtotal'], 2); ?></span>
                       </div>
                   </div>
               </div>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>
       
       <?php if (!empty($productos)): ?>
       <div class="res-comp-section">
           <h3>Productos Individuales</h3>
           
           <?php foreach($productos as $producto): ?>
               <div class="res-comp-item">
                   <div class="res-comp-item-image">
                       <img src="<?php echo $producto['imagen_url']; ?>" alt="<?php echo $producto['nombre']; ?>">
                   </div>
                   <div class="res-comp-item-info">
                       <h4><?php echo $producto['nombre']; ?></h4>
                       <p class="res-comp-item-category"><?php echo $producto['categoria']; ?></p>
                       <div class="res-comp-item-pricing">
                           <div class="res-comp-price-quantity">
                               <span class="res-comp-price">Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></span>
                               <span class="res-comp-quantity">x <?php echo $producto['cantidad']; ?></span>
                           </div>
                           <span class="res-comp-subtotal">Bs. <?php echo number_format($producto['subtotal'], 2); ?></span>
                       </div>
                   </div>
               </div>
           <?php endforeach; ?>
       </div>
       <?php endif; ?>
       
       <div class="res-comp-total-section">
           <div class="res-comp-total-row">
               <span>Subtotal:</span>
               <span>Bs. <?php echo number_format($totalCarrito, 2); ?></span>
           </div>
           <div class="res-comp-total-row res-comp-grand-total">
               <span>Total:</span>
               <span>Bs. <?php echo number_format($totalCarrito, 2); ?></span>
           </div>
       </div>
       
       <div class="res-comp-continue-container">
           <form id="completePurchaseForm" action="confirmar_compra_candybar.php" method="post">
               <input type="hidden" name="productos_seleccionados" value='<?php echo htmlspecialchars($productosSeleccionados); ?>'>
               <input type="hidden" name="cine_id" value="<?php echo $cineId; ?>">
               <input type="hidden" name="total" value="<?php echo $totalCarrito; ?>">
               <div class="res-comp-buttons">
                   <a href="candybar.php?cine=<?php echo $cineId; ?>" class="res-comp-back-btn">Volver</a>
                   <button type="submit" class="res-comp-continue-btn">Continuar</button>
               </div>
           </form>
       </div>
   </div>
</div>

<style>
.res-comp-logo {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin-bottom: 15px;
}

.res-comp-candybar-title {
    font-size: 24px;
    color: #f39c12;
    margin: 10px 0;
}

.res-comp-item {
    display: flex;
    background-color: #222;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
}

.res-comp-item-image {
    width: 120px;
    height: 100px;
    overflow: hidden;
}

.res-comp-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.res-comp-item-info {
    flex-grow: 1;
    padding: 15px;
}

.res-comp-item-info h4 {
    margin: 0 0 5px;
    color: #fff;
}

.res-comp-item-description,
.res-comp-item-category {
    font-size: 14px;
    color: #aaa;
    margin-bottom: 10px;
}

.res-comp-item-pricing {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.res-comp-price-quantity {
    display: flex;
    align-items: center;
}

.res-comp-price {
    font-weight: bold;
    color: #f39c12;
    margin-right: 10px;
}

.res-comp-quantity {
    background-color: #333;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 14px;
    color: #ddd;
}

.res-comp-subtotal {
    font-weight: bold;
    color: #fff;
}

.res-comp-total-section {
    background-color: #222;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.res-comp-total-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    color: #ddd;
}

.res-comp-grand-total {
    font-weight: bold;
    color: #fff;
    font-size: 18px;
    border-top: 1px solid #333;
    margin-top: 10px;
    padding-top: 15px;
}

.res-comp-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}

.res-comp-back-btn {
    background-color: #555;
    color: #fff;
    padding: 12px 25px;
    border-radius: 4px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s;
}

.res-comp-back-btn:hover {
    background-color: #444;
}

.res-comp-continue-btn {
    background-color: #f39c12;
    color: #fff;
    padding: 12px 25px;
    border: none;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
}

.res-comp-continue-btn:hover {
    background-color: #e67e22;
}

/* Sidebar Items Styles */
.res-comp-sidebar-items {
    margin: 10px 0;
    max-height: 200px;
    overflow-y: auto;
}

.res-comp-sidebar-items::-webkit-scrollbar {
    width: 4px;
}

.res-comp-sidebar-items::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.res-comp-sidebar-items::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.res-comp-sidebar-item {
    background-color: rgba(255, 255, 255, 0.07);
    border-radius: 6px;
    padding: 10px;
    margin-bottom: 8px;
}

.res-comp-sidebar-item-name {
    font-weight: bold;
    color: #f39c12;
    margin-bottom: 5px;
    font-size: 14px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.res-comp-sidebar-item-details {
    display: flex;
    justify-content: space-between;
    color: #ddd;
    font-size: 12px;
}

@media (max-width: 768px) {
    .res-comp-container {
        flex-direction: column;
    }
    
    .res-comp-sidebar {
        width: 100%;
        margin-bottom: 20px;
    }
    
    .res-comp-main-content {
        width: 100%;
    }
    
    .res-comp-item {
        flex-direction: column;
    }
    
    .res-comp-item-image {
        width: 100%;
        height: 150px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>