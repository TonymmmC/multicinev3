<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Verificar sesión
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para ver sus tickets', 'warning');
    redirect('auth/login.php');
}

// Verificar parámetro de pedido
$pedidoId = isset($_GET['pedido']) ? intval($_GET['pedido']) : null;

if (!$pedidoId) {
    setMensaje('Pedido no especificado', 'warning');
    redirect('/multicinev3/');
}

// Obtener datos del pedido
$userId = $_SESSION['user_id'];
$queryPedido = "SELECT pcb.*, c.nombre as cine_nombre, c.direccion as cine_direccion
                FROM pedidos_candy_bar pcb
                LEFT JOIN cines c ON c.id = ?
                WHERE pcb.id = ? AND pcb.user_id = ?";
                
$stmtPedido = $conn->prepare($queryPedido);
$cineId = isset($_POST['cine_id']) ? intval($_POST['cine_id']) : 1; // Usar el cine de POST o default a 1
$stmtPedido->bind_param("iii", $cineId, $pedidoId, $userId);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();

if ($resultPedido->num_rows === 0) {
    setMensaje('Pedido no encontrado o no autorizado', 'warning');
    redirect('/multicinev3/');
}

$pedido = $resultPedido->fetch_assoc();

// Obtener detalles del pedido
$queryDetalles = "SELECT dp.*, 
                 p.nombre as nombre,
                 cp.nombre as categoria,
                 CONCAT('assets/img/candybar/productos/', dp.producto_id, '.jpg') as imagen_url
                 FROM detalle_pedidos_candy dp
                 LEFT JOIN productos p ON dp.producto_id = p.id
                 LEFT JOIN categorias_producto cp ON p.categoria_id = cp.id
                 WHERE dp.pedido_id = ?
                 ORDER BY dp.id";
$stmtDetalles = $conn->prepare($queryDetalles);
$stmtDetalles->bind_param("i", $pedidoId);
$stmtDetalles->execute();
$resultDetalles = $stmtDetalles->get_result();

$detalles = [];
while ($detalle = $resultDetalles->fetch_assoc()) {
    $detalles[] = $detalle;
}

// Obtener número de orden único para código de barras
$numeroOrden = str_pad($pedidoId, 8, '0', STR_PAD_LEFT);
$codigoBarras = date('Ymd') . $numeroOrden;

require_once 'includes/header.php';
?>

<link href="assets/css/ticket-digital-ticket.css" rel="stylesheet">
<!-- Añadir librería QR Code -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

<div class="ticket-d-container">
    <div class="ticket-d-header">
        <h1>¡Pedido realizado con éxito!</h1>
        <p class="ticket-d-subtitle">Gracias por tu compra en <?php echo $pedido['cine_nombre']; ?></p>
    </div>
    
    <div class="ticket-d-main">
        <div class="ticket-d-sidebar">
            <img src="assets/img/logo.jpg" alt="Multicine" class="ticket-d-poster">
            <div class="ticket-d-movie-info">
                <h2>CandyBar</h2>
                <div class="ticket-d-info-item">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo $pedido['cine_nombre']; ?>
                </div>
                <div class="ticket-d-info-item">
                    <i class="fas fa-calendar-alt"></i> 
                    <?php echo strftime('%A %d de %B de %Y', strtotime($pedido['fecha_pedido'])); ?>
                </div>
                <div class="ticket-d-info-item">
                    <i class="fas fa-clock"></i> 
                    <?php echo date('H:i', strtotime($pedido['fecha_pedido'])); ?>
                </div>
                <div class="ticket-d-info-item">
                    <i class="fas fa-credit-card"></i> 
                    Método de pago: Efectivo
                </div>
            </div>
            
            <div class="ticket-d-qrcode-container">
                <div id="qrCode"></div>
                <p class="ticket-d-barcode-number"><?php echo $codigoBarras; ?></p>
            </div>
            
            <div class="ticket-d-actions">
                <button class="ticket-d-print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir ticket
                </button>
                <a href="/multicinev3/" class="ticket-d-home-btn">
                    <i class="fas fa-home"></i> Volver al inicio
                </a>
            </div>
        </div>
        
        <div class="ticket-d-content">
            <div class="ticket-d-section">
                <h3><i class="fas fa-shopping-basket"></i> Detalle del pedido #<?php echo $pedidoId; ?></h3>
                <div class="ticket-d-section-info">
                    Fecha: <?php echo strftime('%d/%m/%Y %H:%M', strtotime($pedido['fecha_pedido'])); ?>
                </div>
                
                <div class="ticket-d-candy-list">
                    <table class="ticket-d-candy-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <img src="<?php echo $detalle['imagen_url']; ?>" alt="<?php echo $detalle['nombre']; ?>" style="width: 50px; height: 50px; margin-right: 10px; object-fit: cover; border-radius: 4px;">
                                            <div>
                                                <div><?php echo $detalle['nombre']; ?></div>
                                                <div style="color: #666; font-size: 12px;"><?php echo $detalle['categoria']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Bs. <?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                    <td><?php echo $detalle['cantidad']; ?></td>
                                    <td>Bs. <?php echo number_format($detalle['precio_unitario'] * $detalle['cantidad'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" align="right">Subtotal:</td>
                                <td>Bs. <?php echo number_format($pedido['total'], 2); ?></td>
                            </tr>
                            <?php if (isset($pedido['descuento_aplicado']) && $pedido['descuento_aplicado'] > 0): ?>
                                <tr>
                                    <td colspan="3" align="right">Descuento:</td>
                                    <td>Bs. <?php echo number_format($pedido['descuento_aplicado'], 2); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" align="right">Total:</td>
                                <td>Bs. <?php echo number_format($pedido['total'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="ticket-d-candy-note">
                    <p><i class="fas fa-info-circle"></i> Presente este ticket en el CandyBar para retirar su pedido.</p>
                    <p><i class="fas fa-exclamation-circle"></i> Este ticket digital es válido como comprobante de compra.</p>
                </div>
            </div>
            
            <div class="ticket-d-info-box">
                <p>Información importante:</p>
                <ul class="ticket-d-info-list">
                    <li>Su pedido estará listo para recoger en el CandyBar del cine.</li>
                    <li>Muestre el código QR al personal para validar su compra.</li>
                    <li>Este pedido es válido únicamente para la fecha indicada.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Generar código QR
    var qr = qrcode(0, 'M');
    qr.addData('<?php echo $codigoBarras; ?>');
    qr.make();
    document.getElementById('qrCode').innerHTML = qr.createImgTag(5);
});
</script>

<?php require_once 'includes/footer.php'; ?>