<div class="search-results-header">
    <h2 class="results-title">Resultados para: <span class="search-term">"<?php echo htmlspecialchars($termino); ?>"</span></h2>
    <p class="results-count"><?php echo count($resultados); ?> películas encontradas</p>
</div>

<div class="search-results-grid">
    <?php foreach ($resultados as $pelicula): ?>
        <div class="movie-card">
            <div class="movie-poster">
                <img src="assets/img/posters/<?php echo $pelicula['id']; ?>.jpg" 
                     onerror="this.src='assets/img/poster-default.jpg'" 
                     alt="<?php echo htmlspecialchars($pelicula['titulo']); ?>">
                <?php 
                switch ($pelicula['estado']) {
                    case 'estreno':
                        echo '<span class="movie-badge movie-badge-new">Estreno</span>';
                        break;
                    case 'regular':
                        echo '<span class="movie-badge movie-badge-playing">En cartelera</span>';
                        break;
                    case 'proximo':
                        echo '<span class="movie-badge movie-badge-soon">Próximamente</span>';
                        break;
                    default:
                        echo '<span class="movie-badge movie-badge-inactive">Inactivo</span>';
                }
                ?>
            </div>
            <div class="movie-info">
                <h3 class="movie-title"><?php echo htmlspecialchars($pelicula['titulo']); ?></h3>
                <div class="movie-meta">
                    <span class="movie-duration"><i class="far fa-clock"></i> <?php echo $pelicula['duracion_min']; ?> min</span>
                    <span class="movie-release"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></span>
                </div>
                <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="movie-details-link">Ver detalles</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>