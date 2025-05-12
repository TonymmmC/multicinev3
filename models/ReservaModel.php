<?php
class ReservaModel {
    private $db;

    public function __construct() {
        $this->db = require 'config/database.php';
    }

    public function crearReserva($userId, $funcionId, $asientos, $metodoPagoId, $totalPagado) {
        // Start transaction
        $this->db->begin_transaction();
        
        try {
            // Insert reservation
            $query = "INSERT INTO reservas (user_id, funcion_id, metodo_pago_id, fecha_reserva, total_pagado, estado) 
                     VALUES (?, ?, ?, NOW(), ?, 'pendiente')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iiid", $userId, $funcionId, $metodoPagoId, $totalPagado);
            $stmt->execute();
            
            $reservaId = $this->db->insert_id;
            
            // Insert reserved seats
            $queryAsientos = "INSERT INTO asientos_reservados (reserva_id, asiento_id, sala_id, precio_final) 
                            VALUES (?, ?, ?, ?)";
            
            $stmtAsientos = $this->db->prepare($queryAsientos);
            
            foreach ($asientos as $asiento) {
                $stmtAsientos->bind_param("iiid", $reservaId, $asiento['id'], $asiento['sala_id'], $asiento['precio']);
                $stmtAsientos->execute();
            }
            
            // Update available seats count in function
            $queryUpdateFuncion = "UPDATE funciones SET asientos_disponibles = asientos_disponibles - ? 
                                 WHERE id = ?";
            
            $stmtUpdateFuncion = $this->db->prepare($queryUpdateFuncion);
            $asientosCount = count($asientos);
            $stmtUpdateFuncion->bind_param("ii", $asientosCount, $funcionId);
            $stmtUpdateFuncion->execute();
            
            // Commit transaction
            $this->db->commit();
            
            return $reservaId;
        } catch (Exception $e) {
            // Rollback on error
            $this->db->rollback();
            return false;
        }
    }
}