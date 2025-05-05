<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Procesar eliminación de multimedia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['media_id']) && isset($_POST['pelicula_id'])) {
    $mediaId = intval($_POST['media_id']);
    $peliculaId = intval($_POST['pelicula_id']);
    
    // Verificar que la relación existe
    $query = "SELECT mp.id, mp.multimedia_id 
              FROM multimedia_pelicula mp 
              WHERE mp.id = ? AND mp.pelicula_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $mediaId, $peliculaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $multimediaId = $row['multimedia_id'];
        
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Eliminar relación
            $query = "DELETE FROM multimedia_pelicula WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $mediaId);
            $stmt->execute();
            
            // Verificar si existen otras relaciones con ese multimedia
            $query = "SELECT COUNT(*) as total FROM multimedia_pelicula WHERE multimedia_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $multimediaId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            // Si no hay otras relaciones, eliminar el multimedia
            if ($row['total'] === 0) {
                $query = "DELETE FROM multimedia WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $multimediaId);
                $stmt->execute();
            }
            
            // Registrar en auditoría
            $userId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'DELETE', 'multimedia_pelicula', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $userId, $mediaId);
            $stmt->execute();
            
            // Confirmar transacción
            $conn->commit();
            
            setMensaje('Multimedia eliminado correctamente', 'success');
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            setMensaje('Error al eliminar multimedia: ' . $e->getMessage(), 'danger');
        }
    } else {
        setMensaje('El archivo multimedia no existe o no pertenece a esta película', 'danger');
    }
    
    redirect("pelicula-form.php?id=$peliculaId");
    exit;
}

// Si no es POST o faltan parámetros, redirigir
redirect('peliculas.php');
?>