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

    <!-- Modal global para torrents/IMDb -->
    <div id="torrentModal" class="global-modal">
        <div class="global-modal-content">
            <div class="global-modal-header">
                <h2 id="torrentModalTitle">Buscar torrents</h2>
                <button id="torrentModalClose" class="global-modal-close">&times;</button>
            </div>
            <div class="global-modal-body">
                <div id="torrentIMDbContainer" class="torrent-imdb-container"></div>
                <div id="torrentSearchStatus" class="torrent-status">
                    <div class="torrent-loading">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Buscando enlaces torrent...</p>
                    </div>
                </div>
                <div id="torrentResultsContainer" class="torrent-results" style="display: none;">
                    <div id="torrentResultsList"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .global-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: 1rem;
        }
        .global-modal.active {
            display: flex;
        }
        .global-modal-content {
            background: #141414;
            border-radius: 12px;
            width: min(100%, 1000px);
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }
        .global-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .global-modal-header h2 {
            margin: 0;
            color: #fff;
        }
        .global-modal-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            line-height: 1;
        }
        .global-modal-body {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .torrent-imdb-container {
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 1rem;
            color: #fff;
        }
        .imdb-modal-info {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .imdb-poster img {
            width: 180px;
            border-radius: 8px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0,0,0,0.4);
        }
        .imdb-poster-placeholder {
            width: 180px;
            height: 270px;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: rgba(255,255,255,0.3);
        }
        .imdb-details {
            flex: 1;
            min-width: 240px;
        }
        .imdb-details h3 {
            margin-top: 0;
            color: #fff;
        }
        .imdb-details p {
            margin: 0.25rem 0;
            color: #ccc;
        }
        .imdb-plot {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .imdb-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.75rem;
            color: #f5c518;
            text-decoration: none;
        }
        .torrent-status, .torrent-results {
            background: rgba(255,255,255,0.03);
            border-radius: 10px;
            padding: 1rem;
            color: #fff;
        }
        .torrent-loading {
            text-align: center;
            color: #ccc;
        }
        .torrent-loading i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        .torrent-loading.error i {
            color: #dc3545;
        }
        .torrent-result {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem;
            margin-bottom: 0.75rem;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.08);
            gap: 1rem;
            flex-wrap: wrap;
        }
        .torrent-title {
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #fff;
        }
        .torrent-meta {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #bbb;
        }
        .torrent-quality {
            background: #e50914;
            padding: 0.1rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            color: #fff;
        }
        .play-torrent-btn {
            background: #e50914;
            border: none;
            color: #fff;
            padding: 0.6rem 1.2rem;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .play-torrent-btn:hover {
            background: #f6121d;
        }
        .torrent-empty {
            text-align: center;
            color: #bbb;
        }
        .torrent-empty i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            color: rgba(255,255,255,0.3);
        }
        @media (max-width: 768px) {
            .global-modal-content {
                padding: 1rem;
            }
            .imdb-modal-info {
                flex-direction: column;
                align-items: center;
            }
            .imdb-poster img,
            .imdb-poster-placeholder {
                width: 100%;
                height: auto;
            }
        }
    </style>

    <!-- Utilidades -->
    <script src="<?php echo $baseUrl; ?>/js/utils.js"></script>

    <!-- Script de inicialización del carrusel -->
    <script src="<?php echo $baseUrl; ?>/assets/js/init-carousel.js"></script>
    
    <!-- Bootstrap JS y dependencias -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
    <!-- WebTorrent for P2P streaming (solo si se necesita) -->
    <script src="https://cdn.jsdelivr.net/npm/webtorrent@latest/webtorrent.min.js"></script>
    
    <!-- Reproductor de video unificado (solo si no se carga en la página) -->
    <?php if (basename($_SERVER['PHP_SELF']) !== 'watch.php'): ?>
    <script src="<?php echo $baseUrl; ?>/js/video-player.js"></script>
    <?php endif; ?>
    
    <!-- Scripts personalizados -->
    <script src="<?php echo $baseUrl; ?>/js/performance-optimizer.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/hero-optimizer.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/hero-trailer-player.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/netflix-gallery.js"></script>
    
    <!-- Dynamic Rows Loader -->
    <script src="<?php echo $baseUrl; ?>/assets/js/dynamic-rows.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/netflix-enhancements.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/animations.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/notifications.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/main.js"></script>
    
    <!-- Script de diagnóstico de botones del reproductor (solo en index.php) -->
    <?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
    <script src="<?php echo $baseUrl; ?>/test-player-buttons.js"></script>
    <?php endif; ?>
    
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
        // Suprimir warnings informativos de librerías de terceros
        (function() {
            // Suprimir warnings de asm.js (WebTorrent) - ejecutar antes que cualquier otro script
            if (typeof console !== 'undefined' && console.warn) {
                const originalWarn = console.warn.bind(console);
                console.warn = function(...args) {
                    const message = String(args[0] || '');
                    if (message.includes('Invalid asm.js') || 
                        message.includes('Unexpected token') ||
                        message.includes('asm.js')) {
                        // Suprimir este warning específico
                        return;
                    }
                    originalWarn.apply(console, args);
                };
            }
            
            // Suprimir errores de postMessage (YouTube iframe, WebTorrent)
            const errorHandler = function(event) {
                if (event.message && (
                    event.message.includes('postMessage') ||
                    event.message.includes('target origin') ||
                    event.message.includes('DOMWindow') ||
                    event.message.includes('Failed to execute')
                )) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    return false;
                }
            };
            
            // Añadir listener con capture para interceptar antes que otros
            window.addEventListener('error', errorHandler, true);
            
            // También capturar errores no capturados
            window.addEventListener('unhandledrejection', function(event) {
                if (event.reason && String(event.reason).includes('postMessage')) {
                    event.preventDefault();
                }
            });
        })();
        
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
