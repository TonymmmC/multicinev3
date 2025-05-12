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