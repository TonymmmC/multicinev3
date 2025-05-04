/**
 * Inicializa efectos visuales para las tarjetas de películas
 */
function initializeCardEffects() {
    const filmCards = document.querySelectorAll('.film-card');
    
    if (filmCards) {
        filmCards.forEach(card => {
            // Efecto de elevación al pasar el ratón
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
                this.style.boxShadow = '0 15px 30px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
            });
            
            // Animación para badges de estreno
            const badge = card.querySelector('.badge-estreno');
            if (badge) {
                setTimeout(() => {
                    badge.classList.add('pulse');
                }, 500);
            }
        });
    }
    
    // Lazy loading para imágenes
    const lazyImages = document.querySelectorAll('.lazy-load');
    if (lazyImages.length > 0) {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy-load');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            lazyImages.forEach(img => {
                imageObserver.observe(img);
            });
        } else {
            // Fallback para navegadores que no soportan IntersectionObserver
            lazyImages.forEach(img => {
                img.src = img.dataset.src;
                img.classList.remove('lazy-load');
            });
        }
    }
}

/**
 * Inicializa tooltips de Bootstrap
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Inicializa la funcionalidad de paginación
 */
function initializePagination() {
    const paginationLinks = document.querySelectorAll('.page-link');
    
    if (paginationLinks) {
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Si el enlace está deshabilitado, prevenir la navegación
                if (this.parentElement.classList.contains('disabled')) {
                    e.preventDefault();
                    return false;
                }
                
                // Para enlaces de paginación que ya tienen URL, permitir la navegación normal
                if (this.getAttribute('href') !== '#') {
                    return true;
                }
                
                e.preventDefault();
                
                // Obtener la página actual y la página de destino
                const currentPage = parseInt(document.querySelector('.pagination .active .page-link').textContent);
                const targetPage = this.textContent === 'Anterior' 
                    ? currentPage - 1 
                    : this.textContent === 'Siguiente'
                        ? currentPage + 1
                        : parseInt(this.textContent);
                
                // Construir la URL con los filtros actuales
                const url = new URL(window.location.href);
                url.searchParams.set('page', targetPage);
                
                // Navegar a la nueva URL
                window.location.href = url.toString();
            });
        });
    }
}

/**
 * Función para actualizar la visualización basada en el modo de vista
 * (cuadrícula o lista)
 */
function updateViewMode(mode) {
    const filmGrid = document.getElementById('film-grid');
    const viewButtons = document.querySelectorAll('.view-mode-btn');
    
    if (filmGrid && viewButtons) {
        // Actualizar clases del contenedor
        if (mode === 'grid') {
            filmGrid.classList.remove('list-view');
            filmGrid.classList.add('grid-view');
            
            // Actualizar la estructura de las tarjetas para vista de cuadrícula
            const cards = filmGrid.querySelectorAll('.film-card');
            cards.forEach(card => {
                card.classList.remove('list-card');
                card.classList.add('grid-card');
            });
        } else {
            filmGrid.classList.remove('grid-view');
            filmGrid.classList.add('list-view');
            
            // Actualizar la estructura de las tarjetas para vista de lista
            const cards = filmGrid.querySelectorAll('.film-card');
            cards.forEach(card => {
                card.classList.remove('grid-card');
                card.classList.add('list-card');
            });
        }
        
        // Actualizar estado de los botones
        viewButtons.forEach(btn => {
            btn.classList.remove('active');
            if ((mode === 'grid' && btn.getAttribute('data-view') === 'grid') ||
                (mode === 'list' && btn.getAttribute('data-view') === 'list')) {
                btn.classList.add('active');
            }
        });
        
        // Guardar preferencia en localStorage
        localStorage.setItem('cartelera-view-mode', mode);
    }
}

/**
 * Inicializa los botones para cambiar el modo de visualización
 */
function initializeViewModeButtons() {
    const viewButtons = document.querySelectorAll('.view-mode-btn');
    
    if (viewButtons) {
        // Aplicar modo guardado o predeterminado
        const savedMode = localStorage.getItem('cartelera-view-mode') || 'grid';
        updateViewMode(savedMode);
        
        // Configurar eventos para botones
        viewButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const mode = this.getAttribute('data-view');
                updateViewMode(mode);
            });
        });
    }
}

/**
 * Inicializa la funcionalidad de filtrado rápido por géneros
 */
function initializeGenreQuickFilter() {
    const genreChips = document.querySelectorAll('.genre-chip');
    
    if (genreChips) {
        genreChips.forEach(chip => {
            chip.addEventListener('click', function() {
                const genreId = this.getAttribute('data-genre-id');
                
                // Actualizar el selector de géneros en el formulario
                const genreSelector = document.getElementById('genero');
                if (genreSelector) {
                    genreSelector.value = genreId;
                    
                    // Enviar el formulario
                    const filterForm = document.getElementById('filter-form');
                    if (filterForm) {
                        filterForm.submit();
                    }
                }
            });
        });
    }
}

/**
 * Inicializa la funcionalidad de favoritos
 */
function initializeFavorites() {
    const favButtons = document.querySelectorAll('.fav-button');
    
    if (favButtons) {
        favButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const peliculaId = this.getAttribute('data-pelicula-id');
                const action = this.getAttribute('data-action');
                
                // Verificar si el usuario está logueado
                if (!document.body.classList.contains('user-logged-in')) {
                    window.location.href = 'auth/login.php';
                    return;
                }
                
                // Enviar solicitud AJAX
                fetch('ajax/administrar_favorito.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `pelicula_id=${peliculaId}&accion=${action}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar el estado del botón
                        if (action === 'agregar') {
                            this.classList.remove('btn-outline-danger');
                            this.classList.add('btn-danger');
                            this.innerHTML = '<i class="fas fa-heart"></i>';
                            this.setAttribute('data-action', 'quitar');
                            this.setAttribute('title', 'Quitar de favoritos');
                        } else {
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-outline-danger');
                            this.innerHTML = '<i class="far fa-heart"></i>';
                            this.setAttribute('data-action', 'agregar');
                            this.setAttribute('title', 'Agregar a favoritos');
                        }
                        
                        // Reinicializar tooltip
                        new bootstrap.Tooltip(this);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ha ocurrido un error al procesar la acción');
                });
            });
        });
    }
}

/**
 * JavaScript para la página de cartelera de Multicine
 * 
 * Este archivo maneja la funcionalidad de filtrado y efectos visuales
 * para la visualización de películas en cartelera.
 */

document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad para los filtros de la cartelera
    initializeFilters();
    
    // Efectos visuales para las tarjetas de películas
    initializeCardEffects();
    
    // Inicializar tooltips
    initializeTooltips();
    
    // Gestionar paginación
    initializePagination();
});

/**
 * Inicializa la funcionalidad de los filtros
 */
function initializeFilters() {
    // Elementos de filtro
    const filterForm = document.getElementById('filter-form');
    const filterSelects = document.querySelectorAll('.filter-select');
    const clearFiltersBtn = document.getElementById('clear-filters');
    
    // Enviar formulario al cambiar un filtro
    if (filterSelects) {
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                if (filterForm) {
                    filterForm.submit();
                }
            });
        });
    }
    
    // Limpiar filtros
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Restablecer todos los selectores a su valor predeterminado
            if (filterSelects) {
                filterSelects.forEach(select => {
                    select.value = '';
                });
            }
            
            // Enviar el formulario
            if (filterForm) {
                filterForm.submit();
            }
        });
    }
    
    // Gestionar collapse para filtros en móvil
    const filterToggle = document.getElementById('filter-toggle');
    const filterCollapse = document.getElementById('filter-collapse');
    
    if (filterToggle && filterCollapse) {
        filterToggle.addEventListener('click', function() {
            const isCollapsed = filterCollapse.classList.contains('show');
            
            if (isCollapsed) {
                filterCollapse.classList.remove('show');
                filterToggle.textContent = 'Mostrar filtros';
            } else {
                filterCollapse.classList.add('show');
                filterToggle.textContent = 'Ocultar filtros';
            }
        });
    }
}