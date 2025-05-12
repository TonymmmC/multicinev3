<?php
require_once __DIR__ . '/../models/PeliculaDetalle.php';
require_once __DIR__ . '/../models/Valoracion.php';
require_once __DIR__ . '/../models/Funcion.php';
require_once __DIR__ . '/Pelicula.php';

class PeliculaDetalleController {
    private $conn;
    private $peliculaDetalleModel;
    private $valoracionModel;
    private $funcionModel;
    private $peliculaController;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->peliculaDetalleModel = new PeliculaDetalle($conn);
        $this->valoracionModel = new Valoracion($conn);
        $this->funcionModel = new Funcion($conn);
        $this->peliculaController = new PeliculaController($conn);
    }
    
    public function mostrarDetalle($peliculaId) {
        // Obtener detalles de la película
        $pelicula = $this->peliculaController->getPeliculaById($peliculaId);
        
        if (!$pelicula) {
            setMensaje('Película no encontrada', 'warning');
            redirect('/multicinev3/');
            return;
        }
        
        // Verificar si está en favoritos
        $esFavorita = $this->peliculaDetalleModel->esFavorita(
            $_SESSION['user_id'] ?? null, 
            $peliculaId
        );
        
        // Configurar rutas de multimedia locales
        $pelicula['multimedia'] = $this->configurarMultimedia($peliculaId, $pelicula);
        
        // Obtener valoraciones
        $valoracionesData = $this->valoracionModel->obtenerDatosValoracion($peliculaId);
        
        // Obtener valoración del usuario actual
        $valoracionUsuario = null;
        if (estaLogueado()) {
            $valoracionUsuario = $this->valoracionModel->obtenerValoracionUsuario(
                $_SESSION['user_id'], 
                $peliculaId
            );
        }
        
        // Obtener formatos disponibles
        $formatos = $this->peliculaDetalleModel->obtenerFormatos($peliculaId);
        
        // Obtener cines con funciones
        $cines = $this->peliculaDetalleModel->obtenerCinesConFunciones($peliculaId);
        
        // Datos para la vista
        $data = [
            'pelicula' => $pelicula,
            'esFavorita' => $esFavorita,
            'valoracionesData' => $valoracionesData,
            'valoracionUsuario' => $valoracionUsuario,
            'formatos' => $formatos,
            'cines' => $cines,
            'peliculaController' => $this->peliculaController
        ];
        
        // Cargar la vista
        require __DIR__ . '/../views/pelicula/detalle.php';
    }
    
    private function configurarMultimedia($peliculaId, $pelicula) {
        // Verificar qué archivos locales existen
        $possiblePosterPaths = [
            "assets/img/posters/{$peliculaId}.jpg",
            "img/posters/{$peliculaId}.jpg"
        ];
        
        $possibleBannerPaths = [
            "assets/img/banners/{$peliculaId}.jpg",
            "img/banners/{$peliculaId}.jpg"
        ];
        
        $possibleTrailerPaths = [
            "assets/videos/trailers/{$peliculaId}.mp4",
            "videos/trailers/{$peliculaId}.mp4"
        ];
        
        $localPosterPath = $this->encontrarArchivoExistente($possiblePosterPaths);
        $localBannerPath = $this->encontrarArchivoExistente($possibleBannerPaths);
        $localTrailerPath = $this->encontrarArchivoExistente($possibleTrailerPaths);
        
        // Configurar multimedia
        $multimedia = $pelicula['multimedia'] ?? [];
        
        if (!isset($multimedia['poster'])) {
            $multimedia['poster'] = ['url' => 'assets/img/poster-default.jpg'];
        } elseif ($localPosterPath) {
            $multimedia['poster']['url'] = $localPosterPath;
        }
        
        if (!isset($multimedia['banner'])) {
            $multimedia['banner'] = ['url' => 'assets/img/movie-backgrounds/default-movie-bg.jpg'];
        } elseif ($localBannerPath) {
            $multimedia['banner']['url'] = $localBannerPath;
        }
        
        $multimedia['hasLocalTrailer'] = !is_null($localTrailerPath);
        $multimedia['localTrailerPath'] = $localTrailerPath;
        
        return $multimedia;
    }
    
    private function encontrarArchivoExistente($paths) {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }
}