<?php
class Cinema {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getCinemaName($cineId) {
        if ($cineId == 0) {
            return "Todos los cines";
        }
        
        $query = "SELECT nombre FROM cines WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $cineId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['nombre'];
        }
        $stmt->close();
        return "Todos los cines";
    }
    
    public function getAllCinemas() {
        $query = "SELECT id, nombre FROM cines WHERE activo = 1 ORDER BY nombre";
        $result = $this->conn->query($query);
        $cines = [];
        
        if ($result && $result->num_rows > 0) {
            while ($cine = $result->fetch_assoc()) {
                $cines[] = $cine;
            }
        }
        
        return $cines;
    }
}
?>