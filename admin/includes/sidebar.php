<div class="sidebar">
    <div class="p-3">
        <h5>Navegación</h5>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
               href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'peliculas.php' || basename($_SERVER['PHP_SELF']) == 'pelicula-form.php' ? 'active' : ''; ?>" 
               href="peliculas.php">
                <i class="fas fa-film"></i> Películas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'funciones.php' || basename($_SERVER['PHP_SELF']) == 'funcion-form.php' ? 'active' : ''; ?>" 
               href="funciones.php">
                <i class="fas fa-calendar-alt"></i> Funciones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'promociones.php' || basename($_SERVER['PHP_SELF']) == 'promocion-form.php' ? 'active' : ''; ?>" 
               href="promociones.php">
                <i class="fas fa-percentage"></i> Promociones
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reservas.php' ? 'active' : ''; ?>" 
               href="reservas.php">
                <i class="fas fa-ticket-alt"></i> Reservas
            </a>
        </li>
        
        <li class="nav-item mt-3">
            <div class="px-3">
                <h6 class="text-muted">CONFIGURACIÓN</h6>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>" 
               href="usuarios.php">
                <i class="fas fa-users"></i> Usuarios
            </a>
        </li>
        <!-- Añadir esto dentro de la sección "CONFIGURACIÓN" -->
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'logs-acceso.php' ? 'active' : ''; ?>" 
            href="logs-acceso.php">
                <i class="fas fa-user-clock"></i> Logs de Acceso
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'intentos-fallidos.php' ? 'active' : ''; ?>" 
            href="intentos-fallidos.php">
                <i class="fas fa-user-shield"></i> Intentos Fallidos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'salas.php' ? 'active' : ''; ?>" 
               href="salas.php">
                <i class="fas fa-door-open"></i> Salas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'generos.php' ? 'active' : ''; ?>" 
               href="generos.php">
                <i class="fas fa-tags"></i> Géneros
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" 
               href="reportes.php">
                <i class="fas fa-chart-bar"></i> Reportes
            </a>
        </li>
        
        <li class="nav-item mt-5">
            <a class="nav-link" href="/multicinev3/auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </li>
    </ul>
</div>