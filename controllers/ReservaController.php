<?php
require_once 'models/FuncionModel.php';
require_once 'models/AsientoModel.php';
require_once 'models/PeliculaModel.php';

class ReservaController {
    private $funcionModel;
    private $asientoModel;
    private $peliculaModel;

    public function __construct() {
        $this->funcionModel = new FuncionModel();
        $this->asientoModel = new AsientoModel();
        $this->peliculaModel = new PeliculaModel();
    }

    public function index() {
        // Get function ID from URL
        $funcionId = isset($_GET['funcion']) ? intval($_GET['funcion']) : null;

        if (!$funcionId) {
            setMensaje('No se ha seleccionado una función', 'warning');
            redirect('/multicinev3/');
        }

        // Get function details
        $funcion = $this->funcionModel->getFuncionById($funcionId);

        if (!$funcion) {
            setMensaje('Función no encontrada', 'warning');
            redirect('/multicinev3/');
        }

        // Get all seats for this room
        $asientos = $this->asientoModel->getAsientosBySalaId($funcion['sala_id']);

        // Get reserved seats for this function
        $asientosReservados = $this->asientoModel->getAsientosReservadosByFuncionId($funcionId);

        // Get movie poster
        $posterUrl = $this->peliculaModel->getPosterUrlByPeliculaId($funcion['pelicula_id']);

        // Load view
        require_once 'views/reserva/index.php';
    }

    public function procesarReserva() {
        // This would handle form submission from the reservation page
        // Process selected seats and redirect to checkout summary
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
            $asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';

            if (!$funcionId || empty($asientosSeleccionados)) {
                setMensaje('Datos de reserva inválidos', 'error');
                redirect('/multicinev3/');
            }

            // Store selected seats in session for use in checkout page
            $_SESSION['asientos_seleccionados'] = explode(',', $asientosSeleccionados);
            $_SESSION['funcion_id'] = $funcionId;

            // Redirect to checkout page
            redirect('resumen_compra.php');
        }
    }
}