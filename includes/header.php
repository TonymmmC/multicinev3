<?php
require_once __DIR__ . '/functions.php';
iniciarSesion();
$conn = require __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multicine - La mejor experiencia cinematográfica</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="/multicinev3/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/multicinev3/">Multicine</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/multicinev3/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/multicinev3/cartelera.php">Cartelera</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/multicinev3/proximamente.php">Próximamente</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/multicinev3/promociones.php">Promociones</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (estaLogueado()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" 
                               data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php echo $_SESSION['email']; ?>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="/multicinev3/perfil.php">Mi Perfil</a>
                                <a class="dropdown-item" href="/multicinev3/reservas.php">Mis Reservas</a>
                                <?php if ($_SESSION['rol_id'] < 3): ?>
                                    <a class="dropdown-item" href="/multicinev3/admin/">Panel Admin</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="/multicinev3/auth/logout.php">Cerrar Sesión</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Registrarse</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php 
        $mensaje = getMensaje();
        if ($mensaje): 
        ?>
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show">
            <?php echo $mensaje['texto']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php endif; ?>