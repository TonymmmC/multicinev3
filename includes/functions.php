<?php
// Iniciar sesión si no está activa
function iniciarSesion() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

// Verificar si el usuario está logueado
function estaLogueado() {
    iniciarSesion();
    return isset($_SESSION['user_id']);
}

// Sanitizar input
function sanitizeInput($data) {
    global $conn;
    if (!isset($conn)) {
        $conn = require __DIR__ . '/../config/database.php';
    }
    return $conn->real_escape_string(htmlspecialchars(trim($data)));
}

// Redireccionar
function redirect($url) {
    header("Location: $url");
    exit;
}

// Mostrar mensaje de alerta
function setMensaje($texto, $tipo = 'info') {
    iniciarSesion();
    $_SESSION['mensaje'] = [
        'texto' => $texto,
        'tipo' => $tipo
    ];
}

// Obtener mensaje de alerta
function getMensaje() {
    iniciarSesion();
    if (isset($_SESSION['mensaje'])) {
        $mensaje = $_SESSION['mensaje'];
        unset($_SESSION['mensaje']);
        return $mensaje;
    }
    return null;
}

/**
 * Obtiene la URL del póster de una película verificando múltiples rutas posibles
 * @param int $peliculaId ID de la película
 * @param string|null $posterUrlDB URL del póster almacenada en la base de datos
 * @return string URL válida del póster
 */
function obtenerPosterUrl($peliculaId, $posterUrlDB = null) {
    // Rutas posibles para buscar el póster
    $possiblePosterPaths = [
        "assets/img/posters/{$peliculaId}.jpg",
        "img/posters/{$peliculaId}.jpg"
    ];
    
    // Verificar si existe alguna de las rutas locales
    foreach ($possiblePosterPaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Si no se encontró una ruta local, devolver la URL de la BD o la imagen predeterminada
    return $posterUrlDB ?? 'assets/img/poster-default.jpg';
}