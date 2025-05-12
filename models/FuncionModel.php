<?php
class FuncionModel {
    private $db;

    public function __construct() {
        $this->db = require 'config/database.php';
    }

    public function getFuncionById($funcionId) {
        $query = "SELECT f.*, p.titulo as pelicula_titulo, p.duracion_min, s.nombre as sala_nombre, 
                s.capacidad, c.nombre as cine_nombre, c.direccion,
                i.nombre as idioma, fmt.nombre as formato, p.id as pelicula_id
                FROM funciones f 
                JOIN peliculas p ON f.pelicula_id = p.id 
                JOIN salas s ON f.sala_id = s.id 
                JOIN cines c ON s.cine_id = c.id 
                JOIN idiomas i ON f.idioma_id = i.id 
                JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id 
                WHERE f.id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $funcionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
}