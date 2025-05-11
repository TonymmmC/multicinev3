<section class="carhome-section">
    <div class="container">
        <div class="carhome-section-header">
            <h2 class="carhome-section-title">Noticias</h2>
            <a href="noticias.php" class="carhome-link-ver">Mostrar todas las noticias</a>
        </div>
        
        <?php if (!empty($noticiasDestacadas)): ?>
            <div class="carhome-news-container">
                <?php foreach ($noticiasDestacadas as $noticia): ?>
                    <div class="carhome-news-card">
                        <a href="<?php echo $noticia['link']; ?>" class="carhome-news-link">
                            <img src="<?php echo $noticia['imagen_url']; ?>" class="carhome-news-img" alt="<?php echo $noticia['titulo']; ?>">
                            <div class="carhome-news-body">
                                <h5 class="carhome-news-title"><?php echo $noticia['titulo']; ?></h5>
                                <p class="carhome-news-text"><?php echo $noticia['resumen']; ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="carhome-empty-message">
                No hay noticias disponibles en este momento.
            </div>
        <?php endif; ?>
    </div>
</section>