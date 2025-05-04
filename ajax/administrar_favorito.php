<?php
/**
 * Archivo AJAX para administrar películas favoritas
 * Este script recibe peticiones POST para agregar o quitar películas de favoritos
 */

// Iniciar sesión y cargar funciones comunes
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario está logueado
if (!estaLogueado()) {
    echo json_encode([
        'success' => false,
        'message' => 'Debes iniciar sesión para gestionar favoritos'
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

// Obtener y validar datos
$peliculaId = isset($_POST['pelicula_id']) ? intval($_POST['pelicula_id']) : 0;
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

// Validar datos
if ($peliculaId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de película no válido'
    ]);
    exit;
}

if ($accion !== 'agregar' && $accion !== 'quitar') {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida'
    ]);
    exit;
}

// Conectar a la base de datos
$conn = require '../config/database.php';

// Cargar controladores
require_once '../controllers/Pelicula.php';
require_once '../models/Pelicula.php';

$peliculaController = new PeliculaController($conn);

// Obtener ID del usuario de la sesión
$usuarioId = $_SESSION['user_id'];

// Ejecutar la acción correspondiente
$resultado = $peliculaController->administrarFavorito($peliculaId, $usuarioId, $accion);

// Devolver resultado como JSON
echo json_encode($resultado);
exit;