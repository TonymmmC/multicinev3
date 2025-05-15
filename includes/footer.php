<?php
require_once __DIR__ . '/footer.php';
iniciarSesion();
$conn = require __DIR__ . '/../config/database.php';
?>
<link rel="stylesheet" href="assets/css/footerdark.css">
<footer class="footer-dark">
    <div class="container">
        <div class="row footer-content">
            <div class="col-md-3 mb-4">
                <h5 class="footer-title">Multicine</h5>
                <p class="footer-description">La mejor experiencia cinematográfica de Bolivia</p>
                <div class="footer-contact">
                    <div class="contact-item">
                        <i class="contact-icon fas fa-map-marker-alt"></i>
                        <span>Av. 16 de Julio #1642, La Paz</span>
                    </div>
                    <div class="contact-item">
                        <i class="contact-icon fas fa-phone-alt"></i>
                        <span>+591 2 2334455</span>
                    </div>
                    <div class="contact-item">
                        <i class="contact-icon fas fa-envelope"></i>
                        <a href="mailto:info@multicine.com.bo" class="contact-link">info@multicine.com.bo</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <h5 class="footer-title">Enlaces</h5>
                <ul class="nav-links">
                    <li><a href="cartelera.php" class="nav-link"><span>Cartelera</span></a></li>
                    <li><a href="proximamente.php" class="nav-link"><span>Próximos estrenos</span></a></li>
                    <li><a href="multipass.php" class="nav-link"><span>MultiPass</span></a></li>
                    <li><a href="promociones.php" class="nav-link"><span>Promociones</span></a></li>
                    <li><a href="nosotros.php" class="nav-link"><span>Sobre Nosotros</span></a></li>
                    <li><a href="contacto.php" class="nav-link"><span>Contacto</span></a></li>
                </ul>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="row">
                    <div class="col-md-12">
                        <h5 class="footer-title">Síguenos</h5>
                        <div class="social-links">
                            <a href="https://instagram.com/multicine" target="_blank" class="social-link">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="https://facebook.com/multicine" target="_blank" class="social-link">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://tiktok.com/multicine" target="_blank" class="social-link">
                                <i class="fab fa-tiktok"></i>
                            </a>
                            <a href="https://youtube.com/multicine" target="_blank" class="social-link">
                                <i class="fab fa-youtube"></i>
                            </a>
                            <a href="https://twitter.com/multicine" target="_blank" class="social-link">
                                <i class="fab fa-twitter"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h5 class="footer-title">Suscríbete al boletín</h5>
                        <form action="suscribir.php" method="post" class="subscribe-form">
                            <div class="input-group">
                                <input type="email" class="form-control subscribe-input" placeholder="Tu email" name="email" required>
                                <div class="input-group-append">
                                    <button class="btn subscribe-btn" type="submit">Suscribir</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-divider"></div>
        
        <div class="row footer-bottom">
            <div class="col-md-6">
                <p class="copyright">
                    &copy; <?php echo date('Y'); ?> Multicine. Todos los derechos reservados.
                </p>
            </div>
            <div class="col-md-6">
                <ul class="legal-links">
                    <li><a href="terminos.php" class="legal-link">Términos y Condiciones</a></li>
                    <li><a href="privacidad.php" class="legal-link">Política de Privacidad</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- Font Awesome for icons -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>

<?php 
    // Mensajes del sistema
    $mensaje = getMensaje();
    if ($mensaje && basename($_SERVER['PHP_SELF']) != 'index.php'): // No mostrar en página principal 
    ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show">
            <?php echo $mensaje['texto']; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
    <?php endif; ?>