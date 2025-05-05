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
$pelicula = [
    'id' => null,
    'titulo' => '',
    'titulo_original' => '',
    'duracion_min' => 90,
    'clasificacion_id' => null,
    'fecha_estreno' => date('Y-m-d'),
    'fecha_salida' => null,
    'estado' => 'proximo',
    'sinopsis' => '',
    'url_trailer' => '',
    'generos' => []
];

$accion = 'crear';
$titulo_pagina = 'Nueva Película';

// Obtener información de la película si estamos editando
if (isset($_GET['id'])) {
    $peliculaId = intval($_GET['id']);
    
    // Consultar película
    $query = "SELECT p.*, pd.sinopsis, pd.url_trailer
              FROM peliculas p
              LEFT JOIN peliculas_detalle pd ON p.id = pd.pelicula_id
              WHERE p.id = ? AND p.deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $peliculaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $pelicula = $result->fetch_assoc();
        
        // Obtener géneros de la película
        $query = "SELECT genero_id FROM genero_pelicula WHERE pelicula_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $peliculaId);
        $stmt->execute();
        $resultGeneros = $stmt->get_result();
        
        $pelicula['generos'] = [];
        while ($row = $resultGeneros->fetch_assoc()) {
            $pelicula['generos'][] = $row['genero_id'];
        }
        
        $accion = 'editar';
        $titulo_pagina = 'Editar Película: ' . $pelicula['titulo'];
    } else {
        setMensaje('La película no existe', 'danger');
        redirect('peliculas.php');
        exit;
    }
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y validar datos
    $titulo = sanitizeInput($_POST['titulo'] ?? '');
    $titulo_original = sanitizeInput($_POST['titulo_original'] ?? '');
    $duracion_min = intval($_POST['duracion_min'] ?? 0);
    $clasificacion_id = !empty($_POST['clasificacion_id']) ? intval($_POST['clasificacion_id']) : null;
    $fecha_estreno = $_POST['fecha_estreno'] ?? null;
    $fecha_salida = !empty($_POST['fecha_salida']) ? $_POST['fecha_salida'] : null;
    $estado = sanitizeInput($_POST['estado'] ?? '');
    $sinopsis = sanitizeInput($_POST['sinopsis'] ?? '');
    $url_trailer = sanitizeInput($_POST['url_trailer'] ?? '');
    $generos = isset($_POST['generos']) && is_array($_POST['generos']) ? $_POST['generos'] : [];
    
    // Validar datos
    $errores = [];
    
    if (empty($titulo)) {
        $errores[] = 'El título es obligatorio';
    }
    
    if ($duracion_min <= 0) {
        $errores[] = 'La duración debe ser mayor que 0';
    }
    
    if (empty($fecha_estreno)) {
        $errores[] = 'La fecha de estreno es obligatoria';
    }
    
    // Si no hay errores, proceder con la inserción/actualización
    if (empty($errores)) {
        // Comenzar transacción
        $conn->begin_transaction();
        
        try {
            if ($accion === 'crear') {
                // Insertar película
                $query = "INSERT INTO peliculas (
                            titulo, titulo_original, duracion_min, clasificacion_id,
                            fecha_estreno, fecha_salida, estado
                          ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssiisss', $titulo, $titulo_original, $duracion_min, $clasificacion_id, $fecha_estreno, $fecha_salida, $estado);
                $stmt->execute();
                
                $peliculaId = $conn->insert_id;
                
                // Insertar detalles
                $query = "INSERT INTO peliculas_detalle (pelicula_id, sinopsis, url_trailer) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('iss', $peliculaId, $sinopsis, $url_trailer);
                $stmt->execute();
                
                // Registrar acción en auditoría
                $userId = $_SESSION['user_id'];
                $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                          VALUES (?, 'INSERT', 'peliculas', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ii', $userId, $peliculaId);
                $stmt->execute();
                
                $mensaje = 'Película creada correctamente';
            } else {
                // Actualizar película
                $query = "UPDATE peliculas SET 
                            titulo = ?, 
                            titulo_original = ?, 
                            duracion_min = ?, 
                            clasificacion_id = ?,
                            fecha_estreno = ?, 
                            fecha_salida = ?, 
                            estado = ?
                          WHERE id = ?";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssiisssi', $titulo, $titulo_original, $duracion_min, $clasificacion_id, $fecha_estreno, $fecha_salida, $estado, $pelicula['id']);
                $stmt->execute();
                
                // Actualizar detalles
                $query = "SELECT pelicula_id FROM peliculas_detalle WHERE pelicula_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $pelicula['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Actualizar registro existente
                    $query = "UPDATE peliculas_detalle SET sinopsis = ?, url_trailer = ? WHERE pelicula_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('ssi', $sinopsis, $url_trailer, $pelicula['id']);
                } else {
                    // Crear nuevo registro de detalles
                    $query = "INSERT INTO peliculas_detalle (pelicula_id, sinopsis, url_trailer) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('iss', $pelicula['id'], $sinopsis, $url_trailer);
                }
                $stmt->execute();
                
                // Registrar acción en auditoría
                $userId = $_SESSION['user_id'];
                $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                          VALUES (?, 'UPDATE', 'peliculas', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ii', $userId, $pelicula['id']);
                $stmt->execute();
                
                $peliculaId = $pelicula['id'];
                $mensaje = 'Película actualizada correctamente';
            }
            
            // Eliminar géneros existentes para esta película
            $query = "DELETE FROM genero_pelicula WHERE pelicula_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $peliculaId);
            $stmt->execute();
            
            // Insertar nuevos géneros
            if (!empty($generos)) {
                $query = "INSERT INTO genero_pelicula (pelicula_id, genero_id) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                
                foreach ($generos as $generoId) {
                    $stmt->bind_param('ii', $peliculaId, $generoId);
                    $stmt->execute();
                }
            }
            
            // Confirmar transacción
            $conn->commit();
            
            setMensaje($mensaje, 'success');
            redirect('peliculas.php');
            exit;
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            setMensaje('Error al guardar la película: ' . $e->getMessage(), 'danger');
        }
    }
}

// Obtener clasificaciones para el select
$clasificaciones = [];
$query = "SELECT id, codigo, descripcion FROM clasificaciones ORDER BY edad_minima";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $clasificaciones[] = $row;
    }
}

// Obtener géneros para el multiselect
$generos = [];
$query = "SELECT id, nombre FROM generos ORDER BY nombre";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $generos[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $titulo_pagina; ?></h1>
            <a href="peliculas.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        
        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <form action="" method="post">
                    <!-- Campos ocultos para edición -->
                    <?php if ($accion === 'editar'): ?>
                        <input type="hidden" name="id" value="<?php echo $pelicula['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="titulo">Título <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="titulo" name="titulo" 
                                       value="<?php echo $pelicula['titulo']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="titulo_original">Título Original</label>
                                <input type="text" class="form-control" id="titulo_original" name="titulo_original" 
                                       value="<?php echo $pelicula['titulo_original']; ?>">
                                <small class="form-text text-muted">Dejar en blanco si es el mismo que el título</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="duracion_min">Duración (minutos) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="duracion_min" name="duracion_min" 
                                       value="<?php echo $pelicula['duracion_min']; ?>" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="clasificacion_id">Clasificación</label>
                                <select class="form-control" id="clasificacion_id" name="clasificacion_id">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($clasificaciones as $clasificacion): ?>
                                        <option value="<?php echo $clasificacion['id']; ?>" 
                                                <?php echo $pelicula['clasificacion_id'] == $clasificacion['id'] ? 'selected' : ''; ?>>
                                            <?php echo $clasificacion['codigo']; ?> - <?php echo $clasificacion['descripcion']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="fecha_estreno">Fecha de Estreno <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fecha_estreno" name="fecha_estreno" 
                                       value="<?php echo $pelicula['fecha_estreno']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="fecha_salida">Fecha de Salida</label>
                                <input type="date" class="form-control" id="fecha_salida" name="fecha_salida" 
                                       value="<?php echo $pelicula['fecha_salida']; ?>">
                                <small class="form-text text-muted">Fecha programada para salir de cartelera</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="estado">Estado <span class="text-danger">*</span></label>
                                <select class="form-control" id="estado" name="estado" required>
                                    <option value="proximo" <?php echo $pelicula['estado'] === 'proximo' ? 'selected' : ''; ?>>Próximamente</option>
                                    <option value="preventa" <?php echo $pelicula['estado'] === 'preventa' ? 'selected' : ''; ?>>Preventa</option>
                                    <option value="estreno" <?php echo $pelicula['estado'] === 'estreno' ? 'selected' : ''; ?>>Estreno</option>
                                    <option value="regular" <?php echo $pelicula['estado'] === 'regular' ? 'selected' : ''; ?>>En cartelera</option>
                                    <option value="inactivo" <?php echo $pelicula['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="generos">Géneros</label>
                                <select class="form-control" id="generos" name="generos[]" multiple>
                                    <?php foreach ($generos as $genero): ?>
                                        <option value="<?php echo $genero['id']; ?>" 
                                                <?php echo in_array($genero['id'], $pelicula['generos']) ? 'selected' : ''; ?>>
                                            <?php echo $genero['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Mantenga presionado Ctrl (o Command en Mac) para seleccionar múltiples géneros</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="url_trailer">URL del Trailer</label>
                        <input type="url" class="form-control" id="url_trailer" name="url_trailer" 
                               value="<?php echo $pelicula['url_trailer']; ?>" placeholder="https://www.youtube.com/embed/...">
                        <small class="form-text text-muted">URL de YouTube en formato embed (ej: https://www.youtube.com/embed/codigo)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sinopsis">Sinopsis</label>
                        <textarea class="form-control" id="sinopsis" name="sinopsis" rows="5"><?php echo $pelicula['sinopsis']; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Película
                        </button>
                        <a href="peliculas.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($accion === 'editar'): ?>
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Gestión de multimedia</h5>
                </div>
                <div class="card-body">
                    <p>Para añadir o cambiar el póster o imágenes de la película, use el formulario a continuación.</p>
                    
                    <!-- Formulario para añadir póster -->
                    <h6>Póster de la película</h6>
                    <form action="multimedia-pelicula.php" method="post" class="mb-4">
                        <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                        <input type="hidden" name="proposito" value="poster">
                        
                        <div class="input-group">
                            <input type="url" class="form-control" name="url" placeholder="https://ejemplo.com/imagen.jpg" required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Añadir Póster</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Ingrese la URL de la imagen del póster</small>
                    </form>
                    
                    <!-- Formulario para añadir imágenes a la galería -->
                    <h6>Galería de imágenes</h6>
                    <form action="multimedia-pelicula.php" method="post">
                        <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                        <input type="hidden" name="proposito" value="galeria">
                        
                        <div class="input-group">
                            <input type="url" class="form-control" name="url" placeholder="https://ejemplo.com/imagen.jpg" required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Añadir a Galería</button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Ingrese la URL de la imagen para la galería</small>
                    </form>
                    
                    <hr>
                    
                    <!-- Mostrar multimedia existente -->
                    <h6>Multimedia actual</h6>
                    <div class="row">
                        <?php
                        $query = "SELECT mp.id, m.tipo, m.url, mp.proposito 
                                  FROM multimedia_pelicula mp
                                  JOIN multimedia m ON mp.multimedia_id = m.id
                                  WHERE mp.pelicula_id = ?
                                  ORDER BY mp.proposito, mp.orden";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param('i', $pelicula['id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result && $result->num_rows > 0):
                            while ($media = $result->fetch_assoc()):
                        ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <img src="<?php echo $media['url']; ?>" class="card-img-top" alt="Multimedia">
                                    <div class="card-body p-2">
                                        <p class="card-text small">
                                            <span class="badge badge-primary"><?php echo $media['proposito']; ?></span>
                                        </p>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo $media['url']; ?>" target="_blank" class="btn btn-info">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-danger" data-toggle="modal" 
                                                    data-target="#eliminarMediaModal" data-id="<?php echo $media['id']; ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    No hay archivos multimedia asociados a esta película.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para eliminar multimedia -->
<?php if ($accion === 'editar'): ?>
<div class="modal fade" id="eliminarMediaModal" tabindex="-1" role="dialog" aria-labelledby="eliminarMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eliminarMediaModalLabel">Confirmar eliminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar este archivo multimedia?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="eliminar-multimedia.php" method="post">
                    <input type="hidden" name="media_id" id="mediaId" value="">
                    <input type="hidden" name="pelicula_id" value="<?php echo $pelicula['id']; ?>">
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para pasar datos al modal de eliminación
    $('#eliminarMediaModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        
        var modal = $(this);
        modal.find('#mediaId').val(id);
    });
</script>
<?php endif; ?>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>