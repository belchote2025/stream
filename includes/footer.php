<?php $baseUrl = rtrim(SITE_URL, '/'); ?>
        </div>
    </main>

    <!-- Pie de página estilo Netflix -->
    <footer style="background-color: #141414; color: #9ea1a6; padding: 4rem 0 2rem; margin-top: 3rem;">
        <style>
            .footer-link {
                color: #c9ccd3 !important;
                transition: color 0.2s ease;
            }
            .footer-link:hover {
                color: #ffffff !important;
                text-decoration: none;
            }
            .footer-heading {
                color: #f0f1f5;
                font-weight: 600;
            }
        </style>
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="footer-heading">Navegación</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo $baseUrl; ?>/" class="footer-link text-decoration-none">Inicio</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/movies.php" class="footer-link text-decoration-none">Películas</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/series.php" class="footer-link text-decoration-none">Series</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/categories.php" class="footer-link text-decoration-none">Categorías</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="footer-heading">Mi cuenta</h5>
                    <ul class="list-unstyled">
<?php if (isLoggedIn()): ?>
                        <li><a href="<?php echo $baseUrl; ?>/profile.php" class="footer-link text-decoration-none">Mi perfil</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/my-list.php" class="footer-link text-decoration-none">Mi lista</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/settings.php" class="footer-link text-decoration-none">Configuración</a></li>
<?php else: ?>
                        <li><a href="<?php echo $baseUrl; ?>/login.php" class="footer-link text-decoration-none">Iniciar sesión</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/register.php" class="footer-link text-decoration-none">Registrarse</a></li>
<?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="footer-heading">Legal</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo $baseUrl; ?>/terms.php" class="footer-link text-decoration-none">Términos de uso</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/privacy.php" class="footer-link text-decoration-none">Política de privacidad</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/cookies.php" class="footer-link text-decoration-none">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="footer-heading">Contáctanos</h5>
                    <ul class="list-unstyled">
                        <li><a href="mailto:soporte@streamingplatform.com" class="footer-link text-decoration-none">Soporte</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/contact.php" class="footer-link text-decoration-none">Contacto</a></li>
                        <li><a href="<?php echo $baseUrl; ?>/faq.php" class="footer-link text-decoration-none">Preguntas frecuentes</a></li>
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
    <script src="<?php echo $baseUrl; ?>/js/video-player.js"></script>
    
    <!-- Scripts personalizados -->
    <script src="<?php echo $baseUrl; ?>/js/performance-optimizer.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/hero-optimizer.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/hero-trailer-player.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/netflix-gallery.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/netflix-enhancements.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/animations.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/notifications.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/main.js"></script>
    
    <!-- Script de prueba de consola (comentado - puede activarse si se necesita) -->
    <!-- <script src="/js/console-test.js"></script> -->
    
    <?php if (defined('APP_ENV') && APP_ENV === 'local'): ?>
    <!-- Debug temporal para detectar respuestas HTML en fetch (solo entorno local) -->
    <script>
        (function() {
            const originalFetch = window.fetch;
            window.fetch = async function(resource, config = {}) {
                const start = performance.now();
                try {
                    const response = await originalFetch(resource, config);
                    const duration = (performance.now() - start).toFixed(0);
                    const contentType = response.headers.get('content-type') || '';
                    
                    if (!response.ok || !contentType.includes('application/json')) {
                        let preview = '';
                        try {
                            preview = (await response.clone().text()).slice(0, 200);
                        } catch (e) {
                            preview = '[Respuesta no legible]';
                        }
                        console.warn('[FETCH DEBUG]', response.status, response.url, `(${duration} ms)`);
                        console.warn('Tipo:', contentType || 'N/A');
                        console.warn('Preview:', preview);
                    }
                    
                    return response;
                } catch (error) {
                    console.error('[FETCH DEBUG] Error en', resource, error);
                    throw error;
                }
            };
        })();
    </script>
    <?php endif; ?>
    
    <script>
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
