<div class="modal fade" id="ratingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Valorar "<?php echo $data['pelicula']['titulo']; ?>"</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="guardar_valoracion.php" method="post">
                    <input type="hidden" name="pelicula_id" value="<?php echo $data['pelicula']['id']; ?>">
                    
                    <div class="form-group">
                        <label>Tu puntuación</label>
                        <div class="mc-rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="mc-rating-star">
                                    <input type="radio" 
                                           name="puntuacion" 
                                           id="rating<?php echo $i; ?>" 
                                           value="<?php echo $i; ?>" 
                                           <?php echo ($data['valoracionUsuario'] && $data['valoracionUsuario']['puntuacion'] == $i) ? 'checked' : ''; ?> 
                                           required>
                                    <label for="rating<?php echo $i; ?>">
                                        <i class="far fa-star"></i>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comentario">Tu comentario (opcional)</label>
                        <textarea class="form-control" id="comentario" name="comentario" rows="3"><?php echo $data['valoracionUsuario'] ? $data['valoracionUsuario']['comentario'] : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo $data['valoracionUsuario'] ? 'Actualizar valoración' : 'Enviar valoración'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>