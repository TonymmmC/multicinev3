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
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor ingrese su email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingrese un email válido';
    } else {
        $resultado = $usuarioController->enviarRecuperacionPassword($email);
        
        if ($resultado['success']) {
            $success = $resultado['message'];
            $email = ''; // Limpiar campo email después de enviar
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
                <h4 class="mb-0">Recuperar Contraseña</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                    <p>Ingresa tu dirección de email para recibir instrucciones sobre cómo restablecer tu contraseña.</p>
                    
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $email; ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Enviar Instrucciones</button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="login.php">Volver al inicio de sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once '../includes/footer.php';
?>