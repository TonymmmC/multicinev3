<div class="hero-banner">
    <img src="<?php echo $peliculaDestacada['banner_url'] ?? 'assets/img/banner-default.jpg'; ?>" alt="<?php echo $peliculaDestacada['titulo']; ?>">
    <div class="content-wrapper">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <div class="title-wrapper">
                        <img src="assets/img/thunderbolts-title.jpg" alt="Thunderbolts" class="movie-title-img">
                    </div>
                    
                    <div class="hero-buttons">
                        <a href="reserva.php?pelicula=<?php echo $peliculaDestacada['id']; ?>" class="btn btn-warning btn-lg">
                            <i class="fas fa-ticket-alt"></i> Comprar ahora
                        </a>
                        <button class="btn btn-outline-light btn-lg ml-2" onclick="agregarFavorito(<?php echo $peliculaDestacada['id']; ?>)">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="format-icons mt-3 d-flex justify-content-between" style="max-width: 200px;">
                        <i class="fas fa-film"></i>
                        <i class="fas fa-volume-up"></i>
                        <i class="fas fa-closed-captioning"></i>
                        <i class="fas fa-glasses"></i>
                        <i class="fas fa-wheelchair"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>