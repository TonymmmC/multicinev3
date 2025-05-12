document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const seatMap = document.querySelector('.res-seat-map');
    const continueBtn = document.getElementById('continueBtn');
    const reservedSeatsCount = document.getElementById('reservedSeatsCount');
    const selectedSeatsDetails = document.getElementById('selectedSeatsDetails');
    const asientosSeleccionados = document.getElementById('asientosSeleccionados');
    
    // Selected seats array
    let selectedSeats = [];
    
    // Maximum number of seats that can be selected
    const MAX_SEATS = 10;
    
    // Seat selection
    if (seatMap) {
        seatMap.addEventListener('click', function(e) {
            const seat = e.target.closest('.res-seat');
            if(!seat || seat.classList.contains('res-occupied')) return;
            
            if(seat.classList.contains('res-free')) {
                // Toggle selection
                if(seat.classList.contains('res-selected')) {
                    seat.classList.remove('res-selected');
                    selectedSeats = selectedSeats.filter(s => s.id !== seat.dataset.id);
                } else {
                    // Check if max seats limit is reached
                    if(selectedSeats.length >= MAX_SEATS) {
                        alert(`Lo sentimos, solo puedes seleccionar un máximo de ${MAX_SEATS} asientos por reserva.`);
                        return;
                    }
                    
                    seat.classList.add('res-selected');
                    selectedSeats.push({
                        id: seat.dataset.id,
                        row: seat.dataset.row,
                        number: seat.dataset.number
                    });
                }
                
                updateSelectedSeatsInfo();
            }
        });
    }
    
    // Update selected seats UI
    function updateSelectedSeatsInfo() {
        if (!reservedSeatsCount) return;
        
        reservedSeatsCount.textContent = selectedSeats.length;
        
        if(selectedSeats.length > 0) {
            // Sort seats by row and number
            selectedSeats.sort((a, b) => {
                if(a.row === b.row) {
                    return parseInt(a.number) - parseInt(b.number);
                }
                return a.row.localeCompare(b.row);
            });
            
            // Format selected seats for display
            let seatsText = selectedSeats.map(seat => `${seat.row}${seat.number}`).join(', ');
            selectedSeatsDetails.textContent = seatsText;
            
            // Update form input and enable continue button
            asientosSeleccionados.value = selectedSeats.map(seat => seat.id).join(',');
            continueBtn.disabled = false;
            
            // Show remaining seats message if approaching limit
            if(selectedSeats.length >= MAX_SEATS - 2 && selectedSeats.length < MAX_SEATS) {
                const remainingSeats = MAX_SEATS - selectedSeats.length;
                const remainingText = document.createElement('div');
                remainingText.classList.add('res-remaining-seats');
                remainingText.textContent = `Puedes seleccionar ${remainingSeats} asiento${remainingSeats > 1 ? 's' : ''} más.`;
                
                // Replace existing message if present
                const existingMsg = document.querySelector('.res-remaining-seats');
                if(existingMsg) {
                    existingMsg.replaceWith(remainingText);
                } else {
                    selectedSeatsDetails.parentNode.appendChild(remainingText);
                }
            } else if(selectedSeats.length === MAX_SEATS) {
                // Show max limit reached message
                const limitText = document.createElement('div');
                limitText.classList.add('res-remaining-seats');
                limitText.textContent = `Has alcanzado el límite máximo de ${MAX_SEATS} asientos.`;
                
                // Replace existing message if present
                const existingMsg = document.querySelector('.res-remaining-seats');
                if(existingMsg) {
                    existingMsg.replaceWith(limitText);
                } else {
                    selectedSeatsDetails.parentNode.appendChild(limitText);
                }
            } else {
                // Remove any existing message
                const existingMsg = document.querySelector('.res-remaining-seats');
                if(existingMsg) {
                    existingMsg.remove();
                }
            }
        } else {
            selectedSeatsDetails.textContent = '';
            asientosSeleccionados.value = '';
            continueBtn.disabled = true;
            
            // Remove any remaining seats message
            const existingMsg = document.querySelector('.res-remaining-seats');
            if(existingMsg) {
                existingMsg.remove();
            }
        }
    }
    
    // Continue button click
    if (continueBtn) {
        continueBtn.addEventListener('click', function() {
            if(selectedSeats.length > 0) {
                document.getElementById('reservationForm').submit();
            }
        });
    }

    // Real-time seat updating - check for updates every 10 seconds
    function checkForSeatUpdates() {
        // Get function ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const funcionId = urlParams.get('funcion');
        
        if (!funcionId) return;
        
        fetch(`api/check_seats.php?funcion_id=${funcionId}`)
        .then(response => response.json())
        .then(data => {
            if(data.updated) {
                // Update occupied seats
                const occupiedSeats = data.occupied_seats;
                const seatElements = document.querySelectorAll('.res-seat');
                
                seatElements.forEach(seat => {
                    const seatId = seat.dataset.id;
                    
                    // If seat is now occupied but wasn't before
                    if(occupiedSeats.includes(parseInt(seatId)) && !seat.classList.contains('res-occupied')) {
                        seat.classList.remove('res-free', 'res-selected');
                        seat.classList.add('res-occupied');
                        
                        // If this seat was selected, remove it from selection
                        if(selectedSeats.some(s => s.id === seatId)) {
                            selectedSeats = selectedSeats.filter(s => s.id !== seatId);
                            updateSelectedSeatsInfo();
                        }
                    }
                });
            }
        })
        .catch(error => console.error('Error checking seat updates:', error));
    }
    
    // Initial check and then every 10 seconds
    if (seatMap) {
        checkForSeatUpdates();
        setInterval(checkForSeatUpdates, 10000);
    }
});