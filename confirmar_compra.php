<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
    setMensaje('Debe iniciar sesión para continuar con la reserva', 'warning');
    redirect('auth/login.php');
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setMensaje('Acceso inválido', 'warning');
    redirect('/multicinev3/');
}

// Get form data
$funcionId = isset($_POST['funcion_id']) ? intval($_POST['funcion_id']) : null;
$asientosSeleccionados = isset($_POST['asientos_seleccionados']) ? $_POST['asientos_seleccionados'] : '';
$productosSeleccionados = isset($_POST['productos_seleccionados']) ? $_POST['productos_seleccionados'] : '';
$codigoPromocional = isset($_POST['codigo_promocional']) ? $_POST['codigo_promocional'] : '';
$descuentoAplicado = isset($_POST['descuento_aplicado']) ? floatval($_POST['descuento_aplicado']) : 0;

if (!$funcionId || empty($asientosSeleccionados)) {
   setMensaje('Datos de reserva incompletos', 'warning');
   redirect('/multicinev3/');
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

// Get seat details
$placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
$queryAsientos = "SELECT a.*, s.id as sala_id FROM asientos a 
                JOIN salas s ON a.sala_id = s.id 
                WHERE a.id IN ($placeholders)";

$stmtAsientos = $conn->prepare($queryAsientos);
$types = str_repeat('i', count($asientosIds));
$stmtAsientos->bind_param($types, ...$asientosIds);
$stmtAsientos->execute();
$resultAsientos = $stmtAsientos->get_result();

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

// Process selected products if any
$productosData = [];
$costoTotalProductos = 0;

if (!empty($productosSeleccionados)) {
    $productosData = json_decode($productosSeleccionados, true);
    
    // Calculate products total
    foreach ($productosData as $producto) {
        $costoTotalProductos += $producto['price'] * $producto['quantity'];
    }
}

// Calculate total before and after discount
$totalSinDescuento = $costoTotalAsientos + $costoTotalProductos;
$totalConDescuento = $totalSinDescuento;

// Apply discount if any
if ($descuentoAplicado > 0 && !empty($codigoPromocional)) {
    // Get discount type from database
    $queryPromo = "SELECT tipo FROM promociones WHERE codigo_promocional = ? LIMIT 1";
    $stmtPromo = $conn->prepare($queryPromo);
    $stmtPromo->bind_param("s", $codigoPromocional);
    $stmtPromo->execute();
    $resultPromo = $stmtPromo->get_result();
    
    if ($resultPromo->num_rows > 0) {
        $promo = $resultPromo->fetch_assoc();
        
        if ($promo['tipo'] === 'porcentaje') {
            $totalConDescuento = $totalSinDescuento * (1 - ($descuentoAplicado / 100));
        } else {
            $totalConDescuento = $totalSinDescuento - $descuentoAplicado;
        }
        
        // Make sure total doesn't go below zero
        $totalConDescuento = max(0, $totalConDescuento);
    }
}

// Get user profile information
$userId = $_SESSION['user_id'];
$queryUsuario = "SELECT u.*, p.* FROM users u 
                LEFT JOIN perfiles_usuario p ON u.id = p.user_id 
                WHERE u.id = ? LIMIT 1";
$stmtUsuario = $conn->prepare($queryUsuario);
$stmtUsuario->bind_param("i", $userId);
$stmtUsuario->execute();
$resultUsuario = $stmtUsuario->get_result();
$usuario = $resultUsuario->fetch_assoc();

// Get user payment methods if any
$queryMetodosPago = "SELECT * FROM metodos_pago WHERE user_id = ? AND activo = 1";
$stmtMetodosPago = $conn->prepare($queryMetodosPago);
$stmtMetodosPago->bind_param("i", $userId);
$stmtMetodosPago->execute();
$resultMetodosPago = $stmtMetodosPago->get_result();

$metodosPago = [];
while ($metodo = $resultMetodosPago->fetch_assoc()) {
    $metodosPago[] = $metodo;
}

//require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/resumen.css" rel="stylesheet">
<link href="assets/css/ticket-confirmation.css" rel="stylesheet">

<div class="confirm-container">
    <div class="confirm-sidebar">
        <div class="confirm-movie-info">
            <a href="/multicinev3/" class="confirm-home-btn">
                <i class="fas fa-home"></i>
            </a>
            <img src="<?php echo $posterUrl; ?>" alt="<?php echo $funcion['pelicula_titulo']; ?>" class="confirm-movie-poster">
            <div class="confirm-age-rating">
                <div class="confirm-rating-circle">12</div>
            </div>
            <h2 class="confirm-movie-title"><?php echo $funcion['pelicula_titulo']; ?></h2>
            <p class="confirm-cinema-name"><?php echo $funcion['cine_nombre']; ?></p>
        </div>
        
        <div class="confirm-cinema-info">
            <h3>Cine</h3>
            <p class="confirm-info-text"><?php echo $funcion['cine_nombre']; ?></p>
            
            <h3>Fecha</h3>
            <p class="confirm-info-text"><?php 
                setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'Spanish');
                echo strftime('%A %d de %B de %Y', strtotime($funcion['fecha_hora'])); 
            ?></p>
            
            <h3>Proyección</h3>
            <p class="confirm-info-text">
                <?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?> <?php echo $funcion['formato']; ?>
                <br>
                <small>Versión Original</small>
            </p>
            <p class="confirm-info-text confirm-end-time">
                Hora prevista de finalización: <?php 
                    $endTime = strtotime($funcion['fecha_hora']) + ($funcion['duracion_min'] * 60);
                    echo date('H:i', $endTime); 
                ?>
            </p>
        </div>
        
        <div class="confirm-summary">
            <h3>Resumen de compra</h3>
            <div class="confirm-summary-item">
                <span><?php echo $numAsientos; ?>x Entradas</span>
                <span>Bs. <?php echo number_format($costoTotalAsientos, 2); ?></span>
            </div>
            
            <?php if ($costoTotalProductos > 0): ?>
            <div class="confirm-summary-item">
                <span>Productos</span>
                <span>Bs. <?php echo number_format($costoTotalProductos, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($descuentoAplicado > 0): ?>
            <div class="confirm-summary-item confirm-discount">
                <span>Descuento</span>
                <span>-Bs. <?php echo number_format($totalSinDescuento - $totalConDescuento, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="confirm-summary-total">
                <span>Total</span>
                <span>Bs. <?php echo number_format($totalConDescuento, 2); ?></span>
            </div>
        </div>
    </div>
    
    <div class="confirm-main-content">
        <h2>Confirmar compra</h2>
        
        <div class="confirm-details-section">
            <h3>Datos del titular</h3>
            <div class="confirm-details-form">
                <div class="confirm-form-row">
                    <div class="confirm-form-group">
                        <label for="nombres">Nombres</label>
                        <input type="text" id="nombres" name="nombres" value="<?php echo htmlspecialchars($usuario['nombres'] ?? ''); ?>" required>
                    </div>
                    <div class="confirm-form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" value="<?php echo htmlspecialchars($usuario['apellidos'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="confirm-form-row">
                    <div class="confirm-form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" readonly>
                    </div>
                    <div class="confirm-form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="text" id="telefono" name="telefono" value="<?php echo htmlspecialchars($usuario['celular'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="confirm-form-row">
                    <div class="confirm-form-group">
                        <label for="nit_ci">NIT/CI</label>
                        <input type="text" id="nit_ci" name="nit_ci" value="<?php echo htmlspecialchars($usuario['nit_ci'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirm-payment-section">
            <h3>Método de pago</h3>
            
            <div class="confirm-payment-options">
                <div class="confirm-payment-tabs">
                    <button class="confirm-payment-tab active" data-target="qr-pay">QR</button>
                    <button class="confirm-payment-tab" data-target="card-pay">Tarjeta</button>
                    <button class="confirm-payment-tab" data-target="tigo-pay">Tigo Money</button>
                </div>
                
                <div class="confirm-payment-content">
                    <!-- QR Payment -->
                    <div id="qr-pay" class="confirm-payment-panel active">
                        <div class="confirm-qr-container">
                            <div class="confirm-qr-code">
                                <img src="assets/img/qr-example.jpg" alt="QR de pago">
                            </div>
                            <div class="confirm-qr-instructions">
                                <h4>Instrucciones para el pago:</h4>
                                <ol>
                                    <li>Escanea el código QR con tu aplicación bancaria</li>
                                    <li>Verifica el monto de Bs. <?php echo number_format($totalConDescuento, 2); ?></li>
                                    <li>Confirma el pago en tu aplicación</li>
                                    <li>El sistema verificará automáticamente tu pago</li>
                                </ol>
                                <div class="confirm-qr-timer">
                                    <p>Este código expirará en: <span id="qrTimer">10:00</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Payment -->
                    <div id="card-pay" class="confirm-payment-panel">
                        <?php if (count($metodosPago) > 0): ?>
                            <div class="confirm-saved-cards">
                                <h4>Tarjetas guardadas</h4>
                                <div class="confirm-cards-list">
                                    <?php foreach ($metodosPago as $metodo): ?>
                                        <?php if ($metodo['tipo'] === 'tarjeta'): ?>
                                            <div class="confirm-card-item">
                                                <input type="radio" name="payment_method" id="card-<?php echo $metodo['id']; ?>" value="<?php echo $metodo['id']; ?>">
                                                <label for="card-<?php echo $metodo['id']; ?>">
                                                    <div class="confirm-card-info">
                                                        <div class="confirm-card-brand">
                                                            <?php if ($metodo['marca'] === 'visa'): ?>
                                                                <i class="fab fa-cc-visa"></i>
                                                            <?php elseif ($metodo['marca'] === 'mastercard'): ?>
                                                                <i class="fab fa-cc-mastercard"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-credit-card"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="confirm-card-details">
                                                            <span class="confirm-card-alias"><?php echo $metodo['alias']; ?></span>
                                                            <span class="confirm-card-number">xxxx xxxx xxxx <?php echo $metodo['ultimos_digitos']; ?></span>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="confirm-new-card">
                            <h4>Nueva tarjeta</h4>
                            <div class="confirm-card-form">
                                <div class="confirm-form-row">
                                    <div class="confirm-form-group">
                                        <label for="card_number">Número de tarjeta</label>
                                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                                <div class="confirm-form-row">
                                    <div class="confirm-form-group">
                                        <label for="card_name">Nombre en la tarjeta</label>
                                        <input type="text" id="card_name" name="card_name" placeholder="NOMBRE APELLIDO">
                                    </div>
                                </div>
                                <div class="confirm-form-row">
                                    <div class="confirm-form-group confirm-form-half">
                                        <label for="card_expiry">Fecha de vencimiento</label>
                                        <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" maxlength="5">
                                    </div>
                                    <div class="confirm-form-group confirm-form-half">
                                        <label for="card_cvv">CVV</label>
                                        <input type="text" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                <div class="confirm-form-row">
                                    <div class="confirm-form-group confirm-form-checkbox">
                                        <input type="checkbox" id="save_card" name="save_card" value="1">
                                        <label for="save_card">Guardar esta tarjeta para futuras compras</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tigo Money Payment -->
                    <div id="tigo-pay" class="confirm-payment-panel">
                        <div class="confirm-tigo-container">
                            <div class="confirm-tigo-form">
                                <div class="confirm-form-row">
                                    <div class="confirm-form-group">
                                        <label for="tigo_number">Número Tigo Money</label>
                                        <input type="text" id="tigo_number" name="tigo_number" placeholder="7XXXXXXXX">
                                    </div>
                                </div>
                                <div class="confirm-tigo-instructions">
                                    <h4>Instrucciones:</h4>
                                    <ol>
                                        <li>Ingresa tu número de Tigo Money</li>
                                        <li>Recibirás un SMS con un código de verificación</li>
                                        <li>Confirma el pago en la aplicación Tigo Money</li>
                                    </ol>
                                </div>
                                <div class="confirm-form-row">
                                    <button type="button" id="triggerTigoPay" class="confirm-tigo-button">Enviar solicitud de pago</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="confirm-buttons-container">
            <form action="procesar_reserva.php" method="post" id="confirmPurchaseForm">
                <input type="hidden" name="funcion_id" value="<?php echo $funcionId; ?>">
                <input type="hidden" name="asientos_seleccionados" value="<?php echo $asientosSeleccionados; ?>">
                <input type="hidden" name="productos_seleccionados" value="<?php echo $productosSeleccionados; ?>">
                <input type="hidden" name="codigo_promocional" value="<?php echo $codigoPromocional; ?>">
                <input type="hidden" name="descuento_aplicado" value="<?php echo $descuentoAplicado; ?>">
                <input type="hidden" name="metodo_pago" id="metodo_pago" value="qr">
                <input type="hidden" name="payment_details" id="payment_details" value="">
                
                <div class="confirm-buttons">
                    <a href="javascript:history.back()" class="confirm-back-btn">Volver</a>
                    <button type="submit" class="confirm-purchase-btn">Confirmar compra</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Payment tab switching
    const paymentTabs = document.querySelectorAll('.confirm-payment-tab');
    const paymentPanels = document.querySelectorAll('.confirm-payment-panel');
    const metodoInput = document.getElementById('metodo_pago');
    
    paymentTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Deactivate all tabs
            paymentTabs.forEach(t => t.classList.remove('active'));
            // Activate clicked tab
            this.classList.add('active');
            
            // Hide all panels
            paymentPanels.forEach(panel => panel.classList.remove('active'));
            
            // Show corresponding panel
            const target = this.getAttribute('data-target');
            document.getElementById(target).classList.add('active');
            
            // Update payment method hidden input
            if (target === 'qr-pay') {
                metodoInput.value = 'qr';
            } else if (target === 'card-pay') {
                metodoInput.value = 'tarjeta';
            } else if (target === 'tigo-pay') {
                metodoInput.value = 'tigo_money';
            }
        });
    });
    
    // Format card number with spaces
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            this.value = formattedValue;
        });
    }
    
    // Format card expiry date
    const cardExpiryInput = document.getElementById('card_expiry');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            this.value = value;
        });
    }
    
    // QR timer countdown
    const qrTimer = document.getElementById('qrTimer');
    if (qrTimer) {
        let minutes = 10;
        let seconds = 0;
        
        const countdown = setInterval(function() {
            if (seconds === 0) {
                if (minutes === 0) {
                    clearInterval(countdown);
                    qrTimer.parentElement.innerHTML = '<span class="confirm-expired">QR expirado. <a href="javascript:location.reload()">Recargar</a></span>';
                    return;
                }
                minutes--;
                seconds = 59;
            } else {
                seconds--;
            }
            
            qrTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }
    
    // Handle Tigo Money payment
    const tigoButton = document.getElementById('triggerTigoPay');
    if (tigoButton) {
        tigoButton.addEventListener('click', function() {
            const tigoNumber = document.getElementById('tigo_number').value;
            if (!tigoNumber || tigoNumber.length < 8) {
                alert('Por favor ingresa un número de Tigo Money válido');
                return;
            }
            
            // Simulate sending request
            tigoButton.textContent = 'Enviando...';
            tigoButton.disabled = true;
            
            // Simulate response after 2 seconds
            setTimeout(function() {
                const tigoContainer = document.querySelector('.confirm-tigo-container');
                tigoContainer.innerHTML = `
                    <div class="confirm-tigo-verification">
                        <h4>Verificación pendiente</h4>
                        <p>Hemos enviado una solicitud de pago a tu número Tigo Money ${tigoNumber}.</p>
                        <p>Por favor revisa tu teléfono y confirma el pago en la aplicación Tigo Money.</p>
                        <div class="confirm-tigo-status">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Esperando confirmación...</span>
                        </div>
                    </div>
                `;
                
                // Update payment details
                document.getElementById('payment_details').value = JSON.stringify({
                    method: 'tigo_money',
                    phone: tigoNumber,
                    status: 'pending'
                });
            }, 2000);
        });
    }
    
    // Handle form submission
    const purchaseForm = document.getElementById('confirmPurchaseForm');
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const paymentMethod = document.getElementById('metodo_pago').value;
            let paymentDetails = {};
            let isValid = true;
            
            if (paymentMethod === 'tarjeta') {
                // Check if a saved card is selected
                const savedCard = document.querySelector('input[name="payment_method"]:checked');
                
                if (savedCard) {
                    // Using saved card
                    paymentDetails = {
                        method: 'tarjeta',
                        saved_card_id: savedCard.value
                    };
                } else {
                    // Using new card
                    const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                    const cardName = document.getElementById('card_name').value;
                    const cardExpiry = document.getElementById('card_expiry').value;
                    const cardCvv = document.getElementById('card_cvv').value;
                    const saveCard = document.getElementById('save_card').checked;
                    
                    if (!cardNumber || cardNumber.length < 15) {
                        alert('Por favor ingresa un número de tarjeta válido');
                        isValid = false;
                    } else if (!cardName) {
                        alert('Por favor ingresa el nombre en la tarjeta');
                        isValid = false;
                    } else if (!cardExpiry || !cardExpiry.includes('/')) {
                        alert('Por favor ingresa una fecha de vencimiento válida');
                        isValid = false;
                    } else if (!cardCvv || cardCvv.length < 3) {
                        alert('Por favor ingresa un CVV válido');
                        isValid = false;
                    }
                    
                    if (isValid) {
                        paymentDetails = {
                            method: 'tarjeta',
                            card_number: `xxxx xxxx xxxx ${cardNumber.slice(-4)}`,
                            card_name: cardName,
                            card_expiry: cardExpiry,
                            save_card: saveCard
                        };
                    }
                }
            } else if (paymentMethod === 'qr') {
                paymentDetails = {
                    method: 'qr',
                    status: 'pending'
                };
            } else if (paymentMethod === 'tigo_money') {
                const tigoNumber = document.getElementById('tigo_number');
                if (tigoNumber && !tigoNumber.value) {
                    alert('Por favor ingresa un número de Tigo Money válido');
                    isValid = false;
                } else {
                    // Verify if tigo payment already triggered
                    if (document.querySelector('.confirm-tigo-verification')) {
                        paymentDetails = JSON.parse(document.getElementById('payment_details').value || '{}');
                    } else {
                        alert('Por favor inicia el proceso de pago con Tigo Money');
                        isValid = false;
                    }
                }
            }
            
            if (isValid) {
                // Validate personal information
                const nombres = document.getElementById('nombres').value;
                const apellidos = document.getElementById('apellidos').value;
                const telefono = document.getElementById('telefono').value;
                const nitCi = document.getElementById('nit_ci').value;
                
                if (!nombres) {
                    alert('Por favor ingresa tu nombre');
                    isValid = false;
                } else if (!apellidos) {
                    alert('Por favor ingresa tus apellidos');
                    isValid = false;
                } else if (!telefono) {
                    alert('Por favor ingresa un número de teléfono');
                    isValid = false;
                } else if (!nitCi) {
                    alert('Por favor ingresa un NIT o CI');
                    isValid = false;
                }
                
                if (isValid) {
                    // Add user details to payment details
                    paymentDetails.user_info = {
                        nombres: nombres,
                        apellidos: apellidos,
                        telefono: telefono,
                        nit_ci: nitCi
                    };
                    
                    // Update hidden input with payment details
                    document.getElementById('payment_details').value = JSON.stringify(paymentDetails);
                    
                    // Simulate payment processing
                    const confirmBtn = document.querySelector('.confirm-purchase-btn');
                    confirmBtn.textContent = 'Procesando...';
                    confirmBtn.disabled = true;
                    
                    // Simulate processing time
                    setTimeout(function() {
                        purchaseForm.submit();
                    }, 1500);
                }
            }
        });
    }
});
</script>

<?php //require_once 'includes/footer.php'; ?>