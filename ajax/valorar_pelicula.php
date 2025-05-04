<?php
/**
 * Archivo AJAX para procesar valoraciones de películas
 * Este script recibe peticiones POST para valorar películas y devuelve JSON
 */

// Iniciar sesión y cargar funciones comunes
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario está logueado
if (!estaLogueado()) {
    echo json_encode([
        'success' => false,
        'message' => 'Debes iniciar sesión para valorar películas'
    ]);
    exit;
}

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Obtener y validar datos del formulario
$peliculaId = isset($_POST['pelicula_id']) ? intval($_POST['pelicula_id']) : 0;
$puntuacion = isset($_POST['puntuacion']) ? intval($_POST['puntuacion']) : 0;
$comentario = isset($_POST['comentario']) ? trim($_POST['comentario']) : null;

// Validar datos
if ($peliculaId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de película no válido'
    ]);
    exit;
}

if ($puntuacion < 1 || $puntuacion > 5) {
    echo json_encode([
        'success' => false,
        'message' => 'La puntuación debe estar entre 1 y 5'
    ]);
    exit;
}

// Limitar longitud del comentario
if ($comentario !== null && strlen($comentario) > 500) {
    $comentario = substr($comentario, 0, 500);
}

// Conectar a la base de datos
$conn = require '../config/database.php';

// Cargar controladores
require_once '../controllers/Pelicula.php';
require_once '../models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener ID del usuario de la sesión
$usuarioId = $_SESSION['user_id'];

// Guardar la valoración
$resultado = $peliculaController->valorarPelicula($peliculaId, $usuarioId, $puntuacion, $comentario);

// Devolver resultado como JSON
echo json_encode($resultado);
exit;