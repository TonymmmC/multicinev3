<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Inicializar variables
$funcion = [
    'id' => null,
    'pelicula_id' => null,
    'sala_id' => null,
    'fecha_hora' => date('Y-m-d\TH:i'),
    'idioma_id' => null,
    'formato_proyeccion_id' => null,
    'precio_base' => 50.00, // Precio por defecto
    'asientos_disponibles' => 0
];

$accion = 'crear';
$titulo_pagina = 'Nueva Función';

// Obtener información de la función si estamos editando
if (isset($_GET['id'])) {
    $funcionId = intval($_GET['id']);
    
    // Consultar función
    $query = "SELECT * FROM funciones WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $funcionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $funcion = $result->fetch_assoc();
        
        // Convertir fecha_hora al formato HTML datetime-local
        $funcion['fecha_hora'] = date('Y-m-d\TH:i', strtotime($funcion['fecha_hora']));
        
        $accion = 'editar';
        
        // Obtener datos adicionales para el título
        $query = "SELECT p.titulo, c.nombre as cine, s.nombre as sala
                 FROM funciones f
                 JOIN peliculas p ON f.pelicula_id = p.id
                 JOIN salas s ON f.sala_id = s.id
                 JOIN cines c ON s.cine_id = c.id
                 WHERE f.id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $funcionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            $titulo_pagina = 'Editar Función: ' . $row['titulo'] . ' - ' . $row['cine'] . ' / ' . $row['sala'];
        } else {
            $titulo_pagina = 'Editar Función #' . $funcionId;
        }
        
        // Verificar si la función ya ha ocurrido
        if (strtotime($funcion['fecha_hora']) < time()) {
            setMensaje('No se pueden editar funciones que ya han ocurrido', 'warning');
            redirect('funciones.php');
            exit;
        }
    } else {
        setMensaje('La función no existe', 'danger');
        redirect('funciones.php');
        exit;
    }
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y validar datos
    $pelicula_id = intval($_POST['pelicula_id'] ?? 0);
    $sala_id = intval($_POST['sala_id'] ?? 0);
    $fecha_hora = $_POST['fecha_hora'] ?? '';
    $idioma_id = intval($_POST['idioma_id'] ?? 0);
    $formato_proyeccion_id = intval($_POST['formato_proyeccion_id'] ?? 0);
    $precio_base = floatval($_POST['precio_base'] ?? 0);
    
    // Validar datos
    $errores = [];
    
    if ($pelicula_id <= 0) {
        $errores[] = 'Debe seleccionar una película';
    }
    
    if ($sala_id <= 0) {
        $errores[] = 'Debe seleccionar una sala';
    }
    
    if (empty($fecha_hora)) {
        $errores[] = 'La fecha y hora es obligatoria';
    } else {
        // Convertir fecha_hora al formato de MySQL
        $fecha_hora = date('Y-m-d H:i:s', strtotime($fecha_hora));
        
        // Verificar que la fecha es futura
        if (strtotime($fecha_hora) < time()) {
            $errores[] = 'La fecha y hora debe ser futura';
        }
    }
    
    if ($idioma_id <= 0) {
        $errores[] = 'Debe seleccionar un idioma';
    }
    
    if ($formato_proyeccion_id <= 0) {
        $errores[] = 'Debe seleccionar un formato de proyección';
    }
    
    if ($precio_base <= 0) {
        $errores[] = 'El precio base debe ser mayor que 0';
    }
    
    // Verificar si la sala está disponible en ese horario
    if (empty($errores)) {
        // Obtener duración de la película
        $query = "SELECT duracion_min FROM peliculas WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $pelicula_id);
        $stmt->execute();
        $resultPelicula = $stmt->get_result();
        
        if ($resultPelicula && $pelicula = $resultPelicula->fetch_assoc()) {
            $duracion_min = $pelicula['duracion_min'];
            
            // Calcular fecha fin de la función (sumando duración y 30 minutos de preparación)
            $fecha_fin = date('Y-m-d H:i:s', strtotime($fecha_hora) + ($duracion_min + 30) * 60);
            
            // Buscar funciones que se solapen con la nueva función en la misma sala
            $query = "SELECT f.id, p.titulo, f.fecha_hora
                     FROM funciones f
                     JOIN peliculas p ON f.pelicula_id = p.id
                     WHERE f.sala_id = ?
                     AND (
                         (f.fecha_hora BETWEEN ? AND ?) OR
                         (DATE_ADD(f.fecha_hora, INTERVAL (p.duracion_min + 30) MINUTE) BETWEEN ? AND ?)
                     )";
                     
            if ($accion === 'editar') {
                $query .= " AND f.id != ?";
            }
            
            $stmt = $conn->prepare($query);
            
            if ($accion === 'editar') {
                $stmt->bind_param('issssi', $sala_id, $fecha_hora, $fecha_fin, $fecha_hora, $fecha_fin, $funcion['id']);
            } else {
                $stmt->bind_param('issss', $sala_id, $fecha_hora, $fecha_fin, $fecha_hora, $fecha_fin);
            }
            
            $stmt->execute();
            $resultSolape = $stmt->get_result();
            
            if ($resultSolape && $resultSolape->num_rows > 0) {
                $solapeFuncion = $resultSolape->fetch_assoc();
                $errores[] = 'La sala no está disponible en ese horario. Hay una función de "' . 
                             $solapeFuncion['titulo'] . '" programada para el ' . 
                             date('d/m/Y H:i', strtotime($solapeFuncion['fecha_hora']));
            }
        } else {
            $errores[] = 'La película seleccionada no existe';
        }
    }
    
    // Obtener capacidad de la sala
    if (empty($errores)) {
        $query = "SELECT capacidad FROM salas WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $sala_id);
        $stmt->execute();
        $resultSala = $stmt->get_result();
        
        if ($resultSala && $sala = $resultSala->fetch_assoc()) {
            $asientos_disponibles = $sala['capacidad'];
        } else {
            $errores[] = 'La sala seleccionada no existe';
        }
    }
    
        // Si no hay errores, proceder con la inserción/actualización
        if (empty($errores)) {
            if ($accion === 'crear') {
                // Insertar función
                $query = "INSERT INTO funciones (
                            pelicula_id, sala_id, fecha_hora, idioma_id,
                            formato_proyeccion_id, precio_base, asientos_disponibles
                          ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iisiidd', $pelicula_id, $sala_id, $fecha_hora, $idioma_id, 
                                  $formato_proyeccion_id, $precio_base, $asientos_disponibles);
                
                if ($stmt->execute()) {
                    $funcionId = $conn->insert_id;
                    
                    // Registrar acción en auditoría
                    $userId = $_SESSION['user_id'];
                    $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                              VALUES (?, 'INSERT', 'funciones', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('ii', $userId, $funcionId);
                    $stmt->execute();
                    
                    setMensaje('Función creada correctamente', 'success');
                    redirect('funciones.php');
                    exit;
                } else {
                    $errores[] = 'Error al crear la función: ' . $conn->error;
                }
            } else {
                // Actualizar función
                $query = "UPDATE funciones SET 
                          pelicula_id = ?, 
                          sala_id = ?, 
                          fecha_hora = ?, 
                          idioma_id = ?,
                          formato_proyeccion_id = ?, 
                          precio_base = ?
                          WHERE id = ?";
            
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iisiidi', $pelicula_id, $sala_id, $fecha_hora, $idioma_id, 
                                  $formato_proyeccion_id, $precio_base, $funcion['id']);
            
                if ($stmt->execute()) {
                    // Registrar acción en auditoría
                    $userId = $_SESSION['user_id'];
                    $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                              VALUES (?, 'UPDATE', 'funciones', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('ii', $userId, $funcion['id']);
                    $stmt->execute();
                    
                    setMensaje('Función actualizada correctamente', 'success');
                    redirect('funciones.php');
                    exit;
                } else {
                    $errores[] = 'Error al actualizar la función: ' . $conn->error;
                }
            } // This closes the else block
        }
    }
    
    // Remove the 'error:' label unless you're using it for error handling
    // error: