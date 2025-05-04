// Cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips de Bootstrap
    $('[data-toggle="tooltip"]').tooltip();
    
    // Cerrar alertas automáticamente después de 5 segundos
    setTimeout(function() {
        $('.alert.alert-dismissible').alert('close');
    }, 5000);
    
    // Validación de formularios
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Validación de contraseñas coincidentes
    const password = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (password && confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
        
        password.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
    
    // Activar los tabs guardados en el localStorage
    const activeTab = localStorage.getItem('activeProfileTab');
    if (activeTab) {
        const tab = document.querySelector(`a[href="${activeTab}"]`);
        if (tab) {
            $(tab).tab('show');
        }
    }
    
    // Guardar el tab activo en localStorage
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeProfileTab', $(e.target).attr('href'));
    });
    
    // Manejo de selección de asientos
    const asientos = document.querySelectorAll('.asiento');
    const asientosSeleccionados = document.getElementById('asientos_seleccionados');
    const totalPrecio = document.getElementById('total_precio');
    const precioPorAsiento = document.getElementById('precio_base');
    
    if (asientos.length > 0 && asientosSeleccionados && totalPrecio && precioPorAsiento) {
        const precioBase = parseFloat(precioPorAsiento.value);
        let seleccionados = [];
        
        asientos.forEach(asiento => {
            asiento.addEventListener('click', function() {
                if (this.classList.contains('ocupado')) {
                    return;
                }
                
                const id = this.getAttribute('data-id');
                const fila = this.getAttribute('data-fila');
                const numero = this.getAttribute('data-numero');
                const asientoInfo = `${fila}${numero} (ID: ${id})`;
                
                if (this.classList.contains('seleccionado')) {
                    // Deseleccionar
                    this.classList.remove('seleccionado');
                    seleccionados = seleccionados.filter(a => a.id !== id);
                } else {
                    // Seleccionar
                    this.classList.add('seleccionado');
                    seleccionados.push({
                        id: id,
                        info: asientoInfo
                    });
                }
                
                // Actualizar campo oculto y total
                actualizarSeleccionAsientos();
            });
        });
        
        function actualizarSeleccionAsientos() {
            // Actualizar campo oculto
            asientosSeleccionados.value = seleccionados.map(a => a.id).join(',');
            
            // Actualizar lista visible
            const listaAsientos = document.getElementById('lista_asientos');
            if (listaAsientos) {
                listaAsientos.innerHTML = '';
                
                seleccionados.forEach(asiento => {
                    const li = document.createElement('li');
                    li.textContent = asiento.info;
                    listaAsientos.appendChild(li);
                });
            }
            
            // Actualizar total
            const total = seleccionados.length * precioBase;
            totalPrecio.textContent = `Bs. ${total.toFixed(2)}`;
            
            // Habilitar o deshabilitar botón de continuar
            const btnContinuar = document.getElementById('btn_continuar');
            if (btnContinuar) {
                btnContinuar.disabled = seleccionados.length === 0;
            }
        }
    }
    
    // Búsqueda en tiempo real
    const searchInput = document.getElementById('search_input');
    const searchResults = document.getElementById('search_results');
    
    if (searchInput && searchResults) {
        let timeoutId;
        
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Limpiar el timeout anterior
            clearTimeout(timeoutId);
            
            if (query.length < 3) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }
            
            // Esperar 500ms después de que el usuario deje de escribir
            timeoutId = setTimeout(function() {
                // Hacer la búsqueda con AJAX
                fetch(`busqueda_ajax.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        searchResults.innerHTML = '';
                        
                        if (data.length === 0) {
                            searchResults.innerHTML = '<p class="text-center p-3">No se encontraron resultados</p>';
                        } else {
                            data.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'search-item p-2 border-bottom';
                                div.innerHTML = `
                                    <a href="pelicula.php?id=${item.id}" class="d-flex align-items-center">
                                        <img src="${item.poster_url || 'assets/img/poster-default.jpg'}" 
                                             class="mr-3" alt="${item.titulo}" width="50">
                                        <div>
                                            <strong>${item.titulo}</strong>
                                            <span class="d-block small">${item.duracion_min} min</span>
                                        </div>
                                    </a>
                                `;
                                searchResults.appendChild(div);
                            });
                        }
                        
                        searchResults.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error en la búsqueda:', error);
                    });
            }, 500);
        });
        
        // Ocultar resultados cuando se hace clic fuera
        document.addEventListener('click', function(event) {
            if (!searchResults.contains(event.target) && event.target !== searchInput) {
                searchResults.style.display = 'none';
            }
        });
    }
    
    // Inicializar Lightbox para galerías
    if (typeof lightbox !== 'undefined') {
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': "Imagen %1 de %2"
        });
    }
});