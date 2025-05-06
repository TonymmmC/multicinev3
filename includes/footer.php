<footer class="footer bg-dark text-white pt-5 pb-3">
    <div class="container">
        <div class="row">
            <div class="col-md-3 mb-4">
                <h5>Multicine</h5>
                <p class="text-muted">La mejor experiencia cinematográfica de Bolivia</p>
                <p class="text-muted">
                    Av. 16 de Julio #1642, La Paz<br>
                    Teléfono: +591 2 2334455<br>
                    Email: <a href="mailto:info@multicine.com.bo">info@multicine.com.bo</a>
                </p>
            </div>
            
            <div class="col-md-3 mb-4">
                <h5>Enlaces</h5>
                <ul class="list-unstyled">
                    <li><a href="cartelera.php">Cartelera</a></li>
                    <li><a href="proximamente.php">Próximos estrenos</a></li>
                    <li><a href="multipass.php">MultiPass</a></li>
                    <li><a href="promociones.php">Promociones</a></li>
                    <li><a href="nosotros.php">Sobre Nosotros</a></li>
                    <li><a href="contacto.php">Contacto</a></li>
                </ul>
            </div>
            
            <div class="col-md-3 mb-4">
                <h5>Síguenos</h5>
                <div class="social-links">
                    <a href="https://facebook.com/multicine" target="_blank" class="mr-2">
                        <i class="fab fa-facebook-f fa-2x"></i>
                    </a>
                    <a href="https://twitter.com/multicine" target="_blank" class="mr-2">
                        <i class="fab fa-twitter fa-2x"></i>
                    </a>
                    <a href="https://instagram.com/multicine" target="_blank" class="mr-2">
                        <i class="fab fa-instagram fa-2x"></i>
                    </a>
                    <a href="https://youtube.com/multicine" target="_blank">
                        <i class="fab fa-youtube fa-2x"></i>
                    </a>
                </div>
                
                <h5 class="mt-4">Suscríbete al boletín</h5>
                <form action="suscribir.php" method="post">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Tu email" name="email" required>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">Suscribir</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!--<div class="col-md-3 mb-4">
                <h5>Descarga nuestra app</h5>
                <div class="app-badges">
                    <a href="#" class="d-block mb-2">
                        <img src="assets/img/google-play.png" alt="Google Play" height="40">
                    </a>
                    <a href="#" class="d-block">
                        <img src="assets/img/app-store.png" alt="App Store" height="40">
                    </a>
                </div>
            </div>-->
        </div>
        
        <hr class="border-secondary">
        
        <div class="row">
            <div class="col-md-6">
                <p class="text-muted mb-0">
                    &copy; <?php echo date('Y'); ?> Multicine. Todos los derechos reservados.
                </p>
                <p class="text-muted small">
                    Desarrollado para Ingeniería de Sistemas - Universidad
                </p>
            </div>
            <div class="col-md-6 text-md-right">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item"><a href="terminos.php">Términos y Condiciones</a></li>
                    <li class="list-inline-item"><a href="privacidad.php">Política de Privacidad</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>