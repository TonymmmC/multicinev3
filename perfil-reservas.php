<?php
require_once 'includes/functions.php';
iniciarSesion();

if (!estaLogueado()) {
    setMensaje('Debes iniciar sesión para acceder a tus reservas', 'warning');
    redirect('/multicinev3/auth/login.php');
}

$conn = require 'config/database.php';
$userId = $_SESSION['user_id'];

$sql = "SELECT r.id, r.fecha_reserva, r.total_pagado, r.estado, 
               p.titulo as pelicula, f.fecha_hora, 
               c.nombre as cine, s.nombre as sala
        FROM reservas r
        JOIN funciones f ON r.funcion_id = f.id
        JOIN peliculas p ON f.pelicula_id = p.id
        JOIN salas s ON f.sala_id = s.id
        JOIN cines c ON s.cine_id = c.id
        WHERE r.user_id = ? 
        ORDER BY r.fecha_reserva DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$reservas = $result->fetch_all(MYSQLI_ASSOC);

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
                    <a href="perfil-reservas.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-ticket-alt"></i> Mis Reservas
                    </a>
                    <a href="perfil-favoritos.php" class="list-group-item list-group-item-action">
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
                    <h5 class="mb-0">Mis Reservas</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservas)): ?>
                        <div class="text-center py-5">
                            <img src="assets/img/no-tickets.jpg" alt="No reservations" class="img-fluid mb-3" style="max-width: 200px;">
                            <h4>Aún no tienes reservas</h4>
                            <p class="text-muted">¿Qué tal si exploras nuestra cartelera y reservas tu primera función?</p>
                            <a href="cartelera.php" class="btn btn-primary">
                                <i class="fas fa-film"></i> Ver Cartelera
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Película</th>
                                        <th>Fecha y Hora</th>
                                        <th>Cine / Sala</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservas as $reserva): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reserva['pelicula']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reserva['fecha_hora'])); ?></td>
                                            <td><?php echo htmlspecialchars($reserva['cine'] . ' / ' . $reserva['sala']); ?></td>
                                            <td>Bs. <?php echo number_format($reserva['total_pagado'], 2); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = '';
                                                switch ($reserva['estado']) {
                                                    case 'pendiente': $badge_class = 'badge-warning'; break;
                                                    case 'aprobado': $badge_class = 'badge-success'; break;
                                                    case 'rechazado': $badge_class = 'badge-danger'; break;
                                                    case 'reembolsado': $badge_class = 'badge-info'; break;
                                                    case 'vencido': $badge_class = 'badge-secondary'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($reserva['estado']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="perfil-detalle-reserva.php?id=<?php echo $reserva['id']; ?>" 
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>