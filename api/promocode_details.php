<?php
header('Content-Type: application/json');

// Permitir solicitudes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos POST como JSON
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!$data || !isset($data['codigo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$codigo = $data['codigo'];

// Conectar a la base de datos
require_once '../config/database.php';

// Obtener detalles del código promocional
$query = "SELECT tipo, valor FROM promociones WHERE codigo_promocional = ? AND activa = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $codigo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Código no encontrado']);
    exit;
}

$promocion = $result->fetch_assoc();

// Responder con éxito y datos del descuento
echo json_encode([
    'success' => true,
    'tipo' => $promocion['tipo'],
    'valor' => $promocion['valor']
]);
?>