<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Obtener estadísticas básicas
$estadisticas = [
    'peliculas' => 0,
    'funciones' => 0,
    'usuarios' => 0,
    'reservas' => 0
];

// Contar películas
$query = "SELECT COUNT(*) as total FROM peliculas WHERE deleted_at IS NULL";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $estadisticas['peliculas'] = $row['total'];
}

// Contar funciones futuras
$query = "SELECT COUNT(*) as total FROM funciones WHERE fecha_hora > NOW()";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $estadisticas['funciones'] = $row['total'];
}

// Contar usuarios
$query = "SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $estadisticas['usuarios'] = $row['total'];
}

// Contar reservas
$query = "SELECT COUNT(*) as total FROM reservas WHERE deleted_at IS NULL";
$result = $conn->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $estadisticas['reservas'] = $row['total'];
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <h1 class="mb-4">Panel de Administración</h1>
        
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Películas</h5>
                                <h2 class="mb-0"><?php echo $estadisticas['peliculas']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-film fa-3x"></i>
                            </div>
                        </div>
                        <p class="card-text mt-3">
                            <a href="peliculas.php" class="text-white">Administrar películas <i class="fas fa-arrow-right"></i></a>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Funciones</h5>
                                <h2 class="mb-0"><?php echo $estadisticas['funciones']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-calendar-alt fa-3x"></i>
                            </div>
                        </div>
                        <p class="card-text mt-3">
                            <a href="funciones.php" class="text-white">Administrar funciones <i class="fas fa-arrow-right"></i></a>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Usuarios</h5>
                                <h2 class="mb-0"><?php echo $estadisticas['usuarios']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-users fa-3x"></i>
                            </div>
                        </div>
                        <p class="card-text mt-3">
                            <a href="usuarios.php" class="text-white">Administrar usuarios <i class="fas fa-arrow-right"></i></a>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Reservas</h5>
                                <h2 class="mb-0"><?php echo $estadisticas['reservas']; ?></h2>
                            </div>
                            <div>
                                <i class="fas fa-ticket-alt fa-3x"></i>
                            </div>
                        </div>
                        <p class="card-text mt-3">
                            <a href="reservas.php" class="text-white">Ver reservas <i class="fas fa-arrow-right"></i></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Acciones rápidas</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="pelicula-form.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle mr-2"></i> Añadir nueva película
                            </a>
                            <a href="funcion-form.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle mr-2"></i> Programar nueva función
                            </a>
                            <a href="promocion-form.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus-circle mr-2"></i> Crear nueva promoción
                            </a>
                            <a href="reportes.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-chart-bar mr-2"></i> Ver reportes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Últimas actividades</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Usuario</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Obtener últimas actividades del log
                                    $query = "SELECT a.created_at, a.accion, a.tabla_afectada, 
                                                    u.email as usuario 
                                            FROM auditoria_sistema a
                                            LEFT JOIN users u ON a.user_id = u.id
                                            ORDER BY a.created_at DESC
                                            LIMIT 5";
                                    $result = $conn->query($query);
                                    
                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo '<tr>';
                                            echo '<td>' . date('d/m/Y H:i', strtotime($row['created_at'])) . '</td>';
                                            echo '<td>' . $row['usuario'] . '</td>';
                                            echo '<td>' . $row['accion'] . ' en ' . $row['tabla_afectada'] . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center">No hay actividades recientes</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Añadir en la sección de estadísticas -->
<div class="row mt-4">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Estadísticas de Acceso</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php
                    // Contar logins exitosos
                    $query = "SELECT COUNT(*) as total FROM logs_acceso WHERE tipo_acceso = 'LOGIN'";
                    $result = $conn->query($query);
                    $loginsExitosos = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
                    
                    // Contar intentos fallidos
                    $query = "SELECT COUNT(*) as total FROM login_attempts";
                    $result = $conn->query($query);
                    $intentosFallidos = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
                    
                    // Logins de hoy
                    $query = "SELECT COUNT(*) as total FROM logs_acceso 
                              WHERE tipo_acceso = 'LOGIN' AND DATE(created_at) = CURDATE()";
                    $result = $conn->query($query);
                    $loginsHoy = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
                    
                    // Intentos fallidos de hoy
                    $query = "SELECT COUNT(*) as total FROM login_attempts 
                              WHERE DATE(attempted_at) = CURDATE()";
                    $result = $conn->query($query);
                    $intentosFallidosHoy = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;
                    ?>
                    
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5>Logins Exitosos</h5>
                                <h2><?php echo $loginsExitosos; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h5>Intentos Fallidos</h5>
                                <h2><?php echo $intentosFallidos; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5>Logins Hoy</h5>
                                <h2><?php echo $loginsHoy; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h5>Intentos Fallidos Hoy</h5>
                                <h2><?php echo $intentosFallidosHoy; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="logs-acceso.php" class="btn btn-primary mr-2">
                        <i class="fas fa-user-clock"></i> Ver Logs de Acceso
                    </a>
                    <a href="intentos-fallidos.php" class="btn btn-secondary">
                        <i class="fas fa-user-shield"></i> Ver Intentos Fallidos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>