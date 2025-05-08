<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Configuración de paginación
$peliculasPorPagina = 12;
$paginaActual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $peliculasPorPagina;

// Obtener filtros
$generoId = isset($_GET['genero']) ? intval($_GET['genero']) : null;
$cineId = isset($_GET['cine']) ? intval($_GET['cine']) : null;
$formatoId = isset($_GET['formato']) ? intval($_GET['formato']) : null;
$idiomaId = isset($_GET['idioma']) ? sanitizeInput($_GET['idioma']) : null;
$tipoSesion = isset($_GET['tipo_sesion']) ? sanitizeInput($_GET['tipo_sesion']) : null;
$orden = isset($_GET['orden']) ? sanitizeInput($_GET['orden']) : 'estreno';

// Construir la consulta base para contar total de registros
$queryTotal = "SELECT COUNT(DISTINCT p.id) as total FROM peliculas p";

// Para el JOIN condicional con géneros
if ($generoId) {
    $queryTotal .= " JOIN genero_pelicula gp ON p.id = gp.pelicula_id";
}

// Para el JOIN condicional con cines
if ($cineId) {
    $queryTotal .= " JOIN funciones f ON p.id = f.pelicula_id
                     JOIN salas s ON f.sala_id = s.id";
}

// Para el JOIN condicional con formatos
if ($formatoId) {
    // Si ya hemos hecho JOIN con funciones, no lo hacemos de nuevo
    if (!$cineId) {
        $queryTotal .= " JOIN funciones f ON p.id = f.pelicula_id";
    }
}

// Para el JOIN condicional con idiomas
if ($idiomaId) {
    // Si ya hemos hecho JOIN con funciones, no lo hacemos de nuevo
    if (!$cineId && !$formatoId) {
        $queryTotal .= " JOIN funciones f ON p.id = f.pelicula_id";
    }
}

// Condiciones WHERE
$queryTotal .= " WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL";

// Añadir filtros específicos
if ($generoId) {
    $queryTotal .= " AND gp.genero_id = ?";
}

if ($cineId) {
    $queryTotal .= " AND s.cine_id = ? AND f.fecha_hora > NOW()";
}

if ($formatoId) {
    $queryTotal .= " AND f.formato_proyeccion_id = ? AND f.fecha_hora > NOW()";
}

if ($idiomaId) {
    $queryTotal .= " AND f.idioma_id = ? AND f.fecha_hora > NOW()";
}

// Preparar y ejecutar la consulta de conteo
$stmtTotal = $conn->prepare($queryTotal);

// Binding para conteo
$paramTypes = '';
$paramValues = [];

if ($generoId) {
    $paramTypes .= 'i';
    $paramValues[] = $generoId;
}

if ($cineId) {
    $paramTypes .= 'i';
    $paramValues[] = $cineId;
}

if ($formatoId) {
    $paramTypes .= 'i';
    $paramValues[] = $formatoId;
}

if ($idiomaId) {
    $paramTypes .= 'i';
    $paramValues[] = $idiomaId;
}

if (!empty($paramTypes)) {
    $stmtTotal->bind_param($paramTypes, ...$paramValues);
}

$stmtTotal->execute();
$resultTotal = $stmtTotal->get_result();
$rowTotal = $resultTotal->fetch_assoc();
$totalPeliculas = $rowTotal['total'];

// Calcular número total de páginas
$totalPaginas = ceil($totalPeliculas / $peliculasPorPagina);
if ($paginaActual > $totalPaginas && $totalPaginas > 0) {
    $paginaActual = $totalPaginas;
    $offset = ($paginaActual - 1) * $peliculasPorPagina;
}

// Construir la consulta para obtener peliculas de la página actual
$query = "SELECT DISTINCT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, p.estado,
                 c.codigo as clasificacion, c.descripcion as clasificacion_desc,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id";

// Añadir JOINs condicionales
if ($generoId) {
    $query .= " JOIN genero_pelicula gp ON p.id = gp.pelicula_id";
}

if ($cineId) {
    $query .= " JOIN funciones f ON p.id = f.pelicula_id
                JOIN salas s ON f.sala_id = s.id";
}

if ($formatoId && !$cineId) {
    $query .= " JOIN funciones f ON p.id = f.pelicula_id";
}

if ($idiomaId && !$cineId && !$formatoId) {
    $query .= " JOIN funciones f ON p.id = f.pelicula_id";
}

// Añadir condición para películas en cartelera
$query .= " WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL";

// Añadir filtros si existen
if ($generoId) {
    $query .= " AND gp.genero_id = ?";
}

if ($cineId) {
    $query .= " AND s.cine_id = ? AND f.fecha_hora > NOW()";
}

if ($formatoId) {
    $query .= " AND f.formato_proyeccion_id = ? AND f.fecha_hora > NOW()";
}

if ($idiomaId) {
    $query .= " AND f.idioma_id = ? AND f.fecha_hora > NOW()";
}

// Ordenar resultados
switch ($orden) {
    case 'titulo':
        $query .= " ORDER BY p.titulo ASC";
        break;
    case 'duracion':
        $query .= " ORDER BY p.duracion_min ASC";
        break;
    case 'estreno':
    default:
        $query .= " ORDER BY p.fecha_estreno DESC";
        break;
}

// Añadir limitación para paginación
$query .= " LIMIT ?, ?";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);

// Bind de parámetros si hay filtros
$paramTypes = '';
$paramValues = [];

if ($generoId) {
    $paramTypes .= 'i';
    $paramValues[] = $generoId;
}

if ($cineId) {
    $paramTypes .= 'i';
    $paramValues[] = $cineId;
}

if ($formatoId) {
    $paramTypes .= 'i';
    $paramValues[] = $formatoId;
}

if ($idiomaId) {
    $paramTypes .= 'i';
    $paramValues[] = $idiomaId;
}

// Añadir parámetros de paginación
$paramTypes .= 'ii';
$paramValues[] = $offset;
$paramValues[] = $peliculasPorPagina;

if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$paramValues);
}

$stmt->execute();
$result = $stmt->get_result();

$peliculas = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Obtener géneros para cada película
        $queryGeneros = "SELECT g.id, g.nombre
                  FROM genero_pelicula gp
                  JOIN generos g ON gp.genero_id = g.id
                  WHERE gp.pelicula_id = ?";
                  
        $stmtGeneros = $conn->prepare($queryGeneros);
        $stmtGeneros->bind_param("i", $row['id']);
        $stmtGeneros->execute();
        $resultGeneros = $stmtGeneros->get_result();
        
        $generos = [];
        while ($genero = $resultGeneros->fetch_assoc()) {
            $generos[] = $genero;
        }
        
        $row['generos'] = $generos;
        
        // Obtener valoración promedio
        $queryValoracion = "SELECT AVG(puntuacion) as promedio, COUNT(*) as total
                           FROM valoraciones 
                           WHERE pelicula_id = ?";
        $stmtValoracion = $conn->prepare($queryValoracion);
        $stmtValoracion->bind_param("i", $row['id']);
        $stmtValoracion->execute();
        $resultValoracion = $stmtValoracion->get_result();
        $valoracion = $resultValoracion->fetch_assoc();
        
        $row['valoracion'] = [
            'promedio' => $valoracion['promedio'] ? round($valoracion['promedio'], 1) : 0,
            'total' => $valoracion['total']
        ];
        
        $peliculas[] = $row;
    }
}

// Obtener lista de géneros para filtros
$queryGeneros = "SELECT id, nombre FROM generos ORDER BY nombre";
$resultGeneros = $conn->query($queryGeneros);
$generos = [];

if ($resultGeneros && $resultGeneros->num_rows > 0) {
    while ($row = $resultGeneros->fetch_assoc()) {
        $generos[] = $row;
    }
}

// Obtener lista de cines para filtros
$queryCines = "SELECT id, nombre, ciudad_id FROM cines WHERE activo = 1 ORDER BY nombre";
$resultCines = $conn->query($queryCines);
$cines = [];

if ($resultCines && $resultCines->num_rows > 0) {
    while ($row = $resultCines->fetch_assoc()) {
        $cines[] = $row;
    }
}

// Obtener lista de formatos para filtros
$queryFormatos = "SELECT id, nombre, recargo FROM formatos WHERE tipo = 'proyeccion' ORDER BY nombre";
$resultFormatos = $conn->query($queryFormatos);
$formatos = [];

if ($resultFormatos && $resultFormatos->num_rows > 0) {
    while ($row = $resultFormatos->fetch_assoc()) {
        $formatos[] = $row;
    }
}

// Obtener lista de idiomas para filtros
$queryIdiomas = "SELECT id, codigo, nombre FROM idiomas ORDER BY nombre";
$resultIdiomas = $conn->query($queryIdiomas);
$idiomas = [];

if ($resultIdiomas && $resultIdiomas->num_rows > 0) {
    while ($row = $resultIdiomas->fetch_assoc()) {
        $idiomas[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/cartelera-dark.css">
<link rel="stylesheet" href="assets/css/cartelera-home.css">
<!-- Contenedor principal -->
<div class="now-playing-container">
    <!-- Cabecera de Now Playing -->
    <div class="now-playing-header">
        <h1>En Cartelar Ahora</h1>
        
        <div class="cinema-selector-wrapper">
            <!-- Selector de cine -->
            <div class="cinema-dropdown">
                <button id="cinemaButton" class="cinema-select-btn">
                    <i class="fas fa-film"></i>
                    <?php echo isset($_GET['cine']) && isset($cines[$_GET['cine']]) ? 
                        $cines[$_GET['cine']]['nombre'] : 'Todos los cines'; ?>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div id="cinemaDropdown" class="cinema-dropdown-content">
                    <a href="cartelera.php" class="<?php echo !isset($_GET['cine']) ? 'active' : ''; ?>">
                        Todos los cines
                    </a>
                    <?php foreach ($cines as $cine): ?>
                        <a href="cartelera.php?cine=<?php echo $cine['id']; ?>" 
                           class="<?php echo isset($_GET['cine']) && $_GET['cine'] == $cine['id'] ? 'active' : ''; ?>">
                            <?php echo $cine['nombre']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="action-buttons">
                <button class="btn-favorite">
                    <i class="fas fa-heart"></i> <span>My cinema</span>
                </button>
                <button class="btn-filters" id="toggleFilters">
                    <i class="fas fa-sliders-h"></i> <span>Filters</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Contenedor principal de contenido -->
    <div class="main-content">
        <!-- Panel de filtros -->
        <div class="filters-panel" id="filtersPanel">
            <!-- Idiomas -->
            <div class="filter-section">
                <h3>LANGUAGES</h3>
                <div class="filter-options">
                    <?php foreach ($idiomas as $idioma): ?>
                        <a href="cartelera.php?idioma=<?php echo $idioma['id']; ?>" 
                           class="filter-option <?php echo $idiomaId == $idioma['id'] ? 'active' : ''; ?>">
                            <?php echo $idioma['codigo']; ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="cartelera.php?idioma=original" 
                       class="filter-option <?php echo $idiomaId == 'original' ? 'active' : ''; ?>">
                        ORIGINAL VERSION
                    </a>
                </div>
            </div>
            
            <!-- Tipo de sesión -->
            <div class="filter-section">
                <h3>TYPE OF SESSION</h3>
                <div class="filter-options">
                    <?php foreach ($formatos as $formato): ?>
                        <a href="cartelera.php?formato=<?php echo $formato['id']; ?>" 
                           class="filter-option <?php echo $formatoId == $formato['id'] ? 'active' : ''; ?>">
                            <?php echo strtoupper($formato['nombre']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Categorías especiales -->
            <div class="filter-section">
                <div class="filter-options">
                    <a href="cartelera.php?tipo=preview" 
                       class="filter-option <?php echo $tipoSesion == 'preview' ? 'active' : ''; ?>">
                        Preview
                    </a>
                    <a href="cartelera.php?tipo=event" 
                       class="filter-option <?php echo $tipoSesion == 'event' ? 'active' : ''; ?>">
                        Event
                    </a>
                    <a href="cartelera.php?tipo=family" 
                       class="filter-option <?php echo $tipoSesion == 'family' ? 'active' : ''; ?>">
                        Family
                    </a>
                </div>
            </div>
            
            <!-- Géneros -->
            <div class="filter-section">
                <h3>GENRES</h3>
                <div class="filter-options genres-grid">
                    <?php foreach ($generos as $genero): ?>
                        <a href="cartelera.php?genero=<?php echo $genero['id']; ?>" 
                           class="filter-option <?php echo $generoId == $genero['id'] ? 'active' : ''; ?>">
                            <?php echo $genero['nombre']; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Botones de acción para filtros -->
            <div class="filter-actions">
                <button id="showResults" class="btn-show-results">
                    Show <?php echo $totalPeliculas; ?> results
                </button>
                <a href="cartelera.php" class="btn-reset-filters">
                    Reset filters
                </a>
            </div>
        </div>
        
        <!-- Grid de películas -->
        <div class="movies-grid">
            <?php if (!empty($peliculas)): ?>
                <?php foreach ($peliculas as $pelicula): ?>
                    <div class="movie-card">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>">
                            <div class="movie-poster">
                                <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                                     alt="<?php echo $pelicula['titulo']; ?>">
                                
                                <?php if ($pelicula['estado'] == 'estreno'): ?>
                                    <span class="movie-badge new">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="movie-info">
                                <h3><?php echo $pelicula['titulo']; ?></h3>
                                <?php if ($pelicula['clasificacion']): ?>
                                    <span class="age-rating"><?php echo $pelicula['clasificacion']; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-results">
                    <p>No se encontraron películas con los filtros seleccionados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle para el dropdown de cines
    const cinemaButton = document.getElementById('cinemaButton');
    const cinemaDropdown = document.getElementById('cinemaDropdown');
    
    if (cinemaButton && cinemaDropdown) {
        cinemaButton.addEventListener('click', function() {
            cinemaDropdown.classList.toggle('show');
        });
        
        // Cerrar dropdown al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.cinema-select-btn') && 
                !event.target.closest('.cinema-select-btn')) {
                if (cinemaDropdown.classList.contains('show')) {
                    cinemaDropdown.classList.remove('show');
                }
            }
        });
    }
    
    // Toggle para el panel de filtros
    const toggleFilters = document.getElementById('toggleFilters');
    const filtersPanel = document.getElementById('filtersPanel');
    
    if (toggleFilters && filtersPanel) {
        toggleFilters.addEventListener('click', function() {
            filtersPanel.classList.toggle('show-filters');
        });
        
        // Cerrar panel al hacer clic en Show Results
        const showResults = document.getElementById('showResults');
        if (showResults) {
            showResults.addEventListener('click', function() {
                filtersPanel.classList.remove('show-filters');
                
                // Recolectar todos los filtros activos
                const activeFilters = document.querySelectorAll('.filter-option.active');
                let queryParams = new URLSearchParams();
                
                activeFilters.forEach(filter => {
                    // Extraer ID y tipo del filtro del href
                    const url = new URL(filter.href);
                    const params = new URLSearchParams(url.search);
                    
                    // Añadir a nuestros parámetros
                    for (const [key, value] of params.entries()) {
                        queryParams.append(key, value);
                    }
                });
                
                // Redirigir con todos los filtros
                window.location.href = 'cartelera.php?' + queryParams.toString();
            });
        }
    }
});
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>