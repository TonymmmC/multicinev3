<div class="search-header">
    <h1 class="search-title">Descubre películas</h1>
    <p class="search-subtitle">Encuentra tus películas favoritas en nuestro catálogo</p>
</div>

<div class="search-form-container">
    <form action="" method="get" class="search-form">
        <div class="search-input-group">
            <input type="text" class="search-input" name="q" placeholder="Buscar por título, género o actor..." 
                   value="<?php echo htmlspecialchars($termino); ?>" required>
            <button class="search-button" type="submit">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>
</div>