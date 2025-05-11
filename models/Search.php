<?php
class Search {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function buscarPeliculas($termino) {
        $searchTerm = "%" . $termino . "%";
        
        $query = "
            SELECT DISTINCT p.id, p.titulo, p.duracion_min, p.estado, p.fecha_estreno, 
                   c.codigo as clasificacion,
                   GROUP_CONCAT(DISTINCT g.nombre SEPARATOR ', ') as generos
            FROM peliculas p
            LEFT JOIN genero_pelicula gp ON p.id = gp.pelicula_id
            LEFT JOIN generos g ON gp.genero_id = g.id
            LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
            WHERE p.deleted_at IS NULL
            AND (
                p.titulo LIKE ? OR 
                p.titulo_original LIKE ? OR
                g.nombre LIKE ?
            )
            GROUP BY p.id
            ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
            LIMIT 20
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            $peliculas[] = $row;
        }
        
        $stmt->close();
        return $peliculas;
    }
    
    public function registrarBusqueda($userId, $termino, $cantidad) {
        $query = "INSERT INTO historial_busqueda (user_id, termino, resultados) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isi", $userId, $termino, $cantidad);
        $stmt->execute();
        $stmt->close();
    }
}
?>