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