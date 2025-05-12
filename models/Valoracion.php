<?php
class Valoracion {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function obtenerDatosValoracion($peliculaId) {
        // Obtener valoración promedio y total
        $queryValoracion = "SELECT AVG(puntuacion) as promedio, COUNT(*) as total 
                           FROM valoraciones 
                           WHERE pelicula_id = ?";
        $stmtValoracion = $this->conn->prepare($queryValoracion);
        $stmtValoracion->bind_param("i", $peliculaId);
        $stmtValoracion->execute();
        $resultValoracion = $stmtValoracion->get_result();
        $valoracion = $resultValoracion->fetch_assoc();
        
        // Obtener distribución de valoraciones
        $queryDistribucion = "SELECT puntuacion, COUNT(*) as total 
                              FROM valoraciones 
                              WHERE pelicula_id = ? 
                              GROUP BY puntuacion 
                              ORDER BY puntuacion DESC";
        $stmtDistribucion = $this->conn->prepare($queryDistribucion);
        $stmtDistribucion->bind_param("i", $peliculaId);
        $stmtDistribucion->execute();
        $resultDistribucion = $stmtDistribucion->get_result();
        
        $distribucion = [];
        while ($row = $resultDistribucion->fetch_assoc()) {
            $distribucion[$row['puntuacion']] = $row['total'];
        }
        
        // Obtener comentarios
        $comentarios = $this->obtenerComentarios($peliculaId);
        
        return [
            'valoraciones' => [
                'promedio' => $valoracion['promedio'] ? round($valoracion['promedio'], 1) : 0,
                'total' => $valoracion['total']
            ],
            'estadisticas' => $distribucion,
            'comentarios' => $comentarios
        ];
    }
    
    public function obtenerComentarios($peliculaId, $limite = 5) {
        $queryComentarios = "SELECT v.id, v.puntuacion, v.comentario, v.fecha_valoracion,
                            u.id as user_id, pu.nombres, pu.apellidos, m.url as imagen_url
                       FROM valoraciones v
                       JOIN users u ON v.user_id = u.id
                       LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
                       LEFT JOIN multimedia m ON pu.imagen_id = m.id
                       WHERE v.pelicula_id = ? AND v.comentario IS NOT NULL
                       ORDER BY v.fecha_valoracion DESC
                       LIMIT ?";
        $stmtComentarios = $this->conn->prepare($queryComentarios);
        $stmtComentarios->bind_param("ii", $peliculaId, $limite);
        $stmtComentarios->execute();
        $resultComentarios = $stmtComentarios->get_result();
        
        $comentarios = [];
        while ($row = $resultComentarios->fetch_assoc()) {
            $comentarios[] = $row;
        }
        return $comentarios;
    }
    
    public function obtenerValoracionUsuario($userId, $peliculaId) {
        $query = "SELECT puntuacion, comentario FROM valoraciones 
                  WHERE user_id = ? AND pelicula_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $userId, $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    }
}