<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

require_once 'controllers/PeliculaDetalleController.php';

// Obtener el ID de la película
$peliculaId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$peliculaId) {
    setMensaje('Película no encontrada', 'warning');
    redirect('/multicinev3/');
}

// Crear controlador y mostrar detalle
$peliculaDetalleController = new PeliculaDetalleController($conn);
$peliculaDetalleController->mostrarDetalle($peliculaId);