<?php
class Event {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getSpecialEvents($cineId = null, $limit = 4) {
        $query = "SELECT e.id, e.nombre, e.fecha_inicio, m.url as imagen_url
                  FROM eventos_especiales e
                  LEFT JOIN multimedia m ON e.imagen_id = m.id";

        if ($cineId !== null) {
            $query .= " WHERE e.cine_id = ? 
                        AND e.fecha_fin >= NOW()
                        AND e.deleted_at IS NULL";
        } else {
            $query .= " WHERE e.fecha_fin >= NOW()
                        AND e.deleted_at IS NULL";
        }

        $query .= " ORDER BY e.fecha_inicio ASC
                    LIMIT ?";

        $stmt = $this->conn->prepare($query);
        
        if ($cineId !== null) {
            $stmt->bind_param('ii', $cineId, $limit);
        } else {
            $stmt->bind_param('i', $limit);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        $eventos = [];
        while ($row = $result->fetch_assoc()) {
            // Determinar etiqueta
            $fechaEvento = strtotime($row['fecha_inicio']);
            if ($fechaEvento < time() + 86400) {
                $row['etiqueta'] = 'HOY';
            } elseif ($fechaEvento < time() + 604800) {
                $row['etiqueta'] = 'ESTA SEMANA';
            } else {
                $row['etiqueta'] = 'PRÓXIMAMENTE';
            }
            
            $eventos[] = $row;
        }
        $stmt->close();

        return $eventos;
    }
}
?>