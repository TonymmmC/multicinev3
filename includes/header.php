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
    <title>Multicine - La mejor experiencia cinematográfica de Bolivia</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo.jpg" alt="Multicine" height="50">
        </a>
        
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cartelera.php">Cartelera</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cines.php">Cines</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="candybar.php">CandyBar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="multipass.php">Club MultiPass</a>
                </li>
            </ul>
            
            <div class="search-box mx-2 d-none d-md-block">
                <form action="buscar.php" method="get">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" placeholder="Buscar películas...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <ul class="navbar-nav ml-auto">
                <!-- Icono del carrito de compras
                <li class="nav-item">
                    <a class="nav-link" href="carrito.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="badge badge-pill badge-warning cart-count">0</span>
                    </a>
                </li>-->
                
                <?php if (estaLogueado()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" data-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo $_SESSION['nombres'] ?: $_SESSION['email']; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="perfil.php">Mi Perfil</a>
                            <a class="dropdown-item" href="reservas.php">Mis Reservas</a>
                            <a class="dropdown-item" href="favoritos.php">Mis Favoritos</a>
                            <?php if ($_SESSION['rol_id'] <= 2): ?>
                                <a class="dropdown-item" href="admin/">Panel Admin</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="auth/logout.php">Cerrar Sesión</a>
                        </div>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/register.php">Crear una cuenta</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="auth/login.php">Iniciar Sesión</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="?lang=<?php echo isset($_SESSION['lang']) && $_SESSION['lang'] == 'es' ? 'en' : 'es'; ?>">
                        <?php echo isset($_SESSION['lang']) && $_SESSION['lang'] == 'es' ? 'EN' : 'ES'; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
    
    <?php 
    // Mensajes del sistema
    $mensaje = getMensaje();
    if ($mensaje && basename($_SERVER['PHP_SELF']) != 'index.php'): // No mostrar en página principal 
    ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show">
            <?php echo $mensaje['texto']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
    <?php endif; ?>