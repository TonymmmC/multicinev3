<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para realizar una reserva', 'warning');
    redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setMensaje('Acceso inválido', 'warning');
    redirect('/multicinev3/');
}

// Get form data
$funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
$asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';
$productosSeleccionados = isset($_POST['productos_seleccionados']) ? $_POST['productos_seleccionados'] : '';

if (!$funcionId || empty($asientosSeleccionados)) {
    setMensaje('Debe seleccionar al menos un asiento', 'warning');
    redirect('reserva.php?funcion=' . $funcionId);
}

// Get function details
$queryFuncion = "SELECT * FROM funciones WHERE id = ?";
$stmtFuncion = $conn->prepare($queryFuncion);
$stmtFuncion->bind_param("i", $funcionId);
$stmtFuncion->execute();
$resultFuncion = $stmtFuncion->get_result();

if ($resultFuncion->num_rows === 0) {
    setMensaje('Función no encontrada', 'warning');
    redirect('/multicinev3/');
}

$funcion = $resultFuncion->fetch_assoc();

// Process selected seats
$asientosIds = explode(',', $asientosSeleccionados);
$asientosIds = array_map('intval', $asientosIds);

// Check if seats are still available
$placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
$queryAsientos = "SELECT a.*, s.id as sala_id FROM asientos a 
                 JOIN salas s ON a.sala_id = s.id 
                 WHERE a.id IN ($placeholders) 
                 AND a.disponible = 1";

$stmtAsientos = $conn->prepare($queryAsientos);
$types = str_repeat('i', count($asientosIds));
$stmtAsientos->bind_param($types, ...$asientosIds);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

if ($resultAsientos->num_rows !== count($asientosIds)) {
    setMensaje('Algunos asientos seleccionados ya no están disponibles', 'warning');
    redirect('reserva.php?funcion=' . $funcionId);
}

// Parse products if any
$productos = [];
if (!empty($productosSeleccionados)) {
    $productos = json_decode($productosSeleccionados, true);
    
    // Validate products
    if (!is_array($productos)) {
        $productos = [];
    }
}

// Start transaction
$conn->begin_transaction();

try {
    // Create reservation
    $userId = $_SESSION['user_id'];
    $queryReserva = "INSERT INTO reservas (user_id, funcion_id, fecha_reserva, total_pagado, estado) 
                    VALUES (?, ?, NOW(), ?, 'pendiente')";
    $stmtReserva = $conn->prepare($queryReserva);
    
    // Calculate total price
    $precioBase = $funcion['precio_base'];
    $totalPagado = $precioBase * count($asientosIds);
    
    // Add product prices
    $totalProductos = 0;
    foreach ($productos as $producto) {
        $totalProductos += $producto['price'] * $producto['quantity'];
    }
    $totalPagado += $totalProductos;
    
    $stmtReserva->bind_param("iid", $userId, $funcionId, $totalPagado);
    $stmtReserva->execute();
    
    $reservaId = $conn->insert_id;
    
    // Insert reserved seats
    $queryAsientosReservados = "INSERT INTO asientos_reservados (reserva_id, asiento_id, sala_id, precio_final) 
                              VALUES (?, ?, ?, ?)";
    $stmtAsientosReservados = $conn->prepare($queryAsientosReservados);
    
    // Reserve each seat
    while ($asiento = $resultAsientos->fetch_assoc()) {
        $stmtAsientosReservados->bind_param("iiid", $reservaId, $asiento['id'], $asiento['sala_id'], $precioBase);
        $stmtAsientosReservados->execute();
    }
    
    // If there are products, create candy bar order
    if (!empty($productos)) {
        $queryPedido = "INSERT INTO pedidos_candy_bar (reserva_id, user_id, fecha_pedido, total, estado) 
                      VALUES (?, ?, NOW(), ?, 'pendiente')";
        $stmtPedido = $conn->prepare($queryPedido);
        
        $stmtPedido->bind_param("iid", $reservaId, $userId, $totalProductos);
        $stmtPedido->execute();
        
        $pedidoId = $conn->insert_id;
        
        // Insert each product
        $queryDetallePedido = "INSERT INTO detalle_pedidos_candy (pedido_id, producto_id, cantidad, precio_unitario, descuento) 
                            VALUES (?, ?, ?, ?, 0)";
        $stmtDetallePedido = $conn->prepare($queryDetallePedido);
        
        foreach ($productos as $producto) {
            $productoId = $producto['id'];
            $cantidad = $producto['quantity'];
            $precioUnitario = $producto['price'];
            
            $stmtDetallePedido->bind_param("iiid", $pedidoId, $productoId, $cantidad, $precioUnitario);
            $stmtDetallePedido->execute();
        }
    }
    
    // Update available seats count
    $queryUpdateFuncion = "UPDATE funciones 
                          SET asientos_disponibles = asientos_disponibles - ? 
                          WHERE id = ?";
    $stmtUpdateFuncion = $conn->prepare($queryUpdateFuncion);
    $numAsientos = count($asientosIds);
    $stmtUpdateFuncion->bind_param("ii", $numAsientos, $funcionId);
    $stmtUpdateFuncion->execute();
    
    // Add audit log
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $asientosStr = implode(',', $asientosIds);
    
    $queryAudit = "INSERT INTO auditoria_sistema 
                (user_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_origen, user_agent) 
                VALUES (?, 'INSERT', 'reservas', ?, ?, ?, ?)";
    $stmtAudit = $conn->prepare($queryAudit);
    $datosNuevos = json_encode([
        'funcion_id' => $funcionId,
        'asientos' => $asientosIds,
        'productos' => $productos,
        'total' => $totalPagado,
        'fecha' => date('Y-m-d H:i:s')
    ]);
    $stmtAudit->bind_param("iisss", $userId, $reservaId, $datosNuevos, $ip, $userAgent);
    $stmtAudit->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Redirect to confirmation page
    setMensaje('¡Reserva realizada con éxito!', 'success');
    redirect('confirmacion_reserva.php?id=' . $reservaId);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    setMensaje('Error al procesar la reserva: ' . $e->getMessage(), 'error');
    redirect('reserva.php?funcion=' . $funcionId);
}