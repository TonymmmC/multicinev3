<div class="modal fade showtime-modal" id="showtimeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <h5 id="modalDate" class="modal-date"></h5>
                    <h3 id="modalTime" class="modal-time"></h3>
                    <p class="version-label">Versión Original</p>
                </div>

                <div class="d-flex">
                    <div class="mr-3">
                        <img id="modalPoster" src="" alt="" class="movie-poster img-fluid" style="max-width: 100px;">
                    </div>
                    <div>
                        <h4 id="modalTitle" class="movie-title"></h4>
                        <div class="movie-meta">
                            <span id="modalRuntime" class="movie-runtime"></span>
                            <span id="modalFormat" class="movie-format"></span>
                        </div>
                        <p class="end-time">Finaliza aproximadamente a las <span id="endTime"></span></p>
                    </div>
                </div>

                <div class="cinema-info">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div id="modalCinema" class="cinema-name"></div>
                            <div class="cinema-details">
                                Sala <span id="modalSala"></span>
                                <br>Sede: <span id="modalSede"></span>
                            </div>
                        </div>
                        <div class="price-info">
                            <div class="price-value">Precio: Bs. <span id="modalPrice"></span></div>
                            <div class="seats-info"><span id="modalSeats"></span> asientos</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <!-- CAMBIO AQUÍ: Agregar id al botón -->
                <a href="#" id="bookNowBtn" class="btn btn-primary btn-comprar-ahora">
                    <i class="fas fa-ticket-alt"></i> Comprar ahora
                </a>
            </div>
        </div>
    </div>
</div>
