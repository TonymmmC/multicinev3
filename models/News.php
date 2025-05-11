<?php
class News {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getHighlightedNews($limit = 2) {
        $query = "SELECT id, titulo, contenido, fecha_publicacion, imagen_id
                  FROM contenido_dinamico
                  WHERE tipo = 'noticia' 
                  AND activo = 1
                  AND deleted_at IS NULL
                  ORDER BY fecha_publicacion DESC
                  LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $noticias = [];
        while ($row = $result->fetch_assoc()) {
            // Obtener URL de imagen
            $queryImg = "SELECT url FROM multimedia WHERE id = ?";
            $stmtImg = $this->conn->prepare($queryImg);
            $stmtImg->bind_param('i', $row['imagen_id']);
            $stmtImg->execute();
            $resultImg = $stmtImg->get_result();
            
            $row['imagen_url'] = ($resultImg && $imgRow = $resultImg->fetch_assoc()) 
                ? $imgRow['url'] 
                : 'assets/img/noticia-default.jpg';
            $stmtImg->close();
            
            // Crear resumen
            $row['resumen'] = substr(strip_tags($row['contenido']), 0, 150) . '...';
            $row['link'] = 'noticia.php?id=' . $row['id'];
            
            $noticias[] = $row;
        }
        
        return $noticias;
    }
}
?>