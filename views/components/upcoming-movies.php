<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Próximamente</h2>
            <a href="proximamente.php" class="carhome-link-ver">Ver todos</a>
        </div>
        
        <?php if (!empty($peliculasProximas)): ?>
            <div class="carhome-slider-container">
                <?php foreach ($peliculasProximas as $pelicula): ?>
                    <div class="carhome-movie-card">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="carhome-movie-link">
                            <div class="carhome-poster-container">
                                <img src="<?php echo obtenerPosterUrl($pelicula['id'], $pelicula['poster_url']); ?>" alt="<?php echo $pelicula['titulo']; ?>" class="carhome-poster-img">
                                <span class="carhome-badge-proximamente">PRÓXIMAMENTE</span>
                            </div>
                            <div class="carhome-movie-info">
                                <h5 class="carhome-movie-title"><?php echo $pelicula['titulo']; ?></h5>
                                <?php if (!empty($pelicula['clasificacion'])): ?>
                                    <span class="carhome-badge-clasificacion"><?php echo $pelicula['clasificacion']; ?></span>
                                <?php endif; ?>
                                <p class="carhome-movie-date">Estreno: <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay próximos estrenos programados en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>