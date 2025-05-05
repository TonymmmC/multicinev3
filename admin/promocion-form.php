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
$promocion = [
    'id' => null,
    'nombre' => '',
    'descripcion' => '',
    'tipo' => 'descuento',
    'valor' => 10,
    'producto_id' => null,
    'fecha_inicio' => date('Y-m-d\TH:i'),
    'fecha_fin' => date('Y-m-d\TH:i', strtotime('+1 month')),
    'codigo_promocional' => strtoupper(substr(md5(uniqid()), 0, 8)),
    'max_usos' => null,
    'usos_actuales' => 0,
    'activa' => 1,
    'cine_id' => null,
    'imagen_id' => null,
    'imagen_url' => null
];

$accion = 'crear';
$titulo_pagina = 'Nueva Promoción';

// Obtener información de la promoción si estamos editando
if (isset($_GET['id'])) {
    $promocionId = intval($_GET['id']);
    
    // Consultar promoción
    $query = "SELECT pr.*, m.url as imagen_url
              FROM promociones pr
              LEFT JOIN multimedia m ON pr.imagen_id = m.id
              WHERE pr.id = ? AND pr.deleted_at IS NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $promocionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $promocion = $result->fetch_assoc();
        
        // Convertir fechas al formato HTML datetime-local
        $promocion['fecha_inicio'] = date('Y-m-d\TH:i', strtotime($promocion['fecha_inicio']));
        $promocion['fecha_fin'] = date('Y-m-d\TH:i', strtotime($promocion['fecha_fin']));
        
        $accion = 'editar';
        $titulo_pagina = 'Editar Promoción: ' . $promocion['nombre'];
    } else {
        setMensaje('La promoción no existe', 'danger');
        redirect('promociones.php');
        exit;
    }
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y validar datos
    $nombre = sanitizeInput($_POST['nombre'] ?? '');
    $descripcion = sanitizeInput($_POST['descripcion'] ?? '');
    $tipo = sanitizeInput($_POST['tipo'] ?? '');
    $valor = ($tipo === 'descuento') ? floatval($_POST['valor'] ?? 0) : null;
    $producto_id = ($tipo === 'producto_gratis') ? intval($_POST['producto_id'] ?? 0) : null;
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;
    $codigo_promocional = sanitizeInput($_POST['codigo_promocional'] ?? '');
    $max_usos = !empty($_POST['max_usos']) ? intval($_POST['max_usos']) : null;
    $activa = isset($_POST['activa']) ? 1 : 0;
    $cine_id = !empty($_POST['cine_id']) ? intval($_POST['cine_id']) : null;
    $imagen_url = sanitizeInput($_POST['imagen_url'] ?? '');
    
    // Validar datos
    $errores = [];
    
    if (empty($nombre)) {
        $errores[] = 'El nombre es obligatorio';
    }
    
    if (!in_array($tipo, ['descuento', '2x1', 'producto_gratis'])) {
        $errores[] = 'El tipo de promoción no es válido';
    }
    
    if ($tipo === 'descuento' && ($valor <= 0 || $valor > 100)) {
        $errores[] = 'El valor del descuento debe estar entre 1 y 100';
    }
    
    if ($tipo === 'producto_gratis' && empty($producto_id)) {
        $errores[] = 'Debe seleccionar un producto para la promoción de producto gratis';
    }
    
    if (empty($fecha_inicio) || empty($fecha_fin)) {
        $errores[] = 'Las fechas de inicio y fin son obligatorias';
    } else {
        // Convertir fechas al formato de MySQL
        $fecha_inicio = date('Y-m-d H:i:s', strtotime($fecha_inicio));
        $fecha_fin = date('Y-m-d H:i:s', strtotime($fecha_fin));
        
        // Verificar que la fecha de fin es posterior a la de inicio
        if (strtotime($fecha_fin) <= strtotime($fecha_inicio)) {
            $errores[] = 'La fecha de fin debe ser posterior a la fecha de inicio';
        }
    }
    
    // Verificar si el código promocional ya existe (solo para nuevas promociones)
    if (!empty($codigo_promocional) && $accion === 'crear') {
        $query = "SELECT id FROM promociones WHERE codigo_promocional = ? AND deleted_at IS NULL";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $codigo_promocional);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errores[] = 'El código promocional ya existe. Por favor, ingrese otro';
        }
    }
    
    // Si no hay errores, proceder con la inserción/actualización
    if (empty($errores)) {
        // Comenzar transacción
        $conn->begin_transaction();
        
        try {
            // Si hay una nueva imagen, insertarla en multimedia
            $imagen_id = $promocion['imagen_id'];
            if (!empty($imagen_url) && $imagen_url !== ($promocion['imagen_url'] ?? '')) {
                $query = "INSERT INTO multimedia (tipo, url, descripcion) VALUES (?, ?, ?)";
                $tipo = 'imagen';
                $descripcion = "Imagen para promoción: $nombre";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('sss', $tipo, $imagen_url, $descripcion);
                $stmt->execute();
                
                $imagen_id = $conn->insert_id;
            }
            
            if ($accion === 'crear') {
                // Insertar promoción
                $query = "INSERT INTO promociones (
                            nombre, descripcion, tipo, valor, producto_id,
                            fecha_inicio, fecha_fin, codigo_promocional, max_usos,
                            activa, cine_id, imagen_id
                          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param('sssdiississi', $nombre, $descripcion, $tipo, $valor, $producto_id,
                                 $fecha_inicio, $fecha_fin, $codigo_promocional, $max_usos,
                                 $activa, $cine_id, $imagen_id);
                $stmt->execute();
                
                $promocionId = $conn->insert_id;
                
                // Registrar acción en auditoría
                $userId = $_SESSION['user_id'];
                $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                          VALUES (?, 'INSERT', 'promociones', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ii', $userId, $promocionId);
                $stmt->execute();
                
                $mensaje = 'Promoción creada correctamente';
            } else {
                // Actualizar promoción
                $query = "UPDATE promociones SET 
                            nombre = ?, 
                            descripcion = ?, 
                            tipo = ?, 
                            valor = ?,
                            producto_id = ?, 
                            fecha_inicio = ?, 
                            fecha_fin = ?,
                            codigo_promocional = ?,
                            max_usos = ?,
                            activa = ?,
                            cine_id = ?";
                
                // Añadir imagen_id solo si hay una nueva imagen
                if (!empty($imagen_id)) {
                    $query .= ", imagen_id = ?";
                }
                
                $query .= " WHERE id = ?";
                
                $stmt = $conn->prepare($query);
                
                if (!empty($imagen_id)) {
                    $stmt->bind_param('sssdiississii', $nombre, $descripcion, $tipo, $valor, $producto_id,
                                    $fecha_inicio, $fecha_fin, $codigo_promocional, $max_usos,
                                    $activa, $cine_id, $imagen_id, $promocion['id']);
                } else {
                    $stmt->bind_param('sssdiissisi', $nombre, $descripcion, $tipo, $valor, $producto_id,
                                    $fecha_inicio, $fecha_fin, $codigo_promocional, $max_usos,
                                    $activa, $cine_id, $promocion['id']);
                }
                
                $stmt->execute();
                
                // Registrar acción en auditoría
                $userId = $_SESSION['user_id'];
                $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                          VALUES (?, 'UPDATE', 'promociones', ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ii', $userId, $promocion['id']);
                $stmt->execute();
                
                $promocionId = $promocion['id'];
                $mensaje = 'Promoción actualizada correctamente';
            }
            
            // Confirmar transacción
            $conn->commit();
            
            setMensaje($mensaje, 'success');
            redirect('promociones.php');
            exit;
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            setMensaje('Error al guardar la promoción: ' . $e->getMessage(), 'danger');
        }
    }
}

// Obtener productos para el select
$productos = [];
$query = "SELECT id, nombre, precio_unitario FROM productos WHERE activo = 1 AND deleted_at IS NULL ORDER BY nombre";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}

// Obtener cines para el select
$cines = [];
$query = "SELECT id, nombre, ciudad_id FROM cines WHERE activo = 1 ORDER BY nombre";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cines[] = $row;
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
            <a href="promociones.php" class="btn btn-secondary">
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
                        <input type="hidden" name="id" value="<?php echo $promocion['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="nombre">Nombre de la Promoción <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?php echo $promocion['nombre']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="tipo">Tipo de Promoción <span class="text-danger">*</span></label>
                                <select class="form-control" id="tipo" name="tipo" required onchange="cambiarTipoPromocion()">
                                    <option value="descuento" <?php echo $promocion['tipo'] === 'descuento' ? 'selected' : ''; ?>>
                                        Descuento (%)
                                    </option>
                                    <option value="2x1" <?php echo $promocion['tipo'] === '2x1' ? 'selected' : ''; ?>>
                                        2x1
                                    </option>
                                    <option value="producto_gratis" <?php echo $promocion['tipo'] === 'producto_gratis' ? 'selected' : ''; ?>>
                                        Producto Gratis
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" 
                                  rows="3"><?php echo $promocion['descripcion']; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6" id="campoValor">
                            <div class="form-group">
                                <label for="valor">Valor de Descuento (%) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="valor" name="valor" 
                                       value="<?php echo $promocion['valor']; ?>" min="1" max="100" step="1">
                            </div>
                        </div>
                        <div class="col-md-6" id="campoProducto" style="display: none;">
                            <div class="form-group">
                                <label for="producto_id">Producto Gratis <span class="text-danger">*</span></label>
                                <select class="form-control" id="producto_id" name="producto_id">
                                    <option value="">Seleccionar producto...</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id']; ?>" 
                                                <?php echo $promocion['producto_id'] == $producto['id'] ? 'selected' : ''; ?>>
                                            <?php echo $producto['nombre']; ?> (Bs. <?php echo number_format($producto['precio_unitario'], 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="fecha_inicio">Fecha de Inicio <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                       value="<?php echo $promocion['fecha_inicio']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="fecha_fin">Fecha de Fin <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="fecha_fin" name="fecha_fin" 
                                       value="<?php echo $promocion['fecha_fin']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="codigo_promocional">Código Promocional</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="codigo_promocional" name="codigo_promocional" 
                                           value="<?php echo $promocion['codigo_promocional']; ?>" maxlength="20">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" onclick="generarCodigo()">
                                            Generar
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Deje en blanco para no utilizar un código</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="max_usos">Usos Máximos</label>
                                <input type="number" class="form-control" id="max_usos" name="max_usos" 
                                       value="<?php echo $promocion['max_usos']; ?>" min="1">
                                <small class="form-text text-muted">Deje en blanco para usos ilimitados</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="cine_id">Cine (opcional)</label>
                                <select class="form-control" id="cine_id" name="cine_id">
                                    <option value="">Todos los cines</option>
                                    <?php foreach ($cines as $cine): ?>
                                        <option value="<?php echo $cine['id']; ?>" 
                                                <?php echo $promocion['cine_id'] == $cine['id'] ? 'selected' : ''; ?>>
                                            <?php echo $cine['nombre']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Si no selecciona ningún cine, la promoción aplicará a todos</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="imagen_url">URL de la Imagen (opcional)</label>
                                <input type="url" class="form-control" id="imagen_url" name="imagen_url" 
                                       value="<?php echo $promocion['imagen_url']; ?>" placeholder="https://ejemplo.com/imagen.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="activa" name="activa" 
                                   <?php echo $promocion['activa'] ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="activa">Promoción Activa</label>
                        </div>
                    </div>
                    
                    <?php if ($accion === 'editar'): ?>
                        <div class="alert alert-info">
                            <p><strong>Información adicional:</strong></p>
                            <ul class="mb-0">
                                <li>Usos actuales: <?php echo $promocion['usos_actuales']; ?></li>
                                <li>Fecha de creación: <?php echo date('d/m/Y H:i', strtotime($promocion['created_at'])); ?></li>
                                <li>Última actualización: <?php echo date('d/m/Y H:i', strtotime($promocion['updated_at'])); ?></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Promoción
                        </button>
                        <a href="promociones.php" class="btn btn-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Cambiar campos visibles según el tipo de promoción
    function cambiarTipoPromocion() {
        var tipo = document.getElementById('tipo').value;
        var campoValor = document.getElementById('campoValor');
        var campoProducto = document.getElementById('campoProducto');
        
        if (tipo === 'descuento') {
            campoValor.style.display = 'block';
            campoProducto.style.display = 'none';
            document.getElementById('valor').required = true;
            document.getElementById('producto_id').required = false;
        } else if (tipo === 'producto_gratis') {
            campoValor.style.display = 'none';
            campoProducto.style.display = 'block';
            document.getElementById('valor').required = false;
            document.getElementById('producto_id').required = true;
        } else { // 2x1
            campoValor.style.display = 'none';
            campoProducto.style.display = 'none';
            document.getElementById('valor').required = false;
            document.getElementById('producto_id').required = false;
        }
    }
    
    // Generar código promocional aleatorio
    function generarCodigo() {
        var caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Sin I, O, 0, 1 para evitar confusiones
        var longitud = 8;
        var codigo = '';
        
        for (var i = 0; i < longitud; i++) {
            codigo += caracteres.charAt(Math.floor(Math.random() * caracteres.length));
        }
        
        document.getElementById('codigo_promocional').value = codigo;
    }
    
    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer campos visibles según el tipo inicial
        cambiarTipoPromocion();
        
        // Si hay imagen, mostrar vista previa
        var imagenUrl = document.getElementById('imagen_url').value;
        if (imagenUrl) {
            // Agregar vista previa
        }
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>