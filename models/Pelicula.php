<?php
class Pelicula {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Obtener películas en cartelera
    public function getPeliculasCartelera($limit = 6) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc,
                         pd.sinopsis, m.url as poster_url
                  FROM peliculas p
                  LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE p.estado IN ('estreno', 'regular') 
                  AND p.deleted_at IS NULL
                  ORDER BY p.fecha_estreno DESC
                  LIMIT ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            // Obtener géneros para cada película
            $row['generos'] = $this->getGenerosPelicula($row['id']);
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
    
    // Obtener películas próximas a estrenarse
    public function getPeliculasProximas($limit = 6) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc,
                         pd.sinopsis, m.url as poster_url
                  FROM peliculas p
                  LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE p.estado = 'proximo'
                  AND p.deleted_at IS NULL
                  ORDER BY p.fecha_estreno ASC
                  LIMIT ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            // Obtener géneros para cada película
            $row['generos'] = $this->getGenerosPelicula($row['id']);
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
    
    // Obtener detalle de una película
    public function getPeliculaById($id) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc,
                         pd.sinopsis, pd.url_trailer
                  FROM peliculas p
                  LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  WHERE p.id = ? AND p.deleted_at IS NULL";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        $pelicula = $result->fetch_assoc();
        
        // Obtener géneros
        $pelicula['generos'] = $this->getGenerosPelicula($id);
        
        // Obtener multimedia
        $pelicula['multimedia'] = $this->getMultimediaPelicula($id);
        
        // Obtener elenco
        $pelicula['elenco'] = $this->getElencoPelicula($id);
        
        return $pelicula;
    }
    
    // Obtener géneros de una película
    private function getGenerosPelicula($peliculaId) {
        $query = "SELECT g.id, g.nombre
                  FROM genero_pelicula gp
                  JOIN generos g ON gp.genero_id = g.id
                  WHERE gp.pelicula_id = ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $generos = [];
        while ($row = $result->fetch_assoc()) {
            $generos[] = $row;
        }
        
        return $generos;
    }
    
    // Obtener multimedia de una película
    private function getMultimediaPelicula($peliculaId) {
        $query = "SELECT m.id, m.tipo, m.url, m.descripcion, mp.proposito
                  FROM multimedia_pelicula mp
                  JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE mp.pelicula_id = ?
                  ORDER BY mp.proposito, mp.orden";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $multimedia = [
            'poster' => null,
            'galeria' => [],
            'trailer' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['proposito'] == 'poster') {
                $multimedia['poster'] = $row;
            } elseif ($row['proposito'] == 'galeria') {
                $multimedia['galeria'][] = $row;
            } elseif ($row['proposito'] == 'trailer') {
                $multimedia['trailer'][] = $row;
            }
        }
        
        return $multimedia;
    }
    
    // Obtener elenco de una película
    private function getElencoPelicula($peliculaId) {
        $query = "SELECT p.id, p.nombre, p.apellido, p.biografia,
                         pp.personaje, rc.nombre as rol, pp.orden_creditos
                  FROM pelicula_persona pp
                  JOIN personas p ON pp.persona_id = p.id
                  JOIN roles_cine rc ON pp.rol_id = rc.id
                  WHERE pp.pelicula_id = ?
                  ORDER BY pp.orden_creditos";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $elenco = [];
        while ($row = $result->fetch_assoc()) {
            $elenco[] = $row;
        }
        
        return $elenco;
    }
    
    // Buscar películas por título
    public function buscarPeliculas($termino, $limit = 10) {
        $termino = "%$termino%";
        
        $query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                         p.estado, m.url as poster_url
                  FROM peliculas p
                  LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE (p.titulo LIKE ? OR p.titulo_original LIKE ?)
                  AND p.deleted_at IS NULL
                  ORDER BY 
                    CASE 
                        WHEN p.estado = 'estreno' THEN 1
                        WHEN p.estado = 'regular' THEN 2
                        WHEN p.estado = 'proximo' THEN 3
                        ELSE 4
                    END,
                    p.fecha_estreno DESC
                  LIMIT ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $termino, $termino, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
}
?>