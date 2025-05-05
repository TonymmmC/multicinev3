<?php
require_once __DIR__ . '/../models/Pelicula.php';

class PeliculaController {
    private $peliculaModel;
    
    public function __construct($db) {
        $this->peliculaModel = new Pelicula($db);
    }
    
    // Obtener películas en cartelera
    public function getPeliculasCartelera($limit = 6) {
        return $this->peliculaModel->getPeliculasCartelera($limit);
    }
    
    // Obtener películas próximas
    public function getPeliculasProximas($limit = 6) {
        return $this->peliculaModel->getPeliculasProximas($limit);
    }
    
    // Obtener detalle de una película
    public function getPeliculaById($id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if (!$id) {
            return null;
        }
        
        return $this->peliculaModel->getPeliculaById($id);
    }
    
    // Buscar películas por título
    public function buscarPeliculas($termino, $limit = 10) {
        $termino = filter_var($termino, FILTER_SANITIZE_STRING);
        if (empty($termino)) {
            return [];
        }
        
        return $this->peliculaModel->buscarPeliculas($termino, $limit);
    }
    
    // Formatear fecha
    public function formatearFecha($fecha) {
        $timestamp = strtotime($fecha);
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        
        return date('j', $timestamp) . ' de ' . $meses[date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);
    }
    
    // Guardar valoración de película
    public function guardarValoracionPelicula($peliculaId, $userId, $puntuacion, $comentario = null) {
        // Validar datos
        $peliculaId = filter_var($peliculaId, FILTER_VALIDATE_INT);
        $userId = filter_var($userId, FILTER_VALIDATE_INT);
        $puntuacion = filter_var($puntuacion, FILTER_VALIDATE_INT);
        
        if (!$peliculaId || !$userId || $puntuacion < 1 || $puntuacion > 5) {
            return [
                'success' => false,
                'message' => 'Datos de valoración inválidos'
            ];
        }
        
        // Validar que la película existe
        $pelicula = $this->peliculaModel->getPeliculaById($peliculaId);
        if (!$pelicula) {
            return [
                'success' => false,
                'message' => 'La película no existe'
            ];
        }
        
        // Guardar valoración
        return $this->peliculaModel->guardarValoracion($peliculaId, $userId, $puntuacion, $comentario);
    }
    
    // Obtener comentarios de una película
    public function getComentariosPelicula($peliculaId, $limit = 10, $offset = 0) {
        $peliculaId = filter_var($peliculaId, FILTER_VALIDATE_INT);
        if (!$peliculaId) {
            return [];
        }
        
        return $this->peliculaModel->getComentariosPelicula($peliculaId, $limit, $offset);
    }
}