<?php
require_once 'includes/functions.php';
iniciarSesion();

// Si no está logueado, redirigir al login
if (!estaLogueado()) {
    setMensaje('Debes iniciar sesión para acceder a tu perfil', 'warning');
    redirect('/multicinev3/auth/login.php');
}

$conn = require 'config/database.php';
require_once 'controllers/Usuario.php';
require_once 'models/Usuario.php';

$usuarioController = new UsuarioController($conn);
$userId = $_SESSION['user_id'];

// Obtener datos del perfil
$perfil = $usuarioController->obtenerPerfil($userId);

// Procesar formulario de actualización de perfil
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar qué formulario se envió
    if (isset($_POST['actualizar_perfil'])) {
        // Procesar actualización de perfil
        $datos = [
            'nombres' => $_POST['nombres'] ?? '',
            'apellidos' => $_POST['apellidos'] ?? '',
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
            'celular' => $_POST['celular'] ?? '',
            'direccion' => $_POST['direccion'] ?? '',
            'nit_ci' => $_POST['nit_ci'] ?? '',
            'idioma_preferido' => $_POST['idioma_preferido'] ?? 'es',
            'modo_oscuro' => isset($_POST['modo_oscuro']) ? 1 : 0
        ];
        
        $resultado = $usuarioController->actualizarPerfil($userId, $datos);
        
        if ($resultado['success']) {
            $success = $resultado['message'];
            // Actualizar datos del perfil después de guardar
            $perfil = $usuarioController->obtenerPerfil($userId);
        } else {
            $error = $resultado['message'];
        }
    } elseif (isset($_POST['cambiar_password'])) {
        // Procesar cambio de contraseña
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Todos los campos son obligatorios';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Las nuevas contraseñas no coinciden';
        } elseif (strlen($newPassword) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres';
        } else {
            $resultado = $usuarioController->cambiarPassword($userId, $currentPassword, $newPassword);
            
            if ($resultado['success']) {
                $success = $resultado['message'];
            } else {
                $error = $resultado['message'];
            }
        }
    }
}

// Incluir header
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
                    <img src="<?php echo $perfil['imagen_url'] ?? 'assets/img/usuario-default.jpg'; ?>" 
                         class="rounded-circle mb-3" width="150" height="150" alt="Foto de perfil">
                    <h4><?php echo $perfil['nombres'] . ' ' . $perfil['apellidos']; ?></h4>
                    <p class="text-muted"><?php echo $perfil['email']; ?></p>
                    <p>
                        <span class="badge badge-primary"><?php echo $perfil['rol_nombre'] ?? 'Cliente'; ?></span>
                    </p>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#perfil" class="list-group-item list-group-item-action active" data-toggle="tab">
                        <i class="fas fa-user"></i> Información Personal
                    </a>
                    <a href="#seguridad" class="list-group-item list-group-item-action" data-toggle="tab">
                        <i class="fas fa-lock"></i> Seguridad
                    </a>
                    <a href="reservas.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-ticket-alt"></i> Mis Reservas
                    </a>
                    <a href="favoritos.php" class="list-group-item list-group-item-action">
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
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="tab-content">
                <!-- Pestaña de Información Personal -->
                <div class="tab-pane fade show active" id="perfil">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Información Personal</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="post">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nombres">Nombres</label>
                                            <input type="text" class="form-control" id="nombres" name="nombres" 
                                                   value="<?php echo $perfil['nombres'] ?? ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="apellidos">Apellidos</label>
                                            <input type="text" class="form-control" id="apellidos" name="apellidos" 
                                                   value="<?php echo $perfil['apellidos'] ?? ''; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email</label>
                                            <input type="email" class="form-control" id="email" 
                                                   value="<?php echo $perfil['email']; ?>" readonly>
                                            <small class="form-text text-muted">El email no se puede cambiar</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                                   value="<?php echo $perfil['fecha_nacimiento'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="celular">Teléfono/Celular</label>
                                            <input type="text" class="form-control" id="celular" name="celular" 
                                                   value="<?php echo $perfil['celular'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="nit_ci">NIT/CI</label>
                                            <input type="text" class="form-control" id="nit_ci" name="nit_ci" 
                                                   value="<?php echo $perfil['nit_ci'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="direccion">Dirección</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" 
                                           value="<?php echo $perfil['direccion'] ?? ''; ?>">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="idioma_preferido">Idioma Preferido</label>
                                            <select class="form-control" id="idioma_preferido" name="idioma_preferido">
                                                <option value="es" <?php echo ($perfil['idioma_preferido'] ?? 'es') == 'es' ? 'selected' : ''; ?>>Español</option>
                                                <option value="en" <?php echo ($perfil['idioma_preferido'] ?? 'es') == 'en' ? 'selected' : ''; ?>>Inglés</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mt-4">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="modo_oscuro" name="modo_oscuro" 
                                                       <?php echo ($perfil['modo_oscuro'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="modo_oscuro">Modo Oscuro</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" name="actualizar_perfil" class="btn btn-primary">Guardar Cambios</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña de Seguridad -->
                <div class="tab-pane fade" id="seguridad">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Cambiar Contraseña</h5>
                        </div>
                        <div class="card-body">
                            <form action="" method="post">
                                <div class="form-group">
                                    <label for="current_password">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="6" required>
                                    <small class="form-text text-muted">La contraseña debe tener al menos 6 caracteres</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" name="cambiar_password" class="btn btn-primary">Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Activar tabs de Bootstrap
$(document).ready(function() {
    $('a[data-toggle="tab"]').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        
        // Actualizar clases active en los elementos de la lista
        $('.list-group-item').removeClass('active');
        $(this).addClass('active');
    });
});
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>