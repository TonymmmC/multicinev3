<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de superadmin (rol_id 1)
if (!estaLogueado() || $_SESSION['rol_id'] != 1) {
    setMensaje('Solo SuperAdmin puede editar clientes', 'danger');
    redirect('/multicinev3/admin/usuarios.php');
}

$conn = require '../config/database.php';

// Inicializar variables
$usuario = [
    'id' => null,
    'email' => '',
    'rol_id' => 3, // Cliente
    'activo' => 1,
    'nombres' => '',
    'apellidos' => '',
    'fecha_nacimiento' => '',
    'celular' => '',
    'direccion' => '',
    'nit_ci' => ''
];

$accion = 'editar'; // Solo permitimos editar, no crear clientes desde el admin
$titulo_pagina = 'Editar Cliente';
$errores = [];

// Obtener información del usuario si estamos editando
if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);
    
    // Consultar usuario
    $query = "SELECT u.id, u.email, u.rol_id, u.activo,
                     pu.nombres, pu.apellidos, pu.fecha_nacimiento, 
                     pu.celular, pu.direccion, pu.nit_ci
              FROM users u
              LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
              WHERE u.id = ? AND u.deleted_at IS NULL AND u.rol_id = 3"; // Solo clientes
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        $titulo_pagina = 'Editar Cliente: ' . $usuario['email'];
    } else {
        setMensaje('El cliente no existe o no se puede editar', 'danger');
        redirect('usuarios.php');
        exit;
    }
} else {
    // Si no hay ID, redirigir
    setMensaje('No se especificó un cliente para editar', 'danger');
    redirect('usuarios.php');
    exit;
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y validar datos
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;
    $nombres = sanitizeInput($_POST['nombres'] ?? '');
    $apellidos = sanitizeInput($_POST['apellidos'] ?? '');
    $fechaNacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $celular = sanitizeInput($_POST['celular'] ?? '');
    $direccion = sanitizeInput($_POST['direccion'] ?? '');
    $nitCi = sanitizeInput($_POST['nit_ci'] ?? '');
    
    // Validaciones
    if (empty($email)) {
        $errores[] = 'El email es obligatorio';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido';
    }
    
    if (!empty($password) && $password !== $confirmarPassword) {
        $errores[] = 'Las contraseñas no coinciden';
    }
    
    // Si no hay errores, proceder con la actualización
    if (empty($errores)) {
        // Comenzar transacción
        $conn->begin_transaction();
        
        try {
            // Actualizar usuario
            $query = "UPDATE users SET email = ?, activo = ? WHERE id = ? AND rol_id = 3"; // Solo clientes
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sii', $email, $activo, $usuario['id']);
            $stmt->execute();
            
            // Actualizar contraseña si se proporcionó una nueva
            if (!empty($password)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ? AND rol_id = 3"; // Solo clientes
                $stmt = $conn->prepare($query);
                $stmt->bind_param('si', $passwordHash, $usuario['id']);
                $stmt->execute();
            }
            
            // Verificar si existe un perfil
            $query = "SELECT id FROM perfiles_usuario WHERE user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $usuario['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Actualizar perfil existente
                $query = "UPDATE perfiles_usuario SET 
                          nombres = ?, 
                          apellidos = ?, 
                          fecha_nacimiento = ?, 
                          celular = ?, 
                          direccion = ?, 
                          nit_ci = ?
                          WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssssi', $nombres, $apellidos, $fechaNacimiento, $celular, $direccion, $nitCi, $usuario['id']);
            } else {
                // Crear nuevo perfil
                $query = "INSERT INTO perfiles_usuario (user_id, nombres, apellidos, fecha_nacimiento, celular, direccion, nit_ci) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('issssss', $usuario['id'], $nombres, $apellidos, $fechaNacimiento, $celular, $direccion, $nitCi);
            }
            $stmt->execute();
            
            // Registrar acción en auditoría
            $adminId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'UPDATE', 'users', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $adminId, $usuario['id']);
            $stmt->execute();
            
            // Confirmar transacción
            $conn->commit();
            
            setMensaje('Cliente actualizado correctamente', 'success');
            redirect('usuarios.php');
            exit;
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            $errores[] = 'Error al actualizar el cliente: ' . $e->getMessage();
        }
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $titulo_pagina; ?></h1>
            <a href="usuarios.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form action="" method="post">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $usuario['email']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6">
                                <small class="form-text text-muted">Dejar en blanco para mantener la contraseña actual</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirmar_password">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" id="confirmar_password" name="confirmar_password">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información de Rol -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Este usuario tiene rol de <strong>Cliente</strong> y puede acceder a las funciones normales del sistema.
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5>Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nombres">Nombres</label>
                                        <input type="text" class="form-control" id="nombres" name="nombres" 
                                               value="<?php echo $usuario['nombres']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="apellidos">Apellidos</label>
                                        <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                               value="<?php echo $usuario['apellidos']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                               value="<?php echo $usuario['fecha_nacimiento']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="celular">Teléfono/Celular</label>
                                        <input type="text" class="form-control" id="celular" name="celular" 
                                               value="<?php echo $usuario['celular']; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="direccion">Dirección</label>
                                        <input type="text" class="form-control" id="direccion" name="direccion" 
                                               value="<?php echo $usuario['direccion']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nit_ci">NIT/CI</label>
                                        <input type="text" class="form-control" id="nit_ci" name="nit_ci" 
                                               value="<?php echo $usuario['nit_ci']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="activo" name="activo" 
                                   <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="activo">Cliente Activo</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cliente
                        </button>
                        <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>