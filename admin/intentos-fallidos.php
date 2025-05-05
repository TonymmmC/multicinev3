<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Filtros
$filtroEmail = isset($_GET['email']) ? sanitizeInput($_GET['email']) : '';
$filtroFecha = isset($_GET['fecha']) ? sanitizeInput($_GET['fecha']) : '';

// Construir la consulta base
$query = "SELECT * FROM login_attempts WHERE 1=1";

// Aplicar filtros
$params = [];
$paramTypes = "";

if (!empty($filtroEmail)) {
    $query .= " AND email LIKE ?";
    $params[] = "%$filtroEmail%";
    $paramTypes .= "s";
}

if (!empty($filtroFecha)) {
    $query .= " AND DATE(attempted_at) = ?";
    $params[] = $filtroFecha;
    $paramTypes .= "s";
}

// Ordenar por fecha descendente
$query .= " ORDER BY attempted_at DESC LIMIT 1000";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$intentos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $intentos[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Intentos de Login Fallidos</h1>
            <a href="logs-acceso.php" class="btn btn-secondary">
                <i class="fas fa-user-clock"></i> Ver Logs de Acceso
            </a>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row">
                    <div class="col-md-5 mb-3">
                        <label for="email">Email</label>
                        <input type="text" class="form-control" id="email" name="email" 
                               value="<?php echo $filtroEmail; ?>" placeholder="Buscar por email...">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="fecha">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo $filtroFecha; ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="intentos-fallidos.php" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Dirección IP</th>
                                <th>Fecha y Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($intentos as $intento): ?>
                                <tr>
                                    <td><?php echo $intento['id']; ?></td>
                                    <td><?php echo $intento['email']; ?></td>
                                    <td><?php echo $intento['ip_address']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($intento['attempted_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($intentos)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No se encontraron registros</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>