    </div> <!-- Cierre de .dashboard-content-wrapper -->
    
    <!-- Pie de página del dashboard -->
    <footer class="dashboard-footer">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. Todos los derechos reservados.</p>
                    <p class="text-muted small mt-2">
                        <a href="/terminos" class="text-muted mx-2">Términos de servicio</a>
                        <span class="text-muted">|</span>
                        <a href="/privacidad" class="text-muted mx-2">Política de privacidad</a>
                        <span class="text-muted">|</span>
                        <a href="/ayuda" class="text-muted mx-2">Ayuda</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts de JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Scripts personalizados -->
    <script src="/js/dashboard.js"></script>
    
    <script>
    // Inicializar tooltips
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Inicializar popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Toggle sidebar en móviles
        const toggleSidebar = document.querySelector('.toggle-sidebar');
        const dashboardSidebar = document.querySelector('.dashboard-sidebar');
        
        if (toggleSidebar && dashboardSidebar) {
            toggleSidebar.addEventListener('click', function() {
                dashboardSidebar.classList.toggle('show');
            });
        }
        
        // Cerrar sidebar al hacer clic fuera de él en móviles
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 992) {
                const isClickInsideSidebar = dashboardSidebar.contains(event.target);
                const isClickOnToggle = toggleSidebar.contains(event.target);
                
                if (!isClickInsideSidebar && !isClickOnToggle && dashboardSidebar.classList.contains('show')) {
                    dashboardSidebar.classList.remove('show');
                }
            }
        });
        
        // Manejar la carga de imágenes con error
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                this.src = '/assets/images/placeholder.jpg';
                this.alt = 'Imagen no disponible';
                this.classList.add('img-error');
            });
        });
        
        // Mostrar mensajes de sesión
        <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '<?php echo addslashes($_SESSION['success']); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($_SESSION['error']); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 8000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
