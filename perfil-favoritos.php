<?php
require_once 'includes/functions.php';
iniciarSesion();

if (!estaLogueado()) {
    setMensaje('Debes iniciar sesión para acceder a tus favoritos', 'warning');
    redirect('/multicinev3/auth/login.php');
}

$conn = require 'config/database.php';
$userId = $_SESSION['user_id'];

// Procesar eliminación de favorito
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $favoritoId = $_GET['eliminar'];
    
    $sql = "DELETE FROM favoritos WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $favoritoId, $userId);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        setMensaje('Película eliminada de favoritos', 'success');
    }
    
    redirect('/multicinev3/perfil-favoritos.php');
}

// Obtener películas favoritas
$sql = "SELECT f.id, f.fecha_agregado, p.id as pelicula_id, p.titulo, p.estado,
               p.fecha_estreno, c.codigo as clasificacion,
               m.url as poster_url, GROUP_CONCAT(g.nombre) as generos
        FROM favoritos f
        JOIN peliculas p ON f.pelicula_id = p.id
        LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
        LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
        LEFT JOIN multimedia m ON mp.multimedia_id = m.id
        LEFT JOIN genero_pelicula gp ON p.id = gp.pelicula_id
        LEFT JOIN generos g ON gp.genero_id = g.id
        WHERE f.user_id = ?
        GROUP BY f.id
        ORDER BY f.fecha_agregado DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$favoritos = $result->fetch_all(MYSQLI_ASSOC);

require_once 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Mi Perfil</h4>
                </div>
                <div class="card-body text-center">
                    <img src="assets/img/usuario-default.jpg" class="rounded-circle mb-3" width="150" height="150" alt="Foto de perfil">
                    <h4><?php echo $_SESSION['nombres'] . ' ' . $_SESSION['apellidos']; ?></h4>
                    <p class="text-muted"><?php echo $_SESSION['email']; ?></p>
                </div>
                <div class="list-group list-group-flush">
                    <a href="perfil.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user"></i> Información Personal
                    </a>
                    <a href="perfil.php#seguridad" class="list-group-item list-group-item-action">
                        <i class="fas fa-lock"></i> Seguridad
                    </a>
                    <a href="perfil-reservas.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt"></i> Mis Reservas
                    </a>
                    <a href="perfil-favoritos.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-heart"></i> Mis Favoritos
                    </a>
                    <?php if (isset($_SESSION['multipass_active']) && $_SESSION['multipass_active']): ?>
                        <a href="multipass.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-id-card"></i> Mi MultiPass
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mis Películas Favoritas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($favoritos)): ?>
                        <div class="text-center py-5">
                            <img src="assets/img/no-favorites.jpg" alt="No favorites" class="img-fluid mb-3" style="max-width: 200px;">
                            <h4>Aún no tienes películas favoritas</h4>
                            <p class="text-muted">Explora nuestra cartelera y añade tus películas favoritas para verlas aquí</p>
                            <a href="cartelera.php" class="btn btn-primary">
                                <i class="fas fa-film"></i> Explorar Cartelera
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($favoritos as $favorito): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="row no-gutters">
                                            <div class="col-md-4">
                                                <img src="<?php echo $favorito['poster_url'] ?: 'assets/img/no-poster.jpg'; ?>" 
                                                     class="card-img" alt="<?php echo htmlspecialchars($favorito['titulo']); ?>">
                                            </div>
                                            <div class="col-md-8">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($favorito['titulo']); ?></h5>
                                                    
                                                    <p class="card-text">
                                                        <small class="text-muted">
                                                            <?php echo $favorito['generos'] ?: 'Sin género especificado'; ?>
                                                        </small>
                                                    </p>
                                                    
                                                    <p class="card-text">
                                                        <?php
                                                        $badge_class = '';
                                                        switch ($favorito['estado']) {
                                                            case 'estreno': $badge_class = 'badge-danger'; break;
                                                            case 'preventa': $badge_class = 'badge-warning'; break;
                                                            case 'proximo': $badge_class = 'badge-info'; break;
                                                            case 'regular': $badge_class = 'badge-success'; break;
                                                            default: $badge_class = 'badge-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?>">
                                                            <?php echo ucfirst($favorito['estado']); ?>
                                                        </span>
                                                        
                                                        <?php if ($favorito['clasificacion']): ?>
                                                            <span class="badge badge-dark">
                                                                <?php echo $favorito['clasificacion']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </p>
                                                    
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="pelicula.php?id=<?php echo $favorito['pelicula_id']; ?>" 
                                                           class="btn btn-primary">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </a>
                                                        <a href="comprar_entradas.php?id=<?php echo $favorito['pelicula_id']; ?>" 
                                                           class="btn btn-success">
                                                            <i class="fas fa-ticket-alt"></i> Comprar
                                                        </a>
                                                        <a href="perfil-favoritos.php?eliminar=<?php echo $favorito['id']; ?>" 
                                                           class="btn btn-danger" 
                                                           onclick="return confirm('¿Estás seguro de eliminar esta película de favoritos?');">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </div>
                                                    
                                                    <p class="card-text mt-2">
                                                        <small class="text-muted">
                                                            Agregado el: <?php echo date('d/m/Y', strtotime($favorito['fecha_agregado'])); ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>