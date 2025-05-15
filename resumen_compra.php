<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
   // If not logged in, store the form data in session to recover after login
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $_SESSION['temp_reservation'] = $_POST;
   }
   
   setMensaje('Debe iniciar sesión para continuar con la reserva', 'warning');
   redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   // Check if there's a stored reservation
   if (isset($_SESSION['temp_reservation'])) {
       $_POST = $_SESSION['temp_reservation'];
       unset($_SESSION['temp_reservation']);
   } else {
       setMensaje('Acceso inválido', 'warning');
       redirect('/multicinev3/');
   }
}

// Get form data
$funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
$asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';

if (!$funcionId || empty($asientosSeleccionados)) {
   setMensaje('Debe seleccionar al menos un asiento', 'warning');
   redirect('reserva.php?funcion=' . $funcionId);
}

// Get function details
$queryFuncion = "SELECT f.*, p.titulo as pelicula_titulo, p.duracion_min, s.nombre as sala_nombre, 
               s.capacidad, c.nombre as cine_nombre, c.direccion,
               i.nombre as idioma, fmt.nombre as formato, p.id as pelicula_id
               FROM funciones f 
               JOIN peliculas p ON f.pelicula_id = p.id 
               JOIN salas s ON f.sala_id = s.id 
               JOIN cines c ON s.cine_id = c.id 
               JOIN idiomas i ON f.idioma_id = i.id 
               JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id 
               WHERE f.id = ?";
$stmtFuncion = $conn->prepare($queryFuncion);
$stmtFuncion->bind_param("i", $funcionId);
$stmtFuncion->execute();
$resultFuncion = $stmtFuncion->get_result();

if ($resultFuncion->num_rows === 0) {
   setMensaje('Función no encontrada', 'warning');
   redirect('/multicinev3/');
}

$funcion = $resultFuncion->fetch_assoc();

// Process selected seats
$asientosIds = explode(',', $asientosSeleccionados);
$asientosIds = array_map('intval', $asientosIds);
$numAsientos = count($asientosIds);

// Check if seats are still available
$placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
$queryAsientos = "SELECT a.*, s.id as sala_id FROM asientos a 
                JOIN salas s ON a.sala_id = s.id 
                WHERE a.id IN ($placeholders) 
                AND a.disponible = 1";

$stmtAsientos = $conn->prepare($queryAsientos);
$types = str_repeat('i', count($asientosIds));
$stmtAsientos->bind_param($types, ...$asientosIds);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

if ($resultAsientos->num_rows !== count($asientosIds)) {
   setMensaje('Algunos asientos seleccionados ya no están disponibles', 'warning');
   redirect('reserva.php?funcion=' . $funcionId);
}

// Get movie poster
$queryPoster = "SELECT m.url 
              FROM multimedia_pelicula mp 
              JOIN multimedia m ON mp.multimedia_id = m.id 
              WHERE mp.pelicula_id = ? AND mp.proposito = 'poster' 
              LIMIT 1";
$stmtPoster = $conn->prepare($queryPoster);
$stmtPoster->bind_param("i", $funcion['pelicula_id']);
$stmtPoster->execute();
$resultPoster = $stmtPoster->get_result();

$posterUrl = 'assets/img/poster-default.jpg';
if ($resultPoster->num_rows > 0) {
   $poster = $resultPoster->fetch_assoc();
   $posterUrl = $poster['url'];
}

// Get selected seats details
$asientosData = [];
while ($asiento = $resultAsientos->fetch_assoc()) {
   $asientosData[] = $asiento;
}

// Sort seats by row and number for display
usort($asientosData, function($a, $b) {
   if ($a['fila'] === $b['fila']) {
       return $a['numero'] - $b['numero'];
   }
   return strcmp($a['fila'], $b['fila']);
});

// Calculate total seat cost
$costoTotalAsientos = $funcion['precio_base'] * $numAsientos;

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/resumen.css" rel="stylesheet">
<link href="assets/css/resumen_compra.css" rel="stylesheet">

<div class="res-comp-container">
   <div class="res-comp-sidebar">
       <div class="res-comp-movie-info">
           <a href="/multicinev3/" class="res-comp-home-btn">
               <i class="fas fa-home"></i>
           </a>
           <img src="<?php echo $posterUrl; ?>" alt="<?php echo $funcion['pelicula_titulo']; ?>" class="res-comp-movie-poster">
           <div class="res-comp-age-rating">
               <div class="res-comp-rating-circle">12</div>
           </div>
           <h2 class="res-comp-movie-title"><?php echo $funcion['pelicula_titulo']; ?></h2>
           <p class="res-comp-cinema-name"><?php echo $funcion['cine_nombre']; ?></p>
       </div>
       
       <div class="res-comp-cinema-info">
           <h3>Cine</h3>
           <p class="res-comp-info-text"><?php echo $funcion['cine_nombre']; ?></p>
           
           <h3>Fecha</h3>
           <p class="res-comp-info-text"><?php 
               setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'Spanish');
               echo strftime('%A %d de %B de %Y', strtotime($funcion['fecha_hora'])); 
           ?></p>
           
           <h3>Proyección</h3>
           <p class="res-comp-info-text">
               <?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?> <?php echo $funcion['formato']; ?>
               <br>
               <small>Versión Original</small>
           </p>
           <p class="res-comp-info-text res-comp-end-time">
               Hora prevista de finalización: <?php 
                   $endTime = strtotime($funcion['fecha_hora']) + ($funcion['duracion_min'] * 60);
                   echo date('H:i', $endTime); 
               ?>
           </p>
       </div>
       
       <div class="res-comp-tickets-info">
           <h3>Mis entradas</h3>
           <div class="res-comp-selected-seats-summary">
               <p>Asientos seleccionados:</p>
               <div class="res-comp-seat-list">
                   <?php 
                   $seatLabels = array_map(function($seat) {
                       return $seat['fila'] . $seat['numero'];
                   }, $asientosData);
                   echo implode(', ', $seatLabels);
                   ?>
               </div>
           </div>
           <div class="res-comp-ticket-item">
               <span><?php echo $numAsientos; ?>x Entrada</span>
               <span>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></span>
           </div>
           
           <div class="res-comp-ticket-total">
               <span>Total</span>
               <span id="totalPriceDisplay">Bs. <?php echo number_format($costoTotalAsientos, 2); ?></span>
           </div>
       </div>
   </div>
   
   <div class="res-comp-main-content">
       <h2>Resumen de compra</h2>
       
       <div class="res-comp-rates-section">
           <h3>Entradas (<?php echo $numAsientos; ?> asientos seleccionados)</h3>
           
           <?php for($i = 0; $i < $numAsientos; $i++): ?>
               <div class="res-comp-rate-item" data-seat="<?php echo $asientosData[$i]['fila'] . $asientosData[$i]['numero']; ?>">
                   <div class="res-comp-rate-info">
                       <span class="res-comp-seat-label">Asiento <?php echo $asientosData[$i]['fila'] . $asientosData[$i]['numero']; ?></span>
                       <span class="res-comp-rate-description">Entrada Cine</span>
                       <span class="res-comp-rate-price">Bs. <?php echo number_format($funcion['precio_base'], 2); ?></span>
                   </div>
               </div>
           <?php endfor; ?>
       </div>
       
       <div class="res-comp-continue-container">
           <form id="completePurchaseForm" action="confirmar_compra.php" method="post">
               <input type="hidden" name="funcion_id" value="<?php echo $funcionId; ?>">
               <input type="hidden" name="asientos_seleccionados" value="<?php echo $asientosSeleccionados; ?>">
               <input type="hidden" name="codigo_promocional" value="">
               <input type="hidden" name="descuento_aplicado" value="0">
               <button type="submit" class="res-comp-continue-btn">Continuar</button>
           </form>
       </div>
   </div>
</div>

<?php require_once 'includes/footer.php'; ?>