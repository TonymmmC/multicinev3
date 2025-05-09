<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener el ID de la película
$peliculaId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$peliculaId) {
    setMensaje('Película no encontrada', 'warning');
    redirect('/multicinev3/');
}

// Obtener detalles de la película
$pelicula = $peliculaController->getPeliculaById($peliculaId);

if (!$pelicula) {
    setMensaje('Película no encontrada', 'warning');
    redirect('/multicinev3/');
}

// Verificar si está en favoritos
$esFavorita = false;
if (estaLogueado()) {
    $userId = $_SESSION['user_id'];
    $query = "SELECT id FROM favoritos WHERE user_id = ? AND pelicula_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $peliculaId);
    $stmt->execute();
    $resultFav = $stmt->get_result();
    $esFavorita = $resultFav->num_rows > 0;
}

// Configurar rutas de multimedia locales - probar múltiples rutas
$possiblePosterPaths = [
    "assets/img/posters/{$peliculaId}.jpg",
    "img/posters/{$peliculaId}.jpg"
];
$possibleBannerPaths = [
    "assets/img/banners/{$peliculaId}.jpg",
    "img/banners/{$peliculaId}.jpg"
];
$possibleTrailerPaths = [
    "assets/videos/trailers/{$peliculaId}.mp4",
    "videos/trailers/{$peliculaId}.mp4"
];

// Verificar qué rutas existen
$localPosterPath = null;
$localBannerPath = null;
$localTrailerPath = null;

foreach ($possiblePosterPaths as $path) {
    if (file_exists($path)) {
        $localPosterPath = $path;
        break;
    }
}

foreach ($possibleBannerPaths as $path) {
    if (file_exists($path)) {
        $localBannerPath = $path;
        break;
    }
}

foreach ($possibleTrailerPaths as $path) {
    if (file_exists($path)) {
        $localTrailerPath = $path;
        break;
    }
}

$hasLocalPoster = !is_null($localPosterPath);
$hasLocalBanner = !is_null($localBannerPath);
$hasLocalTrailer = !is_null($localTrailerPath);

// Asignar rutas locales si existen, de lo contrario mantener las URLs externas
if (!isset($pelicula['multimedia'])) {
    $pelicula['multimedia'] = [];
}

if (!isset($pelicula['multimedia']['poster'])) {
    $pelicula['multimedia']['poster'] = ['url' => 'assets/img/poster-default.jpg'];
} elseif ($hasLocalPoster) {
    $pelicula['multimedia']['poster']['url'] = $localPosterPath;
}

if (!isset($pelicula['multimedia']['banner'])) {
    $pelicula['multimedia']['banner'] = ['url' => 'assets/img/movie-backgrounds/default-movie-bg.jpg'];
} elseif ($hasLocalBanner) {
    $pelicula['multimedia']['banner']['url'] = $localBannerPath;
}

// Obtener valoraciones
$valoracionesData = [];
if (isset($pelicula['id'])) {
    // Obtener valoración promedio y total
    $queryValoracion = "SELECT AVG(puntuacion) as promedio, COUNT(*) as total 
                       FROM valoraciones 
                       WHERE pelicula_id = ?";
    $stmtValoracion = $conn->prepare($queryValoracion);
    $stmtValoracion->bind_param("i", $pelicula['id']);
    $stmtValoracion->execute();
    $resultValoracion = $stmtValoracion->get_result();
    $valoracion = $resultValoracion->fetch_assoc();
    
    // Obtener distribución de valoraciones
    $queryDistribucion = "SELECT puntuacion, COUNT(*) as total 
                          FROM valoraciones 
                          WHERE pelicula_id = ? 
                          GROUP BY puntuacion 
                          ORDER BY puntuacion DESC";
    $stmtDistribucion = $conn->prepare($queryDistribucion);
    $stmtDistribucion->bind_param("i", $pelicula['id']);
    $stmtDistribucion->execute();
    $resultDistribucion = $stmtDistribucion->get_result();
    
    $distribucion = [];
    while ($row = $resultDistribucion->fetch_assoc()) {
        $distribucion[$row['puntuacion']] = $row['total'];
    }
    
    // Obtener comentarios
    $queryComentarios = "SELECT v.id, v.puntuacion, v.comentario, v.fecha_valoracion,
                        u.id as user_id, pu.nombres, pu.apellidos, m.url as imagen_url
                   FROM valoraciones v
                   JOIN users u ON v.user_id = u.id
                   LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
                   LEFT JOIN multimedia m ON pu.imagen_id = m.id
                   WHERE v.pelicula_id = ? AND v.comentario IS NOT NULL
                   ORDER BY v.fecha_valoracion DESC
                   LIMIT 5";
    $stmtComentarios = $conn->prepare($queryComentarios);
    $stmtComentarios->bind_param("i", $pelicula['id']);
    $stmtComentarios->execute();
    $resultComentarios = $stmtComentarios->get_result();
    
    $comentarios = [];
    while ($row = $resultComentarios->fetch_assoc()) {
        $comentarios[] = $row;
    }
    
    $valoracionesData = [
        'valoraciones' => [
            'promedio' => $valoracion['promedio'] ? round($valoracion['promedio'], 1) : 0,
            'total' => $valoracion['total']
        ],
        'estadisticas' => $distribucion,
        'comentarios' => $comentarios
    ];
}

// Obtener formatos
$queryFormatos = "SELECT DISTINCT f.nombre FROM formatos f 
                  JOIN funciones func ON f.id = func.formato_proyeccion_id
                  WHERE func.pelicula_id = ?";
$stmtFormatos = $conn->prepare($queryFormatos);
$stmtFormatos->bind_param("i", $peliculaId);
$stmtFormatos->execute();
$resultFormatos = $stmtFormatos->get_result();
$formatos = [];
while ($row = $resultFormatos->fetch_assoc()) {
    $formatos[] = $row['nombre'];
}

// Obtener cines con funciones
$queryCines = "SELECT DISTINCT c.id, c.nombre, c.direccion 
               FROM cines c
               JOIN salas s ON s.cine_id = c.id
               JOIN funciones f ON f.sala_id = s.id
               WHERE f.pelicula_id = ? AND c.activo = 1
               ORDER BY c.nombre";
$stmtCines = $conn->prepare($queryCines);
$stmtCines->bind_param("i", $peliculaId);
$stmtCines->execute();
$resultCines = $stmtCines->get_result();
$cines = [];
while ($row = $resultCines->fetch_assoc()) {
    $cines[] = $row;
}

// Incluir header
require_once 'includes/header.php';
?>

<link href="assets/css/movie-details.css" rel="stylesheet">

<!-- Hero Banner Section -->
<div class="mc-hero-banner">
<img src="assets/img/banners/<?php echo $peliculaId; ?>.jpg" onerror="this.src='assets/img/movie-backgrounds/default-movie-bg.jpg'" class="mc-hero-bg" alt="<?php echo $pelicula['titulo']; ?> banner">
    <div class="mc-hero-overlay"></div>
    <div class="container mc-movie-content">
    <img src="<?php echo $pelicula['multimedia']['poster']['url'] ?? 'assets/img/poster-default.jpg'; ?>" class="mc-movie-poster" alt="<?php echo $pelicula['titulo']; ?>">
        <div class="mc-movie-info">
            <div class="d-flex mb-2">
                <?php if (!empty($formatos)): ?>
                    <?php foreach($formatos as $formato): ?>
                        <span class="mc-format-tag"><?php echo $formato; ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="mc-format-tag">2D</span>
                <?php endif; ?>
            </div>
            <h1 class="mc-movie-title"><?php echo $pelicula['titulo']; ?></h1>
            <div class="mc-movie-meta">
                <?php if (isset($valoracionesData['valoraciones']) && $valoracionesData['valoraciones']['promedio'] > 0): ?>
                    <div class="mc-movie-meta-item">
                        <i class="fas fa-star text-warning"></i> <?php echo $valoracionesData['valoraciones']['promedio']; ?>/5 (<?php echo $valoracionesData['valoraciones']['total']; ?>)
                    </div>
                <?php endif; ?>
                
                <div class="mc-movie-meta-item">
                    <i class="far fa-clock"></i> <?php echo $pelicula['duracion_min']; ?> minutos
                </div>
                
                <div class="mc-movie-meta-item">
                    <?php echo $pelicula['clasificacion'] ?? 'N/A'; ?>
                </div>
                
                <div class="mc-movie-meta-item">
                    <i class="fas fa-calendar-alt"></i> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?>
                </div>
            </div>
            
            <?php if (!empty($pelicula['generos'])): ?>
                <div class="mc-movie-meta mb-3">
                    <div class="mc-movie-meta-item">
                        <strong>Géneros:</strong> 
                        <?php 
                        $generos = array_map(function($genero) {
                            return $genero['nombre'];
                        }, $pelicula['generos']);
                        echo implode(', ', $generos);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($pelicula['sinopsis'])): ?>
                <p class="mc-movie-synopsis"><?php echo $pelicula['sinopsis']; ?></p>
            <?php endif; ?>
            
            <!-- Nuevo enlace a información, trailers y detalles -->
            <div class="mb-3">
                <a href="pelicula-detalle.php?id=<?php echo $pelicula['id']; ?>" class="mcc-link-secondary">
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
                        <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                        <?php if ($esFavorita): ?>
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
            <?php if (!empty($cines)): ?>
                <?php foreach($cines as $cine): ?>
                    <div class="mc-cinema-card" data-cine-id="<?php echo $cine['id']; ?>">
                        <div class="mc-cinema-name">
                            <?php echo $cine['nombre']; ?>
                            <span class="mc-cinema-badge"><?php echo $cine['direccion']; ?></span>
                        </div>
                        
                        <div class="mc-showtime-container">
                            <?php
                            // Obtener horarios disponibles para hoy
                            $queryHorarios = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles, 
                                              s.nombre as sala_nombre, i.nombre as idioma, fmt.nombre as formato 
                                              FROM funciones f 
                                              JOIN salas s ON f.sala_id = s.id 
                                              JOIN idiomas i ON f.idioma_id = i.id 
                                              JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id
                                              WHERE f.pelicula_id = ? 
                                              AND s.cine_id = ?
                                              AND DATE(f.fecha_hora) = CURDATE()
                                              ORDER BY f.fecha_hora ASC";
                            $stmtHorarios = $conn->prepare($queryHorarios);
                            $stmtHorarios->bind_param("ii", $peliculaId, $cine['id']);
                            $stmtHorarios->execute();
                            $resultHorarios = $stmtHorarios->get_result();
                            
                            if ($resultHorarios->num_rows > 0):
                                while ($horario = $resultHorarios->fetch_assoc()): 
                                    $tooltipInfo = "{$horario['formato']} | {$horario['idioma']} | {$horario['sala_nombre']} | Bs. " . number_format($horario['precio_base'], 2);
                            ?>
                                <a href="#" 
                                   class="mc-showtime-btn" 
                                   data-toggle="modal" 
                                   data-target="#showtimeModal"
                                   data-id="<?php echo $horario['id']; ?>"
                                   data-time="<?php echo date('H:i', strtotime($horario['fecha_hora'])); ?>"
                                   data-date="<?php echo date('Y-m-d', strtotime($horario['fecha_hora'])); ?>"
                                   data-format="<?php echo $horario['formato']; ?>"
                                   data-sala="<?php echo $horario['sala_nombre']; ?>"
                                   data-title="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
                                   data-runtime="<?php echo $pelicula['duracion_min']; ?>"
                                   data-price="<?php echo number_format($horario['precio_base'], 2); ?>"
                                   title="<?php echo $tooltipInfo; ?>">
                                    <?php echo date('H:i', strtotime($horario['fecha_hora'])); ?>
                                </a>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <p class="text-muted">No hay funciones disponibles para hoy.</p>
                            <?php endif; ?>
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
            // Obtener thumbnail/poster para el trailer
            $possibleTrailerThumbnails = [
                "assets/img/posters/trailer-{$peliculaId}.jpg",
                "img/posters/trailer-{$peliculaId}.jpg"
            ];
            
            $trailerThumbnail = "assets/img/posters/default-trailer-thumbnail.jpg";
            foreach($possibleTrailerThumbnails as $path) {
                if(file_exists($path)) {
                    $trailerThumbnail = $path;
                    break;
                }
            }
            
            if ($hasLocalTrailer): 
            ?>
                <div id="localTrailerContainer">
                    <video id="trailerVideo" controls poster="<?php echo $trailerThumbnail; ?>" class="w-100">
                        <source src="<?php echo $localTrailerPath; ?>" type="video/mp4">
                        <p class="text-center">Tu navegador no soporta la reproducción de videos.</p>
                    </video>
                    <div class="mc-trailer-play-btn" id="playTrailerBtn">
                        <i class="fas fa-play"></i>
                    </div>
                </div>
            <?php 
            elseif (!empty($pelicula['url_trailer'])): 
                // Si no hay trailer local pero hay URL de YouTube, mostrar thumbnail con botón de play
                // Extraer ID de YouTube si es posible
                $youtubePattern = '/^https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/';
                $youtubeShortPattern = '/^https?:\/\/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/';
                $videoId = null;
                
                if (preg_match($youtubePattern, $pelicula['url_trailer'], $matches)) {
                    $videoId = $matches[1];
                } elseif (preg_match($youtubeShortPattern, $pelicula['url_trailer'], $matches)) {
                    $videoId = $matches[1];
                }
                
                // Usar un thumbnail local en lugar de uno de YouTube para evitar peticiones
                $posterUrl = $trailerThumbnail;
                if ($videoId) {
                    // Solo guardamos el ID para uso posterior, pero usamos nuestra propia imagen
                    // Esto evita peticiones a YouTube hasta que el usuario realmente quiera ver el trailer
            ?>
                    <div id="youtubeEmbed" data-video-id="<?php echo $videoId; ?>">
                        <img src="<?php echo $posterUrl; ?>" alt="<?php echo $pelicula['titulo']; ?> trailer poster" class="video-poster">
                        <div class="mc-trailer-play-btn" id="playLocalBtn" data-trailer-url="<?php echo $pelicula['url_trailer']; ?>">
                            <i class="fas fa-play"></i>
                        </div>
                    </div>
            <?php
                } else {
            ?>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="<?php echo $pelicula['url_trailer']; ?>" allowfullscreen></iframe>
                    </div>
            <?php 
                }
            else: 
            ?>
                <div class="mc-empty-trailer">
                    <img src="<?php echo $trailerThumbnail; ?>" alt="No hay trailer disponible" class="w-100">
                    <div class="mc-trailer-unavailable">
                        <i class="fas fa-film"></i>
                        <p>Trailer no disponible</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Cast section -->
    <?php if (!empty($pelicula['elenco'])): ?>
    <div class="mc-details-section">
        <h3 class="mc-section-title">Elenco y equipo</h3>
        <div class="mc-cast-container">
            <?php foreach ($pelicula['elenco'] as $actor): ?>
                <?php 
                // Verificar si existe imagen local del actor en varias rutas
                $possibleActorImages = [
                    "assets/img/cast/{$actor['id']}.jpg",
                    "img/cast/{$actor['id']}.jpg"
                ];
                
                $actorImgSrc = 'assets/img/cast/default-actor.jpg';
                foreach($possibleActorImages as $path) {
                    if(file_exists($path)) {
                        $actorImgSrc = $path;
                        break;
                    }
                }
                
                if(!file_exists($actorImgSrc) && isset($actor['imagen_url'])) {
                    $actorImgSrc = $actor['imagen_url'];
                }
                ?>
                <div class="mc-cast-item">
                    <img src="<?php echo $actorImgSrc; ?>" class="mc-cast-img" alt="<?php echo $actor['nombre'] . ' ' . $actor['apellido']; ?>">
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
                    <?php echo number_format($valoracionesData['valoraciones']['promedio'] ?? 0, 1); ?>
                </div>
                <div class="mc-star-rating mb-2">
                    <?php 
                    $promedio = $valoracionesData['valoraciones']['promedio'] ?? 0;
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
                    <?php echo $valoracionesData['valoraciones']['total'] ?? 0; ?> valoraciones
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
                
                <?php if (!empty($valoracionesData['comentarios'])): ?>
                    <?php foreach ($valoracionesData['comentarios'] as $comentario): ?>
                        <div class="mc-review-card">
                            <div class="mc-review-header">
                                <?php 
                                // Verificar imagen de perfil local en múltiples rutas
                                $possibleUserImages = [
                                    "assets/img/avatars/{$comentario['user_id']}.jpg",
                                    "img/avatars/{$comentario['user_id']}.jpg"
                                ];
                                
                                $userImgSrc = 'assets/img/avatar-default.jpg';
                                foreach($possibleUserImages as $path) {
                                    if(file_exists($path)) {
                                        $userImgSrc = $path;
                                        break;
                                    }
                                }
                                
                                if(!file_exists($userImgSrc) && isset($comentario['imagen_url'])) {
                                    $userImgSrc = $comentario['imagen_url'];
                                }
                                ?>
                                <img src="<?php echo $userImgSrc; ?>" 
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

<!-- Modal para valoración -->
<div class="modal fade" id="ratingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valorar "<?php echo $pelicula['titulo']; ?>"</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                // Verificar valoración actual del usuario
                if (estaLogueado()) {
                    $userId = $_SESSION['user_id'];
                    $query = "SELECT puntuacion, comentario FROM valoraciones 
                              WHERE user_id = ? AND pelicula_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ii", $userId, $pelicula['id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $userRating = null;
                    $userHasRated = false;
                    
                    if ($result && $result->num_rows > 0) {
                        $userRating = $result->fetch_assoc();
                        $userHasRated = true;
                    }
                }
                ?>
                <form action="guardar_valoracion.php" method="post">
                    <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                    
                    <div class="form-group">
                        <label>Tu puntuación</label>
                        <div class="mc-rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="mc-rating-star">
                                    <input type="radio" 
                                           name="puntuacion" 
                                           id="rating<?php echo $i; ?>" 
                                           value="<?php echo $i; ?>" 
                                           <?php echo ($userHasRated && $userRating['puntuacion'] == $i) ? 'checked' : ''; ?> 
                                           required>
                                    <label for="rating<?php echo $i; ?>">
                                        <i class="far fa-star"></i>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comentario">Tu comentario (opcional)</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3"><?php echo $userHasRated ? $userRating['comentario'] : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $userHasRated ? 'Actualizar valoración' : 'Enviar valoración'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para compartir -->
<div class="modal fade" id="compartirModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compartir "<?php echo $pelicula['titulo']; ?>"</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Comparte esta película en tus redes sociales:</p>
                
                <?php
                $urlActual = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $textoCompartir = '¡Mira "' . $pelicula['titulo'] . '" en Multicine!';
                ?>
                
                <div class="d-flex justify-content-center">
                    <!-- Facebook -->
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($urlActual); ?>" target="_blank" class="btn btn-primary mx-2">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                    
                    <!-- Twitter -->
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($textoCompartir); ?>&url=<?php echo urlencode($urlActual); ?>" target="_blank" class="btn btn-info mx-2">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($textoCompartir . ' ' . $urlActual); ?>" target="_blank" class="btn btn-success mx-2">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
                
                <hr>
                
                <div class="form-group mt-3">
                    <label for="enlaceCompartir">Copiar enlace:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="enlaceCompartir" value="<?php echo $urlActual; ?>" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copiarEnlace()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para las funciones -->
<div class="modal fade" id="showtimeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <h5 id="modalDate"></h5>
                    <h3 id="modalTime" class="font-weight-bold"></h3>
                    <p class="text-muted">Versión Original</p>
                </div>

                <div class="d-flex">
                    <div class="mr-3">
                        <img id="modalPoster" src="" alt="" class="img-fluid" style="max-width: 100px;">
                    </div>
                    <div>
                        <h4 id="modalTitle" class="mb-1"></h4>
                        <div class="d-flex align-items-center">
                            <span id="modalRuntime" class="text-muted mr-2"></span>
                            <span id="modalFormat" class="badge badge-warning"></span>
                        </div>
                        <p id="modalExpectedEnd" class="small mt-2">Finaliza aproximadamente a las <span id="endTime"></span></p>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light">
                            <div>
                                <strong id="modalCinema"></strong><br>
                                <small>Sala <span id="modalSala"></span></small>
                                <!-- Añadir la sede -->
                                <small class="d-block">Sede: <span id="modalSede"></span></small>
                            </div>
                            <div class="text-right">
                                <strong>Precio: Bs. <span id="modalPrice"></span></strong><br>
                                <small><span id="modalSeats"></span> asientos</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="bookNowBtn" class="btn btn-primary btn-block">
                    <!-- Añadir icono de ticket -->
                    <i class="fas fa-ticket-alt"></i> Comprar ahora
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejo de fechas para funciones
    const dateTabs = document.querySelectorAll('.mc-date-tab');
    dateTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const selectedDate = this.dataset.date;
            
            // Actualizar tab activo
            dateTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Cargar funciones para la fecha seleccionada
            loadShowtimes(selectedDate);
        });
    });
    
    // Función para cargar horarios
    function loadShowtimes(date) {
        const cineContainers = document.querySelectorAll('.mc-cinema-card');
        cineContainers.forEach(cineCard => {
            const cineId = cineCard.dataset.cineId;
            const showtimeContainer = cineCard.querySelector('.mc-showtime-container');
            
            // Mostrar cargando
            showtimeContainer.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>';
            
            // Hacer petición AJAX para obtener horarios
            fetch(`api/horarios.php?pelicula_id=<?php echo $peliculaId; ?>&cine_id=${cineId}&fecha=${date}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    data.forEach(horario => {
                        const hora = new Date(horario.fecha_hora).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                        const tooltipInfo = `${horario.formato} | ${horario.idioma} | ${horario.sala_nombre} | Bs. ${parseFloat(horario.precio_base).toFixed(2)}`;
                        
                        html += `<a href="#" 
                                class="mc-showtime-btn" 
                                data-toggle="modal" 
                                data-target="#showtimeModal"
                                data-id="${horario.id}"
                                data-time="${hora}"
                                data-date="${date}"
                                data-format="${horario.formato}"
                                data-sala="${horario.sala_nombre}"
                                data-cinema="${cineCard.querySelector('.mc-cinema-name').innerText.split('\n')[0]}"
                                data-sede="${cineCard.querySelector('.mc-cinema-badge')?.innerText || 'La Paz'}"
                                data-title="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
                                data-runtime="${horario.duracion || <?php echo $pelicula['duracion_min']; ?>}"
                                data-price="${parseFloat(horario.precio_base).toFixed(2)}"
                                data-seats="${horario.asientos_disponibles || 100}"
                                title="${tooltipInfo}">
                                    ${hora}
                                </a>`;
                    });
                    showtimeContainer.innerHTML = html;
                    
                    // Inicializar tooltips
                    $('[data-toggle="tooltip"]').tooltip();
                } else {
                    // Cambiar el mensaje de error por "No hay funciones disponibles"
                    showtimeContainer.innerHTML = '<p class="text-muted">No hay funciones disponibles para esta fecha.</p>';
                }
            })
            .catch(error => {
                // Mostrar mensaje de "No hay funciones disponibles" en vez de error
                showtimeContainer.innerHTML = '<p class="text-muted">No hay funciones disponibles para esta fecha.</p>';
                console.error('Error loading showtimes:', error);
            });
        });
    }
    
    // Reproducción de trailer
    const trailerBtn = document.getElementById('trailerBtn');
    const youtubeEmbed = document.getElementById('youtubeEmbed');
    const playTrailerBtn = document.getElementById('playTrailerBtn');
    const playLocalBtn = document.getElementById('playLocalBtn');
    const trailerVideo = document.getElementById('trailerVideo');
    
    if (trailerBtn && youtubeEmbed) {
        trailerBtn.addEventListener('click', function() {
            const videoId = youtubeEmbed.dataset.videoId;
            
            // Reemplazar la imagen con el iframe
            youtubeEmbed.innerHTML = `
                <iframe width="100%" height="100%" 
                    src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            `;
            
            // Hacer scroll hasta el trailer
            youtubeEmbed.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    } else if (trailerBtn && trailerVideo) {
        trailerBtn.addEventListener('click', function() {
            trailerVideo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            trailerVideo.play();
            // Ocultar el botón de reproducción
            if (playTrailerBtn) playTrailerBtn.style.display = 'none';
        });
    }
    
    if (playLocalBtn && youtubeEmbed) {
        playLocalBtn.addEventListener('click', function() {
            const videoId = youtubeEmbed.dataset.videoId;
            
            // Reemplazar la imagen con el iframe
            youtubeEmbed.innerHTML = `
                <iframe width="100%" height="100%" 
                    src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            `;
        });
    }
    
    // Manejo de estrellas para valoración
    const ratingStars = document.querySelectorAll('.mc-rating-input label');
    const ratingInputs = document.querySelectorAll('.mc-rating-input input');
    
    function updateStars(rating) {
        ratingStars.forEach((star, index) => {
            const starIcon = star.querySelector('i');
            if (index < rating) {
                starIcon.classList.remove('far');
                starIcon.classList.add('fas');
            } else {
                starIcon.classList.remove('fas');
                starIcon.classList.add('far');
            }
        });
    }
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('mouseenter', () => {
            updateStars(index + 1);
        });
    });
    
    document.querySelector('.mc-rating-input')?.addEventListener('mouseleave', () => {
        const checkedInput = document.querySelector('.mc-rating-input input:checked');
        const rating = checkedInput ? parseInt(checkedInput.value) : 0;
        updateStars(rating);
    });
    
    ratingInputs.forEach((input, index) => {
        input.addEventListener('change', () => {
            updateStars(index + 1);
        });
    });
    
    // Inicializar estrellas si hay valoración previa
    const checkedInput = document.querySelector('.mc-rating-input input:checked');
    if (checkedInput) {
        const initialRating = parseInt(checkedInput.value);
        updateStars(initialRating);
    }
    
    // Inicializar tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Configuración del modal de showtime
        $('#showtimeModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const time = button.data('time');
        const date = new Date(button.data('date'));
        const format = button.data('format');
        const sala = button.data('sala');
        const cinema = button.data('cinema');
        const sede = button.data('sede');  // Nueva línea para obtener la sede
        const title = button.data('title');
        const runtime = button.data('runtime');
        const price = button.data('price');
        const seats = button.data('seats');
        
        // Format date as "Sunday 11 May"
        const options = { weekday: 'long', day: 'numeric', month: 'long' };
        const formattedDate = date.toLocaleDateString('es-ES', options);
        
        // Calculate end time (time + runtime minutes)
        const [hours, minutes] = time.split(':').map(num => parseInt(num, 10));
        const endDate = new Date(date);
        endDate.setHours(hours, minutes + parseInt(runtime, 10));
        const endTime = endDate.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        
        // Update modal contents
        $('#modalDate').text(formattedDate);
        $('#modalTime').text(time);
        $('#modalTitle').text(title);
        $('#modalRuntime').text(`Duración: ${runtime} min`);
        $('#modalFormat').text(format);
        $('#endTime').text(endTime);
        $('#modalSala').text(sala || '1');
        $('#modalCinema').text(cinema);
        $('#modalSede').text(sede);  // Nueva línea para mostrar la sede
        $('#modalPrice').text(price);
        $('#modalSeats').text(seats);
        
        // Actualizar la imagen del poster
        $('#modalPoster').attr('src', $('.mc-movie-poster').attr('src'));
        $('#modalPoster').attr('alt', title);
        
        // Set the booking URL
        $('#bookNowBtn').attr('href', `reserva.php?funcion=${id}`);
    });
});

function copiarEnlace() {
    const enlace = document.getElementById('enlaceCompartir');
    enlace.select();
    document.execCommand('copy');
    
    // Mostrar mensaje de éxito
    const boton = enlace.nextElementSibling.querySelector('button');
    const iconoOriginal = boton.innerHTML;
    boton.innerHTML = '<i class="fas fa-check"></i>';
    
    setTimeout(() => {
        boton.innerHTML = iconoOriginal;
    }, 2000);
}
</script>

<?php require_once 'includes/footer.php'; ?>