    </div> <!-- Cierre de .dashboard-content-wrapper -->
    
    <!-- Pie de página del dashboard -->
    <footer class="dashboard-footer">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>. Todos los derechos reservados.</p>
                    <p class="text-muted small mt-2">
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/terminos" class="text-muted mx-2">Términos de servicio</a>
                        <span class="text-muted">|</span>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/privacidad" class="text-muted mx-2">Política de privacidad</a>
                        <span class="text-muted">|</span>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/ayuda" class="text-muted mx-2">Ayuda</a>
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
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/dashboard.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['success'])): ?>
        if (window.dashboardToast) {
            window.dashboardToast({
                type: 'success',
                title: '¡Éxito!',
                text: '<?php echo addslashes($_SESSION['success']); ?>'
            });
        }
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        if (window.dashboardToast) {
            window.dashboardToast({
                type: 'error',
                title: 'Error',
                text: '<?php echo addslashes($_SESSION['error']); ?>',
                duration: 8000
            });
        }
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
