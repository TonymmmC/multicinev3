<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Verificar que el usuario esté logueado
if (!estaLogueado()) {
    setMensaje('Debes iniciar sesión para valorar películas', 'warning');
    redirect('auth/login.php');
    exit;
}

// Procesar envío de valoración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peliculaId = isset($_POST['pelicula_id']) ? intval($_POST['pelicula_id']) : 0;
    $puntuacion = isset($_POST['puntuacion']) ? intval($_POST['puntuacion']) : 0;
    $comentario = isset($_POST['comentario']) ? sanitizeInput($_POST['comentario']) : null;
    
    if ($peliculaId <= 0 || $puntuacion < 1 || $puntuacion > 5) {
        setMensaje('Datos de valoración inválidos', 'danger');
        redirect("pelicula.php?id=$peliculaId");
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Verificar si ya existe una valoración
    $query = "SELECT id FROM valoraciones WHERE user_id = ? AND pelicula_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $peliculaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar valoración existente
        $valoracionId = $result->fetch_assoc()['id'];
        $query = "UPDATE valoraciones SET 
                  puntuacion = ?, 
                  comentario = ?,
                  fecha_valoracion = NOW()
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isi", $puntuacion, $comentario, $valoracionId);
        
        if ($stmt->execute()) {
            setMensaje('Valoración actualizada correctamente', 'success');
        } else {
            setMensaje('Error al actualizar la valoración', 'danger');
        }
    } else {
        // Crear nueva valoración
        $query = "INSERT INTO valoraciones 
                  (user_id, pelicula_id, puntuacion, comentario) 
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiis", $userId, $peliculaId, $puntuacion, $comentario);
        
        if ($stmt->execute()) {
            setMensaje('Valoración enviada correctamente', 'success');
        } else {
            setMensaje('Error al enviar la valoración', 'danger');
        }
    }
    
    // Redireccionar a la página de la película
    redirect("pelicula.php?id=$peliculaId#valoraciones");
    exit;
} else {
    // Si no es POST, redireccionar a la página principal
    redirect('index.php');
    exit;
}
?>