                <!-- Footer -->
                <footer class="mt-5 py-3 text-center text-muted">
                    <p class="mb-0">© <?php echo date('Y'); ?> Streaming Platform. Todos los derechos reservados.</p>
                </footer>
            </div> <!-- End of .admin-content -->
        </div> <!-- End of .row -->
    </div> <!-- End of .container-fluid -->

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Inicializar tooltips
        $(function () {
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
        
        // Mostrar notificaciones
        <?php if (isset($_SESSION['flash_message'])): ?>
        $(document).ready(function() {
            const flashMsg = <?php echo json_encode($_SESSION['flash_message']); ?>;
            if (flashMsg) {
                Swal.fire({
                    icon: flashMsg.type || 'info',
                    title: flashMsg.title || 'Mensaje',
                    text: flashMsg.message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            }
        });
        <?php 
        // Limpiar el mensaje después de mostrarlo
        unset($_SESSION['flash_message']);
        endif; 
        ?>
    </script>
</body>
</html>
