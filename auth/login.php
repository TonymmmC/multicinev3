<?php
require_once '../includes/functions.php';
iniciarSesion();

// Si ya está logueado, redirigir a la página principal
if (estaLogueado()) {
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';
require_once '../models/Usuario.php';
$usuario = new Usuario($conn);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor ingrese su email y contraseña';
    } else {
        if ($usuario->login($email, $password)) {
            setMensaje('Has iniciado sesión correctamente', 'success');
            redirect('/multicinev3/');
        } else {
            // Registrar intento fallido
            $usuario->registrarIntentoFallido($email);
            $error = 'Credenciales incorrectas';
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
                <h4 class="mb-0">Iniciar Sesión</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="" method="post">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Iniciar Sesión</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="recuperar-password.php">¿Olvidaste tu contraseña?</a>
                </div>
                <hr>
                <div class="text-center">
                    <p>¿No tienes una cuenta? <a href="register.php">Regístrate</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once '../includes/footer.php';
?>