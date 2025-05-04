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

$errores = [];
$datos = [
    'email' => '',
    'nombres' => '',
    'apellidos' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger los datos del formulario
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $nombres = sanitizeInput($_POST['nombres'] ?? '');
    $apellidos = sanitizeInput($_POST['apellidos'] ?? '');
    
    // Guardar los datos para rellenar el formulario en caso de error
    $datos = [
        'email' => $email,
        'nombres' => $nombres,
        'apellidos' => $apellidos
    ];
    
    // Intentar registrar al usuario
    $resultado = $usuarioController->registro($email, $password, $passwordConfirm, $nombres, $apellidos);
    
    if ($resultado['success']) {
        setMensaje($resultado['message'], 'success');
        redirect('/multicinev3/auth/login.php');
    } else {
        if (isset($resultado['errores'])) {
            $errores = $resultado['errores'];
        } else {
            $errores[] = $resultado['message'];
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
                <h4 class="mb-0">Crear Cuenta</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="" method="post" class="needs-validation" novalidate>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo $datos['email']; ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un email válido.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                        <div class="invalid-feedback">
                            La contraseña debe tener al menos 6 caracteres.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="password_confirm" 
                               name="password_confirm" required>
                        <div class="invalid-feedback">
                            Las contraseñas deben coincidir.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombres">Nombres</label>
                        <input type="text" class="form-control" id="nombres" name="nombres"
                               value="<?php echo $datos['nombres']; ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese sus nombres.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" class="form-control" id="apellidos" name="apellidos"
                               value="<?php echo $datos['apellidos']; ?>" required>
                        <div class="invalid-feedback">
                            Por favor ingrese sus apellidos.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Registrarse</button>
                </form>
                
                <hr>
                <div class="text-center">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Iniciar Sesión</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once '../includes/footer.php';
?>