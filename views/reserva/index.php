<?php require_once 'includes/header.php'; ?>

<link href="assets/css/reserva.css" rel="stylesheet">

<div class="res-container">
    <!-- Movie info sidebar -->
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

<script src="assets/js/reserva.js"></script>

<?php require_once 'includes/footer.php'; ?>