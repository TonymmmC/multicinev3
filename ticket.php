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
$queryPedido = "SELECT p.*, pp.nombre as producto_nombre, pp.precio_unitario, dp.cantidad
               FROM pedidos_candy_bar p
               JOIN detalle_pedidos_candy dp ON p.id = dp.pedido_id
               JOIN productos pp ON dp.producto_id = pp.id
               WHERE p.reserva_id = ?";
$stmtPedido = $conn->prepare($queryPedido);
$stmtPedido->bind_param("i", $reservaId);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();

$pedidoData = [];
$tienePedido = $resultPedido->num_rows > 0;

if ($tienePedido) {
    while ($item = $resultPedido->fetch_assoc()) {
        $pedidoData[] = $item;
    }
}

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/ticket-digital.css" rel="stylesheet">

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
                    Presenta el código QR o el código de barras en la entrada del cine para acceder a tu función.
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
                            <div class="ticket-d-card-barcode">
                                <div class="ticket-d-barcode-svg">
                                    <!-- Simulación de código de barras con CSS -->
                                    <?php for($i = 0; $i < 30; $i++): ?>
                                        <div class="ticket-d-barcode-line" style="width: <?php echo rand(1, 3); ?>px; margin-right: <?php echo rand(1, 3); ?>px;"></div>
                                    <?php endfor; ?>
                                </div>
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
            
            <?php if ($tienePedido): ?>
                <div class="ticket-d-section">
                    <h3><i class="fas fa-utensils"></i> Productos Candy Bar</h3>
                    <p class="ticket-d-section-info">
                        Presenta este comprobante en el Candy Bar para reclamar tus productos.
                    </p>
                    
                    <div class="ticket-d-candy-card">
                        <div class="ticket-d-candy-header">
                            <h4>Pedido Candy Bar #<?php echo $pedidoData[0]['id']; ?></h4>
                            <div class="ticket-d-candy-status <?php echo strtolower($pedidoData[0]['estado']); ?>">
                                <?php echo ucfirst($pedidoData[0]['estado']); ?>
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
                                    <?php foreach($pedidoData as $item): ?>
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
                                        <td>Bs. <?php echo number_format($pedidoData[0]['total'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="ticket-d-candy-note">
                            <p><i class="fas fa-info-circle"></i> Recoge tus productos antes de ingresar a la sala.</p>
                        </div>
                    </div>
                </div>
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

<?php require_once 'includes/footer.php'; ?>