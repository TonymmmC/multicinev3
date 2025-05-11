<?php
require_once 'models/Search.php';

class SearchController {
    private $conn;
    private $searchModel;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->searchModel = new Search($conn);
    }
    
    public function index() {
        $termino = $this->sanitizeInput($_GET['q'] ?? '');
        $resultados = [];
        
        if (!empty($termino)) {
            $resultados = $this->searchModel->buscarPeliculas($termino);
            
            // Registrar búsqueda si el usuario está logueado
            if (estaLogueado()) {
                $this->searchModel->registrarBusqueda($_SESSION['user_id'], $termino, count($resultados));
            }
        }
        
        return [
            'termino' => $termino,
            'resultados' => $resultados
        ];
    }
    
    private function sanitizeInput($input) {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input);
        return $input;
    }
}
?>