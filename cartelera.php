<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Cargar controladores y modelos
require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener filtros
$generoId = isset($_GET['genero']) ? intval($_GET['genero']) : null;
$cineId = isset($_GET['cine']) ? intval($_GET['cine']) : null;
$formatoId = isset($_GET['formato']) ? intval($_GET['formato']) : null;
$orden = isset($_GET['orden']) ? sanitizeInput($_GET['orden']) : 'estreno';

// Construir la consulta base para películas
$queryPeliculas = "SELECT DISTINCT p.id, p.titulo, p.titulo_original, p.duracion_min, 
                p.fecha_estreno, p.estado,
                c.codigo as clasificacion, c.descripcion as clasificacion_desc
         FROM peliculas p
         LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id";

// Añadir condición para películas en cartelera
$queryPeliculas .= " WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL";

// Añadir filtros si existen
if ($generoId) {
    $queryPeliculas .= " AND p.id IN (SELECT pelicula_id FROM genero_pelicula WHERE genero_id = ?)";
}

if ($cineId) {
    $queryPeliculas .= " AND p.id IN (
                SELECT DISTINCT f.pelicula_id 
                FROM funciones f 
                JOIN salas s ON f.sala_id = s.id 
                WHERE s.cine_id = ? AND f.fecha_hora > NOW()
            )";
}

if ($formatoId) {
    $queryPeliculas .= " AND p.id IN (
                SELECT DISTINCT f.pelicula_id 
                FROM funciones f 
                WHERE f.formato_proyeccion_id = ? AND f.fecha_hora > NOW()
            )";
}

// Ordenar resultados
switch ($orden) {
    case 'titulo':
        $queryPeliculas .= " ORDER BY p.titulo ASC";
        break;
    case 'duracion':
        $queryPeliculas .= " ORDER BY p.duracion_min ASC";
        break;
    case 'estreno':
    default:
        $queryPeliculas .= " ORDER BY p.fecha_estreno DESC";
        break;
}

// Preparar y ejecutar la consulta
$stmtPeliculas = $conn->prepare($queryPeliculas);

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

if (!empty($paramTypes)) {
    $stmtPeliculas->bind_param($paramTypes, ...$paramValues);
}

$stmtPeliculas->execute();
$resultPeliculas = $stmtPeliculas->get_result();

$peliculas = [];
if ($resultPeliculas && $resultPeliculas->num_rows > 0) {
    while ($row = $resultPeliculas->fetch_assoc()) {
        // Obtener póster para cada película
        $queryPoster = "SELECT m.url 
                        FROM multimedia_pelicula mp 
                        JOIN multimedia m ON mp.multimedia_id = m.id 
                        WHERE mp.pelicula_id = ? AND mp.proposito = 'poster'
                        LIMIT 1";
        $stmtPoster = $conn->prepare($queryPoster);
        $stmtPoster->bind_param("i", $row['id']);
        $stmtPoster->execute();
        $resultPoster = $stmtPoster->get_result();
        
        if ($resultPoster && $resultPoster->num_rows > 0) {
            $poster = $resultPoster->fetch_assoc();
            $row['poster_url'] = $poster['url'];
        } else {
            $row['poster_url'] = 'assets/img/poster-default.jpg';
        }
        
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
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row">
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Aplicar filtros
                        </button>
                        <a href="cartelera.php" class="btn btn-outline-secondary ml-2">
                            <i class="fas fa-times"></i> Limpiar filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php if (!empty($peliculas)): ?>
        <?php foreach ($peliculas as $pelicula): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card h-100 shadow-sm film-card">
                    <div class="position-relative">
                        <img src="<?php echo $pelicula['poster_url']; ?>" 
                             class="card-img-top" alt="<?php echo $pelicula['titulo']; ?>">
                        <?php if ($pelicula['estado'] == 'estreno'): ?>
                            <span class="badge badge-danger position-absolute" style="top: 10px; right: 10px;">ESTRENO</span>
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
                            <strong>Estreno:</strong> <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent text-center">
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-info-circle"></i> Ver detalles
                        </a>
                        <a href="reserva.php?pelicula=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-ticket-alt"></i> Reservar
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No se encontraron películas con los filtros seleccionados.
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Paginación (para implementación futura) -->
<nav aria-label="Paginación de cartelera" class="mt-4">
    <ul class="pagination justify-content-center">
        <li class="page-item disabled">
            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Anterior</a>
        </li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">3</a></li>
        <li class="page-item">
            <a class="page-link" href="#">Siguiente</a>
        </li>
    </ul>
</nav>

<!-- Script para enviar el formulario al cambiar los filtros -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectElements = document.querySelectorAll('select[name="genero"], select[name="cine"], select[name="formato"], select[name="orden"]');
    
    selectElements.forEach(function(select) {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    
    // Efecto de hover para las tarjetas de películas
    const filmCards = document.querySelectorAll('.film-card');
    filmCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('shadow');
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('shadow');
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
.film-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.film-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.card-img-top {
    height: 350px;
    object-fit: cover;
}
@media (max-width: 768px) {
    .card-img-top {
        height: 250px;
    }
}
</style>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>