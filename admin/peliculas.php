<?php
require_once '../includes/functions.php';
iniciarSesion();

// Verificar si el usuario tiene permisos de administrador (rol_id 1 o 2)
if (!estaLogueado() || $_SESSION['rol_id'] > 2) {
    setMensaje('No tienes permisos para acceder al panel de administración', 'danger');
    redirect('/multicinev3/');
}

$conn = require '../config/database.php';

// Procesar eliminación de película (marcado como eliminado)
if (isset($_POST['eliminar_pelicula']) && isset($_POST['id'])) {
    $peliculaId = intval($_POST['id']);
    
    // Verificar que la película existe
    $query = "SELECT id FROM peliculas WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $peliculaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Marcar como eliminado (soft delete)
        $query = "UPDATE peliculas SET deleted_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $peliculaId);
        
        if ($stmt->execute()) {
            // Registrar en auditoría
            $userId = $_SESSION['user_id'];
            $query = "INSERT INTO auditoria_sistema (user_id, accion, tabla_afectada, registro_id) 
                      VALUES (?, 'DELETE', 'peliculas', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $userId, $peliculaId);
            $stmt->execute();
            
            setMensaje('Película eliminada correctamente', 'success');
        } else {
            setMensaje('Error al eliminar la película', 'danger');
        }
    } else {
        setMensaje('La película no existe', 'danger');
    }
    
    redirect('peliculas.php');
    exit;
}

// Obtener listado de películas
$query = "SELECT p.id, p.titulo, p.titulo_original, p.duracion_min, 
                 p.fecha_estreno, p.fecha_salida, p.estado,
                 c.codigo as clasificacion,
                 m.url as poster_url
          FROM peliculas p
          LEFT JOIN clasificaciones c ON p.clasificacion_id = c.id
          LEFT JOIN multimedia_pelicula mp ON p.id = mp.pelicula_id AND mp.proposito = 'poster'
          LEFT JOIN multimedia m ON mp.multimedia_id = m.id
          WHERE p.deleted_at IS NULL
          ORDER BY p.fecha_estreno DESC";

$result = $conn->query($query);
$peliculas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Obtener géneros para cada película
        $queryGeneros = "SELECT g.nombre
                         FROM genero_pelicula gp
                         JOIN generos g ON gp.genero_id = g.id
                         WHERE gp.pelicula_id = ?";
        $stmt = $conn->prepare($queryGeneros);
        $stmt->bind_param('i', $row['id']);
        $stmt->execute();
        $resultGeneros = $stmt->get_result();
        
        $generos = [];
        while ($genero = $resultGeneros->fetch_assoc()) {
            $generos[] = $genero['nombre'];
        }
        
        $row['generos'] = $generos;
        $peliculas[] = $row;
    }
}

// Incluir header
require_once 'includes/header.php';
?>

<div class="d-flex">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="content-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Administración de Películas</h1>
            <a href="pelicula-form.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nueva Película
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
                                <th>Título</th>
                                <th>Duración</th>
                                <th>Clasificación</th>
                                <th>Géneros</th>
                                <th>Estado</th>
                                <th>Fecha Estreno</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peliculas as $pelicula): ?>
                                <tr>
                                    <td><?php echo $pelicula['id']; ?></td>
                                    <td>
                                        <?php if (!empty($pelicula['poster_url'])): ?>
                                            <img src="<?php echo $pelicula['poster_url']; ?>" alt="<?php echo $pelicula['titulo']; ?>" 
                                                 class="img-thumbnail" style="max-width: 50px;">
                                        <?php else: ?>
                                            <div class="text-center bg-light p-2" style="width: 50px; height: 50px;">
                                                <i class="fas fa-film"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $pelicula['titulo']; ?>
                                        <?php if (!empty($pelicula['titulo_original']) && $pelicula['titulo_original'] != $pelicula['titulo']): ?>
                                            <small class="d-block text-muted"><?php echo $pelicula['titulo_original']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $pelicula['duracion_min']; ?> min</td>
                                    <td>
                                        <?php if (!empty($pelicula['clasificacion'])): ?>
                                            <span class="badge badge-info"><?php echo $pelicula['clasificacion']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php foreach ($pelicula['generos'] as $genero): ?>
                                            <span class="badge badge-secondary"><?php echo $genero; ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $estadoClases = [
                                            'estreno' => 'success',
                                            'preventa' => 'info',
                                            'proximo' => 'warning',
                                            'regular' => 'primary',
                                            'inactivo' => 'secondary'
                                        ];
                                        
                                        $estadoTexto = [
                                            'estreno' => 'Estreno',
                                            'preventa' => 'Preventa',
                                            'proximo' => 'Próximamente',
                                            'regular' => 'En cartelera',
                                            'inactivo' => 'Inactivo'
                                        ];
                                        
                                        $clase = $estadoClases[$pelicula['estado']] ?? 'secondary';
                                        $texto = $estadoTexto[$pelicula['estado']] ?? 'Desconocido';
                                        ?>
                                        <span class="badge badge-<?php echo $clase; ?>"><?php echo $texto; ?></span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($pelicula['fecha_estreno'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../pelicula.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-info" 
                                               target="_blank" data-toggle="tooltip" title="Ver en sitio">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="pelicula-form.php?id=<?php echo $pelicula['id']; ?>" class="btn btn-sm btn-primary" 
                                               data-toggle="tooltip" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" 
                                                    data-target="#eliminarModal" data-id="<?php echo $pelicula['id']; ?>"
                                                    data-titulo="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
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
                <p>¿Estás seguro de que deseas eliminar la película <strong id="peliculaTitulo"></strong>?</p>
                <p class="text-danger">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <form action="" method="post">
                    <input type="hidden" name="id" id="peliculaId" value="">
                    <button type="submit" name="eliminar_pelicula" class="btn btn-danger">Eliminar</button>
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
        var titulo = button.data('titulo');
        
        var modal = $(this);
        modal.find('#peliculaId').val(id);
        modal.find('#peliculaTitulo').text(titulo);
    });
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>