<?php
// api/check_seats.php
require_once '../includes/functions.php';
$conn = require '../config/database.php';

// Get function ID
$funcionId = isset($_GET['funcion_id']) ? intval($_GET['funcion_id']) : null;

if (!$funcionId) {
    echo json_encode(['error' => 'Missing function ID']);
    exit;
}

// Get reserved seats for this function
$queryReservados = "SELECT ar.asiento_id 
                  FROM asientos_reservados ar 
                  JOIN reservas r ON ar.reserva_id = r.id 
                  WHERE r.funcion_id = ?";
$stmtReservados = $conn->prepare($queryReservados);
$stmtReservados->bind_param("i", $funcionId);
$stmtReservados->execute();
$resultReservados = $stmtReservados->get_result();

$asientosReservados = [];
while ($row = $resultReservados->fetch_assoc()) {
    $asientosReservados[] = (int)$row['asiento_id'];
}

// Return updated seat information
echo json_encode([
    'updated' => true,
    'occupied_seats' => $asientosReservados
]);