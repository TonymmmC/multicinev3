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
$query = "SELECT DISTINCT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
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

<div class="jumbotron jumbotron-fluid bg-primary text-white mb-4">
    <div class="container">
        <h1 class="display-4">Cartelera</h1>
        <p class="lead">Descubre las películas en cartelera en Multicine</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Filtros</h5>
                <button class="btn btn-sm btn-outline-primary d-md-none" type="button" data-toggle="collapse" data-target="#filtrosCollapse">
                    <i class="fas fa-filter"></i> Mostrar filtros
                </button>
            </div>
            <div class="card-body collapse show" id="filtrosCollapse">
                <form action="" method="get" class="row" id="formFiltros">
                    <!-- Mantener valor de página si existe -->
                    <?php if (isset($_GET['pagina'])): ?>
                        <input type="hidden" name="pagina" value="1">
                    <?php endif; ?>
                    
                    <div class="col-md-3 mb-3">
                        <label for="genero">Género</label>
                        <select class="form-control" id="genero" name="genero">
                            <option value="">Todos los géneros</option>
                            <?php foreach ($generos as $genero): ?>
                                <option value="<?php echo $genero['id']; ?>" <?php echo $generoId == $genero['id'] ? 'selected' : ''; ?>>
                                    <?php echo $genero['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="cine">Cine</label>
                        <select class="form-control" id="cine" name="cine">
                            <option value="">Todos los cines</option>
                            <?php foreach ($cines as $cine): ?>
                                <option value="<?php echo $cine['id']; ?>" <?php echo $cineId == $cine['id'] ? 'selected' : ''; ?>>
                                    <?php echo $cine['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="formato">Formato</label>
                        <select class="form-control" id="formato" name="formato">
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
                    
                    <div class="col-md-3 mb-3">
                        <label for="orden">Ordenar por</label>
                        <select class="form-control" id="orden" name="orden">
                            <option value="estreno" <?php echo $orden == 'estreno' ? 'selected' : ''; ?>>Fecha de estreno</option>
                            <option value="titulo" <?php echo $orden == 'titulo' ? 'selected' : ''; ?>>Título</option>
                            <option value="duracion" <?php echo $orden == 'duracion' ? 'selected' : ''; ?>>Duración</option>
                        </select>
                    </div>
                    
                    <div class="col-md-12 text-center">
                        <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                        <a href="cartelera.php" class="btn btn-outline-secondary ml-2">Limpiar filtros</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Mostrar resultados y contador -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4>Películas en cartelera</h4>
    </div>
    <div>
        <p class="mb-0">Mostrando <?php echo count($peliculas); ?> de <?php echo $totalPeliculas; ?> películas</p>
    </div>
</div>

<div class="row">
    <?php if (!empty($peliculas)): ?>
        <?php foreach ($peliculas as $pelicula): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100">
                    <div class="position-relative">
                        <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                             class="card-img-top" alt="<?php echo $pelicula['titulo']; ?>">
                        <?php if ($pelicula['valoracion']['promedio'] > 0): ?>
                            <div class="position-absolute" style="top: 10px; right: 10px; background-color: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 20px;">
                                <i class="fas fa-star text-warning"></i> 
                                <?php echo $pelicula['valoracion']['promedio']; ?>/5
                                <small>(<?php echo $pelicula['valoracion']['total']; ?>)</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $pelicula['titulo']; ?></h5>
                        
                        <?php if (!empty($pelicula['titulo_original']) && $pelicula['titulo_original'] != $pelicula['titulo']): ?>
                            <p class="text-muted small"><?php echo $pelicula['titulo_original']; ?></p>
                        <?php endif; ?>
                        
                        <p class="card-text">
                            <span class="badge badge-info"><?php echo $pelicula['clasificacion'] ?? 'N/A'; ?></span>
                            <span class="badge badge-secondary"><?php echo $pelicula['duracion_min']; ?> min</span>
                        </p>
                        
                        <?php if (!empty($pelicula['generos'])): ?>
                            <p class="card-text small">
                                <?php foreach ($pelicula['generos'] as $genero): ?>
                                    <span class="badge badge-primary"><?php echo $genero['nombre']; ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                        
                        <p class="card-text small">
                            <strong>Estreno:</strong> <?php echo $peliculaController->formatearFecha($pelicula['fecha_estreno']); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary">Ver detalles</a>
                        <a href="reserva.php?pelicula=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-success">Reservar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                No se encontraron películas con los filtros seleccionados.
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Paginación mejorada -->
<?php if ($totalPaginas > 1): ?>
    <nav aria-label="Paginación de cartelera" class="mt-4">
        <ul class="pagination justify-content-center">
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

<!-- Script para enviar el formulario al cambiar los filtros -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectElements = document.querySelectorAll('select[name="genero"], select[name="cine"], select[name="formato"], select[name="orden"]');
    
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
        document.getElementById('filtrosCollapse').classList.remove('show');
    }
});
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>