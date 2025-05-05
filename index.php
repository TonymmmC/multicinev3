<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

<<<<<<< HEAD
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
=======
// Obtener cine seleccionado (por defecto La Paz - ID: 1)
$cineSeleccionado = isset($_GET['cine']) ? intval($_GET['cine']) : 
    (isset($_SESSION['cine_id']) ? $_SESSION['cine_id'] : 1);

// Guardar preferencia de cine
$_SESSION['cine_id'] = $cineSeleccionado;

// Obtener nombre del cine
$query = "SELECT nombre FROM cines WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $cineSeleccionado);
$stmt->execute();
$result = $stmt->get_result();
$nombreCine = ($result && $row = $result->fetch_assoc()) ? $row['nombre'] : 'La Paz';

// Obtener película destacada
$query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                 pd.sinopsis, pd.url_trailer, 
                 c.codigo as clasificacion,
                 MAX(CASE WHEN mp.proposito = 'poster' THEN m.url END) as poster_url,
                 MAX(CASE WHEN mp.proposito = 'banner' THEN m.url END) as banner_url
          FROM peliculas p
          LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          WHERE p.estado = 'estreno' AND p.deleted_at IS NULL
          GROUP BY p.id
          ORDER BY p.fecha_estreno DESC
          LIMIT 1";

$result = $conn->query($query);
$peliculaDestacada = $result->fetch_assoc();

// Si no hay película en estreno, buscar cualquier película en cartelera
if (!$peliculaDestacada) {
    $query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                     pd.sinopsis, pd.url_trailer, 
                     c.codigo as clasificacion,
                     MAX(CASE WHEN mp.proposito = 'poster' THEN m.url END) as poster_url,
                     MAX(CASE WHEN mp.proposito = 'banner' THEN m.url END) as banner_url
              FROM peliculas p
              LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
              LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
              LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id
              LEFT JOIN multimedia m ON mp.multimedia_id = m.id
              WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL
              GROUP BY p.id
              ORDER BY p.fecha_estreno DESC
              LIMIT 1";
    
    $result = $conn->query($query);
    $peliculaDestacada = $result->fetch_assoc();
}

// Obtener formatos disponibles para la película destacada
if ($peliculaDestacada) {
    $query = "SELECT DISTINCT f.nombre 
              FROM funciones func
              JOIN formatos f ON func.formato_proyeccion_id = f.id
              WHERE func.pelicula_id = ? AND func.fecha_hora > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $peliculaDestacada['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $peliculaDestacada['formatos'] = [];
    while ($row = $result->fetch_assoc()) {
        $peliculaDestacada['formatos'][] = strtolower($row['nombre']);
    }
}

// Obtener películas en cartelera
$query = "SELECT p.id, p.titulo, p.duracion_min, p.estado, 
                 c.codigo as clasificacion,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          JOIN funciones f ON p.id = f.pelicula_id
          JOIN salas s ON f.sala_id = s.id
          WHERE p.estado IN ('estreno', 'regular') 
          AND p.deleted_at IS NULL
          AND f.fecha_hora > NOW()
          AND s.cine_id = ?
          GROUP BY p.id
          ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
          LIMIT 6";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $cineSeleccionado);
$stmt->execute();
$result = $stmt->get_result();

$peliculasCartelera = [];
while ($row = $result->fetch_assoc()) {
    $peliculasCartelera[] = $row;
}

// Obtener eventos especiales
$query = "SELECT e.id, e.nombre, e.fecha_inicio, m.url as imagen_url
          FROM eventos_especiales e
          LEFT JOIN multimedia m ON e.imagen_id = m.id
          WHERE e.cine_id = ? 
          AND e.fecha_fin >= NOW()
          AND e.deleted_at IS NULL
          ORDER BY e.fecha_inicio ASC
          LIMIT 4";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $cineSeleccionado);
$stmt->execute();
$result = $stmt->get_result();

$eventosEspeciales = [];
while ($row = $result->fetch_assoc()) {
    // Determinar etiqueta según tipo de evento
    $fechaEvento = strtotime($row['fecha_inicio']);
    if ($fechaEvento < time() + 86400) { // Hoy o mañana
        $row['etiqueta'] = 'HOY';
    } elseif ($fechaEvento < time() + 604800) { // Esta semana
        $row['etiqueta'] = 'ESTA SEMANA';
    } else {
        $row['etiqueta'] = 'PRÓXIMAMENTE';
    }
    
    $eventosEspeciales[] = $row;
}

// Obtener noticias destacadas
$query = "SELECT id, titulo, contenido, fecha_publicacion, imagen_id
          FROM contenido_dinamico
          WHERE tipo = 'noticia' 
          AND activo = 1
          AND deleted_at IS NULL
          ORDER BY fecha_publicacion DESC
          LIMIT 2";

$result = $conn->query($query);
$noticiasDestacadas = [];

while ($row = $result->fetch_assoc()) {
    // Obtener URL de la imagen
    $queryImg = "SELECT url FROM multimedia WHERE id = ?";
    $stmt = $conn->prepare($queryImg);
    $stmt->bind_param('i', $row['imagen_id']);
    $stmt->execute();
    $resultImg = $stmt->get_result();
    
    $row['imagen_url'] = ($resultImg && $imgRow = $resultImg->fetch_assoc()) 
        ? $imgRow['url'] 
        : 'assets/img/noticia-default.jpg';
    
    // Crear resumen del contenido
    $row['resumen'] = substr(strip_tags($row['contenido']), 0, 150) . '...';
    
    // URL de la noticia
    $row['link'] = 'noticia.php?id=' . $row['id'];
    
    $noticiasDestacadas[] = $row;
}

// Obtener películas próximas
$query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                 c.codigo as clasificacion,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          WHERE p.estado = 'proximo' 
          AND p.deleted_at IS NULL
          ORDER BY p.fecha_estreno ASC
          LIMIT 6";

$result = $conn->query($query);
$peliculasProximas = [];
while ($row = $result->fetch_assoc()) {
    $peliculasProximas[] = $row;
>>>>>>> ef46c51 (Actualizacion del sistema)
}

// Incluir header
require_once 'includes/header.php';
?>

<<<<<<< HEAD
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
=======
<!-- Hero Banner Principal con Película Destacada -->
<div class="hero-banner" style="background-image: url('<?php echo $peliculaDestacada['banner_url'] ?? 'assets/img/banner-default.jpg'; ?>');">
    <div class="container">
        <div class="hero-content">
            <h1 class="movie-title-img"><?php echo $peliculaDestacada['titulo']; ?></h1>
            
            <div class="hero-buttons mt-4">
                <a href="reserva.php?pelicula=<?php echo $peliculaDestacada['id']; ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-ticket-alt"></i> Comprar ahora
                </a>
                <button class="btn btn-outline-light btn-lg ml-2" onclick="agregarFavorito(<?php echo $peliculaDestacada['id']; ?>)">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <div class="movie-formats mt-3">
                <?php foreach ($peliculaDestacada['formatos'] ?? [] as $formato): ?>
                    <img src="assets/img/formatos/<?php echo $formato; ?>.png" alt="<?php echo $formato; ?>" height="30">
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Selector de Cine -->
<div class="cinema-selector bg-dark py-3">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-3">
                <h5 class="text-white mb-0">¿Dónde?</h5>
            </div>
            <div class="col-md-9">
                <select class="form-control" id="cineSelectorHome" onchange="cambiarCine(this.value)">
                    <?php
                    // Obtener lista de cines
                    $queryCines = "SELECT id, nombre FROM cines WHERE activo = 1 ORDER BY nombre";
                    $resultCines = $conn->query($queryCines);
                    
                    if ($resultCines && $resultCines->num_rows > 0) {
                        while ($cine = $resultCines->fetch_assoc()) {
                            $selected = $cineSeleccionado == $cine['id'] ? 'selected' : '';
                            echo "<option value='{$cine['id']}' {$selected}>{$cine['nombre']}</option>";
                        }
                    } else {
                        echo "<option value='1'>La Paz</option>";
                        echo "<option value='2'>El Alto</option>";
                        echo "<option value='3'>Santa Cruz</option>";
                    }
                    ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Sección Cartelera Actual -->
<section class="movies-section py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Ahora en Cartelera en <span id="nombreCineActual"><?php echo $nombreCine; ?></span></h2>
            <a href="cartelera.php" class="btn btn-outline-primary">Ver todas</a>
        </div>
        
        <?php if (!empty($peliculasCartelera)): ?>
            <div class="row">
                <?php foreach ($peliculasCartelera as $pelicula): ?>
                    <div class="col-md-2 col-6 mb-4">
                        <div class="movie-card">
                            <div class="position-relative">
                                <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" alt="<?php echo $pelicula['titulo']; ?>" class="img-fluid rounded">
                                <?php if ($pelicula['estado'] == 'estreno'): ?>
                                    <span class="badge badge-danger position-absolute" style="top: 10px; right: 10px;">ESTRENO</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="mt-2 mb-1"><?php echo $pelicula['titulo']; ?> <span class="badge badge-secondary"><?php echo $pelicula['clasificacion']; ?></span></h5>
                            <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary btn-block">Ver detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay películas en cartelera en <?php echo $nombreCine; ?> en este momento.
>>>>>>> ef46c51 (Actualizacion del sistema)
            </div>
        <?php endif; ?>
    </div>
</section>

<<<<<<< HEAD
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
=======
<!-- Sección Eventos Especiales -->
<section class="special-events-section py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Especiales y Eventos en <span id="nombreCineEventos"><?php echo $nombreCine; ?></span></h2>
            <a href="eventos.php" class="btn btn-outline-primary">Ver todos</a>
        </div>
        
        <?php if (!empty($eventosEspeciales)): ?>
            <div class="row">
                <?php foreach ($eventosEspeciales as $evento): ?>
                    <div class="col-md-3 col-6 mb-4">
                        <div class="movie-card">
                            <div class="position-relative">
                                <img src="<?php echo $evento['imagen_url'] ?? 'assets/img/evento-default.jpg'; ?>" alt="<?php echo $evento['nombre']; ?>" class="img-fluid rounded">
                                <?php if (!empty($evento['etiqueta'])): ?>
                                    <span class="badge badge-warning position-absolute" style="top: 10px; right: 10px;"><?php echo $evento['etiqueta']; ?></span>
                                <?php endif; ?>
                            </div>
                            <h5 class="mt-2 mb-1"><?php echo $evento['nombre']; ?></h5>
                            <a href="evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary btn-block">Ver detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay eventos especiales en <?php echo $nombreCine; ?> en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección Noticias -->
<section class="news-section py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Noticias</h2>
            <a href="noticias.php" class="text-primary">Mostrar todas las noticias</a>
        </div>
        
        <?php if (!empty($noticiasDestacadas)): ?>
            <div class="row">
                <?php foreach ($noticiasDestacadas as $noticia): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <img src="<?php echo $noticia['imagen_url']; ?>" class="card-img-top" alt="<?php echo $noticia['titulo']; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $noticia['titulo']; ?></h5>
                                <p class="card-text"><?php echo $noticia['resumen']; ?></p>
                                <a href="<?php echo $noticia['link']; ?>" class="btn btn-primary">Leer más</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay noticias disponibles en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección Próximos Estrenos -->
<section class="coming-soon-section py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Próximamente</h2>
            <a href="proximamente.php" class="btn btn-outline-primary">Ver todos</a>
        </div>
        
        <?php if (!empty($peliculasProximas)): ?>
            <div class="row">
                <?php foreach ($peliculasProximas as $pelicula): ?>
                    <div class="col-md-2 col-6 mb-4">
                        <div class="movie-card">
                            <div class="position-relative">
                                <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" alt="<?php echo $pelicula['titulo']; ?>" class="img-fluid rounded">
                                <span class="badge badge-warning position-absolute" style="top: 10px; right: 10px;">PRÓXIMAMENTE</span>
                            </div>
                            <h5 class="mt-2 mb-1"><?php echo $pelicula['titulo']; ?> <span class="badge badge-secondary"><?php echo $pelicula['clasificacion']; ?></span></h5>
                            <p class="small text-muted">Estreno: <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No hay próximos estrenos programados en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección de Ayuda -->
<section class="help-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2>¿Tienes alguna pregunta?</h2>
                <p>Visita nuestro servicio de atención al cliente de WhatsApp</p>
                <a href="https://wa.me/59170000000" class="btn btn-success">
                    <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                </a>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Preguntas frecuentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="card">
                                <div class="card-header" id="headingOne">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseOne">
                                            ¿Cómo compro entradas en línea?
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#faqAccordion">
                                    <div class="card-body">
                                        Selecciona la película, función y asientos que deseas, luego procede al pago con tus métodos preferidos.
                                    </div>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header" id="headingTwo">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link collapsed" type="button" data-toggle="collapse" data-target="#collapseTwo">
                                            ¿Cómo funciona MultiPass?
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#faqAccordion">
                                    <div class="card-body">
                                        MultiPass es nuestra suscripción mensual que te permite obtener entradas gratis y descuentos exclusivos.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <a href="faq.php" class="btn btn-link mt-3">Ver más preguntas</a>
                    </div>
>>>>>>> ef46c51 (Actualizacion del sistema)
                </div>
            </div>
        </div>
    </div>
</section>

<<<<<<< HEAD
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
=======
<!-- Añadir estilos CSS -->
<style>
/* Estilos para la barra de navegación transparente sobre el banner */
.navbar {
    background-color: rgba(0,0,0,0.5); 
    position: absolute;
    width: 100%;
    z-index: 1000;
}

.navbar.scrolled {
    background-color: #343a40; 
    position: fixed;
    top: 0;
    transition: background-color 0.3s ease;
}

/* Estilos para el banner principal */
.hero-banner {
    height: 80vh;
    background-size: cover;
    background-position: center;
    position: relative;
    display: flex;
    align-items: center;
    color: white;
}

.hero-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.8) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    padding-top: 70px; 
}

.movie-title-img {
    font-size: 3.5rem;
    font-weight: bold;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
}

/* Estilo para las tarjetas de películas */
.movie-card {
    transition: transform 0.3s ease;
}

.movie-card:hover {
    transform: translateY(-5px);
}
</style>

<!-- Scripts JavaScript -->
<script>
// Cambiar estilo de navbar al hacer scroll
$(window).scroll(function() {
    if ($(this).scrollTop() > 100) {
        $('.navbar').addClass('scrolled');
    } else {
        $('.navbar').removeClass('scrolled');
    }
});

// Función para cambiar de cine seleccionado
function cambiarCine(cineId) {
    window.location.href = 'index.php?cine=' + cineId;
}

// Función para agregar a favoritos
function agregarFavorito(peliculaId) {
    <?php if (estaLogueado()): ?>
        $.ajax({
            url: 'favoritos.php',
            type: 'POST',
            data: { 
                pelicula_id: peliculaId,
                action: 'agregar'
            },
            success: function(response) {
                try {
                    const data = JSON.parse(response);
                    if (data.success) {
                        alert('Película añadida a favoritos');
                    }
                } catch (e) {
                    console.error('Error al procesar la respuesta:', e);
                }
            }
        });
    <?php else: ?>
        // Redirigir a login si no está logueado
        window.location.href = 'auth/login.php';
    <?php endif; ?>
}
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>
>>>>>>> ef46c51 (Actualizacion del sistema)
