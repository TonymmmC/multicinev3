<?php
class Movie {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getFeaturedMovie($cineId = null) {
        $query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, p.estado, 
                         c.codigo as clasificacion,
                         m.url as poster_url,
                         MAX(CASE WHEN mp.proposito = 'banner' THEN m2.url END) as banner_url,
                         pd.sinopsis, pd.url_trailer
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  LEFT JOIN multimedia_pelicula mp2 ON p.id = mp2.pelicula_id AND mp2.proposito = 'banner'
                  LEFT JOIN multimedia m2 ON mp2.multimedia_id = m2.id
                  LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
                  LEFT JOIN funciones f ON p.id = f.pelicula_id
                  LEFT JOIN salas s ON f.sala_id = s.id";

        if ($cineId !== null) {
            $query .= " WHERE p.estado IN ('estreno', 'regular') 
                        AND p.deleted_at IS NULL
                        AND (s.cine_id = ? OR s.cine_id IS NULL)";
        } else {
            $query .= " WHERE p.estado IN ('estreno', 'regular') 
                        AND p.deleted_at IS NULL";
        }

        $query .= " GROUP BY p.id
                    ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
                    LIMIT 1";

        $stmt = $this->conn->prepare($query);
        
        if ($cineId !== null) {
            $stmt->bind_param('i', $cineId);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $pelicula = $result->fetch_assoc();
        $stmt->close();

        // Si no hay película, buscar alternativa
        if (!$pelicula) {
            $query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                             pd.sinopsis, pd.url_trailer, 
                             c.codigo as clasificacion,
                             MAX(CASE WHEN mp.proposito = 'poster' THEN m.url END) as poster_url,
                             MAX(CASE WHEN mp.proposito = 'banner' THEN m.url END) as banner_url
                      FROM peliculas p
                      LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
                      LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                      LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id
                      LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                      WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL
                      GROUP BY p.id
                      ORDER BY p.fecha_estreno DESC
                      LIMIT 1";
            
            $result = $this->conn->query($query);
            $pelicula = $result->fetch_assoc();
        }

        return $pelicula;
    }
    
    public function getMovieFormats($movieId) {
        $query = "SELECT DISTINCT f.nombre 
                  FROM funciones func
                  JOIN formatos f ON func.formato_proyeccion_id = f.id
                  WHERE func.pelicula_id = ? AND func.fecha_hora > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $movieId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $formatos = [];
        while ($row = $result->fetch_assoc()) {
            $formatos[] = strtolower($row['nombre']);
        }
        $stmt->close();
        
        return $formatos;
    }
    
    public function getNowPlayingMovies($cineId = null, $limit = 6) {
        $query = "SELECT DISTINCT p.id, p.titulo, p.duracion_min, p.estado, 
                         c.codigo as clasificacion,
                         m.url as poster_url
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  LEFT JOIN (
                      SELECT pelicula_id, MIN(multimedia_id) as multimedia_id 
                      FROM multimedia_pelicula 
                      WHERE proposito = 'poster' 
                      GROUP BY pelicula_id
                  ) AS mp ON p.id = mp.pelicula_id
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  LEFT JOIN funciones f ON p.id = f.pelicula_id
                  LEFT JOIN salas s ON f.sala_id = s.id
                  WHERE p.estado IN ('estreno', 'regular') 
                  AND p.deleted_at IS NULL";

        if ($cineId !== null) {
            $query .= " AND (s.cine_id = ? OR (s.id IS NULL AND f.id IS NULL))";
        }

        $query .= " GROUP BY p.id, p.titulo, p.duracion_min, p.estado, c.codigo, m.url
                    ORDER BY p.estado = 'estreno' DESC, p.fecha_estreno DESC
                    LIMIT ?";

        $stmt = $this->conn->prepare($query);
        
        if ($cineId !== null) {
            $stmt->bind_param('ii', $cineId, $limit);
        } else {
            $stmt->bind_param('i', $limit);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            $peliculas[] = $row;
        }
        $stmt->close();

        return $peliculas;
    }
    
    public function getUpcomingMovies($limit = 6) {
        $query = "SELECT p.id, p.titulo, p.duracion_min, p.fecha_estreno, 
                         c.codigo as clasificacion,
                         m.url as poster_url
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
                  LEFT JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE p.estado = 'proximo' 
                  AND p.deleted_at IS NULL
                  ORDER BY p.fecha_estreno ASC
                  LIMIT ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $limit);
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