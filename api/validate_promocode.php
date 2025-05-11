<?php
require_once '../includes/functions.php';
iniciarSesion();
$conn = require '../config/database.php';

// Verificar si la solicitud es POST y si es formato JSON
header('Content-Type: application/json');

// Obtener el cuerpo de la solicitud
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData);

if (!$data || !isset($data->codigo) || !isset($data->funcion_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Datos inválidos'
    ]);
    exit;
}

$codigo = $data->codigo;
$funcionId = (int)$data->funcion_id;

// Verificar si el código existe y es válido
$query = "SELECT p.id, p.tipo, p.valor, p.fecha_fin, p.max_usos, p.usos_actuales  
          FROM promociones p 
          WHERE p.codigo_promocional = ? 
          AND p.activa = 1 
          AND p.fecha_inicio <= NOW() 
          AND p.fecha_fin >= NOW()";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Código promocional inválido o expirado'
    ]);
    exit;
}

$promocion = $result->fetch_assoc();

// Verificar si se ha alcanzado el límite de usos
if ($promocion['max_usos'] !== null && $promocion['usos_actuales'] >= $promocion['max_usos']) {
    echo json_encode([
        'success' => false,
        'message' => 'Este código ha alcanzado su límite de usos'
    ]);
    exit;
}

// Verificar si la promoción aplica para esta función (se podría expandir con más lógica)
// Por ejemplo, verificar si la promoción es específica para ciertas películas o cines

// Todo está bien, devolver la información de descuento
echo json_encode([
    'success' => true,
    'message' => 'Código promocional válido',
    'tipo' => $promocion['tipo'],
    'descuento' => $promocion['valor']
]);

// Opcional: Podríamos incrementar el contador de usos aquí,
// pero es mejor hacerlo en el procesamiento final de la reserva
// para evitar contabilizar usos que no se completan
?>