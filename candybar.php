<?php
require_once 'includes/functions.php';
iniciarSesion();
$conn = require 'config/database.php';

// Verificar si viene de una compra completa
$compraCompletada = isset($_GET['compra_completada']) && $_GET['compra_completada'] == 1;

// Obtener cine seleccionado
if (isset($_GET['cine'])) {
    $cineSeleccionado = intval($_GET['cine']);
    $_SESSION['cine_id'] = $cineSeleccionado; // Guardar en sesión
} else {
    $cineSeleccionado = isset($_SESSION['cine_id']) ? $_SESSION['cine_id'] : 1;
}

// Consulta para obtener productos
$query = "SELECT p.id, p.nombre, p.precio_unitario, c.nombre as categoria, ic.stock,
                 CONCAT('assets/img/candybar/productos/', p.id, '.jpg') as imagen_url
          FROM productos p
          JOIN categorias_producto c ON p.categoria_id = c.id
          JOIN inventario_cine ic ON p.id = ic.producto_id
          WHERE p.activo = 1 AND ic.cine_id = ? AND ic.stock > 0
          ORDER BY p.nombre ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $cineSeleccionado);
$stmt->execute();
$resultProductos = $stmt->get_result();

$productos = [];
while ($row = $resultProductos->fetch_assoc()) {
    $productos[] = $row;
}
$stmt->close();

// Consulta para combos
$queryCombos = "SELECT c.id, c.nombre, c.descripcion, c.precio, 
                CONCAT('assets/img/candybar/combos/', c.id, '.jpg') as imagen_url
               FROM combos c
               WHERE c.activo = 1 AND (c.fecha_fin IS NULL OR c.fecha_fin > NOW())
               ORDER BY c.nombre";
               
$resultCombos = $conn->query($queryCombos);

$combos = [];
if ($resultCombos && $resultCombos->num_rows > 0) {
    while ($row = $resultCombos->fetch_assoc()) {
        // Verificar disponibilidad de productos en el combo
        $queryComboProductos = "SELECT cp.producto_id, cp.cantidad, ic.stock 
                               FROM combo_producto cp
                               JOIN inventario_cine ic ON cp.producto_id = ic.producto_id
                               WHERE cp.combo_id = ? AND ic.cine_id = ?";
        $stmtComboProductos = $conn->prepare($queryComboProductos);
        $stmtComboProductos->bind_param("ii", $row['id'], $cineSeleccionado);
        $stmtComboProductos->execute();
        $resultComboProductos = $stmtComboProductos->get_result();
        
        $disponible = true;
        while ($producto = $resultComboProductos->fetch_assoc()) {
            if ($producto['stock'] < $producto['cantidad']) {
                $disponible = false;
                break;
            }
        }
        
        $row['disponible'] = $disponible;
        if ($disponible) {
            $combos[] = $row;
        }
        
        $stmtComboProductos->close();
    }
}

// Obtener lista de cines para selector
$queryCines = "SELECT id, nombre, ciudad_id FROM cines WHERE activo = 1 ORDER BY nombre";
$resultCines = $conn->query($queryCines);
$cines = [];

if ($resultCines && $resultCines->num_rows > 0) {
    while ($row = $resultCines->fetch_assoc()) {
        $cines[] = $row;
    }
}

// Obtener nombre del cine seleccionado
$nombreCine = "Todos los cines";
if ($cineSeleccionado > 0) {
    foreach ($cines as $cine) {
        if ($cine['id'] == $cineSeleccionado) {
            $nombreCine = $cine['nombre'];
            break;
        }
    }
}

require_once 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/candybar.css">

<!-- Contenedor principal -->
<div class="candybar-main-container">
    <!-- Cabecera -->
    <div class="candybar-header">
        <h1>CandyBar</h1>
        
        <!-- Selector de cine -->
        <div class="cinema-selector">
            <button id="cinemaButton" class="cinema-select-btn">
                <i class="fas fa-film"></i>
                <?php echo $nombreCine; ?>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div id="cinemaDropdown" class="cinema-dropdown-content">
                <?php foreach ($cines as $cine): ?>
                    <a href="candybar.php?cine=<?php echo $cine['id']; ?>" 
                       class="<?php echo $cineSeleccionado == $cine['id'] ? 'active' : ''; ?>">
                        <?php echo $cine['nombre']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Contenedor de productos -->
    <div class="candybar-content">
        <!-- Sección de Combos -->
        <?php if (!empty($combos)): ?>
        <section class="combos-section">
            <h2 class="section-title">Combos Especiales</h2>
            <div class="products-grid">
                <?php foreach ($combos as $combo): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $combo['imagen_url']; ?>" alt="<?php echo $combo['nombre']; ?>">
                        </div>
                        <div class="product-info">
                            <h3><?php echo $combo['nombre']; ?></h3>
                            <p class="product-description"><?php echo $combo['descripcion']; ?></p>
                            <div class="product-price">Bs. <?php echo number_format($combo['precio'], 2); ?></div>
                            <button class="add-to-cart-btn" data-id="<?php echo $combo['id']; ?>" data-type="combo" data-name="<?php echo $combo['nombre']; ?>" data-price="<?php echo $combo['precio']; ?>">
                                <i class="fas fa-shopping-cart"></i> Agregar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Sección de Productos -->
        <?php if (!empty($productos)): ?>
        <section class="products-section">
            <h2 class="section-title">Productos Individuales</h2>
            <div class="products-grid">
                <?php foreach ($productos as $producto): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $producto['imagen_url']; ?>" alt="<?php echo $producto['nombre']; ?>">
                        </div>
                        <div class="product-info">
                            <h3><?php echo $producto['nombre']; ?></h3>
                            <p class="product-category"><?php echo $producto['categoria']; ?></p>
                            <div class="product-price">Bs. <?php echo number_format($producto['precio_unitario'], 2); ?></div>
                            <button class="add-to-cart-btn" data-id="<?php echo $producto['id']; ?>" data-type="producto" data-name="<?php echo $producto['nombre']; ?>" data-price="<?php echo $producto['precio_unitario']; ?>">
                                <i class="fas fa-shopping-cart"></i> Agregar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (empty($productos) && empty($combos)): ?>
            <div class="no-results">
                <p>No se encontraron productos disponibles en este cine.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Carrito flotante -->
    <div class="floating-cart" id="floatingCart">
        <div class="cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Carrito</h3>
            <span class="cart-count" id="cartCount">0</span>
        </div>
        <div class="cart-body" id="cartItems">
            <p class="empty-cart-message">Tu carrito está vacío</p>
        </div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cartTotal">Bs. 0.00</span>
            </div>
            <button id="checkoutBtn" class="checkout-btn" disabled>
                Proceder al pago
            </button>
        </div>
    </div>
</div>

<!-- Formulario oculto para enviar datos del carrito -->
<form id="cartForm" action="resumen_compra_candybar.php" method="post" style="display: none;">
    <input type="hidden" name="productos_seleccionados" id="productosSeleccionados" value="">
    <input type="hidden" name="cine_id" id="cineId" value="<?php echo $cineSeleccionado; ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si viene de una compra completada
    <?php if ($compraCompletada): ?>
    // Limpiar el carrito si viene de una compra completada
    localStorage.removeItem('candybarCart');
    <?php endif; ?>
    
    // Toggle para el dropdown de cines
    const cinemaButton = document.getElementById('cinemaButton');
    const cinemaDropdown = document.getElementById('cinemaDropdown');
    
    if (cinemaButton && cinemaDropdown) {
        cinemaButton.addEventListener('click', function() {
            cinemaDropdown.classList.toggle('show');
        });
        
        // Cerrar dropdown al hacer clic fuera
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.cinema-select-btn') && 
                !event.target.closest('.cinema-select-btn')) {
                if (cinemaDropdown.classList.contains('show')) {
                    cinemaDropdown.classList.remove('show');
                }
            }
        });
    }
    
    // Manejo del carrito
    let cart = [];
    
    // Inicializar carrito desde localStorage si existe
    if (localStorage.getItem('candybarCart')) {
        try {
            cart = JSON.parse(localStorage.getItem('candybarCart'));
            updateCartDisplay();
        } catch (error) {
            console.error('Error al cargar el carrito:', error);
            localStorage.removeItem('candybarCart');
            cart = [];
        }
    }
    
    // Añadir evento a todos los botones de agregar al carrito
    const addButtons = document.querySelectorAll('.add-to-cart-btn');
    addButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const type = this.getAttribute('data-type');
            const name = this.getAttribute('data-name');
            const price = parseFloat(this.getAttribute('data-price'));
            
            // Verificar si el producto ya está en el carrito
            const existingItem = cart.findIndex(item => item.id === id && item.type === type);
            
            if (existingItem !== -1) {
                // Incrementar cantidad
                cart[existingItem].quantity++;
            } else {
                // Añadir nuevo item
                cart.push({
                    id: id,
                    type: type,
                    name: name,
                    price: price,
                    quantity: 1
                });
            }
            
            // Guardar carrito en localStorage
            localStorage.setItem('candybarCart', JSON.stringify(cart));
            
            // Actualizar la vista del carrito
            updateCartDisplay();
            
            // Mostrar notificación
            showNotification(`${name} añadido al carrito`);
        });
    });
    
    // Función para actualizar la visualización del carrito
    function updateCartDisplay() {
        const cartCount = document.getElementById('cartCount');
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('cartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');
        
        // Contar total de productos
        const totalItems = cart.reduce((total, item) => total + item.quantity, 0);
        cartCount.textContent = totalItems;
        
        // Actualizar lista de productos
        if (totalItems === 0) {
            cartItems.innerHTML = '<p class="empty-cart-message">Tu carrito está vacío</p>';
            checkoutBtn.disabled = true;
        } else {
            let html = '';
            let total = 0;
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                total += itemTotal;
                
                html += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">Bs. ${item.price.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn minus" data-index="${index}">-</button>
                        <span>${item.quantity}</span>
                        <button class="quantity-btn plus" data-index="${index}">+</button>
                    </div>
                    <button class="remove-item-btn" data-index="${index}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                `;
            });
            
            cartItems.innerHTML = html;
            cartTotal.textContent = `Bs. ${total.toFixed(2)}`;
            checkoutBtn.disabled = false;
            
            // Añadir eventos a los botones de cantidad
            const minusButtons = cartItems.querySelectorAll('.quantity-btn.minus');
            const plusButtons = cartItems.querySelectorAll('.quantity-btn.plus');
            const removeButtons = cartItems.querySelectorAll('.remove-item-btn');
            
            minusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    if (cart[index].quantity > 1) {
                        cart[index].quantity--;
                    } else {
                        cart.splice(index, 1);
                    }
                    localStorage.setItem('candybarCart', JSON.stringify(cart));
                    updateCartDisplay();
                });
            });
            
            plusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    cart[index].quantity++;
                    localStorage.setItem('candybarCart', JSON.stringify(cart));
                    updateCartDisplay();
                });
            });
            
            removeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    cart.splice(index, 1);
                    localStorage.setItem('candybarCart', JSON.stringify(cart));
                    updateCartDisplay();
                });
            });
        }
    }
    
    // Función para mostrar notificación
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Mostrar notificación
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);
        
        // Ocultar y eliminar después de 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
    
    // Manejar el checkout
    const checkoutBtn = document.getElementById('checkoutBtn');
    const cartForm = document.getElementById('cartForm');
    const productosSeleccionados = document.getElementById('productosSeleccionados');
    
    checkoutBtn.addEventListener('click', function() {
        if (cart.length > 0) {
            productosSeleccionados.value = JSON.stringify(cart);
            cartForm.submit();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>