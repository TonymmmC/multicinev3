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