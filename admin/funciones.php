<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Procesar eliminación de función
if (isset($_POST['eliminar_funcion']) && isset($_POST['id'])) {
    $funcionId = intval($_POST['id']);
    
    // Verificar que la función existe
    $query = "SELECT id, fecha_hora FROM funciones WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $funcionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $funcion = $result->fetch_assoc();
        
        // No permitir eliminar funciones que ya han ocurrido
        if (strtotime($funcion['fecha_hora']) < time()) {
            setMensaje('No se pueden eliminar funciones que ya han ocurrido', 'danger');
            redirect('funciones.php');
            exit;
        }
        
        // Verificar si hay reservas para esta función
        $query = "SELECT COUNT(*) as total FROM reservas WHERE funcion_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $funcionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            setMensaje('No se puede eliminar esta función porque ya tiene reservas', 'warning');
            redirect('funciones.php');
            exit;
        }
        
        // Eliminar la función
        $query = "DELETE FROM funciones WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $funcionId);
        
        if ($stmt->execute()) {
            // Registrar en auditoría
            $userId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'DELETE', 'funciones', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $userId, $funcionId);
            $stmt->execute();
            
            setMensaje('Función eliminada correctamente', 'success');
        } else {
            setMensaje('Error al eliminar la función', 'danger');
        }
    } else {
        setMensaje('La función no existe', 'danger');
    }
    
    redirect('funciones.php');
    exit;
}

// Obtener listado de funciones
$query = "SELECT f.id, f.fecha_hora, f.precio_base, f.asientos_disponibles,
                 p.titulo as pelicula_titulo, p.duracion_min,
                 s.nombre as sala_nombre, c.nombre as cine_nombre,
                 i.codigo as idioma_codigo, fp.nombre as formato
          FROM funciones f
          JOIN peliculas p ON f.pelicula_id = p.id
          JOIN salas s ON f.sala_id = s.id
          JOIN cines c ON s.cine_id = c.id
          JOIN idiomas i ON f.idioma_id = i.id
          JOIN formatos fp ON f.formato_proyeccion_id = fp.id
          ORDER BY f.fecha_hora DESC
          LIMIT 500";

$result = $conn->query($query);
$funciones = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $funciones[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Administración de Funciones</h1>
            <a href="funcion-form.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nueva Función
            </a>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover datatable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha y Hora</th>
                                <th>Película</th>
                                <th>Cine / Sala</th>
                                <th>Formato</th>
                                <th>Idioma</th>
                                <th>Precio Base</th>
                                <th>Asientos Disponibles</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($funciones as $funcion): 
                                $fechaHora = strtotime($funcion['fecha_hora']);
                                $esPasada = $fechaHora < time();
                                $estado = $esPasada ? 'Pasada' : 'Próxima';
                                $claseEstado = $esPasada ? 'secondary' : 'success';
                            ?>
                                <tr class="<?php echo $esPasada ? 'table-secondary' : ''; ?>">
                                    <td><?php echo $funcion['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', $fechaHora); ?></td>
                                    <td><?php echo $funcion['pelicula_titulo']; ?></td>
                                    <td><?php echo $funcion['cine_nombre'] . ' / ' . $funcion['sala_nombre']; ?></td>
                                    <td><?php echo $funcion['formato']; ?></td>
                                    <td><?php echo $funcion['idioma_codigo']; ?></td>
                                    <td>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></td>
                                    <td><?php echo $funcion['asientos_disponibles']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $claseEstado; ?>">
                                            <?php echo $estado; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="funcion-form.php?id=<?php echo $funcion['id']; ?>" 
                                               class="btn btn-sm btn-primary" data-toggle="tooltip" title="Editar"
                                               <?php echo $esPasada ? 'disabled' : ''; ?>>
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-toggle="modal" data-target="#eliminarModal" 
                                                    data-id="<?php echo $funcion['id']; ?>"
                                                    data-pelicula="<?php echo $funcion['pelicula_titulo']; ?>"
                                                    data-fecha="<?php echo date('d/m/Y H:i', $fechaHora); ?>"
                                                    title="Eliminar"
                                                    <?php echo $esPasada ? 'disabled' : ''; ?>>
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
                <p>¿Estás seguro de que deseas eliminar la función de <strong id="funcionPelicula"></strong> programada para <strong id="funcionFecha"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="" method="post">
                    <input type="hidden" name="id" id="funcionId" value="">
                    <button type="submit" name="eliminar_funcion" class="btn btn-danger">Eliminar</button>
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
        var pelicula = button.data('pelicula');
        var fecha = button.data('fecha');
        
        var modal = $(this);
        modal.find('#funcionId').val(id);
        modal.find('#funcionPelicula').text(pelicula);
        modal.find('#funcionFecha').text(fecha);
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>