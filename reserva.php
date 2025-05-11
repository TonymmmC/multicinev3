<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Get function ID from URL
$funcionId = isset($_GET['funcion']) ? intval($_GET['funcion']) : null;

if (!$funcionId) {
    setMensaje('No se ha seleccionado una función', 'warning');
    redirect('/multicinev3/');
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

// Get all seats for this room
$queryAsientos = "SELECT a.id, a.fila, a.numero, a.tipo, a.disponible 
                 FROM asientos a 
                 WHERE a.sala_id = ? 
                 ORDER BY a.fila, a.numero";
$stmtAsientos = $conn->prepare($queryAsientos);
$stmtAsientos->bind_param("i", $funcion['sala_id']);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

$asientos = [];
while ($row = $resultAsientos->fetch_assoc()) {
    $asientos[] = $row;
}

// Get reserved seats for this function
$queryReservados = "SELECT ar.asiento_id 
                  FROM asientos_reservados ar 
                  JOIN reservas r ON ar.reserva_id = r.id 
                  WHERE r.funcion_id = ?";
$stmtReservados = $conn->prepare($queryReservados);
$stmtReservados->bind_param("i", $funcionId);
$stmtReservados->execute();
$resultReservados = $stmtReservados->get_result();

$asientosReservados = [];
while ($row = $resultReservados->fetch_assoc()) {
    $asientosReservados[] = $row['asiento_id'];
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

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">

<div class="res-container">
    <div class="res-sidebar">
        <div class="res-movie-info">
            <a href="/multicinev3/" class="res-home-btn">
                <i class="fas fa-home"></i>
            </a>
            <img src="<?php echo $posterUrl; ?>" alt="<?php echo $funcion['pelicula_titulo']; ?>" class="res-movie-poster">
            <div class="res-age-rating">
                <div class="res-rating-circle">12</div>
            </div>
            <h2 class="res-movie-title"><?php echo $funcion['pelicula_titulo']; ?></h2>
            <p class="res-cinema-name"><?php echo $funcion['cine_nombre']; ?></p>
        </div>
        
        <div class="res-cinema-info">
            <h3>Cine</h3>
            <p class="res-info-text"><?php echo $funcion['cine_nombre']; ?></p>
            
            <h3>Fecha</h3>
            <p class="res-info-text"><?php 
                setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'Spanish');
                echo strftime('%A %d de %B de %Y', strtotime($funcion['fecha_hora'])); 
            ?></p>
            
            <h3>Proyección</h3>
            <p class="res-info-text">
                <?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?> <?php echo $funcion['formato']; ?>
                <br>
                <small>Versión Original</small>
            </p>
            <p class="res-info-text res-end-time">
                Hora prevista de finalización: <?php 
                    $endTime = strtotime($funcion['fecha_hora']) + ($funcion['duracion_min'] * 60);
                    echo date('H:i', $endTime); 
                ?>
            </p>
        </div>
    </div>
    
    <div class="res-main-content">
        <div class="res-auditorium-info">
            <h3>Sala <?php echo substr($funcion['sala_nombre'], -1); ?></h3>
            
            <div class="res-seat-selection">
                <h2>Selecciona tu(s) asiento(s)</h2>
                <p><?php echo $funcion['asientos_disponibles']; ?> asientos libres</p>
                
                <div class="res-seat-map">
                    <?php 
                    // Organize seats by row
                    $seatsByRow = [];
                    foreach($asientos as $asiento) {
                        $fila = $asiento['fila'];
                        if(!isset($seatsByRow[$fila])) {
                            $seatsByRow[$fila] = [];
                        }
                        $seatsByRow[$fila][$asiento['numero']] = $asiento;
                    }
                    
                    // Sort rows in reverse alphabetical order for inverted layout
                    krsort($seatsByRow);
                    
                    // Display seats
                    foreach($seatsByRow as $fila => $asientosEnFila) {
                        echo '<div class="res-seat-row">';
                        
                        // Sort seats by number for proper display
                        ksort($asientosEnFila);
                        
                        // Display seats with proper spacing
                        for($i = 1; $i <= 33; $i++) {
                            if(isset($asientosEnFila[$i])) {
                                $asiento = $asientosEnFila[$i];
                                $seatClass = 'res-seat';
                                
                                // Check if seat is reserved
                                if(in_array($asiento['id'], $asientosReservados)) {
                                    $seatClass .= ' res-occupied';
                                } 
                                // Check if seat is not available
                                elseif(!$asiento['disponible']) {
                                    $seatClass .= ' res-unavailable';
                                }
                                // Otherwise, it's a free seat
                                else {
                                    $seatClass .= ' res-free';
                                }
                                
                                echo "<div class=\"$seatClass\" data-id=\"{$asiento['id']}\" data-row=\"{$asiento['fila']}\" data-number=\"{$asiento['numero']}\">
                                    <img src=\"assets/icons/seat.svg\" class=\"seat-icon\" alt=\"Seat\">
                                    <span class=\"res-seat-label\">{$asiento['fila']}{$asiento['numero']}</span>
                                </div>";
                            } else {
                                // Empty space where there's no seat
                                echo '<div class="res-no-seat"></div>';
                            }
                        }
                        
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <div class="res-screen">
                    <p>Pantalla</p>
                    <div class="res-screen-line"></div>
                </div>
                
                <div class="res-seat-legend">
                    <div class="res-legend-item">
                        <div class="res-seat res-my-seat">
                            <img src="assets/icons/seat.svg" class="seat-icon" alt="My Seat">
                        </div>
                        <span>Mis asientos</span>
                    </div>
                    <div class="res-legend-item">
                        <div class="res-seat res-free">
                            <img src="assets/icons/seat.svg" class="seat-icon" alt="Free Seat">
                        </div>
                        <span>Asientos libres</span>
                    </div>
                    <div class="res-legend-item">
                        <div class="res-seat res-occupied">
                            <img src="assets/icons/seat.svg" class="seat-icon" alt="Occupied Seat">
                        </div>
                        <span>Asientos ocupados</span>
                    </div>
                </div>
                
                <div class="res-selected-seats">
                    <div class="res-count-badge">
                        <span id="reservedSeatsCount">0</span> asientos reservados
                    </div>
                    <div id="selectedSeatsDetails"></div>
                </div>
                
                <div class="res-continue-container">
                    <button class="res-continue-btn" id="continueBtn" disabled>Continuar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="reservationForm" action="resumen_compra.php" method="post">
    <input type="hidden" name="funcion_id" value="<?php echo $funcionId; ?>">
    <input type="hidden" name="asientos_seleccionados" id="asientosSeleccionados" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const seatMap = document.querySelector('.res-seat-map');
    const continueBtn = document.getElementById('continueBtn');
    const reservedSeatsCount = document.getElementById('reservedSeatsCount');
    const selectedSeatsDetails = document.getElementById('selectedSeatsDetails');
    const asientosSeleccionados = document.getElementById('asientosSeleccionados');
    
    // Selected seats array
    let selectedSeats = [];
    
    // Seat selection
    seatMap.addEventListener('click', function(e) {
        const seat = e.target.closest('.res-seat');
        if(!seat || seat.classList.contains('res-occupied')) return;
        
        if(seat.classList.contains('res-free')) {
            // Toggle selection
            if(seat.classList.contains('res-selected')) {
                seat.classList.remove('res-selected');
                selectedSeats = selectedSeats.filter(s => s.id !== seat.dataset.id);
            } else {
                seat.classList.add('res-selected');
                selectedSeats.push({
                    id: seat.dataset.id,
                    row: seat.dataset.row,
                    number: seat.dataset.number
                });
            }
            
            updateSelectedSeatsInfo();
        }
    });
    
    // Update selected seats UI
    function updateSelectedSeatsInfo() {
        reservedSeatsCount.textContent = selectedSeats.length;
        
        if(selectedSeats.length > 0) {
            // Sort seats by row and number
            selectedSeats.sort((a, b) => {
                if(a.row === b.row) {
                    return parseInt(a.number) - parseInt(b.number);
                }
                return a.row.localeCompare(b.row);
            });
            
            // Format selected seats for display
            let seatsText = selectedSeats.map(seat => `${seat.row}${seat.number}`).join(', ');
            selectedSeatsDetails.textContent = seatsText;
            
            // Update form input and enable continue button
            asientosSeleccionados.value = selectedSeats.map(seat => seat.id).join(',');
            continueBtn.disabled = false;
        } else {
            selectedSeatsDetails.textContent = '';
            asientosSeleccionados.value = '';
            continueBtn.disabled = true;
        }
    }
    
    // Continue button click
    continueBtn.addEventListener('click', function() {
        if(selectedSeats.length > 0) {
            document.getElementById('reservationForm').submit();
        }
    });

    // Real-time seat updating - check for updates every 10 seconds
    function checkForSeatUpdates() {
        fetch(`api/check_seats.php?funcion_id=<?php echo $funcionId; ?>`)
        .then(response => response.json())
        .then(data => {
            if(data.updated) {
                // Update occupied seats
                const occupiedSeats = data.occupied_seats;
                const seatElements = document.querySelectorAll('.res-seat');
                
                seatElements.forEach(seat => {
                    const seatId = seat.dataset.id;
                    
                    // If seat is now occupied but wasn't before
                    if(occupiedSeats.includes(parseInt(seatId)) && !seat.classList.contains('res-occupied')) {
                        seat.classList.remove('res-free', 'res-selected');
                        seat.classList.add('res-occupied');
                        
                        // If this seat was selected, remove it from selection
                        if(selectedSeats.some(s => s.id === seatId)) {
                            selectedSeats = selectedSeats.filter(s => s.id !== seatId);
                            updateSelectedSeatsInfo();
                        }
                    }
                });
            }
        })
        .catch(error => console.error('Error checking seat updates:', error));
    }
    
    // Initial check and then every 10 seconds
    checkForSeatUpdates();
    setInterval(checkForSeatUpdates, 10000);
});
</script>

<?php require_once 'includes/footer.php'; ?>