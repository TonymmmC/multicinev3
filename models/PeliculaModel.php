<?php
class PeliculaModel {
    private $db;

    public function __construct() {
        $this->db = require 'config/database.php';
    }

    public function getPosterUrlByPeliculaId($peliculaId) {
        $query = "SELECT m.url 
                 FROM multimedia_pelicula mp 
                 JOIN multimedia m ON mp.multimedia_id = m.id 
                 WHERE mp.pelicula_id = ? AND mp.proposito = 'poster' 
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("i", $peliculaId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $posterUrl = 'assets/img/poster-default.jpg';
        if ($result->num_rows > 0) {
            $poster = $result->fetch_assoc();
            $posterUrl = $poster['url'];
        }
        
        return $posterUrl;
    }
}