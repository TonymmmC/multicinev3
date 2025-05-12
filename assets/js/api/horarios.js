<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Funcion.php';

header('Content-Type: application/json');

// Obtener parámetros
$peliculaId = isset($_GET['pelicula_id']) ? intval($_GET['pelicula_id']) : 0;
$cineId = isset($_GET['cine_id']) ? intval($_GET['cine_id']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

if (!$peliculaId || !$cineId || !$fecha) {
    echo json_encode(['error' => 'Parámetros inválidos']);
    exit;
}

try {
    $conn = require __DIR__ . '/../config/database.php';
    $funcionModel = new Funcion($conn);
    
    $funciones = $funcionModel->obtenerFuncionesPorCineYFecha($peliculaId, $cineId, $fecha);
    
    echo json_encode($funciones);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener funciones']);
}