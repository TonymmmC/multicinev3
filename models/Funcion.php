<?php
class Funcion {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function obtenerFuncionesPorCineYFecha($peliculaId, $cineId, $fecha) {
        $query = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles, 
                  s.nombre as sala_nombre, i.nombre as idioma, fmt.nombre as formato 
                  FROM funciones f 
                  JOIN salas s ON f.sala_id = s.id 
                  JOIN idiomas i ON f.idioma_id = i.id 
                  JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id
                  WHERE f.pelicula_id = ? 
                  AND s.cine_id = ?
                  AND DATE(f.fecha_hora) = ?
                  ORDER BY f.fecha_hora ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iis", $peliculaId, $cineId, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $funciones = [];
        while ($row = $result->fetch_assoc()) {
            $funciones[] = $row;
        }
        return $funciones;
    }
}