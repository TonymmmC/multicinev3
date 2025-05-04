<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Consulta para obtener películas en cartelera
$query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                 pd.sinopsis, m.url as poster_url
          FROM peliculas p
          LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          WHERE p.estado IN ('estreno', 'regular') 
          AND p.deleted_at IS NULL
          ORDER BY p.fecha_estreno DESC
          LIMIT 6";

$result = $conn->query($query);
$peliculas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $peliculas[] = $row;
    }
}

// Consulta para obtener promociones activas
$query = "SELECT pr.id, pr.nombre, pr.descripcion, m.url as imagen_url
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
        <div class="carousel-item active">
            <img src="https://via.placeholder.com/1200x400?text=Película+Destacada" class="d-block w-100" alt="Banner 1">
            <div class="carousel-caption d-none d-md-block">
                <h2>Las mejores películas en cartelera</h2>
                <p>Vive la experiencia cinematográfica definitiva</p>
                <a href="cartelera.php" class="btn btn-primary">Ver Cartelera</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://via.placeholder.com/1200x400?text=Promociones" class="d-block w-100" alt="Banner 2">
            <div class="carousel-caption d-none d-md-block">
                <h2>Promociones especiales</h2>
                <p>Descubre nuestras ofertas exclusivas</p>
                <a href="promociones.php" class="btn btn-primary">Ver Promociones</a>
            </div>
        </div>
        <div class="carousel-item">
            <img src="https://via.placeholder.com/1200x400?text=MultiPass" class="d-block w-100" alt="Banner 3">
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
        <?php foreach ($peliculas as $pelicula): ?>
            <div class="col-md-4 col-lg-2 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $pelicula['poster_url'] ?? 'https://via.placeholder.com/300x450?text=Poster'; ?>" 
                         class="card-img-top" alt="<?php echo $pelicula['titulo']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $pelicula['titulo']; ?></h5>
                        <p class="card-text small"><?php echo $pelicula['duracion_min']; ?> min</p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary btn-block">Ver detalles</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($peliculas)): ?>
            <div class="col-12">
                <div class="alert alert-info">No hay películas en cartelera en este momento.</div>
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
    
    <div class="row">
        <?php foreach ($promociones as $promocion): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <img src="<?php echo $promocion['imagen_url'] ?? 'https://via.placeholder.com/400x300?text=Promoción'; ?>" 
                         class="card-img-top" alt="<?php echo $promocion['nombre']; ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $promocion['nombre']; ?></h5>
                        <p class="card-text"><?php echo $promocion['descripcion']; ?></p>
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
                <img src="https://via.placeholder.com/600x400?text=MultiPass" class="img-fluid rounded" alt="MultiPass">
            </div>
        </div>
    </div>
</section>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>