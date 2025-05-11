<?php
require_once 'models/Cinema.php';
require_once 'models/Movie.php';
require_once 'models/Event.php';
require_once 'models/News.php';

class HomeController {
    private $conn;
    private $cinemaModel;
    private $movieModel;
    private $eventModel;
    private $newsModel;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->cinemaModel = new Cinema($conn);
        $this->movieModel = new Movie($conn);
        $this->eventModel = new Event($conn);
        $this->newsModel = new News($conn);
    }
    
    public function index() {
        // Obtener cine seleccionado
        $cineSeleccionado = $this->getCineSeleccionado();
        $cineParaConsulta = ($cineSeleccionado == 0) ? null : $cineSeleccionado;
        
        // Guardar preferencia
        $_SESSION['cine_id'] = $cineSeleccionado;
        
        // Obtener datos
        $data = [
            'cineSeleccionado' => $cineSeleccionado,
            'nombreCine' => $this->cinemaModel->getCinemaName($cineSeleccionado),
            'peliculaDestacada' => $this->movieModel->getFeaturedMovie($cineParaConsulta),
            'peliculasCartelera' => $this->movieModel->getNowPlayingMovies($cineParaConsulta),
            'eventosEspeciales' => $this->eventModel->getSpecialEvents($cineParaConsulta),
            'noticiasDestacadas' => $this->newsModel->getHighlightedNews(),
            'peliculasProximas' => $this->movieModel->getUpcomingMovies(),
            'listaCines' => $this->cinemaModel->getAllCinemas()
        ];
        
        // Obtener formatos para película destacada
        if ($data['peliculaDestacada']) {
            $data['peliculaDestacada']['formatos'] = $this->movieModel->getMovieFormats($data['peliculaDestacada']['id']);
        }
        
        return $data;
    }
    
    private function getCineSeleccionado() {
        return isset($_GET['cine']) ? intval($_GET['cine']) : 
            (isset($_SESSION['cine_id']) ? $_SESSION['cine_id'] : 1);
    }
}
?>