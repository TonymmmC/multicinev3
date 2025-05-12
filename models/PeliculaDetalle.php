<?php
class PeliculaDetalle {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function esFavorita($userId, $peliculaId) {
        if (!$userId) return false;
        
        $query = "SELECT id FROM favoritos WHERE user_id = ? AND pelicula_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $userId, $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    public function obtenerFormatos($peliculaId) {
        $query = "SELECT DISTINCT f.nombre 
                  FROM formatos f 
                  JOIN funciones func ON f.id = func.formato_proyeccion_id
                  WHERE func.pelicula_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $formatos = [];
        while ($row = $result->fetch_assoc()) {
            $formatos[] = $row['nombre'];
        }
        return $formatos;
    }
    
    public function obtenerCinesConFunciones($peliculaId) {
        $query = "SELECT DISTINCT c.id, c.nombre, c.direccion 
                  FROM cines c
                  JOIN salas s ON s.cine_id = c.id
                  JOIN funciones f ON f.sala_id = s.id
                  WHERE f.pelicula_id = ? AND c.activo = 1
                  ORDER BY c.nombre";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $cines = [];
        while ($row = $result->fetch_assoc()) {
            $cines[] = $row;
        }
        return $cines;
    }
}