<?php
// router.php
require_once 'includes/functions.php';
iniciarSesion();

define('CONTROLLERS', [
    'reserva' => 'ReservaController',
    'api' => 'ApiController'
]);

$controller = isset($_GET['c']) ? $_GET['c'] : '';
$action = isset($_GET['a']) ? $_GET['a'] : 'index';

if (array_key_exists($controller, CONTROLLERS)) {
    $controllerName = CONTROLLERS[$controller];
    $controllerFile = "controllers/{$controllerName}.php";
    
    if (file_exists($controllerFile)) {
        require_once $controllerFile;
        $controllerInstance = new $controllerName();
        
        if (method_exists($controllerInstance, $action)) {
            $controllerInstance->$action();
        } else {
            setMensaje('Acción no encontrada', 'error');
            redirect('/multicinev3/');
        }
    } else {
        setMensaje('Controlador no encontrado', 'error');
        redirect('/multicinev3/');
    }
} else {
    setMensaje('Controlador no válido', 'error');
    redirect('/multicinev3/');
}