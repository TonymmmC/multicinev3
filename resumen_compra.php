<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
    // If not logged in, store the form data in session to recover after login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['temp_reservation'] = $_POST;
    }
    
    setMensaje('Debe iniciar sesión para continuar con la reserva', 'warning');
    redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Check if there's a stored reservation
    if (isset($_SESSION['temp_reservation'])) {
        $_POST = $_SESSION['temp_reservation'];
        unset($_SESSION['temp_reservation']);
    } else {
        setMensaje('Acceso inválido', 'warning');
        redirect('/multicinev3/');
    }
}

// Get form data
$funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
$asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';

if (!$funcionId || empty($asientosSeleccionados)) {
    setMensaje('Debe seleccionar al menos un asiento', 'warning');
    redirect('reserva.php?funcion=' . $funcionId);
}

// Get function details
$queryFuncion = "SELECT f.*, p.titulo as pelicula_titulo, p.duracion_min, s.nombre as sala_nombre, 
                s.capacidad, c.nombre as cine_nombre, c.direccion,
                i.nombre as idioma, fmt.nombre as formato, p.id as pelicula_id
                FROM funciones f 
                JOIN peliculas p ON f.pelicula_id = p.id 
                JOIN salas s ON f.sala_id = s.id 
                JOIN cines c ON s.cine_id = c.id 
                JOIN idiomas i ON f.idioma_id = i.id 
                JOIN formatos fmt ON f.formato_proyeccion_id = fmt.id 
                WHERE f.id = ?";
$stmtFuncion = $conn->prepare($queryFuncion);
$stmtFuncion->bind_param("i", $funcionId);
$stmtFuncion->execute();
$resultFuncion = $stmtFuncion->get_result();

if ($resultFuncion->num_rows === 0) {
    setMensaje('Función no encontrada', 'warning');
    redirect('/multicinev3/');
}

$funcion = $resultFuncion->fetch_assoc();

// Process selected seats
$asientosIds = explode(',', $asientosSeleccionados);
$asientosIds = array_map('intval', $asientosIds);
$numAsientos = count($asientosIds);

// Check if seats are still available
$placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
$queryAsientos = "SELECT a.*, s.id as sala_id FROM asientos a 
                 JOIN salas s ON a.sala_id = s.id 
                 WHERE a.id IN ($placeholders) 
                 AND a.disponible = 1";

$stmtAsientos = $conn->prepare($queryAsientos);
$types = str_repeat('i', count($asientosIds));
$stmtAsientos->bind_param($types, ...$asientosIds);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

if ($resultAsientos->num_rows !== count($asientosIds)) {
    setMensaje('Algunos asientos seleccionados ya no están disponibles', 'warning');
    redirect('reserva.php?funcion=' . $funcionId);
}

// Get movie poster
$queryPoster = "SELECT m.url 
               FROM multimedia_pelicula mp 
               JOIN multimedia m ON mp.multimedia_id = m.id 
               WHERE mp.pelicula_id = ? AND mp.proposito = 'poster' 
               LIMIT 1";
$stmtPoster = $conn->prepare($queryPoster);
$stmtPoster->bind_param("i", $funcion['pelicula_id']);
$stmtPoster->execute();
$resultPoster = $stmtPoster->get_result();

$posterUrl = 'assets/img/poster-default.jpg';
if ($resultPoster->num_rows > 0) {
    $poster = $resultPoster->fetch_assoc();
    $posterUrl = $poster['url'];
}

// Get selected seats details
$asientosData = [];
while ($asiento = $resultAsientos->fetch_assoc()) {
    $asientosData[] = $asiento;
}

// Sort seats by row and number for display
usort($asientosData, function($a, $b) {
    if ($a['fila'] === $b['fila']) {
        return $a['numero'] - $b['numero'];
    }
    return strcmp($a['fila'], $b['fila']);
});

// Calculate total seat cost
$costoTotalAsientos = $funcion['precio_base'] * $numAsientos;

// Get candy bar products (for the next step)
$queryProductos = "SELECT p.id, p.nombre, p.precio_unitario, p.categoria_id, c.nombre as categoria_nombre
                  FROM productos p
                  JOIN categorias_producto c ON p.categoria_id = c.id
                  WHERE p.activo = 1
                  ORDER BY c.nombre, p.nombre";
$stmtProductos = $conn->prepare($queryProductos);
$stmtProductos->execute();
$resultProductos = $stmtProductos->get_result();

$productos = [];
while ($producto = $resultProductos->fetch_assoc()) {
    $categoriaId = $producto['categoria_id'];
    if(!isset($productos[$categoriaId])) {
        $productos[$categoriaId] = [
            'nombre' => $producto['categoria_nombre'],
            'items' => []
        ];
    }
    $productos[$categoriaId]['items'][] = $producto;
}

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/resumen.css" rel="stylesheet">

<div class="res-container">
    <div class="res-sidebar">
        <div class="res-movie-info">
            <a href="/multicinev3/" class="res-home-btn">
                <i class="fas fa-home"></i>
            </a>
            <img src="<?php echo $posterUrl; ?>" alt="<?php echo $funcion['pelicula_titulo']; ?>" class="res-movie-poster">
            <div class="res-age-rating">
                <div class="res-rating-circle">12</div>
            </div>
            <h2 class="res-movie-title"><?php echo $funcion['pelicula_titulo']; ?></h2>
            <p class="res-cinema-name"><?php echo $funcion['cine_nombre']; ?></p>
        </div>
        
        <div class="res-cinema-info">
            <h3>Cine</h3>
            <p class="res-info-text"><?php echo $funcion['cine_nombre']; ?></p>
            
            <h3>Fecha</h3>
            <p class="res-info-text"><?php 
                setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'Spanish');
                echo strftime('%A %d de %B de %Y', strtotime($funcion['fecha_hora'])); 
            ?></p>
            
            <h3>Proyección</h3>
            <p class="res-info-text">
                <?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?> <?php echo $funcion['formato']; ?>
                <br>
                <small>Versión Original</small>
            </p>
            <p class="res-info-text res-end-time">
                Hora prevista de finalización: <?php 
                    $endTime = strtotime($funcion['fecha_hora']) + ($funcion['duracion_min'] * 60);
                    echo date('H:i', $endTime); 
                ?>
            </p>
        </div>
        
        <div class="res-tickets-info">
            <h3>Mis entradas</h3>
            <div class="res-selected-seats-summary">
                <p>Asientos seleccionados:</p>
                <div class="res-seat-list">
                    <?php 
                    $seatLabels = array_map(function($seat) {
                        return $seat['fila'] . $seat['numero'];
                    }, $asientosData);
                    echo implode(', ', $seatLabels);
                    ?>
                </div>
            </div>
            <div class="res-ticket-item">
                <span><?php echo $numAsientos; ?>x Entrada</span>
                <span>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></span>
            </div>
            
            <div class="res-ticket-total">
                <span>Total</span>
                <span id="totalPriceDisplay">Bs. <?php echo number_format($costoTotalAsientos, 2); ?></span>
            </div>
        </div>
    </div>
    
    <div class="res-main-content">
        <h2>Selecciona tus tarifas</h2>
        
        <div class="res-rates-section">
            <h3>Entradas (<?php echo $numAsientos; ?> asientos seleccionados)</h3>
            
            <?php for($i = 0; $i < $numAsientos; $i++): ?>
                <div class="res-rate-item" data-seat="<?php echo $asientosData[$i]['fila'] . $asientosData[$i]['numero']; ?>">
                    <div class="res-rate-info">
                        <span class="res-seat-label">Asiento <?php echo $asientosData[$i]['fila'] . $asientosData[$i]['numero']; ?></span>
                        <span class="res-rate-description">Entrada Cine</span>
                        <span class="res-rate-price">Bs. <?php echo number_format($funcion['precio_base'], 2); ?></span>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        
        <div class="res-vouchers-section">
            <h3>Códigos promocionales</h3>
            <p>Usa tus cupones online introduciendo el código impreso en tu cupón (16 caracteres).</p>
            <div class="res-code-container">
                <button class="res-code-btn" id="showCodeInput">Introducir código</button>
                <div class="res-code-input-container" id="codeInputContainer" style="display: none;">
                    <input type="text" class="res-code-input" id="promoCode" placeholder="Introduce tu código">
                    <button class="res-apply-btn" id="applyCode">Aplicar</button>
                    <div id="codeMessage"></div>
                </div>
            </div>
        </div>
        
        <div class="res-candy-bar-section">
            <h3>Candy Bar</h3>
            
            <?php foreach($productos as $categoriaId => $categoria): ?>
                <div class="res-product-category">
                    <h4><?php echo $categoria['nombre']; ?></h4>
                    
                    <div class="res-products-grid">
                        <?php foreach($categoria['items'] as $producto): ?>
                            <div class="res-product-item" data-id="<?php echo $producto['id']; ?>" data-price="<?php echo $producto['precio_unitario']; ?>">
                                <div class="res-product-image">
                                    <img src="assets/img/products/<?php echo $producto['id']; ?>.jpg" alt="<?php echo $producto['nombre']; ?>" onerror="this.src='assets/img/product-default.jpg'">
                                </div>
                                <h5><?php echo $producto['nombre']; ?></h5>
                                <div class="res-product-price">Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></div>
                                <div class="res-product-actions">
                                    <button class="res-product-btn">Añadir</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="res-continue-container">
            <form id="completePurchaseForm" action="procesar_reserva.php" method="post">
                <input type="hidden" name="funcion_id" value="<?php echo $funcionId; ?>">
                <input type="hidden" name="asientos_seleccionados" value="<?php echo $asientosSeleccionados; ?>">
                <input type="hidden" name="productos_seleccionados" id="productosSeleccionados" value="">
                <input type="hidden" name="codigo_promocional" id="codigoPromocional" value="">
                <input type="hidden" name="descuento_aplicado" id="descuentoAplicado" value="0">
                <button type="submit" class="res-continue-btn">Continuar</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Promo code functionality
    const showCodeBtn = document.getElementById('showCodeInput');
    const codeInputContainer = document.getElementById('codeInputContainer');
    const promoCodeInput = document.getElementById('promoCode');
    const applyCodeBtn = document.getElementById('applyCode');
    const codigoPromocionalInput = document.getElementById('codigoPromocional');
    const codeMessageDiv = document.getElementById('codeMessage');
    const descuentoAplicadoInput = document.getElementById('descuentoAplicado');
    const totalPriceDisplay = document.getElementById('totalPriceDisplay');
    
    // Original price calculation
    const basePrice = <?php echo $funcion['precio_base']; ?>;
    const numSeats = <?php echo $numAsientos; ?>;
    let totalPrice = basePrice * numSeats;
    
    showCodeBtn.addEventListener('click', function() {
        codeInputContainer.style.display = 'flex';
        showCodeBtn.style.display = 'none';
        promoCodeInput.focus();
    });
    
    applyCodeBtn.addEventListener('click', function() {
        const code = promoCodeInput.value.trim();
        if(!code) {
            codeMessageDiv.innerHTML = '<div class="res-code-error">Por favor ingrese un código válido.</div>';
            return;
        }
        
        // Show loading state
        applyCodeBtn.disabled = true;
        applyCodeBtn.textContent = 'Validando...';
        codeMessageDiv.innerHTML = '';
        
        // Validate promo code with the server
        fetch('api/validate_promocode.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                codigo: code,
                funcion_id: <?php echo $funcionId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Valid promo code
                codigoPromocionalInput.value = code;
                descuentoAplicadoInput.value = data.descuento;
                
                // Update UI with success message
                codeInputContainer.innerHTML = `<div class="res-code-success">Código aplicado: ${code}</div>`;
                
                // Update total price in sidebar
                const discount = parseFloat(data.descuento);
                let newTotal = totalPrice;
                
                if (data.tipo === 'porcentaje') {
                    newTotal = totalPrice * (1 - (discount / 100));
                } else if (data.tipo === 'valor_fijo') {
                    newTotal = totalPrice - discount;
                }
                
                // Make sure total doesn't go below zero
                newTotal = Math.max(0, newTotal);
                totalPriceDisplay.textContent = `Bs. ${newTotal.toFixed(2)}`;
            } else {
                // Invalid promo code
                applyCodeBtn.disabled = false;
                applyCodeBtn.textContent = 'Aplicar';
                codeMessageDiv.innerHTML = `<div class="res-code-error">${data.message || 'Código inválido o expirado.'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            applyCodeBtn.disabled = false;
            applyCodeBtn.textContent = 'Aplicar';
            codeMessageDiv.innerHTML = '<div class="res-code-error">Error al validar el código. Por favor intente nuevamente.</div>';
        });
    });
    
    // Product selection
    const productItems = document.querySelectorAll('.res-product-item');
    const productosSeleccionados = document.getElementById('productosSeleccionados');
    let selectedProducts = [];
    
    productItems.forEach(item => {
        const addBtn = item.querySelector('.res-product-btn');
        
        addBtn.addEventListener('click', function() {
            const productId = item.dataset.id;
            const productPrice = parseFloat(item.dataset.price);
            const productName = item.querySelector('h5').textContent;
            
            // Check if product already exists
            const existingIndex = selectedProducts.findIndex(p => p.id === productId);
            
            if (existingIndex !== -1) {
                // Increment quantity if exists
                selectedProducts[existingIndex].quantity++;
            } else {
                // Add new product
                selectedProducts.push({
                    id: productId,
                    price: productPrice,
                    name: productName,
                    quantity: 1
                });
            }
            
            // Update hidden input
            updateSelectedProducts();
            
            // Visual feedback
            addBtn.textContent = 'Añadido';
            addBtn.classList.add('res-added');
            
            setTimeout(() => {
                addBtn.textContent = 'Añadir';
                addBtn.classList.remove('res-added');
            }, 1500);
        });
    });
    
    function updateSelectedProducts() {
        if(selectedProducts.length > 0) {
            // Format as JSON
            productosSeleccionados.value = JSON.stringify(selectedProducts);
        } else {
            productosSeleccionados.value = '';
        }
    }
    
    // Form submission
    document.getElementById('completePurchaseForm').addEventListener('submit', function(e) {
        // Update all inputs before submission
        updateSelectedProducts();
    });
});
</script>

<style>
.res-selected-seats-summary {
    margin-bottom: 15px;
    font-size: 14px;
}

.res-selected-seats-summary p {
    margin: 0 0 5px;
    font-weight: bold;
}

.res-seat-list {
    color: #ffca2c;
}

.res-rate-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f8f8;
    padding: 12px 15px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.res-rate-info {
    display: flex;
    flex-direction: column;
}

.res-seat-label {
    font-size: 12px;
    color: #ffca2c;
    font-weight: bold;
    margin-bottom: 2px;
}

.res-rate-description {
    font-weight: bold;
}

.res-rate-price {
    font-size: 13px;
    color: #666;
}

.res-rates-section h3 {
    margin: 15px 0;
    font-size: 18px;
}

.res-code-input-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.res-code-input {
    flex: 1;
    min-width: 200px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.res-apply-btn {
    padding: 8px 20px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.res-apply-btn:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
}

#codeMessage {
    flex: 0 0 100%;
    margin-top: 5px;
}

.res-code-error {
    color: #dc3545;
    font-size: 14px;
}

.res-code-success {
    color: #28a745;
    font-size: 14px;
    font-weight: bold;
}
</style>

<?php require_once 'includes/footer.php'; ?>