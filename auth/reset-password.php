<?php
require_once '../includes/functions.php';
iniciarSesion();

// Si ya está logueado, redirigir a la página principal
if (estaLogueado()) {
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';
require_once '../controllers/Usuario.php';
require_once '../models/Usuario.php';

$usuarioController = new UsuarioController($conn);

$error = '';
$success = '';

// Verificar el token
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($token) || empty($email)) {
    $error = 'Enlace de recuperación inválido o expirado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'Por favor ingrese una nueva contraseña';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Las contraseñas no coinciden';
    } else {
        $resultado = $usuarioController->restablecerPassword($email, $token, $password);
        
        if ($resultado['success']) {
            $success = $resultado['message'];
        } else {
            $error = $resultado['message'];
        }
    }
}

// Incluir header
require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Restablecer Contraseña</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <div class="text-center mt-3">
                        <a href="recuperar-password.php">Solicitar un nuevo enlace</a>
                    </div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Iniciar Sesión</a>
                    </div>
                <?php else: ?>
                    <p>Ingresa tu nueva contraseña.</p>
                    
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="password">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="password_confirm" 
                                   name="password_confirm" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Restablecer Contraseña</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once '../includes/footer.php';
?>