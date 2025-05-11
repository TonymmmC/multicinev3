<?php
// Archivo: api/horarios.php

header('Content-Type: application/json');

// Obtener parámetros de la URL
$pelicula_id = isset($_GET['pelicula_id']) ? intval($_GET['pelicula_id']) : 0;
$cine_id = isset($_GET['cine_id']) ? intval($_GET['cine_id']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

// Validar parámetros
if (!$pelicula_id || !$cine_id || !$fecha) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan parámetros requeridos']);
    exit;
}

// Conectar a la base de datos
require_once '../config/database.php';

// Consultar horarios disponibles
$queryHorarios = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles, 
                  s.nombre as sala_nombre, i.nombre as idioma, fmt.nombre as formato 
                  FROM funciones f 
                  JOIN salas s ON f.sala_id = s.id 
                  JOIN idiomas i ON f.idioma_id = i.id 
                  JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id
                  WHERE f.pelicula_id = ? 
                  AND s.cine_id = ?
                  AND DATE(f.fecha_hora) = ?
                  ORDER BY f.fecha_hora ASC";

$stmt = $conn->prepare($queryHorarios);
$stmt->bind_param("iis", $pelicula_id, $cine_id, $fecha);
$stmt->execute();
$result = $stmt->get_result();

$horarios = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $horarios[] = $row;
    }
}

echo json_encode($horarios);
?>