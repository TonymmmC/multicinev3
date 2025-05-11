<?php
require_once 'includes/functions.php';
require_once 'config/config.php';
require_once 'controllers/HomeController.php';

iniciarSesion();
$conn = require 'config/database.php';

// Inicializar controlador y obtener datos
$homeController = new HomeController($conn);
$data = $homeController->index();

// Extraer variables para las vistas
extract($data);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multicine - Inicio</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/cartelera-home.css">
    <link rel="stylesheet" href="public/css/home.css">
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <?php require_once 'includes/header.php'; ?>
    
    <main>
        <?php 
        // Componentes de la página principal
        require_once 'views/components/hero-banner.php';
        require_once 'views/components/cinema-selector.php';
        require_once 'views/components/movies-carousel.php';
        require_once 'views/components/events-carousel.php';
        require_once 'views/components/news-section.php';
        require_once 'views/components/upcoming-movies.php';
        require_once 'views/components/help-section.php';
        ?>
    </main>
    
    <?php require_once 'includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/home.js"></script>
    
    <!-- Script para función de favoritos -->
    <script>
    function agregarFavorito(peliculaId) {
        <?php if (estaLogueado()): ?>
            $.ajax({
                url: 'favoritos.php',
                type: 'POST',
                data: { 
                    pelicula_id: peliculaId,
                    action: 'agregar'
                },
                success: function(response) {
                    try {
                        const data = JSON.parse(response);
                        if (data.success) {
                            alert('Película añadida a favoritos');
                        }
                    } catch (e) {
                        console.error('Error al procesar la respuesta:', e);
                    }
                }
            });
        <?php else: ?>
            window.location.href = 'auth/login.php';
        <?php endif; ?>
    }
    </script>
</body>
</html>