<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Procesar formulario de multimedia
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peliculaId = intval($_POST['pelicula_id'] ?? 0);
    $proposito = sanitizeInput($_POST['proposito'] ?? '');
    $url = sanitizeInput($_POST['url'] ?? '');
    
    // Validar datos
    if (empty($peliculaId) || empty($proposito) || empty($url)) {
        setMensaje('Todos los campos son obligatorios', 'danger');
        redirect("pelicula-form.php?id=$peliculaId");
        exit;
    }
    
    // Verificar que la película existe
    $query = "SELECT id FROM peliculas WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $peliculaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        setMensaje('La película no existe', 'danger');
        redirect('peliculas.php');
        exit;
    }
    
    // Verificar si el propósito es válido
    $propositos_validos = ['poster', 'banner', 'galeria', 'trailer'];
    if (!in_array($proposito, $propositos_validos)) {
        setMensaje('El propósito especificado no es válido', 'danger');
        redirect("pelicula-form.php?id=$peliculaId");
        exit;
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Determinar el tipo de multimedia según la URL o proposito
        $tipo = 'imagen';
        if ($proposito === 'trailer' || strpos($url, 'youtube.com') !== false || strpos($url, 'vimeo.com') !== false) {
            $tipo = 'video';
        }
        
        // Insertar en la tabla multimedia
        $query = "INSERT INTO multimedia (tipo, url, descripcion) VALUES (?, ?, ?)";
        $descripcion = "Multimedia para película ID: $peliculaId";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('sss', $tipo, $url, $descripcion);
        $stmt->execute();
        
        $multimediaId = $conn->insert_id;
        
        // Si es póster, eliminar cualquier póster existente
        if ($proposito === 'poster') {
            $query = "DELETE mp FROM multimedia_pelicula mp 
                      JOIN multimedia m ON mp.multimedia_id = m.id 
                      WHERE mp.pelicula_id = ? AND mp.proposito = 'poster'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $peliculaId);
            $stmt->execute();
        }
        
        // Obtener el último orden para este propósito
        $query = "SELECT MAX(orden) as ultimo_orden FROM multimedia_pelicula 
                  WHERE pelicula_id = ? AND proposito = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('is', $peliculaId, $proposito);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $orden = ($row['ultimo_orden'] ?? 0) + 1;
        
        // Insertar relación película-multimedia
        $query = "INSERT INTO multimedia_pelicula (pelicula_id, multimedia_id, proposito, orden) 
                  VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('iisi', $peliculaId, $multimediaId, $proposito, $orden);
        $stmt->execute();
        
        // Registrar en auditoría
        $userId = $_SESSION['user_id'];
        $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                  VALUES (?, 'INSERT', 'multimedia', ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $userId, $multimediaId);
        $stmt->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        setMensaje('Multimedia añadido correctamente', 'success');
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conn->rollback();
        setMensaje('Error al añadir multimedia: ' . $e->getMessage(), 'danger');
    }
    
    redirect("pelicula-form.php?id=$peliculaId");
    exit;
}

// Si no es POST, redirigir
redirect('peliculas.php');