<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Ahora en Cartelera en <span id="nombreCineActual"><?php echo $nombreCine; ?></span></h2>
            <a href="cartelera.php?cine=<?php echo $cineSeleccionado; ?>" class="carhome-link-ver">Ver todas</a>
        </div>
        
        <?php if (!empty($peliculasCartelera)): ?>
            <div class="carhome-slider-container">
                <?php foreach ($peliculasCartelera as $pelicula): ?>
                    <div class="carhome-movie-card">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="carhome-movie-link">
                            <div class="carhome-poster-container">
                                <img src="<?php echo obtenerPosterUrl($pelicula['id'], $pelicula['poster_url']); ?>" alt="<?php echo $pelicula['titulo']; ?>" class="carhome-poster-img">
                                <?php if ($pelicula['estado'] == 'estreno'): ?>
                                    <span class="carhome-badge-estreno">ESTRENO</span>
                                <?php endif; ?>
                            </div>
                            <div class="carhome-movie-info">
                                <h5 class="carhome-movie-title"><?php echo $pelicula['titulo']; ?></h5>
                                <?php if (!empty($pelicula['clasificacion'])): ?>
                                    <span class="carhome-badge-clasificacion"><?php echo $pelicula['clasificacion']; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay películas en cartelera en <?php echo $nombreCine; ?> en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>