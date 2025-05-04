<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener el ID de la película
$peliculaId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$peliculaId) {
    setMensaje('ID de película no válido', 'danger');
    redirect('cartelera.php');
}

// Obtener detalles de la película
$pelicula = $peliculaController->getPeliculaById($peliculaId);

if (!$pelicula) {
    setMensaje('Película no encontrada', 'danger');
    redirect('cartelera.php');
}

// Verificar si el usuario ya marcó la película como favorita
$esFavorita = false;
if (estaLogueado()) {
    $esFavorita = $peliculaController->esFavorita($peliculaId, $_SESSION['user_id']);
}

// Obtener valoraciones
$valoracionesData = $peliculaController->getValoracionesPelicula($peliculaId, 5);
$valoraciones = $valoracionesData['valoraciones'];
$estadisticasValoraciones = $valoracionesData['estadisticas'];

// Incluir header
require_once 'includes/header.php';
?>

<div class="container mb-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
            <li class="breadcrumb-item"><a href="cartelera.php">Cartelera</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo $pelicula['titulo']; ?></li>
        </ol>
    </nav>

    <!-- Película header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm bg-dark text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <img src="<?php echo $pelicula['poster_url']; ?>" 
                                 alt="<?php echo $pelicula['titulo']; ?>" 
                                 class="img-fluid rounded" style="max-height: 400px;">
                        </div>
                        <div class="col-md-8">
                            <h1 class="mb-2"><?php echo $pelicula['titulo']; ?></h1>
                            
                            <?php if (!empty($pelicula['titulo_original']) && $pelicula['titulo_original'] != $pelicula['titulo']): ?>
                                <h5 class="text-light mb-3"><?php echo $pelicula['titulo_original']; ?></h5>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <?php if ($estadisticasValoraciones['total_valoraciones'] > 0): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="rating-stars me-2">
                                            <?php
                                            $rating = $estadisticasValoraciones['puntuacion_promedio'];
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                } elseif ($i - 0.5 <= $rating) {
                                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <span class="rating-value text-warning fw-bold"><?php echo $rating; ?></span>
                                        <span class="text-muted ms-2">(<?php echo $estadisticasValoraciones['total_valoraciones']; ?> valoraciones)</span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">Sin valoraciones</div>
                                <?php endif; ?>
                                
                                <?php if (!empty($pelicula['generos'])): ?>
                                    <div class="mb-2">
                                        <?php foreach ($pelicula['generos'] as $genero): ?>
                                            <span class="badge bg-primary me-1"><?php echo $genero['nombre']; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Clasificación:</strong> 
                                        <span class="badge bg-info"><?php echo $pelicula['clasificacion'] ?? 'N/A'; ?></span>
                                        <?php if (!empty($pelicula['clasificacion_desc'])): ?>
                                            <span class="text-light small"><?php echo $pelicula['clasificacion_desc']; ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Duración:</strong> <?php echo $pelicula['duracion_min']; ?> minutos</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Estreno:</strong> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?></p>
                                </div>
                                <?php if ($pelicula['estado'] == 'estreno'): ?>
                                    <div class="col-md-6">
                                        <span class="badge bg-danger">ESTRENO</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-3">
                                <a href="#funciones" class="btn btn-success me-2">
                                    <i class="fas fa-ticket-alt"></i> Ver funciones
                                </a>
                                <a href="#trailer" class="btn btn-primary me-2">
                                    <i class="fas fa-play-circle"></i> Ver trailer
                                </a>
                                <?php if (estaLogueado()): ?>
                                    <button id="btnFavorito" class="btn <?php echo $esFavorita ? 'btn-danger' : 'btn-outline-light'; ?>" 
                                            data-pelicula="<?php echo $peliculaId; ?>" 
                                            data-action="<?php echo $esFavorita ? 'quitar' : 'agregar'; ?>">
                                        <i class="<?php echo $esFavorita ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <?php echo $esFavorita ? 'Quitar de favoritos' : 'Agregar a favoritos'; ?>
                                    </button>
                                <?php else: ?>
                                    <a href="auth/login.php" class="btn btn-outline-light">
                                        <i class="far fa-heart"></i> Inicia sesión para guardar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Sinopsis y detalles -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Sinopsis</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($pelicula['sinopsis'])): ?>
                        <p><?php echo nl2br($pelicula['sinopsis']); ?></p>
                    <?php else: ?>
                        <p class="text-muted">No hay sinopsis disponible para esta película.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($pelicula['elenco'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h5 mb-0">Elenco</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            // Agrupar por rol
                            $elencoAgrupado = [];
                            foreach ($pelicula['elenco'] as $persona) {
                                if (!isset($elencoAgrupado[$persona['rol']])) {
                                    $elencoAgrupado[$persona['rol']] = [];
                                }
                                $elencoAgrupado[$persona['rol']][] = $persona;
                            }
                            
                            // Mostrar por grupos
                            foreach ($elencoAgrupado as $rol => $personas): 
                            ?>
                                <div class="col-12 mb-3">
                                    <h3 class="h6 border-bottom pb-2"><?php echo $rol; ?></h3>
                                    <div class="row">
                                        <?php foreach ($personas as $persona): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo !empty($persona['imagen_url']) ? $persona['imagen_url'] : 'assets/img/actor-default.jpg'; ?>" 
                                                         class="rounded-circle me-2" alt="<?php echo $persona['nombre'] . ' ' . $persona['apellido']; ?>"
                                                         width="50" height="50">
                                                    <div>
                                                        <div class="fw-bold"><?php echo $persona['nombre'] . ' ' . $persona['apellido']; ?></div>
                                                        <?php if (!empty($persona['personaje'])): ?>
                                                            <div class="text-muted small"><?php echo $persona['personaje']; ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Trailer -->
            <div id="trailer" class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Trailer</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($pelicula['url_trailer'])): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="<?php echo $pelicula['url_trailer']; ?>" title="Trailer de <?php echo $pelicula['titulo']; ?>" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No hay trailer disponible para esta película.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Galería de imágenes -->
            <?php if (!empty($pelicula['multimedia']['galeria'])): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h2 class="h5 mb-0">Galería</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($pelicula['multimedia']['galeria'] as $imagen): ?>
                                <div class="col-md-4 mb-3">
                                    <a href="<?php echo $imagen['url']; ?>" data-lightbox="galeria-pelicula" data-title="<?php echo $pelicula['titulo']; ?>">
                                        <img src="<?php echo $imagen['url']; ?>" class="img-fluid rounded" alt="<?php echo $pelicula['titulo']; ?>">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Valoraciones -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h2 class="h5 mb-0">Valoraciones</h2>
                </div>
                <div class="card-body">
                    <?php if ($estadisticasValoraciones['total_valoraciones'] > 0): ?>
                        <div class="text-center mb-3">
                            <div class="display-4 fw-bold text-warning"><?php echo $estadisticasValoraciones['puntuacion_promedio']; ?></div>
                            <div class="rating-stars mb-2">
                                <?php
                                $rating = $estadisticasValoraciones['puntuacion_promedio'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star text-warning"></i>';
                                    } elseif ($i - 0.5 <= $rating) {
                                        echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-warning"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <div class="text-muted"><?php echo $estadisticasValoraciones['total_valoraciones']; ?> valoraciones</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-1">
                                <div class="me-2">5 <i class="fas fa-star text-warning"></i></div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($estadisticasValoraciones['cinco_estrellas'] / $estadisticasValoraciones['total_valoraciones']) * 100; ?>%"></div>
                                </div>
                                <div class="ms-2"><?php echo $estadisticasValoraciones['cinco_estrellas']; ?></div>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <div class="me-2">4 <i class="fas fa-star text-warning"></i></div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($estadisticasValoraciones['cuatro_estrellas'] / $estadisticasValoraciones['total_valoraciones']) * 100; ?>%"></div>
                                </div>
                                <div class="ms-2"><?php echo $estadisticasValoraciones['cuatro_estrellas']; ?></div>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <div class="me-2">3 <i class="fas fa-star text-warning"></i></div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($estadisticasValoraciones['tres_estrellas'] / $estadisticasValoraciones['total_valoraciones']) * 100; ?>%"></div>
                                </div>
                                <div class="ms-2"><?php echo $estadisticasValoraciones['tres_estrellas']; ?></div>
                            </div>
                            <div class="d-flex align-items-center mb-1">
                                <div class="me-2">2 <i class="fas fa-star text-warning"></i></div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($estadisticasValoraciones['dos_estrellas'] / $estadisticasValoraciones['total_valoraciones']) * 100; ?>%"></div>
                                </div>
                                <div class="ms-2"><?php echo $estadisticasValoraciones['dos_estrellas']; ?></div>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="me-2">1 <i class="fas fa-star text-warning"></i></div>
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($estadisticasValoraciones['una_estrella'] / $estadisticasValoraciones['total_valoraciones']) * 100; ?>%"></div>
                                </div>
                                <div class="ms-2"><?php echo $estadisticasValoraciones['una_estrella']; ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <p class="text-muted">No hay valoraciones disponibles</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (estaLogueado()): ?>
                        <div class="mb-3">
                            <h3 class="h6">¿Ya viste esta película? ¡Valórala!</h3>
                            <form id="formValorar" action="ajax/valorar_pelicula.php" method="post">
                                <input type="hidden" name="pelicula_id" value="<?php echo $peliculaId; ?>">
                                <div class="mb-3">
                                    <div class="rating">
                                        <input type="radio" id="star5" name="puntuacion" value="5" /><label for="star5"></label>
                                        <input type="radio" id="star4" name="puntuacion" value="4" /><label for="star4"></label>
                                        <input type="radio" id="star3" name="puntuacion" value="3" /><label for="star3"></label>
                                        <input type="radio" id="star2" name="puntuacion" value="2" /><label for="star2"></label>
                                        <input type="radio" id="star1" name="puntuacion" value="1" /><label for="star1"></label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <textarea class="form-control" name="comentario" rows="2" placeholder="Escribe un comentario (opcional)"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Enviar valoración</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <a href="auth/login.php" class="btn btn-outline-primary">Inicia sesión para valorar</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($valoraciones)): ?>
                        <div class="mt-4">
                            <h3 class="h6 border-bottom pb-2">Comentarios recientes</h3>
                            <?php foreach ($valoraciones as $valoracion): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex align-items-center mb-2">
                                        <img src="<?php echo !empty($valoracion['imagen_url']) ? $valoracion['imagen_url'] : 'assets/img/user-default.jpg'; ?>" 
                                             class="rounded-circle me-2" alt="Avatar" width="32" height="32">
                                        <div>
                                            <div class="fw-bold"><?php echo $valoracion['nombres'] . ' ' . $valoracion['apellidos']; ?></div>
                                            <div class="small text-muted">
                                                <?php echo date('d/m/Y', strtotime($valoracion['fecha_valoracion'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rating-stars mb-2">
                                        <?php
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $valoracion['puntuacion']) {
                                                echo '<i class="fas fa-star text-warning"></i>';
                                            } else {
                                                echo '<i class="far fa-star text-warning"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($valoracion['comentario'])): ?>
                                        <p class="small mb-0"><?php echo nl2br($valoracion['comentario']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center">
                                <a href="valoraciones.php?id=<?php echo $peliculaId; ?>" class="btn btn-sm btn-outline-primary">
                                    Ver todas las valoraciones
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Funciones disponibles -->
    <div id="funciones" class="card shadow-sm mb-4">
        <div class="card-header bg-success text-white">
            <h2 class="h5 mb-0">Funciones disponibles</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($pelicula['funciones'])): ?>
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
                            <?php 
                            // Agrupar funciones por fecha
                            $funcionesPorFecha = [];
                            foreach ($pelicula['funciones'] as $funcion) {
                                $fecha = date('Y-m-d', strtotime($funcion['fecha_hora']));
                                if (!isset($funcionesPorFecha[$fecha])) {
                                    $funcionesPorFecha[$fecha] = [];
                                }
                                $funcionesPorFecha[$fecha][] = $funcion;
                            }
                            
                            // Mostrar funciones por fecha
                            foreach ($funcionesPorFecha as $fecha => $funciones): 
                                $fechaFormateada = date('d/m/Y', strtotime($fecha));
                            ?>
                                <tr class="table-light">
                                    <td colspan="8" class="fw-bold"><?php echo $fechaFormateada; ?></td>
                                </tr>
                                <?php foreach ($funciones as $funcion): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?></td>
                                        <td><?php echo $funcion['cine']; ?></td>
                                        <td><?php echo $funcion['sala']; ?></td>
                                        <td><?php echo $funcion['formato']; ?></td>
                                        <td><?php echo $funcion['idioma']; ?></td>
                                        <td>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></td>
                                        <td>
                                            <?php echo $funcion['asientos_disponibles']; ?> 
                                            <?php if ($funcion['asientos_disponibles'] < 10): ?>
                                                <span class="badge bg-warning">¡Pocos asientos!</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="reserva.php?funcion=<?php echo $funcion['id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-ticket-alt"></i> Reservar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay funciones disponibles para esta película en este momento.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Películas relacionadas -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0">Películas que podrían interesarte</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Implementación futura: mostrar películas del mismo género o director -->
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Próximamente: recomendaciones personalizadas basadas en tus preferencias.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos de la página -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sistema de valoración con estrellas
    const ratingStars = document.querySelectorAll('.rating input');
    ratingStars.forEach(star => {
        star.addEventListener('change', function() {
            // Actualizar visualmente las estrellas
            const value = this.value;
            for (let i = 1; i <= 5; i++) {
                const label = document.querySelector(`label[for="star${i}"]`);
                if (i <= value) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            }
        });
    });
    
    // Formulario de valoración
    const formValorar = document.getElementById('formValorar');
    if (formValorar) {
        formValorar.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Recargar la página para mostrar la nueva valoración
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ha ocurrido un error al enviar la valoración');
            });
        });
    }
    
    // Botón de favoritos
    const btnFavorito = document.getElementById('btnFavorito');
    if (btnFavorito) {
        btnFavorito.addEventListener('click', function() {
            const peliculaId = this.getAttribute('data-pelicula');
            const action = this.getAttribute('data-action');
            
            fetch('ajax/administrar_favorito.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `pelicula_id=${peliculaId}&accion=${action}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar el estado del botón
                    if (action === 'agregar') {
                        this.classList.remove('btn-outline-light');
                        this.classList.add('btn-danger');
                        this.innerHTML = '<i class="fas fa-heart"></i> Quitar de favoritos';
                        this.setAttribute('data-action', 'quitar');
                    } else {
                        this.classList.remove('btn-danger');
                        this.classList.add('btn-outline-light');
                        this.innerHTML = '<i class="far fa-heart"></i> Agregar a favoritos';
                        this.setAttribute('data-action', 'agregar');
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ha ocurrido un error al procesar la acción');
            });
        });
    }
});
</script>

<style>
/* Estilos para el sistema de valoración */
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}
.rating input {
    display: none;
}
.rating label {
    cursor: pointer;
    width: 30px;
    height: 30px;
    margin-right: 5px;
    position: relative;
    background: url('assets/img/star-empty.png') no-repeat;
    background-size: 100%;
}
.rating input:checked ~ label,
.rating input:hover ~ label,
.rating label:hover {
    background: url('assets/img/star-filled.png') no-repeat;
    background-size: 100%;
}
.rating label.active {
    background: url('assets/img/star-filled.png') no-repeat;
    background-size: 100%;
}
</style>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>