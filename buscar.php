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

<div class="mb-4">
    <h1>Buscar Películas</h1>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="get">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" placeholder="Buscar películas..." 
                               value="<?php echo $termino; ?>" required>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if (!empty($termino)): ?>
            <div class="mb-3">
                <h5>Resultados para: <span class="text-primary">"<?php echo $termino; ?>"</span></h5>
                <p>Se encontraron <?php echo count($resultados); ?> resultados</p>
            </div>
            
            <?php if (!empty($resultados)): ?>
                <div class="list-group">
                    <?php foreach ($resultados as $pelicula): ?>
                        <a href="pelicula.php?id=<?php echo $pelicula['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="row">
                                <div class="col-md-2">
                                    <img src="<?php echo $pelicula['poster_url'] ?? 'assets/img/poster-default.jpg'; ?>" 
                                         class="img-fluid rounded" alt="<?php echo $pelicula['titulo']; ?>">
                                </div>
                                <div class="col-md-10">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo $pelicula['titulo']; ?></h5>
                                        <small>
                                            <?php 
                                            switch ($pelicula['estado']) {
                                                case 'estreno':
                                                    echo '<span class="badge badge-success">Estreno</span>';
                                                    break;
                                                case 'regular':
                                                    echo '<span class="badge badge-info">En cartelera</span>';
                                                    break;
                                                case 'proximo':
                                                    echo '<span class="badge badge-warning">Próximamente</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge badge-secondary">Inactivo</span>';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        <strong>Duración:</strong> <?php echo $pelicula['duracion_min']; ?> min
                                        <strong class="ml-3">Estreno:</strong> <?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    No se encontraron películas que coincidan con tu búsqueda.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                Ingresa un término de búsqueda para encontrar películas.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>