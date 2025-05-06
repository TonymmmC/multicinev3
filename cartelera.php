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
$orden = isset($_GET['orden']) ? sanitizeInput($_GET['orden']) : 'estreno';

// Construir la consulta base para contar total de registros
$queryTotal = "SELECT COUNT(DISTINCT p.id) as total
          FROM peliculas p";

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

// Incluir header
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/cartelera.css">

<!-- Cabecera de cartelera -->
<div class="carte-header">
    <div class="container">
        <h1 class="carte-title">Cartelera</h1>
        <p class="carte-subtitle">Descubre las películas en cartelera en Multicine</p>
    </div>
</div>

<div class="container carte-container">
    <!-- Sección de filtros -->
    <div class="carte-filters">
        <h5 class="mb-3">Filtros</h5>
        <form action="" method="get" id="formFiltros">
            <!-- Mantener valor de página si existe -->
            <?php if (isset($_GET['pagina'])): ?>
                <input type="hidden" name="pagina" value="1">
            <?php endif; ?>
            
            <div class="carte-filters-row">
                <div class="carte-filter-group">
                    <label for="genero" class="carte-filter-label">Género</label>
                    <select class="carte-filter-select" id="genero" name="genero">
                        <option value="">Todos los géneros</option>
                        <?php foreach ($generos as $genero): ?>
                            <option value="<?php echo $genero['id']; ?>" <?php echo $generoId == $genero['id'] ? 'selected' : ''; ?>>
                                <?php echo $genero['nombre']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="carte-filter-group">
                    <label for="cine" class="carte-filter-label">Cine</label>
                    <select class="carte-filter-select" id="cine" name="cine">
                        <option value="">Todos los cines</option>
                        <?php foreach ($cines as $cine): ?>
                            <option value="<?php echo $cine['id']; ?>" <?php echo $cineId == $cine['id'] ? 'selected' : ''; ?>>
                                <?php echo $cine['nombre']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="carte-filter-group">
                    <label for="formato" class="carte-filter-label">Formato</label>
                    <select class="carte-filter-select" id="formato" name="formato">
                        <option value="">Todos los formatos</option>
                        <?php foreach ($formatos as $formato): ?>
                            <option value="<?php echo $formato['id']; ?>" <?php echo $formatoId == $formato['id'] ? 'selected' : ''; ?>>
                                <?php echo $formato['nombre']; ?> 
                                <?php if ($formato['recargo'] > 0): ?>
                                    (+Bs. <?php echo number_format($formato['recargo'], 2); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="carte-filter-group">
                    <label for="orden" class="carte-filter-label">Ordenar por</label>
                    <select class="carte-filter-select" id="orden" name="orden">
                        <option value="estreno" <?php echo $orden == 'estreno' ? 'selected' : ''; ?>>Fecha de estreno</option>
                        <option value="titulo" <?php echo $orden == 'titulo' ? 'selected' : ''; ?>>Título</option>
                        <option value="duracion" <?php echo $orden == 'duracion' ? 'selected' : ''; ?>>Duración</option>
                    </select>
                </div>
            </div>
            
            <div class="carte-filters-buttons">
                <button type="submit" class="carte-btn-apply">Aplicar filtros</button>
                <a href="cartelera.php" class="carte-btn-clear">Limpiar filtros</a>
            </div>
        </form>
    </div>

    <!-- Mostrar resultados y contador -->
    <div class="carte-movies-header">
        <h2 class="carte-movies-title">Películas en cartelera</h2>
        <span class="carte-movies-count">Mostrando <?php echo count($peliculas); ?> de <?php echo $totalPeliculas; ?> películas</span>
    </div>

    <!-- Grid de películas -->
    <?php if (!empty($peliculas)): ?>
        <div class="carte-movies-grid">
            <?php foreach ($peliculas as $pelicula): ?>
                <div class="carte-movie-card">
                    <div class="position-relative">
                        <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                             class="carte-movie-poster" alt="<?php echo $pelicula['titulo']; ?>">
                        
                        <?php if ($pelicula['estado'] == 'estreno'): ?>
                            <div class="badge badge-danger position-absolute" style="top: 10px; right: 10px;">ESTRENO</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="carte-movie-info">
                        <h5 class="carte-movie-title"><?php echo $pelicula['titulo']; ?></h5>
                        
                        <?php if (!empty($pelicula['titulo_original']) && $pelicula['titulo_original'] != $pelicula['titulo']): ?>
                            <p class="carte-movie-original-title"><?php echo $pelicula['titulo_original']; ?></p>
                        <?php endif; ?>
                        
                        <div class="carte-movie-badges">
                            <?php if ($pelicula['clasificacion']): ?>
                                <span class="carte-badge carte-badge-rating"><?php echo $pelicula['clasificacion']; ?></span>
                            <?php endif; ?>
                            <span class="carte-badge carte-badge-duration"><?php echo $pelicula['duracion_min']; ?> min</span>
                        </div>
                        
                        <?php if (!empty($pelicula['generos'])): ?>
                            <div class="carte-movie-genres">
                                <?php foreach ($pelicula['generos'] as $genero): ?>
                                    <span class="carte-genre-tag"><?php echo $genero['nombre']; ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($pelicula['valoracion']['promedio'] > 0): ?>
                            <div class="carte-rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($pelicula['valoracion']['promedio'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="carte-rating-count">(<?php echo $pelicula['valoracion']['total']; ?>)</span>
                            </div>
                        <?php endif; ?>
                        
                        <p class="carte-movie-release">
                            <strong>Estreno:</strong> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?>
                        </p>
                    </div>
                    
                    <div class="carte-movie-actions">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="carte-btn-details">Ver detalles</a>
                        <a href="reserva.php?pelicula=<?php echo $pelicula['id']; ?>" class="carte-btn-reserve">Reservar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="carte-no-results">
            No se encontraron películas con los filtros seleccionados.
        </div>
    <?php endif; ?>

    <!-- Paginación mejorada -->
    <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Paginación de cartelera">
            <ul class="pagination carte-pagination">
                <?php 
                // Construir query string para mantener filtros
                $queryParams = [];
                if ($generoId) $queryParams['genero'] = $generoId;
                if ($cineId) $queryParams['cine'] = $cineId;
                if ($formatoId) $queryParams['formato'] = $formatoId;
                if ($orden) $queryParams['orden'] = $orden;
                
                // Función para generar URL con parámetros
                function generateUrl($page, $params) {
                    $params['pagina'] = $page;
                    return 'cartelera.php?' . http_build_query($params);
                }
                ?>
                
                <!-- Botón anterior -->
                <li class="page-item <?php echo ($paginaActual <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo ($paginaActual > 1) ? generateUrl($paginaActual - 1, $queryParams) : '#'; ?>" aria-label="Anterior">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <!-- Mostrar páginas -->
                <?php
                // Determinar rango de páginas a mostrar
                $rango = 2; // Mostrar 2 páginas antes y después de la actual
                $inicio = max(1, $paginaActual - $rango);
                $fin = min($totalPaginas, $paginaActual + $rango);
                
                // Si estamos cerca del inicio, mostrar más páginas al final
                if ($inicio <= $rango + 1) {
                    $fin = min($totalPaginas, $inicio + $rango * 2);
                }
                
                // Si estamos cerca del final, mostrar más páginas al inicio
                if ($fin >= $totalPaginas - $rango) {
                    $inicio = max(1, $fin - $rango * 2);
                }
                
                // Primera página
                if ($inicio > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . generateUrl(1, $queryParams) . '">1</a></li>';
                    if ($inicio > 2) {
                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                }
                
                // Mostrar enlaces de paginación
                for ($i = $inicio; $i <= $fin; $i++) {
                    echo '<li class="page-item ' . ($i == $paginaActual ? 'active' : '') . '">';
                    echo '<a class="page-link" href="' . generateUrl($i, $queryParams) . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                // Última página
                if ($fin < $totalPaginas) {
                    if ($fin < $totalPaginas - 1) {
                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="' . generateUrl($totalPaginas, $queryParams) . '">' . $totalPaginas . '</a></li>';
                }
                ?>
                
                <!-- Botón siguiente -->
                <li class="page-item <?php echo ($paginaActual >= $totalPaginas) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo ($paginaActual < $totalPaginas) ? generateUrl($paginaActual + 1, $queryParams) : '#'; ?>" aria-label="Siguiente">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Script para enviar el formulario al cambiar los filtros -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectElements = document.querySelectorAll('#genero, #cine, #formato, #orden');
    
    selectElements.forEach(function(select) {
        select.addEventListener('change', function() {
            // Reiniciar a la página 1 cuando se cambia un filtro
            const paginaInput = document.querySelector('input[name="pagina"]');
            if (paginaInput) {
                paginaInput.value = 1;
            }
            this.form.submit();
        });
    });
    
    // Para dispositivos móviles: toggle de filtros
    const mediaQuery = window.matchMedia('(max-width: 767.98px)');
    if (mediaQuery.matches) {
        const filtrosContent = document.querySelector('.carte-filters-row');
        const filtrosButtons = document.querySelector('.carte-filters-buttons');
        
        if (filtrosContent) {
            filtrosContent.style.display = 'none';
        }
        
        const filtrosTitle = document.querySelector('.carte-filters h5');
        if (filtrosTitle) {
            filtrosTitle.style.cursor = 'pointer';
            filtrosTitle.addEventListener('click', function() {
                if (filtrosContent.style.display === 'none') {
                    filtrosContent.style.display = 'flex';
                    filtrosButtons.style.display = 'flex';
                } else {
                    filtrosContent.style.display = 'none';
                    filtrosButtons.style.display = 'none';
                }
            });
            
            // Agregar icono de toggle
            filtrosTitle.innerHTML += ' <i class="fas fa-chevron-down"></i>';
        }
    }
});
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>