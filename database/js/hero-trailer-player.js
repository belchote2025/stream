/**
 * Reproductor de Trailers para el Hero Carousel
 * Maneja la reproducción automática de trailers en el carrusel
 */

(function() {
    'use strict';

    // Gestor compartido para la API de YouTube
    window.YouTubeAPIManager = window.YouTubeAPIManager || (function() {
        const state = {
            callbacks: [],
            hooked: false,
            ready: false
        };

        function flushCallbacks() {
            if (!window.YT || typeof window.YT.Player !== 'function') {
                return;
            }
            state.ready = true;
            const pending = state.callbacks.splice(0);
            pending.forEach(callback => {
                try {
                    callback();
                } catch (error) {
                    console.error('Error al ejecutar callback de YouTube:', error);
                }
            });
        }

        function hookOnReady() {
            if (state.hooked) return;
            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function() {
                if (typeof previous === 'function') {
                    try {
                        previous();
                    } catch (error) {
                        console.error('Error en callback previo de YouTube:', error);
                    }
                }
                flushCallbacks();
            };
            state.hooked = true;
        }

        return {
            register(callback) {
                if (typeof callback !== 'function') return;
                if (window.YT && typeof window.YT.Player === 'function') {
                    state.ready = true;
                    callback();
                    return;
                }
                state.callbacks.push(callback);
                hookOnReady();
            },
            ensureScriptLoaded() {
                if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
                    const tag = document.createElement('script');
                    tag.src = 'https://www.youtube.com/iframe_api';
                    const firstScriptTag = document.getElementsByTagName('script')[0];
                    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                }
                hookOnReady();
            }
        };
    })();

    let youtubePlayers = new Map();
    let currentActiveIndex = -1;
    let isYouTubeAPIReady = false;

    // Inicializar cuando el DOM esté listo
    function init() {
        const heroSlides = document.querySelectorAll('.hero-slide');
        if (heroSlides.length === 0) return;

        // Encontrar el slide activo inicial
        const activeSlide = document.querySelector('.hero-slide.active');
        if (activeSlide) {
            currentActiveIndex = parseInt(activeSlide.dataset.index) || 0;
        }

        // Inicializar videos HTML5
        initHTML5Videos();

        // Cargar API de YouTube si hay videos de YouTube
        if (document.querySelector('.hero-youtube-player')) {
            loadYouTubeAPI();
        }

        // Observar cambios en el slide activo
        observeActiveSlide();

        // Iniciar reproducción del slide activo
        if (activeSlide) {
            playActiveSlide(activeSlide);
        }
    }

    // Inicializar videos HTML5
    function initHTML5Videos() {
        const videos = document.querySelectorAll('.hero-trailer-video');
        
        videos.forEach((video, index) => {
            video.addEventListener('loadedmetadata', () => {
                // Video cargado, listo para reproducir
            });

            video.addEventListener('error', (e) => {
                console.warn('Error al cargar video:', video.src);
                // Ocultar video en caso de error
                video.style.display = 'none';
            });

            // Preload del primer video
            if (index === currentActiveIndex) {
                video.load();
            }
        });
    }

    // Cargar API de YouTube
    function loadYouTubeAPI() {
        if (window.YT && window.YT.Player) {
            isYouTubeAPIReady = true;
            initYouTubePlayers();
            return;
        }
        
        if (window.YouTubeAPIManager) {
            window.YouTubeAPIManager.register(() => {
                isYouTubeAPIReady = true;
                initYouTubePlayers();
            });
            window.YouTubeAPIManager.ensureScriptLoaded();
        } else {
            // Fallback en caso extremo
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            const firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

            const previous = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function() {
                if (typeof previous === 'function') {
                    previous();
                }
                isYouTubeAPIReady = true;
                initYouTubePlayers();
            };
        }
    }

    function playHTML5Trailer(video) {
        if (!video) return;

        const attemptPlay = () => {
            const playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(error => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    console.warn('Error al reproducir video:', error);
                });
            }
        };

        video.pause();
        video.currentTime = 0;

        if (video.readyState >= 2) {
            attemptPlay();
        } else {
            const onLoaded = () => {
                video.removeEventListener('loadeddata', onLoaded);
                attemptPlay();
            };
            video.addEventListener('loadeddata', onLoaded, { once: true });
            video.load();
        }
    }

    // Inicializar reproductores de YouTube
    function initYouTubePlayers() {
        const youtubeContainers = document.querySelectorAll('.hero-youtube-player');
        
        youtubeContainers.forEach((container) => {
            const videoId = container.dataset.videoId;
            const index = parseInt(container.dataset.index) || 0;
            
            if (!videoId || youtubePlayers.has(index)) return;

            const playerId = `hero-youtube-${index}`;
            container.id = playerId;

            try {
                const player = new YT.Player(playerId, {
                    host: 'https://www.youtube.com',
                    videoId: videoId,
                    playerVars: {
                        'autoplay': 0,
                        'controls': 0,
                        'disablekb': 1,
                        'fs': 0,
                        'iv_load_policy': 3,
                        'modestbranding': 1,
                        'playsinline': 1,
                        'rel': 0,
                        'showinfo': 0,
                        'loop': 1,
                        'mute': 1,
                        'enablejsapi': 1,
                        'origin': window.location.origin
                    },
                    events: {
                        'onReady': function(event) {
                            youtubePlayers.set(index, event.target);
                            // Si es el slide activo, reproducir
                            if (index === currentActiveIndex) {
                                playYouTubeVideo(index);
                            }
                        },
                        'onStateChange': function(event) {
                            // Loop automático cuando termine
                            if (event.data === YT.PlayerState.ENDED) {
                                event.target.playVideo();
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error al crear reproductor de YouTube:', error);
            }
        });
    }

    // Observar cambios en el slide activo (versión optimizada sin MutationObserver)
    function observeActiveSlide() {
        // Usar polling en lugar de MutationObserver para evitar bloqueos
        let lastActiveIndex = currentActiveIndex;
        
        const checkInterval = setInterval(() => {
            const activeSlide = document.querySelector('.hero-slide.active');
            if (activeSlide) {
                const index = parseInt(activeSlide.dataset.index) || -1;
                if (index !== lastActiveIndex) {
                    lastActiveIndex = index;
                    currentActiveIndex = index;
                    playActiveSlide(activeSlide);
                }
            }
        }, 1000); // Verificar cada segundo en lugar de constantemente
        
        // Limpiar cuando la página se oculta
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(checkInterval);
            }
        });
    }

    // Reproducir el slide activo
    function playActiveSlide(slide) {
        const index = parseInt(slide.dataset.index) || -1;
        
        // Pausar todos los demás slides
        pauseAllSlides(index);

        // Reproducir video HTML5 si existe
        const video = slide.querySelector('.hero-trailer-video');
        if (video) {
            playHTML5Trailer(video);
        }

        // Reproducir YouTube si existe
        if (youtubePlayers.has(index)) {
            playYouTubeVideo(index);
        } else {
            // Intentar inicializar si aún no está listo
            const youtubeContainer = slide.querySelector('.hero-youtube-player');
            if (youtubeContainer && isYouTubeAPIReady) {
                // El reproductor se inicializará automáticamente
            }
        }
    }

    // Reproducir video de YouTube
    function playYouTubeVideo(index) {
        const player = youtubePlayers.get(index);
        if (player && typeof player.playVideo === 'function') {
            try {
                player.playVideo();
            } catch (error) {
                console.warn('Error al reproducir video de YouTube:', error);
            }
        }
    }

    // Pausar un slide específico
    function pauseSlide(slide, index) {
        // Pausar video HTML5
        const video = slide.querySelector('.hero-trailer-video');
        if (video) {
            video.pause();
        }

        // Pausar YouTube
        if (youtubePlayers.has(index)) {
            const player = youtubePlayers.get(index);
            if (player && typeof player.pauseVideo === 'function') {
                try {
                    player.pauseVideo();
                } catch (error) {
                    console.warn('Error al pausar video de YouTube:', error);
                }
            }
        }
    }

    // Pausar todos los slides
    function pauseAllSlides(exceptIndex = null) {
        // Pausar todos los videos HTML5
        const videos = document.querySelectorAll('.hero-trailer-video');
        videos.forEach(video => {
            const parentSlide = video.closest('.hero-slide');
            const slideIndex = parentSlide ? parseInt(parentSlide.dataset.index) || 0 : null;
            if (exceptIndex !== null && slideIndex === exceptIndex) {
                return;
            }
            video.pause();
        });

        // Pausar todos los videos de YouTube
        youtubePlayers.forEach((player, idx) => {
            if (exceptIndex !== null && idx === exceptIndex) {
                return;
            }
            if (player && typeof player.pauseVideo === 'function') {
                try {
                    player.pauseVideo();
                } catch (error) {
                    // Ignorar errores
                }
            }
        });
    }

    // Limpiar cuando la página se oculta
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pauseAllSlides();
        } else {
            // Reanudar el slide activo
            const activeSlide = document.querySelector('.hero-slide.active');
            if (activeSlide) {
                playActiveSlide(activeSlide);
            }
        }
    });

    // Limpiar reproductores al salir
    window.addEventListener('beforeunload', () => {
        pauseAllSlides();
        youtubePlayers.clear();
    });

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exportar API pública
    window.HeroTrailerPlayer = {
        playActive: playActiveSlide,
        pauseAll: pauseAllSlides,
        init: init
    };

})();

