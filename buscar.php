<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/Pelicula.php';
require_once 'models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

$termino = sanitizeInput($_GET['q'] ?? '');
$resultados = [];

if (!empty($termino)) {
    $resultados = $peliculaController->buscarPeliculas($termino);
    
    // Registrar búsqueda si el usuario está logueado
    if (estaLogueado()) {
        $userId = $_SESSION['user_id'];
        $cantidad = count($resultados);
        
        $query = "INSERT INTO historial_busqueda (user_id, termino, resultados) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $userId, $termino, $cantidad);
        $stmt->execute();
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<link href="assets/css/buscar.css" rel="stylesheet">

<div class="search-page-container">
    <div class="search-header">
        <h1 class="search-title">Descubre películas</h1>
        <p class="search-subtitle">Encuentra tus películas favoritas en nuestro catálogo</p>
    </div>

    <div class="search-form-container">
        <form action="" method="get" class="search-form">
            <div class="search-input-group">
                <input type="text" class="search-input" name="q" placeholder="Buscar por título, género o actor..." 
                       value="<?php echo $termino; ?>" required>
                <button class="search-button" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
    
    <?php if (!empty($termino)): ?>
        <div class="search-results-header">
            <h2 class="results-title">Resultados para: <span class="search-term">"<?php echo $termino; ?>"</span></h2>
            <p class="results-count"><?php echo count($resultados); ?> películas encontradas</p>
        </div>
        
        <?php if (!empty($resultados)): ?>
            <div class="search-results-grid">
                <?php foreach ($resultados as $pelicula): ?>
                    <div class="movie-card">
                        <div class="movie-poster">
                            <img src="assets/img/posters/<?php echo $pelicula['id']; ?>.jpg" onerror="this.src='assets/img/poster-default.jpg'" 
                                 alt="<?php echo $pelicula['titulo']; ?>">
                            <?php 
                            switch ($pelicula['estado']) {
                                case 'estreno':
                                    echo '<span class="movie-badge movie-badge-new">Estreno</span>';
                                    break;
                                case 'regular':
                                    echo '<span class="movie-badge movie-badge-playing">En cartelera</span>';
                                    break;
                                case 'proximo':
                                    echo '<span class="movie-badge movie-badge-soon">Próximamente</span>';
                                    break;
                                default:
                                    echo '<span class="movie-badge movie-badge-inactive">Inactivo</span>';
                            }
                            ?>
                        </div>
                        <div class="movie-info">
                            <h3 class="movie-title"><?php echo $pelicula['titulo']; ?></h3>
                            <div class="movie-meta">
                                <span class="movie-duration"><i class="far fa-clock"></i> <?php echo $pelicula['duracion_min']; ?> min</span>
                                <span class="movie-release"><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></span>
                            </div>
                            <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="movie-details-link">Ver detalles</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-film"></i>
                </div>
                <h3 class="no-results-title">No encontramos resultados</h3>
                <p class="no-results-text">Intenta con otros términos o revisa nuestra cartelera completa.</p>
                <a href="cartelera.php" class="no-results-link">Ver todas las películas</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="search-suggestions">
            <div class="suggestion-icon">
                <i class="fas fa-search"></i>
            </div>
            <h3 class="suggestion-title">¿Qué quieres ver hoy?</h3>
            <p class="suggestion-text">Ingresa el título de una película, un género o un actor para comenzar tu búsqueda.</p>
            <div class="suggestion-examples">
                <span class="suggestion-tag" onclick="document.querySelector('.search-input').value='acción'; document.querySelector('.search-form').submit();">Acción</span>
                <span class="suggestion-tag" onclick="document.querySelector('.search-input').value='comedia'; document.querySelector('.search-form').submit();">Comedia</span>
                <span class="suggestion-tag" onclick="document.querySelector('.search-input').value='2023'; document.querySelector('.search-form').submit();">Estrenos 2023</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>