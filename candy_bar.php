<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Check if user is logged in
if (!estaLogueado()) {
   setMensaje('Debe iniciar sesión para continuar', 'warning');
   redirect('auth/login.php');
}

// Retrieve reservation data from localStorage (will be done on client side)
// For this server-side code, check for any passed parameters
$funcionId = isset($_GET['funcion_id']) ? intval($_GET['funcion_id']) : null;
$asientosSeleccionados = isset($_GET['asientos_seleccionados']) ? $_GET['asientos_seleccionados'] : '';
$codigoPromocional = isset($_GET['codigo_promocional']) ? $_GET['codigo_promocional'] : '';
$descuentoAplicado = isset($_GET['descuento_aplicado']) ? $_GET['descuento_aplicado'] : 0;

// We'll validate these later with JavaScript when submitting

// Get function details if we have a function ID
$funcion = null;
$asientosData = [];
$numAsientos = 0;
$costoTotalAsientos = 0;
$posterUrl = 'assets/img/poster-default.jpg';

if ($funcionId) {
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

    if ($resultFuncion->num_rows > 0) {
        $funcion = $resultFuncion->fetch_assoc();
        
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

        if ($resultPoster->num_rows > 0) {
           $poster = $resultPoster->fetch_assoc();
           $posterUrl = $poster['url'];
        }
        
        // Process selected seats if we have them
        if (!empty($asientosSeleccionados)) {
            $asientosIds = explode(',', $asientosSeleccionados);
            $asientosIds = array_map('intval', $asientosIds);
            $numAsientos = count($asientosIds);
            
            // Get seats info
            $placeholders = implode(',', array_fill(0, count($asientosIds), '?'));
            $queryAsientos = "SELECT a.*, s.id as sala_id FROM asientos a 
                            JOIN salas s ON a.sala_id = s.id 
                            WHERE a.id IN ($placeholders)";

            $stmtAsientos = $conn->prepare($queryAsientos);
            $types = str_repeat('i', count($asientosIds));
            $stmtAsientos->bind_param($types, ...$asientosIds);
            $stmtAsientos->execute();
            $resultAsientos = $stmtAsientos->get_result();

            while ($asiento = $resultAsientos->fetch_assoc()) {
               $asientosData[] = $asiento;
            }
            
            // Calculate total
            $costoTotalAsientos = $funcion['precio_base'] * $numAsientos;
        }
    }
}

// Get candy bar products and their details
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

// Get combo details
$queryCombos = "SELECT c.id as combo_id, c.nombre as combo_nombre, c.precio as combo_precio,
               c.descripcion as combo_descripcion, cp.producto_id, cp.cantidad,
               p.nombre as producto_nombre, p.categoria_id
               FROM combos c
               JOIN combo_producto cp ON c.id = cp.combo_id
               JOIN productos p ON cp.producto_id = p.id
               WHERE c.activo = 1
               ORDER BY c.id, p.categoria_id";
$stmtCombos = $conn->prepare($queryCombos);
$stmtCombos->execute();
$resultCombos = $stmtCombos->get_result();

$combosDetalles = [];
$combosInfo = [];
while ($combo = $resultCombos->fetch_assoc()) {
   $comboId = $combo['combo_id'];
   
   // Store combo info once
   if (!isset($combosInfo[$comboId])) {
       $combosInfo[$comboId] = [
           'id' => $comboId,
           'nombre' => $combo['combo_nombre'],
           'precio' => $combo['combo_precio'],
           'descripcion' => $combo['combo_descripcion']
       ];
   }
   
   // Store products in each combo
   if (!isset($combosDetalles[$comboId])) {
       $combosDetalles[$comboId] = [
           'productos' => []
       ];
   }
   
   $combosDetalles[$comboId]['productos'][] = [
       'id' => $combo['producto_id'],
       'nombre' => $combo['producto_nombre'],
       'cantidad' => $combo['cantidad'],
       'categoria_id' => $combo['categoria_id']
   ];
}

// Add combos as products to display
if (!empty($combosInfo)) {
   // Find or create the combos category
   $combosCategoriaId = 1; // Assuming 1 is for combos
   if (!isset($productos[$combosCategoriaId])) {
       $productos[$combosCategoriaId] = [
           'nombre' => 'Combos',
           'items' => []
       ];
   }
   
   // Add each combo
   foreach ($combosInfo as $combo) {
       $productos[$combosCategoriaId]['items'][] = [
           'id' => $combo['id'],
           'nombre' => $combo['nombre'],
           'precio_unitario' => $combo['precio'],
           'categoria_id' => $combosCategoriaId,
           'es_combo' => true
       ];
   }
}

// Imagen para cada producto basada en su ID
function getProductImageUrl($producto, $esCombo = false) {
   $id = $producto['id'];
   $nombre = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $producto['nombre']));
   
   $baseDir = 'assets/img/candybar/';
   $tipo = $esCombo ? 'combo' : 'producto';
   
   // Verificar si existe imagen con el ID del producto
   $imgPath = $baseDir . $tipo . '/' . $id . '.jpg';
   if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/multicinev3/' . $imgPath)) {
       return $imgPath;
   }
   
   // Imagen por defecto según tipo de producto
   $nombreLower = strtolower($producto['nombre']);
   
   if ($esCombo) {
       return $baseDir . 'combo/combo' . ($id % 3 + 1) . '.jpg'; // Rotamos entre combo1, combo2 y combo3
   } else if (strpos($nombreLower, 'pipoca') !== false || strpos($nombreLower, 'palomita') !== false) {
       if (strpos($nombreLower, 'dulce') !== false) {
           return $baseDir . 'producto/popcorn-sweet.jpg';
       } else if (strpos($nombreLower, 'mixta') !== false) {
           return $baseDir . 'producto/popcorn-mix.jpg';
       } else {
           return $baseDir . 'producto/popcorn-salt.jpg';
       }
   } else if (strpos($nombreLower, 'coca') !== false) {
       if (strpos($nombreLower, 'sin azúcar') !== false || strpos($nombreLower, 'zero') !== false) {
           return $baseDir . 'producto/coke-zero.jpg';
       } else {
           return $baseDir . 'producto/coke.jpg';
       }
   } else if (strpos($nombreLower, 'fanta') !== false) {
       if (strpos($nombreLower, 'papaya') !== false) {
           return $baseDir . 'producto/fanta-papaya.jpg';
       } else {
           return $baseDir . 'producto/fanta.jpg';
       }
   } else if (strpos($nombreLower, 'sprite') !== false) {
       return $baseDir . 'producto/sprite.jpg';
   } else if (strpos($nombreLower, 'nacho') !== false) {
       return $baseDir . 'producto/nachos.jpg';
   }
   
   // Imagen por defecto genérica
   return $baseDir . 'producto/generic.jpg';
}

require_once 'includes/header.php';
?>

<link href="assets/css/reserva.css" rel="stylesheet">
<link href="assets/css/resumen.css" rel="stylesheet">
<link href="assets/css/resumen_compra.css" rel="stylesheet">

<div class="res-comp-container">
   <div class="res-comp-sidebar">
       <div class="res-comp-movie-info">
           <a href="javascript:history.back()" class="res-comp-home-btn">
               <i class="fas fa-arrow-left"></i>
           </a>
           <img src="<?php echo $posterUrl; ?>" alt="<?php echo $funcion ? $funcion['pelicula_titulo'] : 'Película'; ?>" class="res-comp-movie-poster">
           <div class="res-comp-age-rating">
               <div class="res-comp-rating-circle">12</div>
           </div>
           <h2 class="res-comp-movie-title"><?php echo $funcion ? $funcion['pelicula_titulo'] : 'Película'; ?></h2>
           <p class="res-comp-cinema-name"><?php echo $funcion ? $funcion['cine_nombre'] : 'Cine'; ?></p>
       </div>
       
       <?php if ($funcion): ?>
       <div class="res-comp-cinema-info">
           <h3>Cine</h3>
           <p class="res-comp-info-text"><?php echo $funcion['cine_nombre']; ?></p>
           
           <h3>Fecha</h3>
           <p class="res-comp-info-text"><?php 
               setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'Spanish');
               echo strftime('%A %d de %B de %Y', strtotime($funcion['fecha_hora'])); 
           ?></p>
           
           <h3>Proyección</h3>
           <p class="res-comp-info-text">
               <?php echo date('H:i', strtotime($funcion['fecha_hora'])); ?> <?php echo $funcion['formato']; ?>
               <br>
               <small>Versión Original</small>
           </p>
           <p class="res-comp-info-text res-comp-end-time">
               Hora prevista de finalización: <?php 
                   $endTime = strtotime($funcion['fecha_hora']) + ($funcion['duracion_min'] * 60);
                   echo date('H:i', $endTime); 
               ?>
           </p>
       </div>
       <?php endif; ?>
       
       <div class="res-comp-tickets-info">
           <?php if (!empty($asientosData)): ?>
           <h3>Mis entradas</h3>
           <div class="res-comp-selected-seats-summary">
               <p>Asientos seleccionados:</p>
               <div class="res-comp-seat-list">
                   <?php 
                   $seatLabels = array_map(function($seat) {
                       return $seat['fila'] . $seat['numero'];
                   }, $asientosData);
                   echo implode(', ', $seatLabels);
                   ?>
               </div>
           </div>
           <div class="res-comp-ticket-item">
               <span><?php echo $numAsientos; ?>x Entrada</span>
               <span>Bs. <?php echo number_format($funcion['precio_base'], 2); ?></span>
           </div>
           <?php endif; ?>
           
           <!-- Sección de productos seleccionados -->
           <div id="miProductosContainer" class="res-comp-products-container">
               <h3>Mis productos</h3>
               <div id="selectedProductsList" class="res-comp-selected-products-list">
                   <!-- Aquí se agregarán los productos seleccionados -->
                   <p class="empty-cart-message">Aún no ha seleccionado ningún producto</p>
               </div>
           </div>
           
           <div class="res-comp-ticket-total">
               <span>Total</span>
               <span id="totalPriceDisplay">
                   <?php if ($costoTotalAsientos > 0): ?>
                   Bs. <?php echo number_format($costoTotalAsientos, 2); ?>
                   <?php else: ?>
                   Bs. 0.00
                   <?php endif; ?>
               </span>
           </div>
       </div>
   </div>
   
   <div class="res-comp-main-content">
       <h2>Candy Bar</h2>
       <p>Añada productos de nuestro candy bar a su pedido</p>
       
       <div class="res-comp-candy-bar-section">
           <?php foreach($productos as $categoriaId => $categoria): ?>
               <div class="res-comp-product-category">
                   <h3><?php echo $categoria['nombre']; ?></h3>
                   
                   <div class="res-comp-products-grid">
                       <?php foreach($categoria['items'] as $producto): ?>
                           <?php 
                           $esCombo = isset($producto['es_combo']) ? $producto['es_combo'] : false;
                           if (!$esCombo) {
                               $esCombo = (strtolower($categoria['nombre']) === 'combos');
                           }
                           $imageUrl = getProductImageUrl($producto, $esCombo);
                           
                           // Obtener detalles del combo
                           $comboDetalles = $esCombo && isset($combosDetalles[$producto['id']]) 
                                          ? json_encode($combosDetalles[$producto['id']]) 
                                          : 'null';
                           ?>
                           
                           <div class="res-comp-product-item" 
                                data-id="<?php echo $producto['id']; ?>" 
                                data-price="<?php echo $producto['precio_unitario']; ?>"
                                data-name="<?php echo $producto['nombre']; ?>"
                                data-is-combo="<?php echo $esCombo ? 'true' : 'false'; ?>"
                                data-combo-details='<?php echo $comboDetalles; ?>'>
                               <div class="res-comp-product-image">
                                   <img src="<?php echo $imageUrl; ?>" 
                                        alt="<?php echo $producto['nombre']; ?>" 
                                        onerror="this.src='assets/img/candybar/producto/popcorn-mix.jpg'">
                               </div>
                               <h5><?php echo $producto['nombre']; ?></h5>
                               <div class="res-comp-product-price">Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></div>
                               <div class="res-comp-product-actions">
                                   <button class="res-comp-product-btn">Añadir</button>
                               </div>
                           </div>
                       <?php endforeach; ?>
                   </div>
               </div>
           <?php endforeach; ?>
       </div>
       
       <div class="res-comp-continue-container">
        <form id="completePurchaseForm" action="confirmar_compra.php" method="post">
            <input type="hidden" name="funcion_id" id="funcion_id" value="<?php echo $funcionId; ?>">
            <input type="hidden" name="asientos_seleccionados" id="asientos_seleccionados" value="<?php echo $asientosSeleccionados; ?>">
            <input type="hidden" name="productos_seleccionados" id="productosSeleccionados" value="">
            <input type="hidden" name="codigo_promocional" id="codigoPromocional" value="<?php echo $codigoPromocional; ?>">
            <input type="hidden" name="descuento_aplicado" id="descuentoAplicado" value="<?php echo $descuentoAplicado; ?>">
            <button type="button" id="backButton" class="res-comp-back-btn">Volver a Entradas</button>
            <button type="submit" class="res-comp-continue-btn">Continuar</button>
        </form>
        </div>
   </div>
</div>

<!-- Modal para configurar combo -->
<div id="comboModal" class="res-comp-modal">
   <div class="res-comp-modal-content">
       <span class="res-comp-close">&times;</span>
       <h2>Configura tu combo</h2>
       <div class="res-comp-combo-details">
           <h3 id="comboName"></h3>
           <p id="comboPrice"></p>
       </div>
       
       <div class="res-comp-combo-options">
           <!-- Secciones dinámicas para los componentes del combo -->
           <div id="comboOptionsContainer">
               <!-- Aquí se insertarán dinámicamente las opciones según el combo seleccionado -->
           </div>
       </div>
       
       <div class="res-comp-combo-actions">
           <button id="addComboBtn" class="res-comp-combo-add-btn">Añadir al carrito</button>
       </div>
   </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
   // Get reservation data from localStorage if available
   let reservationData = null;
   try {
       const storedData = localStorage.getItem('currentReservation');
       if (storedData) {
           reservationData = JSON.parse(storedData);
           
           // Fill in form fields from localStorage data
           document.getElementById('funcion_id').value = reservationData.funcion_id || '';
           document.getElementById('asientos_seleccionados').value = reservationData.asientos_seleccionados || '';
           document.getElementById('codigoPromocional').value = reservationData.codigo_promocional || '';
           document.getElementById('descuentoAplicado').value = reservationData.descuento_aplicado || 0;
       }
   } catch (e) {
       console.error('Error parsing reservation data:', e);
   }
   
   // Modal elements
   const comboModal = document.getElementById('comboModal');
   const comboName = document.getElementById('comboName');
   const comboPrice = document.getElementById('comboPrice');
   const closeModal = document.querySelector('.res-comp-close');
   const addComboBtn = document.getElementById('addComboBtn');
   const comboOptionsContainer = document.getElementById('comboOptionsContainer');
   
   // My products section
   const miProductosContainer = document.getElementById('miProductosContainer');
   const selectedProductsList = document.getElementById('selectedProductsList');
   
   // Variables for the current selected product
   let currentProduct = null;
   
   // Original price calculation
   const basePrice = <?php echo $funcion ? $funcion['precio_base'] : 0; ?>;
   const numSeats = <?php echo $numAsientos ?: 0; ?>;
   let ticketsPrice = basePrice * numSeats;
   let productsTotalPrice = 0;
   
   // Calculate total price including any selected products
   const updateTotalPrice = () => {
       let total = ticketsPrice + productsTotalPrice;
       
       // Apply discount if applicable
       const discount = parseFloat(document.getElementById('descuentoAplicado').value || 0);
       if (discount > 0 && document.getElementById('codigoPromocional').value) {
           // Assume percentage discount unless specified otherwise
           total = total * (1 - (discount / 100));
       }
       
       // Make sure total doesn't go below zero
       total = Math.max(0, total);
       document.getElementById('totalPriceDisplay').textContent = `Bs. ${total.toFixed(2)}`;
   };
   
   // Bebidas y palomitas disponibles para selección
   const bebidasDisponibles = [
       { value: 'coca-cola', label: 'Coca Cola' },
       { value: 'coca-cola-zero', label: 'Coca Cola Zero' },
       { value: 'fanta', label: 'Fanta' },
       { value: 'fanta-papaya', label: 'Fanta Papaya' },
       { value: 'sprite', label: 'Sprite' }
   ];
   
   const palomitasDisponibles = [
       { value: 'salada', label: 'Salada' },
       { value: 'dulce', label: 'Dulce' },
       { value: 'mixta', label: 'Mixta' }
   ];
   
   // Back button functionality
   document.getElementById('backButton').addEventListener('click', function() {
       // Go back to reservation page
       window.location.href = 'reserva.php?funcion=' + document.getElementById('funcion_id').value;
   });
   
   // Close modal when clicking X
   closeModal.addEventListener('click', function() {
       comboModal.style.display = 'none';
   });
   
   // Close modal when clicking outside
   window.addEventListener('click', function(event) {
       if (event.target === comboModal) {
           comboModal.style.display = 'none';
       }
   });
   
   // Product selection
   const productItems = document.querySelectorAll('.res-comp-product-item');
   const productosSeleccionados = document.getElementById('productosSeleccionados');
   let selectedProducts = [];
   
   productItems.forEach(item => {
       const addBtn = item.querySelector('.res-comp-product-btn');
       
       addBtn.addEventListener('click', function() {
           const productId = item.dataset.id;
           const productPrice = parseFloat(item.dataset.price);
           const productName = item.dataset.name;
           const isCombo = item.dataset.isCombo === 'true';
           let comboDetails = null;
           
           try {
               if (item.dataset.comboDetails !== 'null') {
                   comboDetails = JSON.parse(item.dataset.comboDetails);
               }
           } catch (e) {
               console.error('Error parsing combo details:', e);
           }
           
           if (isCombo && comboDetails) {
               // Set current product for the modal
               currentProduct = {
                   id: productId,
                   price: productPrice,
                   name: productName,
                   details: comboDetails
               };
               
               // Configure modal based on combo content
               configureComboModal(currentProduct);
               
               // Show modal
               comboModal.style.display = 'block';
           } else {
               // Add regular product
               addProductToSelection(productId, productPrice, productName);
               
               // Visual feedback
               addBtn.textContent = 'Añadido';
               addBtn.classList.add('res-comp-added');
               
               setTimeout(() => {
                   addBtn.textContent = 'Añadir';
                   addBtn.classList.remove('res-comp-added');
               }, 1500);
           }
       });
   });
   
   function configureComboModal(product) {
       comboName.textContent = product.name;
       comboPrice.textContent = `Bs. ${product.price.toFixed(2)}`;
       
       // Clear previous options
       comboOptionsContainer.innerHTML = '';
       
       // Analizar los componentes del combo
       const comboComponents = {
           bebidas: [],
           palomitas: [],
           nachos: false,
           otros: []
       };
       
       if (product.details && product.details.productos) {
            product.details.productos.forEach(item => {
                const nombreLower = item.nombre.toLowerCase();
                const cantidad = parseInt(item.cantidad) || 1;
                
                // Crear entradas según la cantidad
                for (let i = 0; i < cantidad; i++) {
                    if (nombreLower.includes('coca') || nombreLower.includes('fanta') || 
                        nombreLower.includes('sprite') || nombreLower.includes('bebida') || 
                        nombreLower.includes('vaso')) {
                        // Clonar el objeto para cada instancia
                        const bebidaItem = {...item};
                        comboComponents.bebidas.push(bebidaItem);
                    } else if (nombreLower.includes('pipoca') || nombreLower.includes('palomita')) {
                        const palomitaItem = {...item};
                        comboComponents.palomitas.push(palomitaItem);
                    } else if (nombreLower.includes('nacho')) {
                        comboComponents.nachos = true;
                    } else {
                        comboComponents.otros.push({...item});
                    }
                }
            });
        }
       
       // Crear sección de bebidas si hay
       if (comboComponents.bebidas.length > 0) {
           const bebidasSection = document.createElement('div');
           bebidasSection.className = 'res-comp-combo-section';
           
           const title = document.createElement('h4');
           title.textContent = `Selecciona tus bebidas (${comboComponents.bebidas.length})`;
           bebidasSection.appendChild(title);
           
           const optionsGroup = document.createElement('div');
           optionsGroup.className = 'res-comp-combo-option-group';
           
           // Crear un select por cada bebida
           for (let i = 0; i < comboComponents.bebidas.length; i++) {
               const select = document.createElement('select');
               select.className = 'res-comp-combo-select';
               select.id = `bebida-${i}`;
               select.setAttribute('data-index', i);
               
               bebidasDisponibles.forEach(bebida => {
                   const option = document.createElement('option');
                   option.value = bebida.value;
                   option.textContent = bebida.label;
                   select.appendChild(option);
                 });
               
               optionsGroup.appendChild(select);
               if (i < comboComponents.bebidas.length - 1) {
                   // Agregar un separador entre los selects
                   const separator = document.createElement('hr');
                   separator.className = 'res-comp-combo-separator';
                   optionsGroup.appendChild(separator);
               }
           }
           
           bebidasSection.appendChild(optionsGroup);
           comboOptionsContainer.appendChild(bebidasSection);
       }
       
       // Crear sección de palomitas si hay
       if (comboComponents.palomitas.length > 0) {
           const palomitasSection = document.createElement('div');
           palomitasSection.className = 'res-comp-combo-section';
           
           const title = document.createElement('h4');
           title.textContent = `Selecciona tus palomitas (${comboComponents.palomitas.length})`;
           palomitasSection.appendChild(title);
           
           const optionsGroup = document.createElement('div');
           optionsGroup.className = 'res-comp-combo-option-group';
           
           // Crear un select por cada palomita
           for (let i = 0; i < comboComponents.palomitas.length; i++) {
               const select = document.createElement('select');
               select.className = 'res-comp-combo-select';
               select.id = `palomita-${i}`;
               select.setAttribute('data-index', i);
               
               palomitasDisponibles.forEach(palomita => {
                   const option = document.createElement('option');
                   option.value = palomita.value;
                   option.textContent = palomita.label;
                   if (palomita.value === 'mixta') {
                       option.selected = true;
                   }
                   select.appendChild(option);
               });
               
               optionsGroup.appendChild(select);
               if (i < comboComponents.palomitas.length - 1) {
                   // Agregar un separador entre los selects
                   const separator = document.createElement('hr');
                   separator.className = 'res-comp-combo-separator';
                   optionsGroup.appendChild(separator);
               }
           }
           
           palomitasSection.appendChild(optionsGroup);
           comboOptionsContainer.appendChild(palomitasSection);
       }
       
       // Crear sección de nachos si hay
       if (comboComponents.nachos) {
           const nachosSection = document.createElement('div');
           nachosSection.className = 'res-comp-combo-section';
           
           const title = document.createElement('h4');
           title.textContent = '¿Nachos?';
           nachosSection.appendChild(title);
           
           const optionsGroup = document.createElement('div');
           optionsGroup.className = 'res-comp-combo-option-group';
           
           const select = document.createElement('select');
           select.className = 'res-comp-combo-select';
           select.id = 'nachos';
           
           const optionYes = document.createElement('option');
           optionYes.value = 'si';
           optionYes.textContent = 'Sí';
           select.appendChild(optionYes);
           
           const optionNo = document.createElement('option');
           optionNo.value = 'no';
           optionNo.textContent = 'No';
           select.appendChild(optionNo);
           
           optionsGroup.appendChild(select);
           nachosSection.appendChild(optionsGroup);
           comboOptionsContainer.appendChild(nachosSection);
       }
       
       // Crear secciones para otros productos si hay
       if (comboComponents.otros.length > 0) {
           const otrosSection = document.createElement('div');
           otrosSection.className = 'res-comp-combo-section';
           
           const title = document.createElement('h4');
           title.textContent = 'Otros productos incluidos:';
           otrosSection.appendChild(title);
           
           const productosList = document.createElement('ul');
           productosList.className = 'res-comp-combo-productos-list';
           
           comboComponents.otros.forEach(producto => {
               const item = document.createElement('li');
               item.textContent = `${producto.cantidad}x ${producto.nombre}`;
               productosList.appendChild(item);
           });
           
           otrosSection.appendChild(productosList);
           comboOptionsContainer.appendChild(otrosSection);
       }
   }
   
   // Add combo button in modal
   addComboBtn.addEventListener('click', function() {
       if (!currentProduct) return;
       
       // Recopilar las opciones seleccionadas
       const comboOptions = {
           bebidas: [],
           palomitas: [],
           nachos: null,
           otros: []
       };
       
       // Recoger las selecciones de bebidas
       const bebidaSelects = comboOptionsContainer.querySelectorAll('select[id^="bebida-"]');
       bebidaSelects.forEach(select => {
           comboOptions.bebidas.push({
               index: parseInt(select.getAttribute('data-index')),
               valor: select.value,
               label: select.options[select.selectedIndex].text
           });
       });
       
       // Recoger las selecciones de palomitas
       const palomitaSelects = comboOptionsContainer.querySelectorAll('select[id^="palomita-"]');
       palomitaSelects.forEach(select => {
           comboOptions.palomitas.push({
               index: parseInt(select.getAttribute('data-index')),
               valor: select.value,
               label: select.options[select.selectedIndex].text
           });
       });
       
       // Recoger la selección de nachos
       const nachosSelect = document.getElementById('nachos');
       if (nachosSelect) {
           comboOptions.nachos = nachosSelect.value === 'si';
       }
       
       // Obtener otros productos incluidos en el combo
       if (currentProduct.details && currentProduct.details.productos) {
           currentProduct.details.productos.forEach(item => {
               const nombreLower = item.nombre.toLowerCase();
               if (!nombreLower.includes('coca') && !nombreLower.includes('fanta') && 
                   !nombreLower.includes('sprite') && !nombreLower.includes('bebida') && 
                   !nombreLower.includes('vaso') && !nombreLower.includes('pipoca') && 
                   !nombreLower.includes('palomita') && !nombreLower.includes('nacho')) {
                   comboOptions.otros.push(item);
               }
           });
       }
       
       // Añadir el combo a la selección
       addProductToSelection(
           currentProduct.id, 
           currentProduct.price, 
           currentProduct.name, 
           comboOptions
       );
       
       // Cerrar el modal
       comboModal.style.display = 'none';
       
       // Encontrar el botón de añadir para feedback visual
       productItems.forEach(item => {
           if (item.dataset.id === currentProduct.id) {
               const addBtn = item.querySelector('.res-comp-product-btn');
               addBtn.textContent = 'Añadido';
               addBtn.classList.add('res-comp-added');
               
               setTimeout(() => {
                   addBtn.textContent = 'Añadir';
                   addBtn.classList.remove('res-comp-added');
               }, 1500);
           }
       });
   });
   
   function addProductToSelection(id, price, name, options = null) {
        // Verificar si el producto ya existe con las mismas opciones
        const optionsStr = options ? JSON.stringify(options) : '';
        const existingIndex = selectedProducts.findIndex(p => 
            p.id === id && 
            JSON.stringify(p.options) === optionsStr
        );
        
        if (existingIndex !== -1) {
            // Incrementar cantidad si ya existe con las mismas opciones
            selectedProducts[existingIndex].quantity++;
            updateProductList();
        } else {
            // Añadir nuevo producto
            const productObj = {
                id: id,
                price: price,
                name: name,
                quantity: 1,
                options: options,
                is_combo: (options !== null) // Marcar como combo si tiene opciones
            };
            
            selectedProducts.push(productObj);
            updateProductList();
        }
        
        // Actualizar input oculto y display
        updateSelectedProducts();
    }
   
   function updateProductList() {
       // Limpiar la lista
       selectedProductsList.innerHTML = '';
       
       // Calcular total de productos
       productsTotalPrice = 0;
       
       if (selectedProducts.length > 0) {
           // Eliminar mensaje de carrito vacío si está presente
           const emptyCartMsg = selectedProductsList.querySelector('.empty-cart-message');
           if (emptyCartMsg) {
               emptyCartMsg.remove();
           }
           
           // Añadir cada producto a la lista
           selectedProducts.forEach((product, index) => {
               const productElement = document.createElement('div');
               productElement.className = 'res-comp-selected-product';
               
               // Crear texto de opciones del producto
               let productOptionText = '';
               if (product.options) {
                   // Para combos con opciones estructuradas
                   if (product.options.bebidas && product.options.bebidas.length > 0) {
                       const bebidasText = product.options.bebidas.map(b => b.label).join(', ');
                       productOptionText += `<div class="res-comp-product-option">Bebidas: ${bebidasText}</div>`;
                   }
                   
                   if (product.options.palomitas && product.options.palomitas.length > 0) {
                       const palomitasText = product.options.palomitas.map(p => p.label).join(', ');
                       productOptionText += `<div class="res-comp-product-option">Palomitas: ${palomitasText}</div>`;
                   }
                   
                   if (product.options.nachos !== null) {
                       productOptionText += `<div class="res-comp-product-option">Nachos: ${product.options.nachos ? 'Sí' : 'No'}</div>`;
                   }
                   
                   if (product.options.otros && product.options.otros.length > 0) {
                       const otrosText = product.options.otros.map(o => `${o.cantidad}x ${o.nombre}`).join(', ');
                       productOptionText += `<div class="res-comp-product-option">Incluye: ${otrosText}</div>`;
                   }
               }
               
               const itemTotal = product.price * product.quantity;
               productsTotalPrice += itemTotal;
               
               productElement.innerHTML = `
                   <div class="res-comp-product-details">
                       <div class="res-comp-product-name">${product.quantity}x ${product.name}</div>
                       ${productOptionText}
                   </div>
                   <div class="res-comp-product-price-actions">
                       <div class="res-comp-product-total">Bs. ${itemTotal.toFixed(2)}</div>
                       <button class="res-comp-remove-product" data-index="${index}">
                           <i class="fas fa-times"></i>
                       </button>
                   </div>
               `;
               
               selectedProductsList.appendChild(productElement);
               
               // Añadir event listener al botón de eliminar producto
               productElement.querySelector('.res-comp-remove-product').addEventListener('click', function() {
                   removeProduct(index);
               });
           });
       } else {
           // Si no hay productos, mostrar mensaje
           const emptyMessage = document.createElement('p');
           emptyMessage.className = 'empty-cart-message';
           emptyMessage.textContent = 'Aún no ha seleccionado ningún producto';
           selectedProductsList.appendChild(emptyMessage);
       }
       
       // Actualizar el precio total
       updateTotalPrice();
   }
   
   function removeProduct(index) {
       // Verificar si el índice existe
       if (index >= 0 && index < selectedProducts.length) {
           // Eliminar el producto del array
           selectedProducts.splice(index, 1);
           
           // Actualizar la lista de productos y el input oculto
           updateProductList();
           updateSelectedProducts();
       }
   }
   
   function updateSelectedProducts() {
       if(selectedProducts.length > 0) {
           // Formatear como JSON
           productosSeleccionados.value = JSON.stringify(selectedProducts);
       } else {
           productosSeleccionados.value = '';
       }
   }
   
   // Envío del formulario
   document.getElementById('completePurchaseForm').addEventListener('submit', function(e) {
       // Actualizar todos los inputs antes del envío
       updateSelectedProducts();
   });
});
</script>

require_once 'includes/footer.php';
<?