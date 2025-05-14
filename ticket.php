<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Verificar sesión
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para ver sus tickets', 'warning');
    redirect('auth/login.php');
}

// Verificar parámetro de reserva
$reservaId = isset($_GET['reserva']) ? intval($_GET['reserva']) : null;

if (!$reservaId) {
    setMensaje('Reserva no especificada', 'warning');
    redirect('/multicinev3/');
}

// Obtener datos de la reserva
$userId = $_SESSION['user_id'];
$queryReserva = "SELECT r.*, f.fecha_hora, p.titulo as pelicula_titulo, s.nombre as sala_nombre, 
                 c.nombre as cine_nombre, c.direccion as cine_direccion, i.nombre as idioma,
                 fmt.nombre as formato, mp.tipo as tipo_pago
                 FROM reservas r
                 JOIN funciones f ON r.funcion_id = f.id
                 JOIN peliculas p ON f.pelicula_id = p.id
                 JOIN salas s ON f.sala_id = s.id
                 JOIN cines c ON s.cine_id = c.id
                 JOIN idiomas i ON f.idioma_id = i.id
                 JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id
                 LEFT JOIN metodos_pago mp ON r.metodo_pago_id = mp.id
                 WHERE r.id = ? AND r.user_id = ?";
                 
$stmtReserva = $conn->prepare($queryReserva);
$stmtReserva->bind_param("ii", $reservaId, $userId);
$stmtReserva->execute();
$resultReserva = $stmtReserva->get_result();

if ($resultReserva->num_rows === 0) {
    setMensaje('Reserva no encontrada o no autorizada', 'warning');
    redirect('/multicinev3/');
}

$reserva = $resultReserva->fetch_assoc();

// Obtener asientos reservados
$queryAsientos = "SELECT a.fila, a.numero, a.tipo
                 FROM asientos_reservados ar
                 JOIN asientos a ON ar.asiento_id = a.id AND ar.sala_id = a.sala_id
                 WHERE ar.reserva_id = ?
                 ORDER BY a.fila, a.numero";
$stmtAsientos = $conn->prepare($queryAsientos);
$stmtAsientos->bind_param("i", $reservaId);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

$asientos = [];
while ($asiento = $resultAsientos->fetch_assoc()) {
    $asientos[] = $asiento;
}

// Obtener tickets
$queryTickets = "SELECT * FROM tickets WHERE reserva_id = ? ORDER BY id";
$stmtTickets = $conn->prepare($queryTickets);
$stmtTickets->bind_param("i", $reservaId);
$stmtTickets->execute();
$resultTickets = $stmtTickets->get_result();

$tickets = [];
while ($ticket = $resultTickets->fetch_assoc()) {
    $tickets[] = $ticket;
}

// Obtener poster de la película
$queryPoster = "SELECT m.url 
              FROM multimedia_pelicula mp 
              JOIN multimedia m ON mp.multimedia_id = m.id 
              JOIN funciones f ON f.pelicula_id = mp.pelicula_id
              JOIN reservas r ON r.funcion_id = f.id
              WHERE r.id = ? AND mp.proposito = 'poster' 
              LIMIT 1";
$stmtPoster = $conn->prepare($queryPoster);
$stmtPoster->bind_param("i", $reservaId);
$stmtPoster->execute();
$resultPoster = $stmtPoster->get_result();

$posterUrl = 'assets/img/poster-default.jpg';
if ($resultPoster->num_rows > 0) {
   $poster = $resultPoster->fetch_assoc();
   $posterUrl = $poster['url'];
}

// Obtener datos de productos del pedido si existen
$queryPedido = "SELECT pcb.id, pcb.fecha_pedido, pcb.total, pcb.estado,
               dpc.producto_id, dpc.cantidad, dpc.precio_unitario,
               p.nombre as producto_nombre, p.categoria_id
               FROM pedidos_candy_bar pcb
               LEFT JOIN detalle_pedidos_candy dpc ON pcb.id = dpc.pedido_id
               LEFT JOIN productos p ON dpc.producto_id = p.id
               WHERE pcb.reserva_id = ?";
$stmtPedido = $conn->prepare($queryPedido);
$stmtPedido->bind_param("i", $reservaId);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();

$productos = [];
$tienePedido = false;
$pedidoInfo = null;

if ($resultPedido->num_rows > 0) {
    $tienePedido = true;
    while ($item = $resultPedido->fetch_assoc()) {
        if ($pedidoInfo === null) {
            $pedidoInfo = [
                'id' => $item['id'],
                'fecha_pedido' => $item['fecha_pedido'],
                'total' => $item['total'],
                'estado' => $item['estado']
            ];
        }
        if (!empty($item['producto_id'])) {
            $productos[] = $item;
        }
    }
}

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/ticket-digital.css" rel="stylesheet">
<!-- Añadir librería QR Code -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

<style>
/* Estilos generales */
.ticket-d-container {
    max-width: 1200px;
    margin: 30px auto;
    font-family: 'Roboto', sans-serif;
    color: #333;
}

.ticket-d-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.ticket-d-header h1 {
    color: #4caf50;
    font-size: 2.2em;
}

.ticket-d-subtitle {
    color: #777;
    font-size: 1.2em;
}

.ticket-d-main {
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
}

.ticket-d-sidebar {
    flex: 0 0 300px;
    position: sticky;
    top: 20px;
    align-self: flex-start;
}

.ticket-d-content {
    flex: 1;
    min-width: 0;
}

.ticket-d-poster {
    width: 100%;
    max-width: 300px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.ticket-d-movie-info {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.ticket-d-movie-info h2 {
    margin-top: 0;
    color: #333;
    font-size: 1.5em;
}

.ticket-d-info-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 0.95em;
}

.ticket-d-info-item i {
    width: 25px;
    margin-right: 10px;
    color: #4caf50;
}

.ticket-d-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ticket-d-home-btn, 
.ticket-d-print-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 15px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.ticket-d-home-btn {
    background: #333;
    color: white;
}

.ticket-d-print-btn {
    background: #4caf50;
    color: white;
}

.ticket-d-home-btn:hover, 
.ticket-d-print-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.ticket-d-home-btn i, 
.ticket-d-print-btn i {
    margin-right: 8px;
}

.ticket-d-section {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.ticket-d-section h3 {
    display: flex;
    align-items: center;
    margin-top: 0;
    color: #333;
    font-size: 1.3em;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.ticket-d-section h3 i {
    margin-right: 10px;
    color: #4caf50;
}

.ticket-d-section-info {
    margin-bottom: 20px;
    color: #666;
    font-size: 0.95em;
}

/* Estilos para los tickets */
.ticket-d-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
}

.ticket-d-card {
    flex: 0 0 calc(50% - 15px);
    border: 2px dashed #ddd;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    background: #fff;
    position: relative;
}

@media (max-width: 768px) {
    .ticket-d-card {
        flex: 0 0 100%;
    }
}

.ticket-d-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-bottom: 1px dashed #ddd;
}

.ticket-d-cine-logo img {
    height: 30px;
}

.ticket-d-card-number {
    background: #4caf50;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.ticket-d-card-body {
    display: flex;
    justify-content: space-between;
    padding: 10px;
}

.ticket-d-card-movie {
    flex: 1;
}

.ticket-d-card-movie h4 {
    margin: 0 0 5px;
    font-size: 16px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ticket-d-card-movie p {
    margin: 0 0 3px;
    font-size: 12px;
    color: #666;
}

.ticket-d-card-seat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    padding: 5px 10px;
    border-radius: 4px;
    min-width: 60px;
}

.ticket-d-seat-label {
    font-size: 10px;
    color: #888;
    margin-bottom: 2px;
}

.ticket-d-seat-number {
    font-size: 18px;
    font-weight: bold;
    color: #333;
}

.ticket-d-card-qrcode {
    padding: 10px;
    text-align: center;
    background: #f9f9f9;
    border-top: 1px dashed #ddd;
}

.ticket-d-qrcode-container {
    width: 100px;
    height: 100px;
    margin: 0 auto;
}

.ticket-d-qrcode-container img {
    width: 100%;
    height: 100%;
}

.ticket-d-barcode-number {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    margin-top: 5px;
    color: #555;
}

.ticket-d-card-footer {
    padding: 10px;
    font-size: 10px;
    text-align: center;
    color: #777;
    border-top: 1px dashed #ddd;
}

.ticket-d-card-footer p {
    margin: 0 0 3px;
}

.ticket-d-card-nota {
    font-style: italic;
    font-size: 9px;
}

/* Estilos Candy Bar */
.ticket-d-candy-card {
    background: white;
    border: 2px solid #ffd966;
    border-radius: 10px;
    overflow: hidden;
}

.ticket-d-candy-header {
    background: #ffd966;
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ticket-d-candy-header h4 {
    margin: 0;
    color: #333;
}

.ticket-d-candy-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.ticket-d-candy-status.pendiente {
    background: #f39c12;
}

.ticket-d-candy-status.preparando {
    background: #3498db;
}

.ticket-d-candy-status.entregado {
    background: #2ecc71;
}

.ticket-d-candy-status.cancelado {
    background: #e74c3c;
}

.ticket-d-candy-list {
    padding: 15px;
}

.ticket-d-candy-table {
    width: 100%;
    border-collapse: collapse;
}

.ticket-d-candy-table th,
.ticket-d-candy-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.ticket-d-candy-table th {
    background: #f9f9f9;
    font-weight: 500;
}

.ticket-d-candy-table tfoot td {
    font-weight: bold;
    border-top: 2px solid #eee;
    border-bottom: none;
}

.ticket-d-candy-note {
    padding: 15px;
    background: #fff3cd;
    color: #856404;
    font-size: 14px;
    border-top: 1px dashed #ffeeba;
}

.ticket-d-candy-note p {
    margin: 0;
}

.ticket-d-candy-note i {
    margin-right: 5px;
}

/* Info box */
.ticket-d-info-box {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid #4caf50;
}

.ticket-d-info-list {
    margin: 0;
    padding-left: 20px;
}

.ticket-d-info-list li {
    margin-bottom: 8px;
}

.ticket-d-info-list li:last-child {
    margin-bottom: 0;
}

/* Modificaciones para la impresión */
@media print {
    .ticket-d-main {
        display: block;
    }
    
    .ticket-d-header {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .ticket-d-movie-info {
        margin-bottom: 20px;
    }
    
    .ticket-d-actions {
        display: none;
    }
    
    .ticket-d-content {
        width: 100%;
    }
    
    .ticket-d-section {
        page-break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .ticket-d-card {
        page-break-inside: avoid;
    }
    
    header, footer, nav, .no-print {
        display: none !important;
    }
    
    body {
        margin: 0;
        padding: 0;
        background: white;
    }
    
    .ticket-d-container {
        width: 100%;
        margin: 0;
        padding: 0;
    }
    
    .ticket-d-qrcode-container img {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
    }
}

/* Estilos adicionales para el código QR */
.ticket-d-card-qrcode {
    text-align: center;
    padding: 10px;
    border-top: 1px dashed #ccc;
    background-color: #f9f9f9;
}

.ticket-d-qrcode-container {
    margin: 0 auto;
    width: 150px;
    height: 150px;
}

.ticket-d-qrcode-container img {
    width: 100%;
    height: 100%;
}

.ticket-d-barcode-number {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    letter-spacing: 2px;
    margin-top: 8px;
    color: #333;
}
</style>

<div class="ticket-d-container">
    <div class="ticket-d-header">
        <h1>¡Reserva realizada con éxito!</h1>
        <p class="ticket-d-subtitle">Gracias por tu compra en <?php echo $reserva['cine_nombre']; ?></p>
    </div>
    
    <div class="ticket-d-main">
        <div class="ticket-d-sidebar">
            <img src="<?php echo $posterUrl; ?>" alt="<?php echo $reserva['pelicula_titulo']; ?>" class="ticket-d-poster">
            <div class="ticket-d-movie-info">
                <h2><?php echo $reserva['pelicula_titulo']; ?></h2>
                <p class="ticket-d-info-item">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo $reserva['cine_nombre']; ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-calendar-alt"></i> 
                    <?php echo strftime('%A %d de %B de %Y', strtotime($reserva['fecha_hora'])); ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-clock"></i> 
                    <?php echo date('H:i', strtotime($reserva['fecha_hora'])); ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-film"></i> 
                    <?php echo $reserva['formato']; ?> | <?php echo $reserva['idioma']; ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-couch"></i> 
                    Sala <?php echo $reserva['sala_nombre']; ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-ticket-alt"></i> 
                    Asientos: <?php 
                        $asientosTexto = array_map(function($a) { 
                            return $a['fila'] . $a['numero']; 
                        }, $asientos);
                        echo implode(', ', $asientosTexto);
                    ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-credit-card"></i> 
                    Método de pago: <?php 
                        $metodoPago = '';
                        switch($reserva['tipo_pago']) {
                            case 'tarjeta':
                                $metodoPago = 'Tarjeta de crédito/débito';
                                break;
                            case 'qr':
                                $metodoPago = 'Código QR';
                                break;
                            case 'tigo_money':
                                $metodoPago = 'Tigo Money';
                                break;
                            default:
                                $metodoPago = 'Efectivo';
                        }
                        echo $metodoPago;
                    ?>
                </p>
                <p class="ticket-d-info-item">
                    <i class="fas fa-receipt"></i> 
                    Total pagado: Bs. <?php echo number_format($reserva['total_pagado'], 2); ?>
                </p>
            </div>
            
            <div class="ticket-d-actions">
                <a href="/multicinev3/" class="ticket-d-home-btn">
                    <i class="fas fa-home"></i> Volver al inicio
                </a>
                <button class="ticket-d-print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimir tickets
                </button>
            </div>
        </div>
        
        <div class="ticket-d-content">
            <div class="ticket-d-section">
                <h3><i class="fas fa-ticket-alt"></i> Tus tickets</h3>
                <p class="ticket-d-section-info">
                    Presenta el código QR o el código de acceso en la entrada del cine para acceder a tu función.
                </p>
                
                <div class="ticket-d-cards">
                    <?php foreach($tickets as $index => $ticket): ?>
                        <div class="ticket-d-card">
                            <div class="ticket-d-card-header">
                                <div class="ticket-d-cine-logo">
                                    <img src="assets/img/logo.jpg" alt="Logo Multicine">
                                </div>
                                <div class="ticket-d-card-number">
                                    Ticket #<?php echo $index + 1; ?>
                                </div>
                            </div>
                            <div class="ticket-d-card-body">
                                <div class="ticket-d-card-movie">
                                    <h4><?php echo $reserva['pelicula_titulo']; ?></h4>
                                    <p>Sala <?php echo $reserva['sala_nombre']; ?> | <?php echo $reserva['formato']; ?></p>
                                    <p><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?></p>
                                </div>
                                <div class="ticket-d-card-seat">
                                    <div class="ticket-d-seat-label">ASIENTO</div>
                                    <div class="ticket-d-seat-number"><?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?></div>
                                </div>
                            </div>
                            <div class="ticket-d-card-qrcode">
                                <!-- Aquí va el código QR -->
                                <div class="ticket-d-qrcode-container" id="qrcode-<?php echo $ticket['id']; ?>"></div>
                                <div class="ticket-d-barcode-number"><?php echo $ticket['codigo_barras']; ?></div>
                            </div>
                            <div class="ticket-d-card-footer">
                                <p><?php echo $reserva['cine_nombre']; ?></p>
                                <p><?php echo $reserva['cine_direccion']; ?></p>
                                <p class="ticket-d-card-nota">Este ticket no es válido como comprobante fiscal</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if ($tienePedido && !empty($productos)): ?>
                <div class="ticket-d-section">
                    <h3><i class="fas fa-utensils"></i> Productos Candy Bar</h3>
                    <p class="ticket-d-section-info">
                        Presenta este comprobante en el Candy Bar para reclamar tus productos.
                    </p>
                    
                    <div class="ticket-d-candy-card">
                        <div class="ticket-d-candy-header">
                            <h4>Pedido Candy Bar #<?php echo $pedidoInfo['id']; ?></h4>
                            <div class="ticket-d-candy-status <?php echo strtolower($pedidoInfo['estado']); ?>">
                                <?php echo ucfirst($pedidoInfo['estado']); ?>
                            </div>
                        </div>
                        
                        <div class="ticket-d-candy-list">
                            <table class="ticket-d-candy-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($productos as $item): ?>
                                        <tr>
                                            <td><?php echo $item['producto_nombre']; ?></td>
                                            <td><?php echo $item['cantidad']; ?></td>
                                            <td>Bs. <?php echo number_format($item['precio_unitario'], 2); ?></td>
                                            <td>Bs. <?php echo number_format($item['precio_unitario'] * $item['cantidad'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3">Total</td>
                                        <td>Bs. <?php echo number_format($pedidoInfo['total'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="ticket-d-candy-note">
                            <p><i class="fas fa-info-circle"></i> Recoge tus productos antes de ingresar a la sala.</p>
                        </div>
                        
                        <!-- Agregar QR para pedido Candy Bar -->
                        <div class="ticket-d-card-qrcode">
                            <div class="ticket-d-qrcode-container" id="qrcode-candy-<?php echo $pedidoInfo['id']; ?>"></div>
                            <div class="ticket-d-barcode-number">CANDY-<?php echo $pedidoInfo['id']; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Script para generar QR de productos -->
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Generar QR para el pedido candy
                    var qrDataCandy = `
                        Pedido: CANDY-<?php echo $pedidoInfo['id']; ?>
                        Cine: <?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine_nombre'])); ?>
                        Fecha: <?php echo date('d/m/Y H:i', strtotime($pedidoInfo['fecha_pedido'])); ?>
                        Estado: <?php echo ucfirst($pedidoInfo['estado']); ?>
                        Total: Bs. <?php echo number_format($pedidoInfo['total'], 2); ?>
                    `;
                    
                    var qrCandy = qrcode(0, 'M');
                    qrCandy.addData(qrDataCandy);
                    qrCandy.make();
                    
                    document.getElementById('qrcode-candy-<?php echo $pedidoInfo['id']; ?>').innerHTML = qrCandy.createImgTag(6, 0);
                });
                </script>
            <?php endif; ?>
            
            <div class="ticket-d-section">
                <h3><i class="fas fa-question-circle"></i> Información importante</h3>
                <div class="ticket-d-info-box">
                    <ul class="ticket-d-info-list">
                        <li>Presentate al menos <strong>15 minutos</strong> antes del inicio de la función.</li>
                        <li>Para cambios o cancelaciones, comunícate con atención al cliente al menos 2 horas antes de la función.</li>
                        <li>No se permite el ingreso de alimentos y bebidas externas al cine.</li>
                        <li>Los tickets se enviarán también a tu correo electrónico registrado.</li>
                        <li>Para cualquier consulta, llama a nuestra línea de atención: <strong>+591 2 5553333</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Generar códigos QR para cada ticket
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach($tickets as $ticket): ?>
    (function() {
        // Creamos un código QR con los datos del ticket
        var qrData = `
            Ticket: <?php echo $ticket['codigo_barras']; ?>
            Película: <?php echo htmlspecialchars(str_replace("'", "\'", $reserva['pelicula_titulo'])); ?>
            Función: <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>
            Cine: <?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine_nombre'])); ?>
            Sala: <?php echo htmlspecialchars($reserva['sala_nombre']); ?>
        `;
        
        // Crear elemento QR
        var qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        // Insertar el SVG del QR en el contenedor
        document.getElementById('qrcode-<?php echo $ticket['id']; ?>').innerHTML = qr.createImgTag(6, 0);
    })();
    <?php endforeach; ?>
});
</script>

<?php require_once 'includes/footer.php'; ?>