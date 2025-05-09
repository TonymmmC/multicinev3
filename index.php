<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Obtener cine seleccionado (por defecto La Paz - ID: 1)
$cineSeleccionado = isset($_GET['cine']) ? intval($_GET['cine']) : 
    (isset($_SESSION['cine_id']) ? $_SESSION['cine_id'] : 1);

// Si el valor es 0 (Todos los cines), establecer a null para consultas
$cineParaConsulta = ($cineSeleccionado == 0) ? null : $cineSeleccionado;

// Guardar preferencia de cine
$_SESSION['cine_id'] = $cineSeleccionado;

// Obtener nombre del cine
$nombreCine = "Todos los cines";
if ($cineSeleccionado > 0) {
    $query = "SELECT nombre FROM cines WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $cineSeleccionado);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $nombreCine = $row['nombre'];
    }
    $stmt->close();
}

// Obtener película destacada
$query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, p.estado, 
                 c.codigo as clasificacion,
                 m.url as poster_url,
                 MAX(CASE WHEN mp.proposito = 'banner' THEN m2.url END) as banner_url,
                 pd.sinopsis, pd.url_trailer
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          LEFT JOIN multimedia_pelicula mp2 ON p.id = mp2.pelicula_id AND mp2.proposito = 'banner'
          LEFT JOIN multimedia m2 ON mp2.multimedia_id = m2.id
          LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
          LEFT JOIN funciones f ON p.id = f.pelicula_id
          LEFT JOIN salas s ON f.sala_id = s.id";

// Condición para cine específico o todos los cines
if ($cineParaConsulta !== null) {
    $query .= " WHERE p.estado IN ('estreno', 'regular') 
                AND p.deleted_at IS NULL
                AND (s.cine_id = ? OR s.cine_id IS NULL)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $cineParaConsulta);
} else {
    $query .= " WHERE p.estado IN ('estreno', 'regular') 
                AND p.deleted_at IS NULL";
    $stmt = $conn->prepare($query);
}

$query .= " GROUP BY p.id
            ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
            LIMIT 1";

$stmt->execute();
$result = $stmt->get_result();
$peliculaDestacada = $result->fetch_assoc();
$stmt->close();

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
    $stmt->close();
}

// Obtener películas en cartelera
$query = "SELECT p.id, p.titulo, p.duracion_min, p.estado, 
                 c.codigo as clasificacion,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          LEFT JOIN funciones f ON p.id = f.pelicula_id
          LEFT JOIN salas s ON f.sala_id = s.id
          WHERE p.estado IN ('estreno', 'regular') 
          AND p.deleted_at IS NULL";

$query = "SELECT DISTINCT p.id, p.titulo, p.duracion_min, p.estado, 
                 c.codigo as clasificacion,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN (
              SELECT pelicula_id, MIN(multimedia_id) as multimedia_id 
              FROM multimedia_pelicula 
              WHERE proposito = 'poster' 
              GROUP BY pelicula_id
          ) AS mp ON p.id = mp.pelicula_id
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          LEFT JOIN funciones f ON p.id = f.pelicula_id
          LEFT JOIN salas s ON f.sala_id = s.id
          WHERE p.estado IN ('estreno', 'regular') 
          AND p.deleted_at IS NULL";

// Condición para cine específico o todos los cines
if ($cineParaConsulta !== null) {
    $query .= " AND (s.cine_id = ? OR (s.id IS NULL AND f.id IS NULL))";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $cineParaConsulta);
} else {
    $stmt = $conn->prepare($query);
}

$query .= " GROUP BY p.id, p.titulo, p.duracion_min, p.estado, c.codigo, m.url
            ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
            LIMIT 6";

$stmt->execute();
$result = $stmt->get_result();

$peliculasCartelera = [];
while ($row = $result->fetch_assoc()) {
    $peliculasCartelera[] = $row;
}
$stmt->close();

// Obtener eventos especiales
$query = "SELECT e.id, e.nombre, e.fecha_inicio, m.url as imagen_url
          FROM eventos_especiales e
          LEFT JOIN multimedia m ON e.imagen_id = m.id";

if ($cineParaConsulta !== null) {
    $query .= " WHERE e.cine_id = ? 
                AND e.fecha_fin >= NOW()
                AND e.deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $cineParaConsulta);
} else {
    $query .= " WHERE e.fecha_fin >= NOW()
                AND e.deleted_at IS NULL";
    $stmt = $conn->prepare($query);
}

$query .= " ORDER BY e.fecha_inicio ASC
            LIMIT 4";

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
$stmt->close();

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
    $stmt->close();
    
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
}

// Incluir header
require_once 'includes/header.php';
?>

<!-- Hero Banner Principal con Película Destacada -->
<div class="hero-banner">
    <img src="<?php echo $peliculaDestacada['banner_url'] ?? 'assets/img/banner-default.jpg'; ?>" alt="<?php echo $peliculaDestacada['titulo']; ?>">
    <div class="content-wrapper">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="title-wrapper">
                        <img src="assets/img/thunderbolts-title.jpg" alt="Thunderbolts" class="movie-title-img">
                    </div>
                    
                    <div class="hero-buttons">
                        <a href="reserva.php?pelicula=<?php echo $peliculaDestacada['id']; ?>" class="btn btn-warning btn-lg">
                            <i class="fas fa-ticket-alt"></i> Comprar ahora
                        </a>
                        <button class="btn btn-outline-light btn-lg ml-2" onclick="agregarFavorito(<?php echo $peliculaDestacada['id']; ?>)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="format-icons mt-3 d-flex justify-content-between" style="max-width: 200px;">
                        <i class="fas fa-film"></i>
                        <i class="fas fa-volume-up"></i>
                        <i class="fas fa-closed-captioning"></i>
                        <i class="fas fa-glasses"></i>
                        <i class="fas fa-wheelchair"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir CSS de cartelera-home -->
<link rel="stylesheet" href="assets/css/cartelera-home.css">

<!-- Selector de Cine -->
<div class="carhome-cinema-selector">
    <div class="container">
        <div class="carhome-selector-wrapper">
            <span class="carhome-donde">¿Dónde?</span>
            <select class="carhome-form-control" id="cineSelectorHome" onchange="cambiarCine(this.value)">
                <option value="0" <?php echo ($cineSeleccionado == 0) ? 'selected' : ''; ?>>Todos los cines</option>
                <?php
                // Obtener lista de cines desde la BD
                $queryCines = "SELECT id, nombre FROM cines WHERE activo = 1 ORDER BY nombre";
                $resultCines = $conn->query($queryCines);
                
                if ($resultCines && $resultCines->num_rows > 0) {
                    while ($cine = $resultCines->fetch_assoc()) {
                        $selected = $cineSeleccionado == $cine['id'] ? 'selected' : '';
                        echo "<option value='{$cine['id']}' {$selected}>{$cine['nombre']}</option>";
                    }
                }
                ?>
            </select>
        </div>
    </div>
</div>

<!-- Sección Cartelera Actual -->
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

<!-- Sección Eventos Especiales -->
<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Especiales y Eventos en <span id="nombreCineEventos"><?php echo $nombreCine; ?></span></h2>
            <a href="eventos.php" class="carhome-link-ver">Ver todos</a>
        </div>
        
        <?php if (!empty($eventosEspeciales)): ?>
            <div class="carhome-slider-container">
                <?php foreach ($eventosEspeciales as $evento): ?>
                    <div class="carhome-movie-card">
                        <a href="evento.php?id=<?php echo $evento['id']; ?>" class="carhome-movie-link">
                            <div class="carhome-poster-container">
                                <img src="<?php echo $evento['imagen_url'] ?? 'assets/img/evento-default.jpg'; ?>" alt="<?php echo $evento['nombre']; ?>" class="carhome-poster-img">
                                <?php if (!empty($evento['etiqueta'])): ?>
                                    <span class="carhome-badge-evento"><?php echo $evento['etiqueta']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="carhome-movie-info">
                                <h5 class="carhome-movie-title"><?php echo $evento['nombre']; ?></h5>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay eventos especiales en <?php echo $nombreCine; ?> en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección Noticias -->
<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Noticias</h2>
            <a href="noticias.php" class="carhome-link-ver">Mostrar todas las noticias</a>
        </div>
        
        <?php if (!empty($noticiasDestacadas)): ?>
            <div class="carhome-news-container">
                <?php foreach ($noticiasDestacadas as $noticia): ?>
                    <div class="carhome-news-card">
                        <a href="<?php echo $noticia['link']; ?>" class="carhome-news-link">
                            <img src="<?php echo $noticia['imagen_url']; ?>" class="carhome-news-img" alt="<?php echo $noticia['titulo']; ?>">
                            <div class="carhome-news-body">
                                <h5 class="carhome-news-title"><?php echo $noticia['titulo']; ?></h5>
                                <p class="carhome-news-text"><?php echo $noticia['resumen']; ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay noticias disponibles en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección Próximos Estrenos -->
<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Próximamente</h2>
            <a href="proximamente.php" class="carhome-link-ver">Ver todos</a>
        </div>
        
        <?php if (!empty($peliculasProximas)): ?>
            <div class="carhome-slider-container">
                <?php foreach ($peliculasProximas as $pelicula): ?>
                    <div class="carhome-movie-card">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="carhome-movie-link">
                            <div class="carhome-poster-container">
                                <img src="<?php echo obtenerPosterUrl($pelicula['id'], $pelicula['poster_url']); ?>" alt="<?php echo $pelicula['titulo']; ?>" class="carhome-poster-img">
                                <span class="carhome-badge-proximamente">PRÓXIMAMENTE</span>
                            </div>
                            <div class="carhome-movie-info">
                                <h5 class="carhome-movie-title"><?php echo $pelicula['titulo']; ?></h5>
                                <?php if (!empty($pelicula['clasificacion'])): ?>
                                    <span class="carhome-badge-clasificacion"><?php echo $pelicula['clasificacion']; ?></span>
                                <?php endif; ?>
                                <p class="carhome-movie-date">Estreno: <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay próximos estrenos programados en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Sección de Ayuda -->
<section class="help-section py-5 carhome-preguntas-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="carhome-preguntas-title">¿Tienes alguna pregunta?</h2>
                <p class="carhome-preguntas-text">Visita nuestro servicio de atención al cliente de WhatsApp</p>
                <a href="https://wa.me/59170000000" class="btn btn-success carhome-whatsapp-btn">
                    <i class="fab fa-whatsapp"></i> Contactar por WhatsApp
                </a>
            </div>
            <div class="col-md-6">
                <div class="card carhome-preguntas-right">
                    <div class="card-header carhome-faq-header">
                        <h5 class="mb-0 carhome-faq-title">Preguntas frecuentes</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion carhome-faq-items" id="faqAccordion">
                            <div class="card carhome-faq-card">
                                <div class="card-header carhome-faq-item" id="headingOne">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link carhome-faq-link" type="button" data-toggle="collapse" data-target="#collapseOne">
                                            ¿Cómo compro entradas en línea?
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseOne" class="card-collapse" aria-labelledby="headingOne" data-parent="#faqAccordion">
                                    <div class="card-body carhome-faq-body">
                                        Selecciona la película, función y asientos que deseas, luego procede al pago con tus métodos preferidos.
                                    </div>
                                </div>
                            </div>
                            <div class="card carhome-faq-card">
                                <div class="card-header carhome-faq-item" id="headingTwo">
                                    <h2 class="mb-0">
                                        <button class="btn btn-link collapsed carhome-faq-link" type="button" data-toggle="collapse" data-target="#collapseTwo">
                                            ¿Cómo funciona MultiPass?
                                        </button>
                                    </h2>
                                </div>
                                <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#faqAccordion">
                                    <div class="card-body carhome-faq-body">
                                        MultiPass es nuestra suscripción mensual que te permite obtener entradas gratis y descuentos exclusivos.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <a href="faq.php" class="btn btn-link mt-3 carhome-ver-mas">Ver más preguntas</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.navbar {
    background-color: rgba(0,0,0,0.5); 
    position: absolute;
    width: 100%;
    z-index: 1000;
}

.navbar.scrolled {
    background-color: black; 
    position: fixed;
    top: 0;
    transition: background-color 0.3s ease;
}

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

.movie-card {
    transition: transform 0.3s ease;
}

.movie-card:hover {
    transform: translateY(-5px);
}
</style>

<script>
$(window).scroll(function() {
    if ($(this).scrollTop() > 100) {
        $('.navbar').addClass('scrolled');
    } else {
        $('.navbar').removeClass('scrolled');
    }
});

function cambiarCine(cineId) {
    window.location.href = 'index.php?cine=' + cineId;
}

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
        window.location.href = 'auth/login.php';
    <?php endif; ?>
}
</script>

<?php require_once 'includes/footer.php'; ?>