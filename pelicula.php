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

// Incluir header
require_once 'includes/header.php';
?>

<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="cartelera.php">Cartelera</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $pelicula['titulo']; ?></li>
        </ol>
    </nav>
</div>

<div class="row mb-5">
    <!-- Poster y detalles -->
    <div class="col-md-4">
        <div class="position-relative mb-3">
            <img src="<?php echo $pelicula['multimedia']['poster']['url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                 class="img-fluid rounded" alt="<?php echo $pelicula['titulo']; ?>">
                 
            <?php if (isset($valoracionesData['valoraciones']) && $valoracionesData['valoraciones']['promedio'] > 0): ?>
                <div class="position-absolute" style="top: 10px; right: 10px; background-color: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 20px;">
                    <i class="fas fa-star text-warning"></i> 
                    <?php echo $valoracionesData['valoraciones']['promedio']; ?>/5
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Detalles</h5>
            </div>
            <div class="card-body">
                <p><strong>Clasificación:</strong> 
                    <span class="badge badge-info"><?php echo $pelicula['clasificacion'] ?? 'N/A'; ?></span>
                    <?php if (!empty($pelicula['clasificacion_desc'])): ?>
                        <small class="text-muted d-block"><?php echo $pelicula['clasificacion_desc']; ?></small>
                    <?php endif; ?>
                </p>
                <p><strong>Duración:</strong> <?php echo $pelicula['duracion_min']; ?> minutos</p>
                <p><strong>Estreno:</strong> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?></p>
                <?php if (!empty($pelicula['generos'])): ?>
                    <p><strong>Géneros:</strong><br>
                        <?php foreach ($pelicula['generos'] as $genero): ?>
                            <span class="badge badge-secondary"><?php echo $genero['nombre']; ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>
                
                <!-- Acciones -->
                <div class="mt-4">
                    <?php if (estaLogueado()): ?>
                        <a href="#valoraciones" class="btn btn-outline-primary btn-block mb-2">
                            <i class="fas fa-star"></i> Valorar película
                        </a>
                        
                        <form action="favoritos.php" method="post" class="mb-2">
                            <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                            <?php if ($esFavorita): ?>
                                <input type="hidden" name="action" value="quitar">
                                <button type="submit" class="btn btn-outline-danger btn-block">
                                    <i class="fas fa-heart"></i> Quitar de favoritos
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="agregar">
                                <button type="submit" class="btn btn-outline-danger btn-block">
                                    <i class="far fa-heart"></i> Añadir a favoritos
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php else: ?>
                        <a href="auth/login.php" class="btn btn-outline-primary btn-block mb-2">
                            <i class="fas fa-user"></i> Inicia sesión para valorar
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-secondary btn-block" type="button" data-toggle="modal" data-target="#compartirModal">
                        <i class="fas fa-share-alt"></i> Compartir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Información de la película -->
    <div class="col-md-8">
        <h1 class="mb-2"><?php echo $pelicula['titulo']; ?></h1>
        <?php if (!empty($pelicula['titulo_original']) && $pelicula['titulo_original'] != $pelicula['titulo']): ?>
            <h5 class="text-muted mb-3"><?php echo $pelicula['titulo_original']; ?></h5>
        <?php endif; ?>
        
        <?php if (!empty($pelicula['sinopsis'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Sinopsis</h4>
                </div>
                <div class="card-body">
                    <p><?php echo $pelicula['sinopsis']; ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($pelicula['url_trailer'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Trailer</h4>
                </div>
                <div class="card-body">
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="<?php echo $pelicula['url_trailer']; ?>" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($pelicula['elenco'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Elenco</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($pelicula['elenco'] as $actor): ?>
                            <div class="col-md-6">
                                <div class="media mb-3">
                                    <img src="assets/img/actor-default.jpg" class="mr-3 rounded-circle" width="50" height="50" alt="<?php echo $actor['nombre'] . ' ' . $actor['apellido']; ?>">
                                    <div class="media-body">
                                        <h5 class="mt-0 mb-0"><?php echo $actor['nombre'] . ' ' . $actor['apellido']; ?></h5>
                                        <?php if (!empty($actor['personaje'])): ?>
                                            <p class="text-muted mb-0"><?php echo $actor['personaje']; ?></p>
                                        <?php endif; ?>
                                        <small class="text-muted"><?php echo $actor['rol']; ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($pelicula['funciones'])): ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Funciones Disponibles</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Cine</th>
                                    <th>Sala</th>
                                    <th>Formato</th>
                                    <th>Idioma</th>
                                    <th>Precio</th>
                                    <th>Asientos</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pelicula['funciones'] as $funcion): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($funcion['fecha_hora'])); ?></td>
                                        <td><?php echo $funcion['cine']; ?></td>
                                        <td><?php echo $funcion['sala']; ?></td>
                                        <td><?php echo $funcion['formato']; ?></td>
                                        <td><?php echo $funcion['idioma']; ?></td>
                                        <td>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></td>
                                        <td><?php echo $funcion['asientos_disponibles']; ?> libres</td>
                                        <td>
                                            <a href="reserva.php?funcion=<?php echo $funcion['id']; ?>" class="btn btn-sm btn-primary">
                                                Reservar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay funciones disponibles para esta película en este momento.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Galería de imágenes -->
<?php if (!empty($pelicula['multimedia']['galeria'])): ?>
    <section class="mb-5">
        <h3 class="mb-3">Galería</h3>
        <div class="row">
            <?php foreach ($pelicula['multimedia']['galeria'] as $imagen): ?>
                <div class="col-md-3 mb-4">
                    <a href="<?php echo $imagen['url']; ?>" data-lightbox="galeria-pelicula" data-title="<?php echo $pelicula['titulo']; ?>">
                        <img src="<?php echo $imagen['url']; ?>" class="img-fluid rounded" alt="<?php echo $pelicula['titulo']; ?>">
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<!-- Sección de valoraciones -->
<div id="valoraciones" class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0">Valoraciones y comentarios</h4>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-4 text-center">
                <div class="display-4 font-weight-bold text-primary">
                    <?php echo number_format($valoracionesData['valoraciones']['promedio'] ?? 0, 1); ?>
                </div>
                <div class="star-rating mb-2">
                    <?php 
                    $promedio = $valoracionesData['valoraciones']['promedio'] ?? 0;
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $promedio) {
                            echo '<i class="fas fa-star text-warning"></i>';
                        } elseif ($i <= $promedio + 0.5) {
                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                        } else {
                            echo '<i class="far fa-star text-warning"></i>';
                        }
                    }
                    ?>
                </div>
                <div class="text-muted">
                    <?php echo $valoracionesData['valoraciones']['total'] ?? 0; ?> valoraciones
                </div>
            </div>
            <div class="col-md-8">
                <h5>Distribución de valoraciones</h5>
                <?php
                $distribucion = $valoracionesData['estadisticas'] ?? [];
                $totalValoraciones = ($valoracionesData['valoraciones']['total'] ?? 0) > 0 ? 
                                     ($valoracionesData['valoraciones']['total'] ?? 1) : 1;
                
                for ($i = 5; $i >= 1; $i--) {
                    $cantidadEstrellas = $distribucion[$i] ?? 0;
                    $porcentaje = ($cantidadEstrellas / $totalValoraciones) * 100;
                    ?>
                    <div class="d-flex align-items-center mb-1">
                        <div class="mr-3" style="width: 40px;">
                            <?php echo $i; ?> <i class="fas fa-star text-warning"></i>
                        </div>
                        <div class="progress flex-grow-1" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo $porcentaje; ?>%;" 
                                 aria-valuenow="<?php echo $porcentaje; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                        <div class="ml-3" style="width: 40px;">
                            <?php echo $cantidadEstrellas; ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>
        
        <hr>
        
        <!-- Formulario de valoración -->
        <div class="mb-4">
            <h5>¿Ya viste esta película? Valórala</h5>
            <?php if (estaLogueado()): ?>
                <?php
                // Verificar valoración actual del usuario
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
                ?>
                <form action="guardar_valoracion.php" method="post" class="mt-3">
                    <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                    
                    <div class="form-group">
                        <label>Tu puntuación</label>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" 
                                           name="puntuacion" id="rating<?php echo $i; ?>" 
                                           value="<?php echo $i; ?>" 
                                           <?php echo ($userHasRated && $userRating['puntuacion'] == $i) ? 'checked' : ''; ?> 
                                           required>
                                    <label class="form-check-label" for="rating<?php echo $i; ?>">
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
                    
                    <button type="submit" class="btn btn-primary">
                        <?php echo $userHasRated ? 'Actualizar valoración' : 'Enviar valoración'; ?>
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-info">
                    <a href="auth/login.php">Inicia sesión</a> para valorar esta película.
                </div>
            <?php endif; ?>
        </div>
        
        <hr>
        
        <!-- Lista de comentarios -->
        <h5 class="mb-3">Comentarios</h5>
        <?php if (!empty($valoracionesData['comentarios'])): ?>
            <?php foreach ($valoracionesData['comentarios'] as $comentario): ?>
                <div class="media mb-4">
                    <img src="<?php echo $comentario['imagen_url'] ?? 'assets/img/avatar-default.jpg'; ?>" 
                         class="mr-3 rounded-circle" 
                         alt="<?php echo $comentario['nombres']; ?>" 
                         width="50" height="50">
                    <div class="media-body">
                        <h5 class="mt-0">
                            <?php echo $comentario['nombres'] . ' ' . $comentario['apellidos']; ?>
                            <small class="text-muted ml-2">
                                <?php echo date('d/m/Y', strtotime($comentario['fecha_valoracion'])); ?>
                            </small>
                        </h5>
                        <div class="mb-2">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $comentario['puntuacion']) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                            }
                            ?>
                        </div>
                        <p><?php echo $comentario['comentario']; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-light">
                No hay comentarios todavía. ¡Sé el primero en valorar esta película!
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para compartir -->
<div class="modal fade" id="compartirModal" tabindex="-1" role="dialog" aria-labelledby="compartirModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="compartirModalLabel">Compartir "<?php echo $pelicula['titulo']; ?>"</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Comparte esta película en tus redes sociales:</p>
                
                <?php
                $urlActual = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
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

<!-- Incluir Lightbox para la galería de imágenes -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<script>
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

document.addEventListener('DOMContentLoaded', function() {
    // Interactividad para las estrellas de valoración
    const ratingLabels = document.querySelectorAll('.rating-stars .form-check-label');
    const ratingInputs = document.querySelectorAll('.rating-stars input[type="radio"]');
    
    // Función para actualizar las estrellas según la valoración
    function updateStars(rating) {
        ratingLabels.forEach((label, index) => {
            const star = label.querySelector('i');
            if (index < rating) {
                star.classList.remove('far');
                star.classList.add('fas');
            } else {
                star.classList.remove('fas');
                star.classList.add('far');
            }
        });
    }
    
    // Marcar estrellas al pasar el mouse
    ratingLabels.forEach((label, index) => {
        label.addEventListener('mouseenter', () => {
            updateStars(index + 1);
        });
    });
    
    // Restaurar valoración seleccionada al quitar el mouse
    document.querySelector('.rating-stars')?.addEventListener('mouseleave', () => {
        const checkedInput = document.querySelector('.rating-stars input:checked');
        const rating = checkedInput ? parseInt(checkedInput.value) : 0;
        updateStars(rating);
    });
    
    // Actualizar valoración al hacer clic
    ratingInputs.forEach((input, index) => {
        input.addEventListener('change', () => {
            updateStars(index + 1);
        });
    });
    
    // Inicialización - mostrar valoración actual si existe
    const checkedInput = document.querySelector('.rating-stars input:checked');
    if (checkedInput) {
        const initialRating = parseInt(checkedInput.value);
        updateStars(initialRating);
    }
});
</script>

<style>
.rating-stars .form-check-label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ffc107;
    padding: 0 5px;
}
.rating-stars .form-check-input {
    position: absolute;
    opacity: 0;
}
</style>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>