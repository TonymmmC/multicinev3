<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<link href="assets/css/movie-details.css" rel="stylesheet">

<!-- Hero Banner Section -->
<div class="mc-hero-banner">
    <img src="assets/img/banners/<?php echo $data['pelicula']['id']; ?>.jpg" 
         onerror="this.src='assets/img/movie-backgrounds/default-movie-bg.jpg'" 
         class="mc-hero-bg" 
         alt="<?php echo $data['pelicula']['titulo']; ?> banner">
    <div class="mc-hero-overlay"></div>
    <div class="container mc-movie-content">
        <img src="<?php echo $data['pelicula']['multimedia']['poster']['url']; ?>" 
             class="mc-movie-poster" 
             alt="<?php echo $data['pelicula']['titulo']; ?>">
        
        <div class="mc-movie-info">
            <div class="d-flex mb-2">
                <?php if (!empty($data['formatos'])): ?>
                    <?php foreach($data['formatos'] as $formato): ?>
                        <span class="mc-format-tag"><?php echo $formato; ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="mc-format-tag">2D</span>
                <?php endif; ?>
            </div>
            
            <h1 class="mc-movie-title"><?php echo $data['pelicula']['titulo']; ?></h1>
            
            <div class="mc-movie-meta">
                <?php if (isset($data['valoracionesData']['valoraciones']) && $data['valoracionesData']['valoraciones']['promedio'] > 0): ?>
                    <div class="mc-movie-meta-item">
                        <i class="fas fa-star text-warning"></i> 
                        <?php echo $data['valoracionesData']['valoraciones']['promedio']; ?>/5 
                        (<?php echo $data['valoracionesData']['valoraciones']['total']; ?>)
                    </div>
                <?php endif; ?>
                
                <div class="mc-movie-meta-item">
                    <i class="far fa-clock"></i> <?php echo $data['pelicula']['duracion_min']; ?> minutos
                </div>
                
                <div class="mc-movie-meta-item">
                    <?php echo $data['pelicula']['clasificacion'] ?? 'N/A'; ?>
                </div>
                
                <div class="mc-movie-meta-item">
                    <i class="fas fa-calendar-alt"></i> 
                    <?php echo $data['peliculaController']->formatearFecha($data['pelicula']['fecha_estreno']); ?>
                </div>
            </div>
            
            <?php if (!empty($data['pelicula']['generos'])): ?>
                <div class="mc-movie-meta mb-3">
                    <div class="mc-movie-meta-item">
                        <strong>Géneros:</strong> 
                        <?php 
                        $generos = array_map(function($genero) {
                            return $genero['nombre'];
                        }, $data['pelicula']['generos']);
                        echo implode(', ', $generos);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($data['pelicula']['sinopsis'])): ?>
                <p class="mc-movie-synopsis"><?php echo $data['pelicula']['sinopsis']; ?></p>
            <?php endif; ?>
            
            <div class="mb-3">
                <a href="pelicula-detalle.php?id=<?php echo $data['pelicula']['id']; ?>" class="mc-link-secondary">
                    <i class="fas fa-info-circle"></i> Información, trailers y detalles
                </a>
            </div>
            
            <div class="mc-action-buttons">
                <a href="#funciones" class="mc-btn mc-btn-primary">
                    <i class="fas fa-ticket-alt"></i> Comprar entradas
                </a>
                <button class="mc-btn mc-btn-secondary" id="trailerBtn">
                    <i class="fas fa-play"></i> Ver trailer
                </button>
                <?php if (estaLogueado()): ?>
                    <form action="favoritos.php" method="post" class="d-inline">
                        <input type="hidden" name="pelicula_id" value="<?php echo $data['pelicula']['id']; ?>">
                        <?php if ($data['esFavorita']): ?>
                            <input type="hidden" name="action" value="quitar">
                            <button type="submit" class="mc-btn mc-btn-secondary">
                                <i class="fas fa-heart"></i> Favorito
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="agregar">
                            <button type="submit" class="mc-btn mc-btn-secondary">
                                <i class="far fa-heart"></i> Añadir a favoritos
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <!-- Showtimes section -->
    <div class="mc-details-section" id="funciones">
        <h3 class="mc-section-title">Funciones disponibles</h3>
        
        <!-- Date selection tabs -->
        <div class="mc-showtime-tabs">
            <?php
            $today = date('Y-m-d');
            for ($i = 0; $i < 7; $i++) {
                $date = strtotime("+$i day", strtotime($today));
                $day = date('D', $date);
                switch($day) {
                    case 'Mon': $day = 'Lun'; break;
                    case 'Tue': $day = 'Mar'; break;
                    case 'Wed': $day = 'Mié'; break;
                    case 'Thu': $day = 'Jue'; break;
                    case 'Fri': $day = 'Vie'; break;
                    case 'Sat': $day = 'Sáb'; break;
                    case 'Sun': $day = 'Dom'; break;
                }
                $dayNum = date('d', $date);
                $month = date('M', $date);
                switch($month) {
                    case 'Jan': $month = 'Ene'; break;
                    case 'Apr': $month = 'Abr'; break;
                    case 'Aug': $month = 'Ago'; break;
                    case 'Dec': $month = 'Dic'; break;
                }
                
                $activeClass = ($i == 0) ? 'active' : '';
                echo '<div class="mc-date-tab ' . $activeClass . '" data-date="' . date('Y-m-d', $date) . '">';
                echo '<span class="mc-date-number">' . $dayNum . '</span>';
                echo '<span class="mc-date-day">' . $day . '</span>';
                echo '<span class="mc-date-month">' . $month . '</span>';
                echo '</div>';
            }
            ?>
        </div>
        
        <!-- Filter options -->
        <div class="d-flex justify-content-end mb-3">
            <button class="mc-filters-btn">
                <i class="fas fa-filter"></i> Filtros
            </button>
        </div>
        
        <!-- Cinema selection with showtimes -->
        <div class="mc-cinemas-container">
            <?php if (!empty($data['cines'])): ?>
                <?php foreach($data['cines'] as $cine): ?>
                    <div class="mc-cinema-card" data-cine-id="<?php echo $cine['id']; ?>">
                        <div class="mc-cinema-name">
                            <?php echo $cine['nombre']; ?>
                            <span class="mc-cinema-badge"><?php echo $cine['direccion']; ?></span>
                        </div>
                        
                        <div class="mc-showtime-container">
                            <?php
                            // Por ahora mostramos mensaje para funciones de hoy
                            // Las funciones se cargarán dinámicamente con AJAX
                            ?>
                            <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando funciones...</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    No hay funciones disponibles para esta película en este momento.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Trailer section -->
    <div class="mc-details-section">
        <h3 class="mc-section-title">Trailer</h3>
        <div class="mc-trailer-container">
            <?php
            $hasLocalTrailer = $data['pelicula']['multimedia']['hasLocalTrailer'];
            $localTrailerPath = $data['pelicula']['multimedia']['localTrailerPath'];
            
            if ($hasLocalTrailer): 
            ?>
                <div id="localTrailerContainer">
                    <video id="trailerVideo" controls poster="assets/img/posters/<?php echo $data['pelicula']['id']; ?>.jpg" class="w-100">
                        <source src="<?php echo $localTrailerPath; ?>" type="video/mp4">
                        <p class="text-center">Tu navegador no soporta la reproducción de videos.</p>
                    </video>
                    <div class="mc-trailer-play-btn" id="playTrailerBtn">
                        <i class="fas fa-play"></i>
                    </div>
                </div>
            <?php 
            elseif (!empty($data['pelicula']['url_trailer'])): 
                // Manejo de YouTube
                $youtubePattern = '/^https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/';
                $youtubeShortPattern = '/^https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/';
                $videoId = null;
                
                if (preg_match($youtubePattern, $data['pelicula']['url_trailer'], $matches)) {
                    $videoId = $matches[1];
                } elseif (preg_match($youtubeShortPattern, $data['pelicula']['url_trailer'], $matches)) {
                    $videoId = $matches[1];
                }
                
                if ($videoId):
            ?>
                    <div id="youtubeEmbed" data-video-id="<?php echo $videoId; ?>">
                        <img src="assets/img/posters/<?php echo $data['pelicula']['id']; ?>.jpg" 
                             alt="<?php echo $data['pelicula']['titulo']; ?> trailer poster" 
                             class="video-poster">
                        <div class="mc-trailer-play-btn" id="playLocalBtn">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
            <?php else: ?>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="<?php echo $data['pelicula']['url_trailer']; ?>" allowfullscreen></iframe>
                    </div>
            <?php 
                endif;
            else: 
            ?>
                <div class="mc-empty-trailer">
                    <img src="assets/img/posters/default-trailer-thumbnail.jpg" alt="No hay trailer disponible" class="w-100">
                    <div class="mc-trailer-unavailable">
                        <i class="fas fa-film"></i>
                        <p>Trailer no disponible</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cast section -->
    <?php if (!empty($data['pelicula']['elenco'])): ?>
    <div class="mc-details-section">
        <h3 class="mc-section-title">Elenco y equipo</h3>
        <div class="mc-cast-container">
            <?php foreach ($data['pelicula']['elenco'] as $actor): ?>
                <div class="mc-cast-item">
                    <img src="assets/img/cast/<?php echo $actor['id']; ?>.jpg" 
                         onerror="this.src='assets/img/cast/default-actor.jpg'" 
                         class="mc-cast-img" 
                         alt="<?php echo $actor['nombre'] . ' ' . $actor['apellido']; ?>">
                    <div class="mc-cast-name"><?php echo $actor['nombre'] . ' ' . $actor['apellido']; ?></div>
                    <div class="mc-cast-role"><?php echo $actor['personaje'] ?? $actor['rol']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Ratings and reviews section -->
    <div class="mc-details-section" id="valoraciones">
        <h3 class="mc-section-title">Valoraciones y comentarios</h3>
        <div class="row">
            <div class="col-md-4 text-center mb-4">
                <div class="mc-rating-score">
                    <?php echo number_format($data['valoracionesData']['valoraciones']['promedio'] ?? 0, 1); ?>
                </div>
                <div class="mc-star-rating mb-2">
                    <?php 
                    $promedio = $data['valoracionesData']['valoraciones']['promedio'] ?? 0;
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $promedio) {
                            echo '<i class="fas fa-star"></i>';
                        } elseif ($i <= $promedio + 0.5) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    }
                    ?>
                </div>
                <div class="mc-rating-count">
                    <?php echo $data['valoracionesData']['valoraciones']['total'] ?? 0; ?> valoraciones
                </div>
                
                <?php if (estaLogueado()): ?>
                    <button class="mc-btn mc-btn-primary mt-3" data-toggle="modal" data-target="#ratingModal">
                        <i class="fas fa-star"></i> Valorar película
                    </button>
                <?php else: ?>
                    <a href="auth/login.php" class="mc-btn mc-btn-primary mt-3">
                        <i class="fas fa-user"></i> Inicia sesión para valorar
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="col-md-8">
                <h5 class="mb-3">Comentarios recientes</h5>
                
                <?php if (!empty($data['valoracionesData']['comentarios'])): ?>
                    <?php foreach ($data['valoracionesData']['comentarios'] as $comentario): ?>
                        <div class="mc-review-card">
                            <div class="mc-review-header">
                                <img src="assets/img/avatars/<?php echo $comentario['user_id']; ?>.jpg" 
                                     onerror="this.src='assets/img/avatar-default.jpg'" 
                                     class="mc-review-avatar" 
                                     alt="<?php echo $comentario['nombres']; ?>">
                                <div>
                                    <h5 class="mc-review-name"><?php echo $comentario['nombres'] . ' ' . $comentario['apellidos']; ?></h5>
                                    <div class="mc-review-date"><?php echo date('d/m/Y', strtotime($comentario['fecha_valoracion'])); ?></div>
                                </div>
                            </div>
                            <div class="mc-review-rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $comentario['puntuacion']) {
                                        echo '<i class="fas fa-star"></i>';
                                    } else {
                                        echo '<i class="far fa-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="mc-review-content"><?php echo $comentario['comentario']; ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="mc-empty-reviews">
                        <i class="far fa-comments"></i>
                        <p>No hay comentarios todavía. ¡Sé el primero en valorar esta película!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Incluir modals -->
<?php include __DIR__ . '/modals/rating-modal.php'; ?>
<?php include __DIR__ . '/modals/share-modal.php'; ?>
<?php include __DIR__ . '/modals/showtime-modal.php'; ?>

<script>
    var peliculaId = <?php echo $data['pelicula']['id']; ?>;
</script>
<script src="assets/js/movie-details.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>