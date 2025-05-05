<?php
$servidor = "localhost";
$usuario = "root";
$password = "";
$basedatos = "multicinev2";

// Crear conexión
$conn = mysqli_connect($servidor, $usuario, $password, $basedatos);

// Verificar conexión
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Establecer charset para soportar emojis
mysqli_set_charset($conn, "utf8mb4");

return $conn;
?>