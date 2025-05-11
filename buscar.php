<?php
require_once 'includes/functions.php';
require_once 'controllers/SearchController.php';

iniciarSesion();
$conn = require 'config/database.php';

// Inicializar controlador y obtener datos
$searchController = new SearchController($conn);
$data = $searchController->index();

// Extraer variables para las vistas
$termino = $data['termino'];
$resultados = $data['resultados'];

// Incluir header
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Películas - Multicine</title>
    
    <!-- CSS -->
    <link href="assets/css/buscar.css" rel="stylesheet">
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="search-page-container">
        <?php 
        // Formulario de búsqueda
        require_once 'views/search/search-form.php';
        
        if (!empty($termino)) {
            // Mostrar resultados de búsqueda
            if (!empty($resultados)) {
                require_once 'views/search/search-results.php';
            } else {
                // Mostrar mensaje de "sin resultados"
                require_once 'views/search/no-results.php';
            }
        } else {
            // Mostrar sugerencias de búsqueda
            require_once 'views/search/search-suggestions.php';
        }
        ?>
    </div>
    
    <?php require_once 'includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script src="public/js/search.js"></script>
</body>
</html>