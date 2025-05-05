<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

// Para admin normal (rol_id = 2), verificar permisos adicionales
$esSuperAdmin = $_SESSION['rol_id'] == 1;

$conn = require '../config/database.php';

// Procesar eliminación de usuario (soft delete)
if (isset($_POST['eliminar_usuario']) && isset($_POST['id'])) {
    $userId = intval($_POST['id']);
    
    // Solo SuperAdmin puede eliminar usuarios
    if (!$esSuperAdmin) {
        setMensaje('No tienes permisos para eliminar usuarios', 'danger');
        redirect('usuarios.php');
        exit;
    }
    
    // Verificar que el usuario a eliminar no sea SuperAdmin
    $query = "SELECT rol_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['rol_id'] == 1) {
            setMensaje('No se puede eliminar al SuperAdmin', 'danger');
            redirect('usuarios.php');
            exit;
        }
    }
    
    // Verificar que el usuario existe
    $query = "SELECT id FROM users WHERE id = ? AND id != ?"; // No permitir auto-eliminación
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $userId, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Marcar como eliminado (soft delete)
        $query = "UPDATE users SET deleted_at = NOW(), activo = 0 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        
        if ($stmt->execute()) {
            // Registrar en auditoría
            $adminId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'DELETE', 'users', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $adminId, $userId);
            $stmt->execute();
            
            setMensaje('Usuario eliminado correctamente', 'success');
        } else {
            setMensaje('Error al eliminar el usuario', 'danger');
        }
    } else {
        setMensaje('El usuario no existe o no puedes eliminarte a ti mismo', 'danger');
    }
    
    redirect('usuarios.php');
    exit;
}

// Modificar estado (activar/desactivar)
if (isset($_POST['cambiar_estado']) && isset($_POST['id'])) {
    $userId = intval($_POST['id']);
    $nuevoEstado = intval($_POST['estado']);
    
    // Solo SuperAdmin puede modificar estado de usuarios
    if (!$esSuperAdmin) {
        setMensaje('No tienes permisos para modificar usuarios', 'danger');
        redirect('usuarios.php');
        exit;
    }
    
    // Verificar que el usuario a modificar no sea SuperAdmin
    $query = "SELECT rol_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if ($user['rol_id'] == 1) {
            setMensaje('No se puede modificar al SuperAdmin', 'danger');
            redirect('usuarios.php');
            exit;
        }
    }
    
    // Actualizar estado
    $query = "UPDATE users SET activo = ? WHERE id = ? AND id != ?"; // No permitir auto-modificación
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $nuevoEstado, $userId, $_SESSION['user_id']);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        // Registrar en auditoría
        $adminId = $_SESSION['user_id'];
        $accion = $nuevoEstado ? 'ACTIVAR' : 'DESACTIVAR';
        $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                  VALUES (?, ?, 'users', ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('isi', $adminId, $accion, $userId);
        $stmt->execute();
        
        setMensaje('Estado del usuario actualizado correctamente', 'success');
    } else {
        setMensaje('Error al actualizar el estado del usuario', 'danger');
    }
    
    redirect('usuarios.php');
    exit;
}

// Obtener listado de usuarios
$query = "SELECT u.id, u.email, u.activo, u.ultimo_login, u.rol_id, 
                 r.nombre as rol_nombre,
                 pu.nombres, pu.apellidos, pu.celular
          FROM users u
          LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
          LEFT JOIN roles r ON u.rol_id = r.id
          WHERE u.deleted_at IS NULL";
          
// Para admin normal, filtrar para no mostrar ningún usuario (solo ve clientes)
if (!$esSuperAdmin) {
    $query .= " AND u.rol_id = 3";
}

$query .= " ORDER BY u.rol_id ASC, u.id DESC";

$result = $conn->query($query);
$usuarios = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
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
            <h1>Administración de Usuarios</h1>
            <?php if ($esSuperAdmin): // Solo SuperAdmin puede crear admins ?>
            <a href="usuario-form.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Nuevo Administrador
            </a>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Teléfono</th>
                                <th>Último Login</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo $usuario['email']; ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($usuario['nombres']) || !empty($usuario['apellidos'])) {
                                            echo $usuario['nombres'] . ' ' . $usuario['apellidos'];
                                        } else {
                                            echo '<span class="text-muted">Sin completar</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $rolClase = '';
                                        switch ($usuario['rol_id']) {
                                            case 1:
                                                $rolClase = 'danger';
                                                break;
                                            case 2:
                                                $rolClase = 'warning';
                                                break;
                                            default:
                                                $rolClase = 'info';
                                        }
                                        ?>
                                        <span class="badge badge-<?php echo $rolClase; ?>">
                                            <?php echo $usuario['rol_nombre']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($usuario['celular'])) {
                                            echo $usuario['celular'];
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($usuario['ultimo_login'])) {
                                            echo date('d/m/Y H:i', strtotime($usuario['ultimo_login']));
                                        } else {
                                            echo '<span class="text-muted">Nunca</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($usuario['activo']): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                    <?php 
                                        // Verificar permisos
                                        $esUsuarioActual = $usuario['id'] == $_SESSION['user_id'];
                                        $esElSuperAdmin = $usuario['rol_id'] == 1;
                                        
                                        // SuperAdmin puede editar todo excepto a sí mismo (para eliminar)
                                        // Admin normal solo puede ver sin acciones
                                        $puedeModificar = $esSuperAdmin && !$esUsuarioActual;
                                        
                                        // Nadie puede eliminar al SuperAdmin
                                        $puedeEliminar = $puedeModificar && !$esElSuperAdmin;
                                        
                                        if ($puedeModificar):
                                        ?>
                                        <div class="btn-group">
                                            <form action="" method="post" class="mr-1">
                                                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                                                <input type="hidden" name="estado" value="<?php echo $usuario['activo'] ? 0 : 1; ?>">
                                                <button type="submit" name="cambiar_estado" class="btn btn-sm btn-<?php echo $usuario['activo'] ? 'warning' : 'success'; ?>" 
                                                        data-toggle="tooltip" title="<?php echo $usuario['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo $usuario['activo'] ? 'user-slash' : 'user-check'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <?php if ($usuario['rol_id'] == 2): // Para editar administradores ?>
                                                <a href="usuario-form.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-primary mr-1" 
                                                data-toggle="tooltip" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php elseif ($usuario['rol_id'] == 3): // Para editar clientes ?>
                                                <a href="usuario-cliente-form.php?id=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-primary mr-1" 
                                                data-toggle="tooltip" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($puedeEliminar): // Nadie puede eliminar al SuperAdmin ?>
                                                <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" 
                                                        data-target="#eliminarModal" data-id="<?php echo $usuario['id']; ?>"
                                                        data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                                        title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                            <?php if ($esUsuarioActual): ?>
                                                <span class="badge badge-light">Usuario actual</span>
                                            <?php elseif ($esElSuperAdmin): ?>
                                                <span class="badge badge-light">SuperAdmin</span>
                                            <?php else: ?>
                                                <span class="badge badge-light">Sin acciones</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eliminarModalLabel">Confirmar eliminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar al usuario <strong id="usuarioEmail"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="" method="post">
                    <input type="hidden" name="id" id="usuarioId" value="">
                    <button type="submit" name="eliminar_usuario" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para pasar datos al modal de eliminación
    $('#eliminarModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var email = button.data('email');
        
        var modal = $(this);
        modal.find('#usuarioId').val(id);
        modal.find('#usuarioEmail').text(email);
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>