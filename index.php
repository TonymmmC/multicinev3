<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Cargar controladores
require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener películas en cartelera
$peliculasCartelera = $peliculaController->getPeliculasCartelera(6);

// Obtener películas próximas
$peliculasProximas = $peliculaController->getPeliculasProximas(3);

// Consulta para obtener promociones activas
$query = "SELECT pr.id, pr.nombre, pr.descripcion, pr.tipo, pr.valor, m.url as imagen_url
          FROM promociones pr
          LEFT JOIN multimedia m ON pr.imagen_id = m.id
          WHERE pr.fecha_inicio <= NOW() 
          AND pr.fecha_fin >= NOW()
          AND pr.activa = 1
          AND pr.deleted_at IS NULL
          LIMIT 3";

$result = $conn->query($query);
$promociones = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $promociones[] = $row;
    }
}

// Consulta para obtener planes MultiPass
$query = "SELECT id, nombre, descripcion, precio_mensual, incluye_premium
          FROM planes_multipass
          WHERE activo = 1
          AND deleted_at IS NULL
          ORDER BY precio_mensual
          LIMIT 3";

$result = $conn->query($query);
$planesMultipass = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $planesMultipass[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<!-- Banner principal -->
<div id="carouselExampleIndicators" class="carousel slide mb-4" data-ride="carousel">
    <ol class="carousel-indicators">
        <li data-target="#carouselExampleIndicators" data-slide-to="0" class="active"></li>
        <li data-target="#carouselExampleIndicators" data-slide-to="1"></li>
        <li data-target="#carouselExampleIndicators" data-slide-to="2"></li>
    </ol>
    <div class="carousel-inner">
        <!-- Destacar una película en cartelera -->
        <?php if (!empty($peliculasCartelera)): ?>
            <div class="carousel-item active">
                <img src="<?php echo $peliculasCartelera[0]['poster_url'] ?? 'assets/img/banner-default.jpg'; ?>" class="d-block w-100" alt="<?php echo $peliculasCartelera[0]['titulo']; ?>">
                <div class="carousel-caption d-none d-md-block">
                    <h2><?php echo $peliculasCartelera[0]['titulo']; ?></h2>
                    <p><?php echo substr($peliculasCartelera[0]['sinopsis'] ?? '', 0, 100) . '...'; ?></p>
                    <a href="pelicula.php?id=<?php echo $peliculasCartelera[0]['id']; ?>" class="btn btn-primary">Ver Detalles</a>
                </div>
            </div>
        <?php else: ?>
            <div class="carousel-item active">
                <img src="assets/img/banner-default.jpg" class="d-block w-100" alt="Banner predeterminado">
                <div class="carousel-caption d-none d-md-block">
                    <h2>Las mejores películas en cartelera</h2>
                    <p>Vive la experiencia cinematográfica definitiva</p>
                    <a href="cartelera.php" class="btn btn-primary">Ver Cartelera</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Promociones -->
        <div class="carousel-item">
            <img src="assets/img/promociones-banner.jpg" class="d-block w-100" alt="Promociones">
            <div class="carousel-caption d-none d-md-block">
                <h2>Promociones especiales</h2>
                <p>Descubre nuestras ofertas exclusivas</p>
                <a href="promociones.php" class="btn btn-primary">Ver Promociones</a>
            </div>
        </div>
        
        <!-- MultiPass -->
        <div class="carousel-item">
            <img src="assets/img/multipass-banner.jpg" class="d-block w-100" alt="MultiPass">
            <div class="carousel-caption d-none d-md-block">
                <h2>MultiPass</h2>
                <p>La mejor manera de disfrutar del cine</p>
                <a href="multipass.php" class="btn btn-primary">Conoce MultiPass</a>
            </div>
        </div>
    </div>
    <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="sr-only">Anterior</span>
    </a>
    <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="sr-only">Siguiente</span>
    </a>
</div>

<!-- Películas en cartelera -->
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>En Cartelera</h2>
        <a href="cartelera.php" class="btn btn-outline-primary">Ver todas</a>
    </div>
    
    <div class="row">
        <?php foreach ($promociones as $promocion): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $promocion['imagen_url'] ?? 'assets/img/promocion-default.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo $promocion['nombre']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $promocion['nombre']; ?></h5>
                        <p class="card-text"><?php echo substr($promocion['descripcion'], 0, 100) . (strlen($promocion['descripcion']) > 100 ? '...' : ''); ?></p>
                        <?php if ($promocion['tipo'] == 'descuento' && $promocion['valor']): ?>
                            <div class="alert alert-success">
                                <strong>Descuento:</strong> <?php echo $promocion['valor']; ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <a href="promocion.php?id=<?php echo $promocion['id']; ?>" class="btn btn-sm btn-primary">Ver detalles</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($promociones)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay promociones activas en este momento.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MultiPass -->
<section class="bg-light py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2>MultiPass</h2>
                <p class="lead">La mejor manera de disfrutar del cine</p>
                <p>Suscríbete a nuestro plan mensual y obtén entradas gratis, descuentos en candy bar y beneficios exclusivos.</p>
                <a href="multipass.php" class="btn btn-primary">Conoce más</a>
            </div>
            <div class="col-md-6">
                <div class="card-deck">
                    <?php foreach ($planesMultipass as $plan): ?>
                        <div class="card">
                            <div class="card-header text-center">
                                <h5 class="mb-0"><?php echo $plan['nombre']; ?></h5>
                            </div>
                            <div class="card-body">
                                <h4 class="card-title text-center">
                                    Bs. <?php echo number_format($plan['precio_mensual'], 2); ?>
                                    <small class="text-muted">/ mes</small>
                                </h4>
                                <p class="card-text"><?php echo substr($plan['descripcion'], 0, 80) . (strlen($plan['descripcion']) > 80 ? '...' : ''); ?></p>
                            </div>
                            <div class="card-footer text-center">
                                <a href="multipass.php?plan=<?php echo $plan['id']; ?>" class="btn btn-outline-primary btn-sm">Seleccionar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($planesMultipass)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">No hay planes MultiPass disponibles en este momento.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>>
        <?php foreach ($peliculasCartelera as $pelicula): ?>
            <div class="col-md-4 col-lg-2 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo $pelicula['titulo']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $pelicula['titulo']; ?></h5>
                        <p class="card-text small">
                            <span class="badge badge-info"><?php echo $pelicula['clasificacion'] ?? ''; ?></span>
                            <?php echo $pelicula['duracion_min']; ?> min
                        </p>
                        <?php if (!empty($pelicula['generos'])): ?>
                            <p class="card-text small">
                                <?php foreach ($pelicula['generos'] as $genero): ?>
                                    <span class="badge badge-secondary"><?php echo $genero['nombre']; ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary btn-block">Ver detalles</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($peliculasCartelera)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay películas en cartelera en este momento.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Próximos estrenos -->
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Próximos Estrenos</h2>
        <a href="proximamente.php" class="btn btn-outline-primary">Ver todos</a>
    </div>
    
    <div class="row">
        <?php foreach ($peliculasProximas as $pelicula): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo $pelicula['titulo']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $pelicula['titulo']; ?></h5>
                        <p class="card-text small">
                            <span class="badge badge-info"><?php echo $pelicula['clasificacion'] ?? ''; ?></span>
                            <?php echo $pelicula['duracion_min']; ?> min
                        </p>
                        <p class="card-text">
                            <strong>Estreno:</strong> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?>
                        </p>
                        <?php if (!empty($pelicula['generos'])): ?>
                            <p class="card-text small">
                                <?php foreach ($pelicula['generos'] as $genero): ?>
                                    <span class="badge badge-secondary"><?php echo $genero['nombre']; ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary btn-block">Ver detalles</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($peliculasProximas)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay próximos estrenos en este momento.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Promociones -->
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Promociones</h2>
        <a href="promociones.php" class="btn btn-outline-primary">Ver todas</a>
    </div>
    
    <div class="row"