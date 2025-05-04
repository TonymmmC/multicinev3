<?php
require_once '../includes/functions.php';
iniciarSesion();

$conn = require '../config/database.php';
require_once '../controllers/Usuario.php';
require_once '../models/Usuario.php';

$usuarioController = new UsuarioController($conn);
$usuarioController->logout();

redirect('/multicinev3/');
?>