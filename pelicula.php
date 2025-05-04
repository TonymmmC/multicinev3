<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener el ID de la película
$peliculaId = isset($_GET['id']) ? $_GET['id'] : null;

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

// Obtener funciones disponibles para esta película
$query = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles,
                 s.nombre as sala, c.nombre as cine, i.nombre as idioma,
                 fp.nombre as formato
          FROM funciones f
          JOIN salas s ON f.sala_id = s.id
          JOIN cines c ON s.cine_id = c.id
          JOIN idiomas i ON f.idioma_id = i.id
          JOIN formatos fp ON f.formato_proyeccion_id = fp.id
          WHERE f.pelicula_id = ?
          AND f.fecha_hora > NOW()
          ORDER BY f.fecha_hora
          LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $peliculaId);
$stmt->execute();
$result = $stmt->get_result();

$funciones = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funciones[] = $row;
    }
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
        <img src="<?php echo $pelicula['multimedia']['poster']['url'] ?? 'assets/img/poster-default.jpg'; ?>" 
             class="img-fluid rounded mb-3" alt="<?php echo $pelicula['titulo']; ?>">
        
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
        
        <?php if (!empty($funciones)): ?>
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
                                <?php foreach ($funciones as $funcion): ?>
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

<?php
// Incluir footer
require_once 'includes/footer.php';
?>