        </div>
    </main>

    <!-- Pie de página estilo Netflix -->
    <footer style="background-color: #141414; color: #757575; padding: 4rem 0 2rem; margin-top: 3rem;">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5>Navegación</h5>
                    <ul class="list-unstyled">
                        <li><a href="/streaming-platform/" class="text-muted text-decoration-none">Inicio</a></li>
                        <li><a href="/streaming-platform/movies.php" class="text-muted text-decoration-none">Películas</a></li>
                        <li><a href="/streaming-platform/series.php" class="text-muted text-decoration-none">Series</a></li>
                        <li><a href="/streaming-platform/categories.php" class="text-muted text-decoration-none">Categorías</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Mi cuenta</h5>
                    <ul class="list-unstyled">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/streaming-platform/profile.php" class="text-muted text-decoration-none">Mi perfil</a></li>
                            <li><a href="/streaming-platform/my-list.php" class="text-muted text-decoration-none">Mi lista</a></li>
                            <li><a href="/streaming-platform/settings.php" class="text-muted text-decoration-none">Configuración</a></li>
                        <?php else: ?>
                            <li><a href="/streaming-platform/login.php" class="text-muted text-decoration-none">Iniciar sesión</a></li>
                            <li><a href="/streaming-platform/register.php" class="text-muted text-decoration-none">Registrarse</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Legal</h5>
                    <ul class="list-unstyled">
                        <li><a href="/streaming-platform/terms.php" class="text-muted text-decoration-none">Términos de uso</a></li>
                        <li><a href="/streaming-platform/privacy.php" class="text-muted text-decoration-none">Política de privacidad</a></li>
                        <li><a href="/streaming-platform/cookies.php" class="text-muted text-decoration-none">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5>Contáctanos</h5>
                    <ul class="list-unstyled">
                        <li><a href="mailto:soporte@streamingplatform.com" class="text-muted text-decoration-none">Soporte</a></li>
                        <li><a href="/streaming-platform/contact.php" class="text-muted text-decoration-none">Contacto</a></li>
                        <li><a href="/streaming-platform/faq.php" class="text-muted text-decoration-none">Preguntas frecuentes</a></li>
                    </ul>
                    
                    <div class="mt-3">
                        <h6>Síguenos</h6>
                        <div class="d-flex gap-3">
                            <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="text-white"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <!-- WebTorrent for P2P streaming (solo si se necesita) -->
    <script src="https://cdn.jsdelivr.net/npm/webtorrent@latest/webtorrent.min.js"></script>
    
    <!-- Reproductor de video unificado -->
    <script src="/streaming-platform/js/video-player.js"></script>
    
    <!-- Scripts personalizados -->
    <script src="/streaming-platform/js/performance-optimizer.js"></script>
    <script src="/streaming-platform/js/hero-optimizer.js"></script>
    <script src="/streaming-platform/js/hero-trailer-player.js"></script>
    <script src="/streaming-platform/assets/js/netflix-gallery.js"></script>
    <script src="/streaming-platform/js/netflix-enhancements.js"></script>
    <script src="/streaming-platform/js/animations.js"></script>
    <script src="/streaming-platform/js/notifications.js"></script>
    <script src="/streaming-platform/js/main.js"></script>
    
    <!-- Script de prueba de consola (comentado - puede activarse si se necesita) -->
    <!-- <script src="/streaming-platform/js/console-test.js"></script> -->
    
    <script>
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
