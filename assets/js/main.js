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
});