<div class="modal fade" id="compartirModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compartir "<?php echo $data['pelicula']['titulo']; ?>"</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Comparte esta película en tus redes sociales:</p>
                
                <?php
                $urlActual = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $textoCompartir = '¡Mira "' . $data['pelicula']['titulo'] . '" en Multicine!';
                ?>
                
                <div class="d-flex justify-content-center">
                    <!-- Facebook -->
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($urlActual); ?>" 
                       target="_blank" 
                       class="btn btn-primary mx-2">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                    
                    <!-- Twitter -->
                    <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($textoCompartir); ?>&url=<?php echo urlencode($urlActual); ?>" 
                       target="_blank" 
                       class="btn btn-info mx-2">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    
                    <!-- WhatsApp -->
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($textoCompartir . ' ' . $urlActual); ?>" 
                       target="_blank" 
                       class="btn btn-success mx-2">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
                
                <hr>
                
                <div class="form-group mt-3">
                    <label for="enlaceCompartir">Copiar enlace:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="enlaceCompartir" value="<?php echo $urlActual; ?>" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="copiarEnlace()">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>