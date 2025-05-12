document.addEventListener('DOMContentLoaded', function() {
    // Manejo de fechas para funciones
    const dateTabs = document.querySelectorAll('.mc-date-tab');
    const cinemaCards = document.querySelectorAll('.mc-cinema-card');
    
    // Cargar funciones iniciales para hoy
    if (dateTabs.length > 0) {
        loadShowtimes(dateTabs[0].dataset.date);
    }
    
    dateTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const selectedDate = this.dataset.date;
            
            // Actualizar tab activo
            dateTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Cargar funciones para la fecha seleccionada
            loadShowtimes(selectedDate);
        });
    });
    
    // Función para cargar horarios
    function loadShowtimes(date) {
        cinemaCards.forEach(cineCard => {
            const cineId = cineCard.dataset.cineId;
            const showtimeContainer = cineCard.querySelector('.mc-showtime-container');
            
            // Mostrar cargando
            showtimeContainer.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>';
            
            // Hacer petición AJAX para obtener horarios
            fetch(`api/horarios.php?pelicula_id=${peliculaId}&cine_id=${cineId}&fecha=${date}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    data.forEach(horario => {
                        const hora = new Date(horario.fecha_hora).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                        const tooltipInfo = `${horario.formato} | ${horario.idioma} | ${horario.sala_nombre} | Bs. ${parseFloat(horario.precio_base).toFixed(2)}`;
                        
                        html += `<a href="#" 
                                class="mc-showtime-btn" 
                                data-toggle="modal" 
                                data-target="#showtimeModal"
                                data-id="${horario.id}"
                                data-time="${hora}"
                                data-date="${date}"
                                data-format="${horario.formato}"
                                data-sala="${horario.sala_nombre}"
                                data-title="${cineCard.querySelector('.mc-cinema-name').innerText.split('\\n')[0]}"
                                data-runtime="${horario.duracion || document.querySelector('.mc-movie-info').dataset.runtime}"
                                data-price="${parseFloat(horario.precio_base).toFixed(2)}"
                                data-seats="${horario.asientos_disponibles || 100}"
                                title="${tooltipInfo}">
                                    ${hora}
                                </a>`;
                    });
                    showtimeContainer.innerHTML = html;
                    
                    // Inicializar tooltips
                    $('[data-toggle="tooltip"]').tooltip();
                } else {
                    showtimeContainer.innerHTML = '<p class="text-muted">No hay funciones disponibles para esta fecha.</p>';
                }
            })
            .catch(error => {
                showtimeContainer.innerHTML = '<p class="text-muted">No hay funciones disponibles para esta fecha.</p>';
                console.error('Error loading showtimes:', error);
            });
        });
    }
    
    // Reproducción de trailer
    const trailerBtn = document.getElementById('trailerBtn');
    const youtubeEmbed = document.getElementById('youtubeEmbed');
    const playTrailerBtn = document.getElementById('playTrailerBtn');
    const playLocalBtn = document.getElementById('playLocalBtn');
    const trailerVideo = document.getElementById('trailerVideo');
    
    if (trailerBtn && youtubeEmbed) {
        trailerBtn.addEventListener('click', function() {
            const videoId = youtubeEmbed.dataset.videoId;
            
            // Reemplazar la imagen con el iframe
            youtubeEmbed.innerHTML = `
                <iframe width="100%" height="100%" 
                    src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            `;
            
            // Hacer scroll hasta el trailer
            youtubeEmbed.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    } else if (trailerBtn && trailerVideo) {
        trailerBtn.addEventListener('click', function() {
            trailerVideo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            trailerVideo.play();
            // Ocultar el botón de reproducción
            if (playTrailerBtn) playTrailerBtn.style.display = 'none';
        });
    }
    
    if (playLocalBtn && youtubeEmbed) {
        playLocalBtn.addEventListener('click', function() {
            const videoId = youtubeEmbed.dataset.videoId;
            
            // Reemplazar la imagen con el iframe
            youtubeEmbed.innerHTML = `
                <iframe width="100%" height="100%" 
                    src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            `;
        });
    }
    
    // Manejo de estrellas para valoración
    const ratingStars = document.querySelectorAll('.mc-rating-input label');
    const ratingInputs = document.querySelectorAll('.mc-rating-input input');
    
    function updateStars(rating) {
        ratingStars.forEach((star, index) => {
            const starIcon = star.querySelector('i');
            if (index < rating) {
                starIcon.classList.remove('far');
                starIcon.classList.add('fas');
            } else {
                starIcon.classList.remove('fas');
                starIcon.classList.add('far');
            }
        });
    }
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('mouseenter', () => {
            updateStars(index + 1);
        });
    });
    
    document.querySelector('.mc-rating-input')?.addEventListener('mouseleave', () => {
        const checkedInput = document.querySelector('.mc-rating-input input:checked');
        const rating = checkedInput ? parseInt(checkedInput.value) : 0;
        updateStars(rating);
    });
    
    ratingInputs.forEach((input, index) => {
        input.addEventListener('change', () => {
            updateStars(index + 1);
        });
    });
    
    // Inicializar estrellas si hay valoración previa
    const checkedInput = document.querySelector('.mc-rating-input input:checked');
    if (checkedInput) {
        const initialRating = parseInt(checkedInput.value);
        updateStars(initialRating);
    }
    
    // Inicializar tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Configuración del modal de showtime
    $('#showtimeModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const time = button.data('time');
        const date = new Date(button.data('date'));
        const format = button.data('format');
        const sala = button.data('sala');
        const cinema = button.data('title');
        const sede = button.closest('.mc-cinema-card').find('.mc-cinema-badge').text();
        const title = $('.mc-movie-title').text();
        const runtime = $('.mc-movie-meta .fa-clock').parent().text().match(/\d+/)[0];
        const price = button.data('price');
        const seats = button.data('seats');
        
        // Format date as "Sunday 11 May"
        const options = { weekday: 'long', day: 'numeric', month: 'long' };
        const formattedDate = date.toLocaleDateString('es-ES', options);
        
        // Calculate end time (time + runtime minutes)
        const [hours, minutes] = time.split(':').map(num => parseInt(num, 10));
        const endDate = new Date(date);
        endDate.setHours(hours, minutes + parseInt(runtime, 10));
        const endTime = endDate.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        
        // Update modal contents
        $('#modalDate').text(formattedDate);
        $('#modalTime').text(time);
        $('#modalTitle').text(title);
        $('#modalRuntime').text(`Duración: ${runtime} min`);
        $('#modalFormat').text(format);
        $('#endTime').text(endTime);
        $('#modalSala').text(sala || '1');
        $('#modalCinema').text(cinema);
        $('#modalSede').text(sede);
        $('#modalPrice').text(price);
        $('#modalSeats').text(seats);
        
        // Actualizar la imagen del poster
        $('#modalPoster').attr('src', $('.mc-movie-poster').attr('src'));
        $('#modalPoster').attr('alt', title);
        
        // Set the booking URL - IMPORTANTE: Este es el cambio principal
        $('#bookNowBtn').attr('href', `reserva.php?funcion=${id}`);
        
        // Para el debugging
        console.log('URL de reserva actualizada:', `reserva.php?funcion=${id}`);
    });
});

function copiarEnlace() {
    const enlace = document.getElementById('enlaceCompartir');
    enlace.select();
    document.execCommand('copy');
    
    // Mostrar mensaje de éxito
    const boton = enlace.nextElementSibling.querySelector('button');
    const iconoOriginal = boton.innerHTML;
    boton.innerHTML = '<i class="fas fa-check"></i>';
    
    setTimeout(() => {
        boton.innerHTML = iconoOriginal;
    }, 2000);
}