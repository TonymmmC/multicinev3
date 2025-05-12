<?php
// reserva.php
require_once 'includes/functions.php';
iniciarSesion();

// Obtener el ID de la función
$funcionId = isset($_GET['funcion']) ? intval($_GET['funcion']) : null;

if (!$funcionId) {
    setMensaje('No se ha seleccionado una función', 'warning');
    redirect('/multicinev3/');
    exit;
}

// Incluir el controlador y modelos necesarios
require_once 'controllers/ReservaController.php';
require_once 'models/FuncionModel.php';
require_once 'models/AsientoModel.php';
require_once 'models/PeliculaModel.php';

// Instanciar controlador y llamar a la acción
$reservaController = new ReservaController();
$reservaController->index();