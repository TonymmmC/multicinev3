<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Filtros para los logs
$filtroUsuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$filtroTipo = isset($_GET['tipo']) ? sanitizeInput($_GET['tipo']) : '';
$filtroFecha = isset($_GET['fecha']) ? sanitizeInput($_GET['fecha']) : '';

// Construir la consulta base
$query = "SELECT l.id, l.tipo_acceso, l.ip_origen, l.dispositivo, l.user_agent, l.created_at,
                 u.email as usuario
          FROM logs_acceso l
          LEFT JOIN users u ON l.user_id = u.id
          WHERE 1=1";

// Aplicar filtros
$params = [];
$paramTypes = "";

if ($filtroUsuario > 0) {
    $query .= " AND l.user_id = ?";
    $params[] = $filtroUsuario;
    $paramTypes .= "i";
}

if (!empty($filtroTipo)) {
    $query .= " AND l.tipo_acceso = ?";
    $params[] = $filtroTipo;
    $paramTypes .= "s";
}

if (!empty($filtroFecha)) {
    $query .= " AND DATE(l.created_at) = ?";
    $params[] = $filtroFecha;
    $paramTypes .= "s";
}

// Ordenar por fecha descendente
$query .= " ORDER BY l.created_at DESC LIMIT 1000";

// Preparar y ejecutar la consulta
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Obtener usuarios para el filtro
$queryUsuarios = "SELECT id, email FROM users WHERE deleted_at IS NULL ORDER BY email";
$resultUsuarios = $conn->query($queryUsuarios);
$usuarios = [];

if ($resultUsuarios && $resultUsuarios->num_rows > 0) {
    while ($row = $resultUsuarios->fetch_assoc()) {
        $usuarios[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Logs de Acceso</h1>
            <a href="reportes.php" class="btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Ver Reportes
            </a>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Filtros</h5>
            </div>
            <div class="card-body">
                <form action="" method="get" class="row">
                    <div class="col-md-3 mb-3">
                        <label for="usuario">Usuario</label>
                        <select class="form-control" id="usuario" name="usuario">
                            <option value="0">Todos los usuarios</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo $usuario['id']; ?>" 
                                        <?php echo $filtroUsuario == $usuario['id'] ? 'selected' : ''; ?>>
                                    <?php echo $usuario['email']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="tipo">Tipo de Acceso</label>
                        <select class="form-control" id="tipo" name="tipo">
                            <option value="">Todos los tipos</option>
                            <option value="LOGIN" <?php echo $filtroTipo === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                            <option value="LOGOUT" <?php echo $filtroTipo === 'LOGOUT' ? 'selected' : ''; ?>>Logout</option>
                            <option value="FAILED_ATTEMPT" <?php echo $filtroTipo === 'FAILED_ATTEMPT' ? 'selected' : ''; ?>>Intento fallido</option>
                            <option value="TIMEOUT" <?php echo $filtroTipo === 'TIMEOUT' ? 'selected' : ''; ?>>Sesión expirada</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 mb-3">
                        <label for="fecha">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" value="<?php echo $filtroFecha; ?>">
                    </div>
                    
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="logs-acceso.php" class="btn btn-secondary">
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
                                <th>Fecha y Hora</th>
                                <th>Usuario</th>
                                <th>Tipo de Acceso</th>
                                <th>IP</th>
                                <th>Dispositivo</th>
                                <th>Navegador</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): 
                                $tipoClase = '';
                                switch ($log['tipo_acceso']) {
                                    case 'LOGIN':
                                        $tipoClase = 'success';
                                        break;
                                    case 'LOGOUT':
                                        $tipoClase = 'info';
                                        break;
                                    case 'FAILED_ATTEMPT':
                                        $tipoClase = 'danger';
                                        break;
                                    case 'TIMEOUT':
                                        $tipoClase = 'warning';
                                        break;
                                    default:
                                        $tipoClase = 'secondary';
                                }
                                
                                // Detectar navegador
                                $navegador = 'Desconocido';
                                $userAgent = $log['user_agent'];
                                
                                if (strpos($userAgent, 'Chrome') && strpos($userAgent, 'Edge') === false) {
                                    $navegador = 'Chrome';
                                } elseif (strpos($userAgent, 'Firefox')) {
                                    $navegador = 'Firefox';
                                } elseif (strpos($userAgent, 'Edge')) {
                                    $navegador = 'Edge';
                                } elseif (strpos($userAgent, 'Safari') && strpos($userAgent, 'Chrome') === false) {
                                    $navegador = 'Safari';
                                } elseif (strpos($userAgent, 'MSIE') || strpos($userAgent, 'Trident')) {
                                    $navegador = 'Internet Explorer';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo $log['usuario'] ?? 'Anónimo'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $tipoClase; ?>">
                                            <?php echo $log['tipo_acceso']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log['ip_origen']; ?></td>
                                    <td><?php echo $log['dispositivo']; ?></td>
                                    <td><?php echo $navegador; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No se encontraron registros</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para enviar el formulario al cambiar los filtros
    document.addEventListener('DOMContentLoaded', function() {
        const selectElements = document.querySelectorAll('select[name="usuario"], select[name="tipo"]');
        const dateInput = document.querySelector('input[name="fecha"]');
        
        selectElements.forEach(function(select) {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        dateInput.addEventListener('change', function() {
            this.form.submit();
        });
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>