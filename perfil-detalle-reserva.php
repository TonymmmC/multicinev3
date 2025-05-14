<?php
require_once 'includes/functions.php';
iniciarSesion();

if (!estaLogueado()) {
   setMensaje('Debes iniciar sesión para acceder a este contenido', 'warning');
   redirect('/multicinev3/auth/login.php');
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
   setMensaje('ID de reserva inválido', 'danger');
   redirect('/multicinev3/perfil-reservas.php');
}

$reservaId = $_GET['id'];
$userId = $_SESSION['user_id'];
$conn = require 'config/database.php';

// Obtener datos de la reserva
$sql = "SELECT r.id, r.fecha_reserva, r.total_pagado, r.estado, r.descuento_aplicado,
              p.titulo as pelicula, f.fecha_hora, f.precio_base,
              c.nombre as cine, c.direccion as cine_direccion, 
              s.nombre as sala, s.formato_id,
              fm.nombre as formato_sala,
              i.nombre as idioma,
              fp.nombre as formato_proyeccion
       FROM reservas r
       JOIN funciones f ON r.funcion_id = f.id
       JOIN peliculas p ON f.pelicula_id = p.id
       JOIN salas s ON f.sala_id = s.id
       JOIN cines c ON s.cine_id = c.id
       JOIN formatos fm ON s.formato_id = fm.id
       JOIN idiomas i ON f.idioma_id = i.id
       JOIN formatos fp ON f.formato_proyeccion_id = fp.id
       WHERE r.id = ? AND r.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $reservaId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
   setMensaje('No se encontró la reserva o no tienes permiso para verla', 'danger');
   redirect('/multicinev3/perfil-reservas.php');
}

$reserva = $result->fetch_assoc();

// Obtener tickets
$sql = "SELECT id, codigo_barras, ci_usuario, usado, fecha_uso
       FROM tickets
       WHERE reserva_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $reservaId);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);

// Obtener asientos
$sql = "SELECT a.fila, a.numero, a.tipo, ar.precio_final
       FROM asientos_reservados ar
       JOIN asientos a ON ar.asiento_id = a.id AND ar.sala_id = a.sala_id
       WHERE ar.reserva_id = ?
       ORDER BY a.fila, a.numero";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $reservaId);
$stmt->execute();
$result = $stmt->get_result();
$asientos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener productos candy bar
$sql = "SELECT pcb.id, pcb.fecha_pedido, pcb.total, pcb.estado,
              dpc.producto_id, dpc.cantidad, dpc.precio_unitario,
              p.nombre as producto_nombre
       FROM pedidos_candy_bar pcb
       LEFT JOIN detalle_pedidos_candy dpc ON pcb.id = dpc.pedido_id
       LEFT JOIN productos p ON dpc.producto_id = p.id
       WHERE pcb.reserva_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $reservaId);
$stmt->execute();
$result = $stmt->get_result();
$productos = $result->fetch_all(MYSQLI_ASSOC);

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

require_once 'includes/header.php';
?>

<!-- Agregar biblioteca QR -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<!-- Agregar biblioteca jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<style>
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
    background: var(--primary);
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

.ticket-modal-body {
    background: #fff;
    padding: 20px;
    text-align: center;
}

.ticket-modal-poster {
    width: 100%;
    max-width: 200px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.ticket-modal-qr {
    margin: 0 auto 20px;
    width: 200px;
    height: 200px;
}

.ticket-modal-info {
    text-align: left;
    max-width: 400px;
    margin: 0 auto;
}

.ticket-modal-info-item {
    display: flex;
    margin-bottom: 8px;
    align-items: center;
}

.ticket-modal-info-item i {
    width: 25px;
    color: var(--primary);
    margin-right: 10px;
}

.ticket-modal-actions {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.ticket-icon-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
}

.ticket-icon-btn:hover {
    background: var(--primary-dark);
    transform: scale(1.05);
}

.ticket-modal-footer {
    font-size: 12px;
    color: #777;
    text-align: center;
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px dashed #ddd;
}

.modal-body {
    color: #333 !important;
}

.ticket-modal-info-item {
    color: #333 !important;
}

.ticket-modal-info-item span {
    color: #333 !important;
}

.ticket-modal-info {
    color: #333 !important;
}

.ticket-modal-body {
    color: #333 !important;
}

.ticket-modal-footer {
    color: #777 !important;
}

.ticket-d-card-qrcode {
    min-height: 140px;
}

.ticket-d-qrcode-container {
    width: 100px;
    height: 100px;
    margin: 0 auto 10px;
    background-color: #f9f9f9;
    padding: 5px;
    border-radius: 4px;
}

.modal-title {
    color: #333 !important;
    font-weight: bold;
}

.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

/* Para imprimir */
@media print {
    .no-print {
        display: none !important;
    }
}
</style>

<div class="container mt-4">
   <div class="row">
       <div class="col-md-12">
           <nav aria-label="breadcrumb" class="no-print">
               <ol class="breadcrumb">
                   <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                   <li class="breadcrumb-item"><a href="perfil.php">Mi Perfil</a></li>
                   <li class="breadcrumb-item"><a href="perfil-reservas.php">Mis Reservas</a></li>
                   <li class="breadcrumb-item active" aria-current="page">Detalle de Reserva</li>
               </ol>
           </nav>
       </div>
   </div>
   
   <div class="row">
       <div class="col-md-12">
           <div class="card mb-4">
               <div class="card-header bg-primary text-white">
                   <div class="d-flex justify-content-between align-items-center">
                       <h4 class="mb-0">Detalle de Reserva #<?php echo $reservaId; ?></h4>
                       <?php if ($reserva['estado'] === 'aprobado' && strtotime($reserva['fecha_hora']) > time()): ?>
                           <a href="javascript:void(0)" class="btn btn-light btn-sm no-print" onclick="downloadReservationDetailsPDF()">
                               <i class="fas fa-download"></i> Descargar Detalles
                           </a>
                       <?php endif; ?>
                   </div>
               </div>
               <div class="card-body reservation-details">
                   <div class="row mb-4">
                       <div class="col-md-3 text-center">
                           <img src="<?php echo $posterUrl; ?>" alt="<?php echo htmlspecialchars($reserva['pelicula']); ?>" class="img-fluid mb-3" style="max-height: 300px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                       </div>
                       <div class="col-md-9">
                           <div class="row">
                               <div class="col-md-6">
                                   <h5 class="card-title">Información de la Película</h5>
                                   <table class="table table-bordered">
                                       <tr>
                                           <th>Película:</th>
                                           <td><?php echo htmlspecialchars($reserva['pelicula']); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Función:</th>
                                           <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Cine:</th>
                                           <td><?php echo htmlspecialchars($reserva['cine']); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Dirección:</th>
                                           <td><?php echo htmlspecialchars($reserva['cine_direccion']); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Sala:</th>
                                           <td>
                                               <?php echo htmlspecialchars($reserva['sala']); ?> 
                                               (<?php echo htmlspecialchars($reserva['formato_sala']); ?>)
                                           </td>
                                       </tr>
                                       <tr>
                                           <th>Idioma:</th>
                                           <td><?php echo htmlspecialchars($reserva['idioma']); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Formato de Proyección:</th>
                                           <td><?php echo htmlspecialchars($reserva['formato_proyeccion']); ?></td>
                                       </tr>
                                   </table>
                               </div>
                               
                               <div class="col-md-6">
                                   <h5 class="card-title">Información de la Reserva</h5>
                                   <table class="table table-bordered">
                                       <tr>
                                           <th>Estado:</th>
                                           <td>
                                               <?php
                                               $badge_class = '';
                                               switch ($reserva['estado']) {
                                                   case 'pendiente': $badge_class = 'badge-warning'; break;
                                                   case 'aprobado': $badge_class = 'badge-success'; break;
                                                   case 'rechazado': $badge_class = 'badge-danger'; break;
                                                   case 'reembolsado': $badge_class = 'badge-info'; break;
                                                   case 'vencido': $badge_class = 'badge-secondary'; break;
                                               }
                                               ?>
                                               <span class="badge <?php echo $badge_class; ?>">
                                                   <?php echo ucfirst($reserva['estado']); ?>
                                               </span>
                                           </td>
                                       </tr>
                                       <tr>
                                           <th>Fecha de Reserva:</th>
                                           <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])); ?></td>
                                       </tr>
                                       <tr>
                                           <th>Precio Base:</th>
                                           <td>Bs. <?php echo number_format($reserva['precio_base'], 2); ?></td>
                                       </tr>
                                       <?php if ($reserva['descuento_aplicado'] > 0): ?>
                                       <tr>
                                           <th>Descuento Aplicado:</th>
                                           <td>Bs. <?php echo number_format($reserva['descuento_aplicado'], 2); ?></td>
                                       </tr>
                                       <?php endif; ?>
                                       <tr>
                                           <th>Total Pagado:</th>
                                           <td><strong>Bs. <?php echo number_format($reserva['total_pagado'], 2); ?></strong></td>
                                       </tr>
                                       <tr>
                                           <th>Asientos:</th>
                                           <td>
                                               <?php 
                                               $asientos_text = [];
                                               foreach ($asientos as $asiento) {
                                                   $tipo_badge = '';
                                                   switch ($asiento['tipo']) {
                                                       case 'premium': $tipo_badge = '<span class="badge badge-primary">P</span>'; break;
                                                       case 'discapacidad': $tipo_badge = '<span class="badge badge-info">D</span>'; break;
                                                   }
                                                   $asientos_text[] = $asiento['fila'] . $asiento['numero'] . ' ' . $tipo_badge;
                                               }
                                               echo implode(', ', $asientos_text);
                                               ?>
                                           </td>
                                       </tr>
                                   </table>
                               </div>
                           </div>
                       </div>
                   </div>
                   
                   <?php if (!empty($tickets) && $reserva['estado'] === 'aprobado'): ?>
                   <div class="row mt-4">
                       <div class="col-md-12">
                           <h5 class="card-title">
                               <i class="fas fa-ticket-alt text-primary"></i> 
                               Tus Tickets
                               <?php if (strtotime($reserva['fecha_hora']) > time()): ?>
                               <a href="javascript:void(0)" class="btn btn-sm btn-primary float-right no-print" onclick="downloadAllTicketsPDF()">
                                   <i class="fas fa-download"></i> Descargar Todos los Tickets
                               </a>
                               <?php endif; ?>
                           </h5>
                           <p class="text-muted mb-3">
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
                                               <h4><?php echo htmlspecialchars($reserva['pelicula']); ?></h4>
                                               <p>Sala <?php echo htmlspecialchars($reserva['sala']); ?> | <?php echo htmlspecialchars($reserva['formato_proyeccion']); ?></p>
                                               <p><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?></p>
                                           </div>
                                           <div class="ticket-d-card-seat">
                                               <div class="ticket-d-seat-label">ASIENTO</div>
                                               <div class="ticket-d-seat-number"><?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?></div>
                                           </div>
                                       </div>
                                       <div class="ticket-d-card-qrcode">
                                           <?php if (!$ticket['usado'] && strtotime($reserva['fecha_hora']) > time()): ?>
                                           <!-- QR Code -->
                                           <div class="ticket-d-qrcode-container" id="qrcode-<?php echo $ticket['id']; ?>"></div>
                                           <div class="ticket-d-barcode-number"><?php echo $ticket['codigo_barras']; ?></div>
                                           <div class="mt-2 no-print">
                                               <a href="#" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#qrModal<?php echo $ticket['id']; ?>">
                                                   <i class="fas fa-expand-alt"></i> Ver completo
                                               </a>
                                           </div>
                                           <?php else: ?>
                                           <div class="alert alert-<?php echo $ticket['usado'] ? 'success' : 'danger'; ?> mb-0">
                                               <?php if ($ticket['usado']): ?>
                                               <i class="fas fa-check-circle"></i> Ticket utilizado el <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_uso'])); ?>
                                               <?php else: ?>
                                               <i class="fas fa-times-circle"></i> Ticket vencido
                                               <?php endif; ?>
                                           </div>
                                           <?php endif; ?>
                                       </div>
                                   </div>
                                   
                                   <!-- Modal para QR completo -->
                                   <div class="modal fade" id="qrModal<?php echo $ticket['id']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                       <div class="modal-dialog modal-dialog-centered" role="document">
                                           <div class="modal-content">
                                               <div class="modal-header no-print">
                                                   <h5 class="modal-title">Ticket para <?php echo htmlspecialchars($reserva['pelicula']); ?></h5>
                                                   <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                       <span aria-hidden="true">&times;</span>
                                                   </button>
                                               </div>
                                               <div class="modal-body ticket-modal-body">
                                                   <img src="<?php echo $posterUrl; ?>" alt="Poster" class="ticket-modal-poster">
                                                   <div class="ticket-modal-qr" id="qrcode-modal-<?php echo $ticket['id']; ?>"></div>
                                                   <div class="ticket-modal-info">
                                                       <div class="ticket-modal-info-item">
                                                           <i class="fas fa-ticket-alt"></i>
                                                           <span><?php echo $ticket['codigo_barras']; ?></span>
                                                       </div>
                                                       <div class="ticket-modal-info-item">
                                                           <i class="fas fa-film"></i>
                                                           <span><?php echo htmlspecialchars($reserva['pelicula']); ?></span>
                                                       </div>
                                                       <div class="ticket-modal-info-item">
                                                           <i class="fas fa-calendar-alt"></i>
                                                           <span><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?></span>
                                                       </div>
                                                       <div class="ticket-modal-info-item">
                                                           <i class="fas fa-couch"></i>
                                                           <span>Sala <?php echo htmlspecialchars($reserva['sala']); ?> | Asiento <?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?></span>
                                                       </div>
                                                       <div class="ticket-modal-info-item">
                                                           <i class="fas fa-building"></i>
                                                           <span><?php echo htmlspecialchars($reserva['cine']); ?></span>
                                                       </div>
                                                   </div>
                                                   <div class="ticket-modal-actions no-print">
                                                       <button type="button" class="btn btn-primary" onclick="downloadTicketPDF(<?php echo $ticket['id']; ?>)">
                                                           <i class="fas fa-download"></i> Descargar
                                                       </button>
                                                   </div>
                                                   <div class="ticket-modal-footer">
                                                       <p>Este ticket no es válido como comprobante fiscal</p>
                                                       <p><?php echo htmlspecialchars($reserva['cine_direccion']); ?></p>
                                                   </div>
                                               </div>
                                           </div>
                                       </div>
                                   </div>
                               <?php endforeach; ?>
                           </div>
                       </div>
                   </div>
                   <?php endif; ?>
                   
                   <?php if (!empty($productos)): ?>
                   <div class="row mt-4">
                       <div class="col-md-12">
                           <h5 class="card-title">
                               <i class="fas fa-utensils text-primary"></i> 
                               Productos de Candy Bar
                           </h5>
                           <div class="table-responsive">
                               <table class="table table-striped">
                                   <thead>
                                       <tr>
                                           <th>Producto</th>
                                           <th>Cantidad</th>
                                           <th>Precio Unitario</th>
                                           <th>Subtotal</th>
                                       </tr>
                                   </thead>
                                   <tbody>
                                       <?php 
                                       $total_productos = 0;
                                       foreach ($productos as $producto): 
                                           if (empty($producto['producto_id'])) continue;
                                           $subtotal = $producto['cantidad'] * $producto['precio_unitario'];
                                           $total_productos += $subtotal;
                                       ?>
                                       <tr>
                                           <td><?php echo htmlspecialchars($producto['producto_nombre']); ?></td>
                                           <td><?php echo $producto['cantidad']; ?></td>
                                           <td>Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></td>
                                           <td>Bs. <?php echo number_format($subtotal, 2); ?></td>
                                       </tr>
                                       <?php endforeach; ?>
                                   </tbody>
                                   <tfoot>
                                       <tr>
                                           <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                           <td><strong>Bs. <?php echo number_format($total_productos, 2); ?></strong></td>
                                       </tr>
                                   </tfoot>
                               </table>
                           </div>
                           <div class="alert alert-info mt-2">
                               <strong>Estado del pedido:</strong> 
                               <?php 
                               $estado_pedido = isset($productos[0]['estado']) ? $productos[0]['estado'] : 'pendiente';
                               $badge_class_pedido = '';
                               switch ($estado_pedido) {
                                   case 'pendiente': $badge_class_pedido = 'badge-warning'; break;
                                   case 'preparando': $badge_class_pedido = 'badge-info'; break;
                                   case 'entregado': $badge_class_pedido = 'badge-success'; break;
                                   case 'cancelado': $badge_class_pedido = 'badge-danger'; break;
                               }
                               ?>
                               <span class="badge <?php echo $badge_class_pedido; ?>">
                                   <?php echo ucfirst($estado_pedido); ?>
                               </span>
                               <?php if ($estado_pedido != 'entregado' && $estado_pedido != 'cancelado'): ?>
                               <p class="mt-2 mb-0">
                                   <i class="fas fa-info-circle"></i> 
                                   Recoge tus productos antes de ingresar a la sala presentando este comprobante en el Candy Bar.
                               </p>
                               <?php endif; ?>
                           </div>
                       </div>
                   </div>
                   <?php endif; ?>
                   
                   <div class="row mt-4 no-print">
                        <div class="col-md-12 text-center">
                            <a href="perfil-reservas.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver a Mis Reservas
                            </a>
                            
                            <?php if ($reserva['estado'] === 'pendiente'): ?>
                            <a href="verificar_pago.php?id=<?php echo $reservaId; ?>" class="btn btn-warning">
                                <i class="fas fa-sync-alt"></i> Verificar Pago
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
               </div>
           </div>
       </div>
   </div>
</div>

<!-- Contenedor para preparación de tickets -->
<div id="print-container" class="d-none"></div>

<script>
// Definir el objeto jspdf para accederlo globalmente
const { jsPDF } = window.jspdf;

// Generar códigos QR para cada ticket
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach($tickets as $ticket): ?>
    <?php if (!$ticket['usado'] && strtotime($reserva['fecha_hora']) > time()): ?>
    (function() {
        // Creamos un código QR con los datos del ticket
        var qrData = `
            Ticket: <?php echo $ticket['codigo_barras']; ?>
            Película: <?php echo $reserva['pelicula']; ?>
            Función: <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>
            Cine: <?php echo $reserva['cine']; ?>
            Sala: <?php echo $reserva['sala']; ?>
        `;
        
        // Crear QR para la tarjeta (tamaño más pequeño)
        var qr = qrcode(0, 'M');
        qr.addData(qrData);
        qr.make();
        
        var qrCard = document.getElementById('qrcode-<?php echo $ticket['id']; ?>');
        if(qrCard) {
            qrCard.innerHTML = qr.createImgTag(3, 0);
            // Asegurar que la imagen es visible
            var qrImg = qrCard.querySelector('img');
            if(qrImg) {
                qrImg.style.width = '100%';
                qrImg.style.height = '100%';
                qrImg.style.maxWidth = '100px';
                qrImg.style.margin = '0 auto';
                qrImg.style.display = 'block';
            }
        }
        
        // Crear QR para el modal (tamaño más grande)
        var qrModal = document.getElementById('qrcode-modal-<?php echo $ticket['id']; ?>');
        if(qrModal) {
            qrModal.innerHTML = qr.createImgTag(6, 0);
            // Asegurar que la imagen es visible
            var qrImgModal = qrModal.querySelector('img');
            if(qrImgModal) {
                qrImgModal.style.width = '100%';
                qrImgModal.style.height = '100%';
                qrImgModal.style.maxWidth = '200px';
                qrImgModal.style.margin = '0 auto';
                qrImgModal.style.display = 'block';
            }
        }
    })();
    <?php endif; ?>
    <?php endforeach; ?>
});

function downloadTicketPDF(ticketId) {
    // Create PDF first
    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: [100, 150]
    });
    
    // Get ticket data
    <?php foreach($tickets as $index => $ticket): ?>
    if (ticketId == <?php echo $ticket['id']; ?>) {
        const ticketData = {
            code: '<?php echo $ticket['codigo_barras']; ?>',
            movie: '<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['pelicula'])); ?>',
            date: '<?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>',
            room: '<?php echo htmlspecialchars($reserva['sala']); ?>',
            cinema: '<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine'])); ?>',
            address: '<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine_direccion'])); ?>',
            seat: '<?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?>',
            format: '<?php echo htmlspecialchars($reserva['formato_proyeccion']); ?>'
        };
        
        // Add ticket header
        pdf.setFillColor(51, 122, 183);
        pdf.rect(0, 0, 100, 15, 'F');
        pdf.setTextColor(255, 255, 255);
        pdf.setFontSize(14);
        pdf.text('MULTICINE', 50, 8, { align: 'center' });
        pdf.setFontSize(10);
        pdf.text('TICKET #' + ticketData.code, 50, 13, { align: 'center' });
        
        // Add ticket details
        pdf.setTextColor(0, 0, 0);
        pdf.setFontSize(12);
        pdf.text(ticketData.movie, 50, 25, { align: 'center' });
        
        pdf.setFontSize(10);
        pdf.text('Fecha: ' + ticketData.date, 10, 35);
        pdf.text('Sala: ' + ticketData.room + ' | ' + ticketData.format, 10, 42);
        pdf.text('Asiento: ' + ticketData.seat, 10, 49);
        pdf.text('Cine: ' + ticketData.cinema, 10, 56);
        
        // Add QR Code (placeholder text)
        pdf.setFillColor(240, 240, 240);
        pdf.roundedRect(25, 65, 50, 50, 2, 2, 'F');
        pdf.setFontSize(8);
        pdf.text('Código QR para el ticket', 50, 90, { align: 'center' });
        pdf.text(ticketData.code, 50, 95, { align: 'center' });
        
        // Add footer
        pdf.setDrawColor(200, 200, 200);
        pdf.setLineDashPattern([1, 1], 0);
        pdf.line(10, 125, 90, 125);
        pdf.setFontSize(8);
        pdf.text('Este ticket no es válido como comprobante fiscal', 50, 132, { align: 'center' });
        pdf.text(ticketData.address, 50, 138, { align: 'center' });
        
        // Save PDF
        pdf.save('ticket_<?php echo $reservaId; ?>_' + ticketId + '.pdf');
    }
    <?php endforeach; ?>
}

function downloadAllTicketsPDF() {
    // Crear un nuevo PDF
    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4'
    });
    
    // Contador para páginas
    let pageCount = 0;
    
    <?php 
    $validTickets = array_filter($tickets, function($t) use ($reserva) {
        return !$t['usado'] && strtotime($reserva['fecha_hora']) > time();
    });
    ?>
    
    <?php if (!empty($validTickets)): ?>
    
    // Título del documento
    pdf.setFontSize(18);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Tickets para <?php echo htmlspecialchars(str_replace("'", "\'", $reserva['pelicula'])); ?>', 105, 20, { align: 'center' });
    
    pdf.setFontSize(12);
    pdf.setTextColor(0, 0, 0);
    pdf.text('Función: <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>', 105, 30, { align: 'center' });
    pdf.text('Cine: <?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine'])); ?>', 105, 38, { align: 'center' });
    pdf.text('Sala: <?php echo htmlspecialchars($reserva['sala']); ?>', 105, 46, { align: 'center' });
    
    // Iniciar posición Y para tickets
    let yPos = 60;
    const ticketsPerPage = 3;
    let ticketCount = 0;
    
    <?php foreach($validTickets as $index => $ticket): ?>
    
    // Si necesitamos una nueva página
    if (ticketCount > 0 && ticketCount % ticketsPerPage === 0) {
        pdf.addPage();
        yPos = 20;
    }
    
    // Crear ticket
    pdf.setFillColor(245, 245, 245);
    pdf.roundedRect(20, yPos, 170, 60, 3, 3, 'F');
    
    // Borde punteado
    pdf.setDrawColor(180, 180, 180);
    pdf.setLineDashPattern([2, 2], 0);
    pdf.roundedRect(20, yPos, 170, 60, 3, 3, 'S');
    
    // Encabezado del ticket
    pdf.setFillColor(51, 122, 183);
    pdf.rect(20, yPos, 170, 12, 'F');
    
    // Logo y número de ticket
    pdf.setTextColor(255, 255, 255);
    pdf.setFontSize(14);
    pdf.text('MULTICINE', 35, yPos + 8);
    pdf.text('TICKET #<?php echo $ticket['codigo_barras']; ?>', 160, yPos + 8, { align: 'right' });
    
    // Detalles del ticket
    pdf.setTextColor(0, 0, 0);
    pdf.setFontSize(12);
    
    // Columna izquierda: película y detalles
    pdf.text('<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['pelicula'])); ?>', 30, yPos + 22);
    
    pdf.setFontSize(10);
    pdf.text('Fecha: <?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>', 30, yPos + 30);
    pdf.text('Sala: <?php echo htmlspecialchars($reserva['sala']); ?> | <?php echo htmlspecialchars($reserva['formato_proyeccion']); ?>', 30, yPos + 38);
    pdf.text('Asiento: <?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?>', 30, yPos + 46);
    
    // Columna derecha: info de asiento
    pdf.setFillColor(240, 240, 240);
    pdf.roundedRect(140, yPos + 20, 40, 30, 2, 2, 'F');
    
    pdf.setFontSize(10);
    pdf.text('ASIENTO', 160, yPos + 30, { align: 'center' });
    
    pdf.setFontSize(18);
    pdf.text('<?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?>', 160, yPos + 42, { align: 'center' });
    
    // Código de barras en texto
    pdf.setFontSize(8);
    pdf.text('<?php echo $ticket['codigo_barras']; ?>', 160, yPos + 52, { align: 'center' });
    
    // Incrementar posición y contador
    yPos += 70;
    ticketCount++;
    
    <?php endforeach; ?>
    
    // Agregar instrucciones al final
    if (ticketCount % ticketsPerPage === 0) {
        pdf.addPage();
        yPos = 20;
    } else {
        yPos += 20;
    }
    
    pdf.setFontSize(14);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Instrucciones importantes:', 20, yPos);
    
    pdf.setFontSize(10);
    pdf.setTextColor(0, 0, 0);
    yPos += 10;
    pdf.text('• Presentate al menos 15 minutos antes del inicio de la función.', 25, yPos);
    yPos += 8;
    pdf.text('• Presenta este ticket impreso o digital en la entrada de la sala.', 25, yPos);
    yPos += 8;
    pdf.text('• Para cambios o cancelaciones, comunícate con atención al cliente al menos 2 horas antes.', 25, yPos);
    yPos += 8;
    pdf.text('• No se permite el ingreso de alimentos y bebidas externas al cine.', 25, yPos);
    
    // Agregar pie de página
    yPos += 20;
    pdf.setDrawColor(180, 180, 180);
    pdf.setLineDashPattern([1, 1], 0);
    pdf.line(20, yPos, 190, yPos);
    yPos += 10;
    pdf.setFontSize(8);
    pdf.text('<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine_direccion'])); ?>', 105, yPos, { align: 'center' });
    yPos += 5;
    pdf.text('Estos tickets no son válidos como comprobantes fiscales.', 105, yPos, { align: 'center' });
    
    // Descargar PDF
    pdf.save('tickets_reserva_<?php echo $reservaId; ?>.pdf');
    
    <?php else: ?>
    alert('No hay tickets válidos disponibles para descargar.');
    <?php endif; ?>
}

function downloadReservationDetailsPDF() {
    // Crear un nuevo PDF
    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: 'a4'
    });
    
    // Título y logo
    pdf.setFontSize(20);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Detalle de Reserva #<?php echo $reservaId; ?>', 105, 20, { align: 'center' });
    
    // Información de película y función
    pdf.setFontSize(16);
    pdf.setTextColor(0, 0, 0);
    pdf.text('<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['pelicula'])); ?>', 105, 35, { align: 'center' });
    
    // Sección información película
    pdf.setFillColor(245, 245, 245);
    pdf.roundedRect(20, 45, 80, 100, 3, 3, 'F');
    
    pdf.setFontSize(12);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Información de la Película', 60, 55, { align: 'center' });
    
    pdf.setTextColor(0, 0, 0);
    pdf.setFontSize(10);
    
    let yPos = 65;
    
    pdf.setFontSize(10);
    pdf.text('Función:', 25, yPos);
    pdf.text('<?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Cine:', 25, yPos);
    pdf.text('<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine'])); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Sala:', 25, yPos);
    pdf.text('<?php echo htmlspecialchars($reserva['sala']); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Formato de sala:', 25, yPos);
    pdf.text('<?php echo htmlspecialchars($reserva['formato_sala']); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Idioma:', 25, yPos);
    pdf.text('<?php echo htmlspecialchars($reserva['idioma']); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Formato proyección:', 25, yPos);
    pdf.text('<?php echo htmlspecialchars($reserva['formato_proyeccion']); ?>', 75, yPos, { align: 'right' });
    yPos += 10;
    
    // Sección información reserva
    pdf.setFillColor(245, 245, 245);
    pdf.roundedRect(110, 45, 80, 100, 3, 3, 'F');
    
    pdf.setFontSize(12);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Información de la Reserva', 150, 55, { align: 'center' });
    
    pdf.setTextColor(0, 0, 0);
    pdf.setFontSize(10);
    
    yPos = 65;
    
    pdf.text('Estado:', 115, yPos);
    pdf.text('<?php echo ucfirst($reserva['estado']); ?>', 165, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Fecha de reserva:', 115, yPos);
    pdf.text('<?php echo date('d/m/Y H:i', strtotime($reserva['fecha_reserva'])); ?>', 165, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.text('Precio base:', 115, yPos);
    pdf.text('Bs. <?php echo number_format($reserva['precio_base'], 2); ?>', 165, yPos, { align: 'right' });
    yPos += 10;
    
    <?php if ($reserva['descuento_aplicado'] > 0): ?>
    pdf.text('Descuento:', 115, yPos);
    pdf.text('Bs. <?php echo number_format($reserva['descuento_aplicado'], 2); ?>', 165, yPos, { align: 'right' });
    yPos += 10;
    <?php endif; ?>
    
    pdf.text('Total pagado:', 115, yPos);
    pdf.setFontSize(10);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Bs. <?php echo number_format($reserva['total_pagado'], 2); ?>', 165, yPos, { align: 'right' });
    yPos += 10;
    
    pdf.setTextColor(0, 0, 0);
    pdf.text('Asientos:', 115, yPos);
    <?php 
    $asientosSimple = array_map(function($a) { 
        return $a['fila'] . $a['numero']; 
    }, $asientos);
    $asientosTexto = implode(', ', $asientosSimple);
    ?>
    pdf.text('<?php echo $asientosTexto; ?>', 165, yPos, { align: 'right', maxWidth: 45 });
    
    // Instrucciones
    yPos = 155;
    pdf.setFontSize(12);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Instrucciones importantes:', 20, yPos);
    
    pdf.setFontSize(10);
    pdf.setTextColor(0, 0, 0);
    yPos += 10;
    pdf.text('• Presentate al menos 15 minutos antes del inicio de la función.', 25, yPos);
    yPos += 8;
    pdf.text('• Para cambios o cancelaciones, comunícate con atención al cliente al menos 2 horas antes.', 25, yPos);
    yPos += 8;
    pdf.text('• No se permite el ingreso de alimentos y bebidas externas al cine.', 25, yPos);
    yPos += 8;
    pdf.text('• Para cualquier consulta, llama a nuestra línea de atención: +591 2 5553333', 25, yPos);
    
    // Sección de tickets
    <?php if (!empty($validTickets)): ?>
    yPos += 15;
    pdf.setFontSize(12);
    pdf.setTextColor(51, 122, 183);
    pdf.text('Tickets disponibles:', 20, yPos);
    
    yPos += 10;
    pdf.setFontSize(10);
    pdf.setTextColor(0, 0, 0);
    
    <?php foreach($validTickets as $index => $ticket): ?>
    pdf.text('• Ticket #<?php echo $ticket['codigo_barras']; ?> - Asiento <?php echo $asientos[$index]['fila'] . $asientos[$index]['numero']; ?>', 25, yPos);
    yPos += 7;
    <?php endforeach; ?>
    <?php endif; ?>
    
    // Pie de página
    yPos = 265;
    pdf.setDrawColor(180, 180, 180);
    pdf.setLineDashPattern([1, 1], 0);
    pdf.line(20, yPos, 190, yPos);
    
    yPos += 10;
    pdf.setFontSize(8);
    pdf.text('<?php echo htmlspecialchars(str_replace("'", "\'", $reserva['cine_direccion'])); ?>', 105, yPos, { align: 'center' });
    
    // Descargar PDF
    pdf.save('reserva_<?php echo $reservaId; ?>.pdf');
}

// Mejorar estilos de los códigos QR
document.addEventListener('DOMContentLoaded', function() {
    // Ajustar estilos de los QR en las tarjetas
    const qrImages = document.querySelectorAll('.ticket-d-qrcode-container img');
    qrImages.forEach(img => {
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.maxWidth = '100px';
        img.style.display = 'block';
        img.style.margin = '0 auto';
    });
    
    // Ajustar estilos de los QR en los modales
    const qrModalImages = document.querySelectorAll('.ticket-modal-qr img');
    qrModalImages.forEach(img => {
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.maxWidth = '200px';
        img.style.display = 'block';
        img.style.margin = '0 auto';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>