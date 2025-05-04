<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Consulta para obtener promociones activas
$query = "SELECT pr.id, pr.nombre, pr.descripcion, pr.tipo, pr.valor, 
                 pr.fecha_inicio, pr.fecha_fin, pr.codigo_promocional,
                 m.url as imagen_url, c.nombre as cine_nombre,
                 p.nombre as producto_nombre
          FROM promociones pr
          LEFT JOIN multimedia m ON pr.imagen_id = m.id
          LEFT JOIN cines c ON pr.cine_id = c.id
          LEFT JOIN productos p ON pr.producto_id = p.id
          WHERE pr.fecha_inicio <= NOW() 
          AND pr.fecha_fin >= NOW()
          AND pr.activa = 1
          AND pr.deleted_at IS NULL
          ORDER BY pr.fecha_inicio DESC";

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

<div class="jumbotron jumbotron-fluid bg-primary text-white mb-4">
    <div class="container">
        <h1 class="display-4">Promociones</h1>
        <p class="lead">Descubre nuestras mejores ofertas y disfruta al máximo de Multicine</p>
    </div>
</div>

<div class="row">
    <?php if (!empty($promociones)): ?>
        <?php foreach ($promociones as $promocion): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo $promocion['nombre']; ?></h5>
                    </div>
                    <img src="<?php echo $promocion['imagen_url'] ?? 'assets/img/promocion-default.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo $promocion['nombre']; ?>">
                    <div class="card-body">
                        <p class="card-text"><?php echo $promocion['descripcion']; ?></p>
                        
                        <?php if ($promocion['tipo'] == 'descuento' && $promocion['valor']): ?>
                            <div class="alert alert-success">
                                <strong>Descuento:</strong> <?php echo $promocion['valor']; ?>%
                            </div>
                        <?php elseif ($promocion['tipo'] == '2x1'): ?>
                            <div class="alert alert-info">
                                <strong>Promoción 2x1:</strong> ¡Paga 1 y lleva 2!
                            </div>
                        <?php elseif ($promocion['tipo'] == 'producto_gratis' && $promocion['producto_nombre']): ?>
                            <div class="alert alert-warning">
                                <strong>Regalo:</strong> <?php echo $promocion['producto_nombre']; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($promocion['codigo_promocional'])): ?>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control" value="<?php echo $promocion['codigo_promocional']; ?>" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary copy-code" type="button" 
                                            data-code="<?php echo $promocion['codigo_promocional']; ?>">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="small text-muted">
                            <div><strong>Válido desde:</strong> <?php echo date('d/m/Y', strtotime($promocion['fecha_inicio'])); ?></div>
                            <div><strong>Válido hasta:</strong> <?php echo date('d/m/Y', strtotime($promocion['fecha_fin'])); ?></div>
                            
                            <?php if (!empty($promocion['cine_nombre'])): ?>
                                <div><strong>Cine:</strong> <?php echo $promocion['cine_nombre']; ?></div>
                            <?php else: ?>
                                <div><strong>Cine:</strong> Todos los cines</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-center">
                        <?php if (estaLogueado()): ?>
                            <a href="reserva.php?promocion=<?php echo $promocion['id']; ?>" class="btn btn-primary">
                                Usar promoción
                            </a>
                        <?php else: ?>
                            <a href="auth/login.php" class="btn btn-outline-primary">
                                Inicia sesión para usar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="alert alert-info">
                No hay promociones activas en este momento. ¡Vuelve pronto para no perderte nuestras increíbles ofertas!
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Sección de promociones especiales -->
<div class="row mt-5">
    <div class="col-12 mb-4">
        <h2 class="border-bottom pb-2">Promociones especiales</h2>
    </div>
    
    <!-- Promoción de cumpleaños -->
    <div class="col-md-6 mb-4">
        <div class="card bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <img src="assets/img/birthday.png" alt="Promoción de cumpleaños" class="img-fluid" style="max-height: 120px;">
                    </div>
                    <div class="col-md-8">
                        <h4>¡Celebra tu cumpleaños!</h4>
                        <p>Entrada gratis el día de tu cumpleaños presentando tu cédula de identidad.</p>
                        <a href="cumpleanos.php" class="btn btn-sm btn-outline-primary">Más información</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Promoción de estudiantes -->
    <div class="col-md-6 mb-4">
        <div class="card bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <img src="assets/img/student.png" alt="Promoción de estudiantes" class="img-fluid" style="max-height: 120px;">
                    </div>
                    <div class="col-md-8">
                        <h4>Tarifa Estudiante</h4>
                        <p>50% de descuento para estudiantes los días martes presentando credencial vigente.</p>
                        <a href="estudiantes.php" class="btn btn-sm btn-outline-primary">Más información</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para copiar el código promocional al portapapeles
    const copyButtons = document.querySelectorAll('.copy-code');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            
            // Crear un elemento de texto temporal
            const tempInput = document.createElement('input');
            tempInput.value = code;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            // Cambiar el ícono temporalmente
            const icon = this.querySelector('i');
            icon.classList.remove('fa-copy');
            icon.classList.add('fa-check');
            
            // Restaurar después de 2 segundos
            setTimeout(() => {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            }, 2000);
        });
    });
});
</script>

<?php
// Incluir footer
require_once 'includes/footer.php';
?>