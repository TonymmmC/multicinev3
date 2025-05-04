<?php
class Pelicula {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener películas en cartelera con paginación
     * 
     * @param int $limit Número de resultados a mostrar
     * @param int $offset Punto de inicio para la paginación
     * @return array Lista de películas
     */
    public function getPeliculasCartelera($limit = 8, $offset = 0) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, p.estado,
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
                  WHERE p.estado IN ('estreno', 'regular') 
                  AND p.deleted_at IS NULL
                  ORDER BY 
                    CASE 
                        WHEN p.estado = 'estreno' THEN 1
                        ELSE 2
                    END,
                    p.fecha_estreno DESC
                  LIMIT ?, ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            // Obtener póster
            $row['poster_url'] = $this->getPosterPelicula($row['id']);
            
            // Obtener géneros
            $row['generos'] = $this->getGenerosPelicula($row['id']);
            
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
    
    /**
     * Obtener el total de películas en cartelera (para paginación)
     * 
     * @return int Total de películas
     */
    public function getTotalPeliculasCartelera() {
        $query = "SELECT COUNT(*) as total 
                  FROM peliculas 
                  WHERE estado IN ('estreno', 'regular') 
                  AND deleted_at IS NULL";
        
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        
        return (int) $row['total'];
    }
    
    /**
     * Obtener películas próximas a estrenarse
     * 
     * @param int $limit Número de resultados a mostrar
     * @return array Lista de películas
     */
    public function getPeliculasProximas($limit = 6) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
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
            // Obtener póster
            $row['poster_url'] = $this->getPosterPelicula($row['id']);
            
            // Obtener géneros
            $row['generos'] = $this->getGenerosPelicula($row['id']);
            
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
    
    /**
     * Filtrar películas por diversos criterios
     * 
     * @param array $filtros Array asociativo de filtros
     * @param int $limit Número de resultados a mostrar
     * @param int $offset Punto de inicio para la paginación
     * @return array Lista de películas filtradas
     */
    public function filtrarPeliculas($filtros = [], $limit = 8, $offset = 0) {
        $query = "SELECT DISTINCT p.id, p.titulo, p.titulo_original, p.duracion_min, 
                         p.fecha_estreno, p.estado,
                         c.codigo as clasificacion, c.descripcion as clasificacion_desc
                  FROM peliculas p
                  LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id";
        
        // Agregar JOIN para filtrar por género si es necesario
        if (!empty($filtros['genero_id'])) {
            $query .= " JOIN genero_pelicula gp ON p.id = gp.pelicula_id";
        }
        
        // Agregar JOIN para filtrar por cine si es necesario
        if (!empty($filtros['cine_id'])) {
            $query .= " JOIN funciones f ON p.id = f.pelicula_id";
            $query .= " JOIN salas s ON f.sala_id = s.id";
        }
        
        // Condiciones básicas
        $query .= " WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL";
        
        $paramTypes = "";
        $paramValues = [];
        
        // Filtrar por género
        if (!empty($filtros['genero_id'])) {
            $query .= " AND gp.genero_id = ?";
            $paramTypes .= "i";
            $paramValues[] = $filtros['genero_id'];
        }
        
        // Filtrar por cine
        if (!empty($filtros['cine_id'])) {
            $query .= " AND s.cine_id = ? AND f.fecha_hora > NOW()";
            $paramTypes .= "i";
            $paramValues[] = $filtros['cine_id'];
        }
        
        // Filtrar por formato de proyección
        if (!empty($filtros['formato_id'])) {
            if (empty($filtros['cine_id'])) {
                $query .= " AND p.id IN (SELECT DISTINCT pelicula_id FROM funciones WHERE formato_proyeccion_id = ? AND fecha_hora > NOW())";
            } else {
                $query .= " AND f.formato_proyeccion_id = ?";
            }
            $paramTypes .= "i";
            $paramValues[] = $filtros['formato_id'];
        }
        
        // Filtrar por clasificación
        if (!empty($filtros['clasificacion_id'])) {
            $query .= " AND p.clasificacion_id = ?";
            $paramTypes .= "i";
            $paramValues[] = $filtros['clasificacion_id'];
        }
        
        // Ordenar resultados
        if (!empty($filtros['orden'])) {
            switch ($filtros['orden']) {
                case 'titulo':
                    $query .= " ORDER BY p.titulo ASC";
                    break;
                case 'duracion':
                    $query .= " ORDER BY p.duracion_min ASC";
                    break;
                case 'estreno':
                default:
                    $query .= " ORDER BY CASE WHEN p.estado = 'estreno' THEN 1 ELSE 2 END, p.fecha_estreno DESC";
                    break;
            }
        } else {
            $query .= " ORDER BY CASE WHEN p.estado = 'estreno' THEN 1 ELSE 2 END, p.fecha_estreno DESC";
        }
        
        // Paginación
        $query .= " LIMIT ?, ?";
        $paramTypes .= "ii";
        $paramValues[] = $offset;
        $paramValues[] = $limit;
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($paramTypes)) {
            $stmt->bind_param($paramTypes, ...$paramValues);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $peliculas = [];
        while ($row = $result->fetch_assoc()) {
            // Obtener póster
            $row['poster_url'] = $this->getPosterPelicula($row['id']);
            
            // Obtener géneros
            $row['generos'] = $this->getGenerosPelicula($row['id']);
            
            $peliculas[] = $row;
        }
        
        return $peliculas;
    }
    
    /**
     * Obtener el total de películas según filtros (para paginación)
     * 
     * @param array $filtros Array asociativo de filtros
     * @return int Total de películas
     */
    public function getTotalPeliculasFiltradas($filtros = []) {
        $query = "SELECT COUNT(DISTINCT p.id) as total
                  FROM peliculas p";
        
        // Agregar JOIN para filtrar por género si es necesario
        if (!empty($filtros['genero_id'])) {
            $query .= " JOIN genero_pelicula gp ON p.id = gp.pelicula_id";
        }
        
        // Agregar JOIN para filtrar por cine si es necesario
        if (!empty($filtros['cine_id'])) {
            $query .= " JOIN funciones f ON p.id = f.pelicula_id";
            $query .= " JOIN salas s ON f.sala_id = s.id";
        }
        
        // Condiciones básicas
        $query .= " WHERE p.estado IN ('estreno', 'regular') AND p.deleted_at IS NULL";
        
        $paramTypes = "";
        $paramValues = [];
        
        // Filtrar por género
        if (!empty($filtros['genero_id'])) {
            $query .= " AND gp.genero_id = ?";
            $paramTypes .= "i";
            $paramValues[] = $filtros['genero_id'];
        }
        
        // Filtrar por cine
        if (!empty($filtros['cine_id'])) {
            $query .= " AND s.cine_id = ? AND f.fecha_hora > NOW()";
            $paramTypes .= "i";
            $paramValues[] = $filtros['cine_id'];
        }
        
        // Filtrar por formato de proyección
        if (!empty($filtros['formato_id'])) {
            if (empty($filtros['cine_id'])) {
                $query .= " AND p.id IN (SELECT DISTINCT pelicula_id FROM funciones WHERE formato_proyeccion_id = ? AND fecha_hora > NOW())";
            } else {
                $query .= " AND f.formato_proyeccion_id = ?";
            }
            $paramTypes .= "i";
            $paramValues[] = $filtros['formato_id'];
        }
        
        // Filtrar por clasificación
        if (!empty($filtros['clasificacion_id'])) {
            $query .= " AND p.clasificacion_id = ?";
            $paramTypes .= "i";
            $paramValues[] = $filtros['clasificacion_id'];
        }
        
        $stmt = $this->conn->prepare($query);
        
        if (!empty($paramTypes)) {
            $stmt->bind_param($paramTypes, ...$paramValues);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return (int) $row['total'];
    }
    
    /**
     * Obtener detalle de una película
     * 
     * @param int $id ID de la película
     * @return array|null Datos de la película o null si no existe
     */
    public function getPeliculaById($id) {
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, p.fecha_estreno, 
                         p.fecha_salida, p.estado,
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
        
        // Obtener póster
        $pelicula['poster_url'] = $this->getPosterPelicula($id);
        
        // Obtener géneros
        $pelicula['generos'] = $this->getGenerosPelicula($id);
        
        // Obtener multimedia
        $pelicula['multimedia'] = $this->getMultimediaPelicula($id);
        
        // Obtener elenco
        $pelicula['elenco'] = $this->getElencoPelicula($id);
        
        // Obtener funciones disponibles
        $pelicula['funciones'] = $this->getFuncionesPelicula($id);
        
        return $pelicula;
    }
    
    /**
     * Obtener la URL del póster de una película
     * 
     * @param int $peliculaId ID de la película
     * @return string URL del póster o imagen por defecto
     */
    private function getPosterPelicula($peliculaId) {
        $query = "SELECT m.url 
                  FROM multimedia_pelicula mp
                  JOIN multimedia m ON mp.multimedia_id = m.id
                  WHERE mp.pelicula_id = ? AND mp.proposito = 'poster'
                  LIMIT 1";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['url'];
        }
        
        return 'assets/img/poster-default.jpg';
    }
    
    /**
     * Obtener géneros de una película
     * 
     * @param int $peliculaId ID de la película
     * @return array Lista de géneros
     */
    private function getGenerosPelicula($peliculaId) {
        $query = "SELECT g.id, g.nombre, g.icono_url
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
    
    /**
     * Obtener multimedia de una película (posters, galerías, trailers)
     * 
     * @param int $peliculaId ID de la película
     * @return array Multimedia organizada por tipo
     */
    private function getMultimediaPelicula($peliculaId) {
        $query = "SELECT m.id, m.tipo, m.url, m.descripcion, mp.proposito, mp.orden
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
            'trailer' => [],
            'banner' => []
        ];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['proposito'] == 'poster') {
                $multimedia['poster'] = $row;
            } elseif ($row['proposito'] == 'galeria') {
                $multimedia['galeria'][] = $row;
            } elseif ($row['proposito'] == 'trailer') {
                $multimedia['trailer'][] = $row;
            } elseif ($row['proposito'] == 'banner') {
                $multimedia['banner'][] = $row;
            }
        }
        
        return $multimedia;
    }
    
    /**
     * Obtener elenco de una película
     * 
     * @param int $peliculaId ID de la película
     * @return array Lista de personas del elenco
     */
    private function getElencoPelicula($peliculaId) {
        $query = "SELECT p.id, p.nombre, p.apellido, p.biografia,
                         pp.personaje, rc.nombre as rol, pp.orden_creditos,
                         m.url as imagen_url
                  FROM pelicula_persona pp
                  JOIN personas p ON pp.persona_id = p.id
                  JOIN roles_cine rc ON pp.rol_id = rc.id
                  LEFT JOIN multimedia m ON p.imagen_id = m.id
                  WHERE pp.pelicula_id = ?
                  ORDER BY pp.rol_id, pp.orden_creditos";
                  
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
    
    /**
     * Obtener funciones disponibles de una película
     * 
     * @param int $peliculaId ID de la película
     * @return array Lista de funciones
     */
    private function getFuncionesPelicula($peliculaId) {
        $query = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles,
                         s.nombre as sala, c.nombre as cine, c.id as cine_id,
                         i.nombre as idioma, fp.nombre as formato
                  FROM funciones f
                  JOIN salas s ON f.sala_id = s.id
                  JOIN cines c ON s.cine_id = c.id
                  JOIN idiomas i ON f.idioma_id = i.id
                  JOIN formatos fp ON f.formato_proyeccion_id = fp.id
                  WHERE f.pelicula_id = ?
                  AND f.fecha_hora > NOW()
                  ORDER BY f.fecha_hora
                  LIMIT 20";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();

        $funciones = [];
        while ($row = $result->fetch_assoc()) {
            $funciones[] = $row;
        }

        return $funciones;
    }
    
    /**
     * Buscar películas por título o palabras clave
     * 
     * @param string $termino Término de búsqueda
     * @param int $limit Número de resultados a mostrar
     * @return array Lista de películas coincidentes
     */
    public function buscarPeliculas($termino, $limit = 10) {
        $termino = "%$termino%";
        
        $query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, 
                         p.fecha_estreno, p.estado
                  FROM peliculas p
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
            // Obtener póster
            $row['poster_url'] = $this->getPosterPelicula($row['id']);
            
            $peliculas[] = $row;
        }
        
        // Registrar la búsqueda si es necesario (para recomendaciones futuras)
        // Esta funcionalidad puede expandirse según necesidades
        
        return $peliculas;
    }
    
    /**
     * Registrar valoración de una película
     * 
     * @param int $peliculaId ID de la película
     * @param int $usuarioId ID del usuario
     * @param int $puntuacion Puntuación (1-5)
     * @param string|null $comentario Comentario opcional
     * @return array Resultado de la operación
     */
    public function valorarPelicula($peliculaId, $usuarioId, $puntuacion, $comentario = null) {
        // Validar la puntuación
        if ($puntuacion < 1 || $puntuacion > 5) {
            return [
                'success' => false,
                'message' => 'La puntuación debe estar entre 1 y 5'
            ];
        }
        
        // Verificar si ya existe una valoración
        $query = "SELECT id FROM valoraciones 
                  WHERE pelicula_id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $peliculaId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $this->conn->begin_transaction();
        
        try {
            if ($result->num_rows > 0) {
                // Actualizar valoración existente
                $row = $result->fetch_assoc();
                $valoracionId = $row['id'];
                
                $query = "UPDATE valoraciones 
                          SET puntuacion = ?, comentario = ?, fecha_valoracion = NOW() 
                          WHERE id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("isi", $puntuacion, $comentario, $valoracionId);
                $stmt->execute();
            } else {
                // Crear nueva valoración
                $query = "INSERT INTO valoraciones 
                          (pelicula_id, user_id, puntuacion, comentario, fecha_valoracion) 
                          VALUES (?, ?, ?, ?, NOW())";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("iiis", $peliculaId, $usuarioId, $puntuacion, $comentario);
                $stmt->execute();
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Valoración guardada correctamente'
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            
            return [
                'success' => false,
                'message' => 'Error al guardar la valoración: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener valoraciones de una película
     * 
     * @param int $peliculaId ID de la película
     * @param int $limit Número de valoraciones a mostrar
     * @param int $offset Punto de inicio para la paginación
     * @return array Lista de valoraciones
     */
    public function getValoracionesPelicula($peliculaId, $limit = 10, $offset = 0) {
        $query = "SELECT v.id, v.puntuacion, v.comentario, v.fecha_valoracion,
                         u.id as user_id, pu.nombres, pu.apellidos, m.url as imagen_url
                  FROM valoraciones v
                  JOIN users u ON v.user_id = u.id
                  LEFT JOIN perfiles_usuario pu ON u.id = pu.user_id
                  LEFT JOIN multimedia m ON pu.imagen_id = m.id
                  WHERE v.pelicula_id = ?
                  ORDER BY v.fecha_valoracion DESC
                  LIMIT ?, ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $peliculaId, $offset, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $valoraciones = [];
        while ($row = $result->fetch_assoc()) {
            $valoraciones[] = $row;
        }
        
        return $valoraciones;
    }
    
    /**
     * Obtener estadísticas de valoraciones de una película
     * 
     * @param int $peliculaId ID de la película
     * @return array Estadísticas de valoraciones
     */
    public function getEstadisticasValoraciones($peliculaId) {
        $query = "SELECT 
                    COUNT(*) as total_valoraciones,
                    AVG(puntuacion) as puntuacion_promedio,
                    SUM(CASE WHEN puntuacion = 5 THEN 1 ELSE 0 END) as cinco_estrellas,
                    SUM(CASE WHEN puntuacion = 4 THEN 1 ELSE 0 END) as cuatro_estrellas,
                    SUM(CASE WHEN puntuacion = 3 THEN 1 ELSE 0 END) as tres_estrellas,
                    SUM(CASE WHEN puntuacion = 2 THEN 1 ELSE 0 END) as dos_estrellas,
                    SUM(CASE WHEN puntuacion = 1 THEN 1 ELSE 0 END) as una_estrella
                  FROM valoraciones
                  WHERE pelicula_id = ?";
                  
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'total_valoraciones' => 0,
                'puntuacion_promedio' => 0,
                'cinco_estrellas' => 0,
                'cuatro_estrellas' => 0,
                'tres_estrellas' => 0,
                'dos_estrellas' => 0,
                'una_estrella' => 0
            ];
        }
        
        $estadisticas = $result->fetch_assoc();
        $estadisticas['puntuacion_promedio'] = round($estadisticas['puntuacion_promedio'], 1);
        
        return $estadisticas;
    }
    
    /**
     * Marcar una película como favorita para un usuario
     * 
     * @param int $peliculaId ID de la película
     * @param int $usuarioId ID del usuario
     * @return array Resultado de la operación
     */
    public function marcarFavorito($peliculaId, $usuarioId) {
        // Verificar si ya existe como favorito
        $query = "SELECT id FROM favoritos 
                  WHERE pelicula_id = ? AND user_id = ? AND cine_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $peliculaId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Ya está marcado como favorito
            return [
                'success' => true,
                'message' => 'La película ya está en tus favoritos',
                'status' => 'already_exists'
            ];
        }
        
        // Agregar a favoritos
        $query = "INSERT INTO favoritos (user_id, pelicula_id, fecha_agregado) 
                  VALUES (?, ?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $usuarioId, $peliculaId);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'message' => 'Película agregada a favoritos',
                'status' => 'added'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al agregar a favoritos',
                'status' => 'error'
            ];
        }
    }
    
    /**
     * Quitar una película de favoritos
     * 
     * @param int $peliculaId ID de la película
     * @param int $usuarioId ID del usuario
     * @return array Resultado de la operación
     */
    public function quitarFavorito($peliculaId, $usuarioId) {
        $query = "DELETE FROM favoritos 
                  WHERE pelicula_id = ? AND user_id = ? AND cine_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $peliculaId, $usuarioId);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return [
                'success' => true,
                'message' => 'Película eliminada de favoritos',
                'status' => 'removed'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'La película no está en tus favoritos',
                'status' => 'not_found'
            ];
        }
    }
    
    /**
     * Verificar si una película es favorita de un usuario
     * 
     * @param int $peliculaId ID de la película
     * @param int $usuarioId ID del usuario
     * @return bool true si es favorita, false en caso contrario
     */
    public function esFavorita($peliculaId, $usuarioId) {
        $query = "SELECT id FROM favoritos 
                  WHERE pelicula_id = ? AND user_id = ? AND cine_id IS NULL";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $peliculaId, $usuarioId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
}