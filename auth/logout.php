<?php
require_once '../includes/functions.php';
iniciarSesion();

$conn = require '../config/database.php';
require_once '../models/Usuario.php';

// Verificar si hay un usuario logueado
if (isset($_SESSION['user_id'])) {
    // Crear una instancia del modelo Usuario
    $usuario = new Usuario($conn);
    
    // Registrar el cierre de sesión (si tiene esta funcionalidad)
    try {
        $usuario->cerrarSesion($_SESSION['user_id']);
    } catch (Exception $e) {
        // Si hay algún error, simplemente continuar con la destrucción de la sesión
    }
}

// Destruir la sesión
session_destroy();

// Redireccionar a la página principal
redirect('/multicinev3/');