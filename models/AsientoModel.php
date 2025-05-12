<?php
class AsientoModel {
    private $db;

    public function __construct() {
        $this->db = require 'config/database.php';
    }

    public function getAsientosBySalaId($salaId) {
        $query = "SELECT a.id, a.fila, a.numero, a.tipo, a.disponible 
                 FROM asientos a 
                 WHERE a.sala_id = ? 
                 ORDER BY a.fila, a.numero";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $salaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $asientos = [];
        while ($row = $result->fetch_assoc()) {
            $asientos[] = $row;
        }
        
        return $asientos;
    }

    public function getAsientosReservadosByFuncionId($funcionId) {
        $query = "SELECT ar.asiento_id 
                  FROM asientos_reservados ar 
                  JOIN reservas r ON ar.reserva_id = r.id 
                  WHERE r.funcion_id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $funcionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $asientosReservados = [];
        while ($row = $result->fetch_assoc()) {
            $asientosReservados[] = (int)$row['asiento_id'];
        }
        
        return $asientosReservados;
    }
}