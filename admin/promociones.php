<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Procesar eliminación de promoción (marcado como eliminado)
if (isset($_POST['eliminar_promocion']) && isset($_POST['id'])) {
    $promocionId = intval($_POST['id']);
    
    // Verificar que la promoción existe
    $query = "SELECT id, fecha_fin FROM promociones WHERE id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $promocionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Marcar como eliminado (soft delete)
        $query = "UPDATE promociones SET deleted_at = NOW(), activa = 0 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $promocionId);
        
        if ($stmt->execute()) {
            // Registrar en auditoría
            $userId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'DELETE', 'promociones', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $userId, $promocionId);
            $stmt->execute();
            
            setMensaje('Promoción eliminada correctamente', 'success');
        } else {
            setMensaje('Error al eliminar la promoción', 'danger');
        }
    } else {
        setMensaje('La promoción no existe', 'danger');
    }
    
    redirect('promociones.php');
    exit;
}

// Obtener listado de promociones
$query = "SELECT pr.id, pr.nombre, pr.tipo, pr.valor, pr.codigo_promocional,
                 pr.fecha_inicio, pr.fecha_fin, pr.max_usos, pr.usos_actuales,
                 pr.activa, c.nombre as cine_nombre, p.nombre as producto_nombre,
                 m.url as imagen_url
          FROM promociones pr
          LEFT JOIN cines c ON pr.cine_id = c.id
          LEFT JOIN productos p ON pr.producto_id = p.id
          LEFT JOIN multimedia m ON pr.imagen_id = m.id
          WHERE pr.deleted_at IS NULL
          ORDER BY pr.fecha_inicio DESC, pr.fecha_fin DESC";

$result = $conn->query($query);
$promociones = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $promociones[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Administración de Promociones</h1>
            <a href="promocion-form.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nueva Promoción
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Valor/Producto</th>
                                <th>Código</th>
                                <th>Cine</th>
                                <th>Vigencia</th>
                                <th>Usos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promociones as $promocion): 
                                $fechaInicio = strtotime($promocion['fecha_inicio']);
                                $fechaFin = strtotime($promocion['fecha_fin']);
                                $ahora = time();
                                
                                $vigente = ($ahora >= $fechaInicio && $ahora <= $fechaFin && $promocion['activa'] == 1);
                                $futura = ($ahora < $fechaInicio && $promocion['activa'] == 1);
                                $vencida = ($ahora > $fechaFin || $promocion['activa'] == 0);
                                
                                if ($vigente) {
                                    $claseEstado = 'success';
                                    $textoEstado = 'Vigente';
                                } elseif ($futura) {
                                    $claseEstado = 'info';
                                    $textoEstado = 'Futura';
                                } else {
                                    $claseEstado = 'secondary';
                                    $textoEstado = 'Vencida';
                                }
                            ?>
                                <tr>
                                    <td><?php echo $promocion['id']; ?></td>
                                    <td>
                                        <?php if (!empty($promocion['imagen_url'])): ?>
                                            <img src="<?php echo $promocion['imagen_url']; ?>" alt="<?php echo $promocion['nombre']; ?>" 
                                                 class="img-thumbnail" style="max-width: 50px;">
                                        <?php else: ?>
                                            <div class="text-center bg-light p-2" style="width: 50px; height: 50px;">
                                                <i class="fas fa-percentage"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $promocion['nombre']; ?></td>
                                    <td>
                                        <?php
                                        $tipoTexto = [
                                            'descuento' => 'Descuento',
                                            '2x1' => '2x1',
                                            'producto_gratis' => 'Producto gratis'
                                        ];
                                        
                                        $tipoClase = [
                                            'descuento' => 'info',
                                            '2x1' => 'primary',
                                            'producto_gratis' => 'warning'
                                        ];
                                        
                                        $texto = $tipoTexto[$promocion['tipo']] ?? 'Otro';
                                        $clase = $tipoClase[$promocion['tipo']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $clase; ?>"><?php echo $texto; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($promocion['tipo'] === 'descuento' && !empty($promocion['valor'])): ?>
                                            <?php echo $promocion['valor']; ?>%
                                        <?php elseif ($promocion['tipo'] === 'producto_gratis' && !empty($promocion['producto_nombre'])): ?>
                                            <?php echo $promocion['producto_nombre']; ?>
                                        <?php elseif ($promocion['tipo'] === '2x1'): ?>
                                            2x1
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($promocion['codigo_promocional'])): ?>
                                            <code><?php echo $promocion['codigo_promocional']; ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">Sin código</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($promocion['cine_nombre'])): ?>
                                            <?php echo $promocion['cine_nombre']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Todos los cines</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        Del <?php echo date('d/m/Y', $fechaInicio); ?><br>
                                        al <?php echo date('d/m/Y', $fechaFin); ?>
                                    </td>
                                    <td>
                                        <?php echo $promocion['usos_actuales']; ?> 
                                        <?php if (!empty($promocion['max_usos'])): ?>
                                            / <?php echo $promocion['max_usos']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $claseEstado; ?>"><?php echo $textoEstado; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../promociones.php" class="btn btn-sm btn-info" 
                                               target="_blank" data-toggle="tooltip" title="Ver en sitio">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="promocion-form.php?id=<?php echo $promocion['id']; ?>" class="btn btn-sm btn-primary" 
                                               data-toggle="tooltip" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" 
                                                    data-target="#eliminarModal" data-id="<?php echo $promocion['id']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($promocion['nombre']); ?>"
                                                    title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="eliminarModal" tabindex="-1" role="dialog" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eliminarModalLabel">Confirmar eliminación</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que deseas eliminar la promoción <strong id="promocionNombre"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="" method="post">
                    <input type="hidden" name="id" id="promocionId" value="">
                    <button type="submit" name="eliminar_promocion" class="btn btn-danger">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Script para pasar datos al modal de eliminación
    $('#eliminarModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var nombre = button.data('nombre');
        
        var modal = $(this);
        modal.find('#promocionId').val(id);
        modal.find('#promocionNombre').text(nombre);
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>