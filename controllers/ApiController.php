<?php
require_once 'models/AsientoModel.php';

class ApiController {
    private $asientoModel;

    public function __construct() {
        $this->asientoModel = new AsientoModel();
    }

    public function checkSeats() {
        // Get function ID
        $funcionId = isset($_GET['funcion_id']) ? intval($_GET['funcion_id']) : null;

        if (!$funcionId) {
            echo json_encode(['error' => 'Missing function ID']);
            exit;
        }

        // Get reserved seats for this function
        $asientosReservados = $this->asientoModel->getAsientosReservadosByFuncionId($funcionId);

        // Return updated seat information
        echo json_encode([
            'updated' => true,
            'occupied_seats' => $asientosReservados
        ]);
    }
}