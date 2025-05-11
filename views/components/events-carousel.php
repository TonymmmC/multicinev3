<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Especiales y Eventos en <span id="nombreCineEventos"><?php echo $nombreCine; ?></span></h2>
            <a href="eventos.php" class="carhome-link-ver">Ver todos</a>
        </div>
        
        <?php if (!empty($eventosEspeciales)): ?>
            <div class="carhome-slider-container">
                <?php foreach ($eventosEspeciales as $evento): ?>
                    <div class="carhome-movie-card">
                        <a href="evento.php?id=<?php echo $evento['id']; ?>" class="carhome-movie-link">
                            <div class="carhome-poster-container">
                                <img src="<?php echo $evento['imagen_url'] ?? 'assets/img/evento-default.jpg'; ?>" alt="<?php echo $evento['nombre']; ?>" class="carhome-poster-img">
                                <?php if (!empty($evento['etiqueta'])): ?>
                                    <span class="carhome-badge-evento"><?php echo $evento['etiqueta']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="carhome-movie-info">
                                <h5 class="carhome-movie-title"><?php echo $evento['nombre']; ?></h5>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay eventos especiales en <?php echo $nombreCine; ?> en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>