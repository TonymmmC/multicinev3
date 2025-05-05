</div><!-- .container-fluid -->
        
        <footer class="bg-dark text-white text-center py-3 mt-5">
            <p class="mb-0">Multicine Admin Panel &copy; <?php echo date('Y'); ?></p>
            <p class="small mb-0">Desarrollado para Ingeniería de Sistemas - Universidad</p>
        </footer>

        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        
        <!-- DataTables -->
        <script src="https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.10.23/js/dataTables.bootstrap4.min.js"></script>
        
        <script>
            // Inicializar DataTables
            $(document).ready(function() {
                if ($('.datatable').length > 0) {
                    $('.datatable').DataTable({
                        "language": {
                            "url": "//cdn.datatables.net/plug-ins/1.10.23/i18n/Spanish.json"
                        },
                        "pageLength": 25
                    });
                }
                
                // Habilitar tooltips
                $('[data-toggle="tooltip"]').tooltip();
            });
        </script>
    </body>
</html>