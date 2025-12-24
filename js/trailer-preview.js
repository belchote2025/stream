/**
 * Sistema de Vista Previa de Trailers Mejorado
 * Reproduce trailers automáticamente al pasar el mouse sobre las tarjetas
 */

(function() {
    'use strict';

    // Configuración
    const config = {
        hoverDelay: 500, // Milisegundos antes de reproducir el trailer
        autoplay: true,
        muted: true,
        loop: true,
        volume: 0.3
    };

    // Estado global
    let currentTrailerCard = null;
    let hoverTimeout = null;
    let trailerPlayer = null;

    /**
     * Inicializar sistema de trailers
     */
    function initTrailerPreview() {
        // Mejorar tarjetas existentes
        enhanceExistingCards();
        
        // Observar nuevas tarjetas añadidas dinámicamente
        observeNewCards();
        
        console.log('✅ Sistema de vista previa de trailers inicializado');
    }

    /**
     * Mejorar tarjetas existentes
     */
    function enhanceExistingCards() {
        document.querySelectorAll('.content-card').forEach(card => {
            setupTrailerPreview(card);
        });
    }

    /**
     * Observar nuevas tarjetas añadidas dinámicamente
     */
    function observeNewCards() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && node.classList.contains('content-card')) {
                            setupTrailerPreview(node);
                        }
                        // También buscar tarjetas dentro del nodo añadido
                        const cards = node.querySelectorAll && node.querySelectorAll('.content-card');
                        if (cards) {
                            cards.forEach(card => setupTrailerPreview(card));
                        }
                    }
                });
            });
        });

        // Observar cambios en contenedores de contenido
        document.querySelectorAll('.row-content, .content-row').forEach(container => {
            observer.observe(container, { childList: true, subtree: true });
        });
    }

    /**
     * Configurar vista previa de trailer para una tarjeta
     */
    function setupTrailerPreview(card) {
        // Evitar configuración duplicada
        if (card.dataset.trailerPreviewSetup === 'true') {
            return;
        }

        const trailerUrl = card.dataset.trailerUrl || 
                          card.querySelector('[data-trailer-url]')?.dataset.trailerUrl || 
                          card.querySelector('.content-poster-clickable')?.dataset.trailerUrl;

        if (!trailerUrl) {
            return;
        }

        card.dataset.trailerPreviewSetup = 'true';
        card.dataset.trailerUrl = trailerUrl;

        // Crear contenedor de trailer si no existe
        let trailerContainer = card.querySelector('.content-trailer-container');
        if (!trailerContainer) {
            const mediaContainer = card.querySelector('.content-card-media');
            if (mediaContainer) {
                trailerContainer = document.createElement('div');
                trailerContainer.className = 'content-trailer-container';
                trailerContainer.style.cssText = 'display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10; background: #000; border-radius: 6px; overflow: hidden;';
                
                const wrapper = document.createElement('div');
                wrapper.className = 'content-trailer-wrapper';
                trailerContainer.appendChild(wrapper);
                
                mediaContainer.appendChild(trailerContainer);
            }
        }

        // Event listeners
        card.addEventListener('mouseenter', () => handleMouseEnter(card, trailerUrl));
        card.addEventListener('mouseleave', handleMouseLeave);
        card.addEventListener('click', (e) => {
            // Si se hace clic en el trailer, no propagar
            if (e.target.closest('.content-trailer-container')) {
                e.stopPropagation();
            }
        });
    }

    /**
     * Manejar entrada del mouse
     */
    function handleMouseEnter(card, trailerUrl) {
        // Cancelar timeout anterior si existe
        if (hoverTimeout) {
            clearTimeout(hoverTimeout);
        }

        // Si ya hay un trailer reproduciéndose en otra tarjeta, detenerlo
        if (currentTrailerCard && currentTrailerCard !== card) {
            stopTrailer(currentTrailerCard);
        }

        currentTrailerCard = card;

        // Esperar antes de reproducir (evitar reproducciones accidentales)
        hoverTimeout = setTimeout(() => {
            playTrailer(card, trailerUrl);
        }, config.hoverDelay);
    }

    /**
     * Manejar salida del mouse
     */
    function handleMouseLeave() {
        if (hoverTimeout) {
            clearTimeout(hoverTimeout);
            hoverTimeout = null;
        }

        if (currentTrailerCard) {
            stopTrailer(currentTrailerCard);
            currentTrailerCard = null;
        }
    }

    /**
     * Reproducir trailer
     */
    function playTrailer(card, trailerUrl) {
        const trailerContainer = card.querySelector('.content-trailer-container');
        const wrapper = card.querySelector('.content-trailer-wrapper');
        
        if (!trailerContainer || !wrapper) {
            return;
        }

        // Ocultar poster
        const poster = card.querySelector('.content-card-media img');
        if (poster) {
            poster.style.opacity = '0';
        }

        // Mostrar contenedor de trailer
        trailerContainer.style.display = 'block';

        // Si ya hay un player, reutilizarlo
        if (wrapper.querySelector('iframe')) {
            return;
        }

        // Extraer ID de YouTube si es una URL de YouTube
        const youtubeId = extractYouTubeId(trailerUrl);
        
        if (youtubeId) {
            // Crear iframe de YouTube con autoplay
            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube.com/embed/${youtubeId}?autoplay=1&mute=1&loop=1&playlist=${youtubeId}&controls=0&modestbranding=1&rel=0&enablejsapi=1`;
            iframe.allow = 'autoplay; encrypted-media';
            iframe.style.cssText = 'width: 100%; height: 100%; border: none;';
            iframe.frameBorder = '0';
            
            wrapper.innerHTML = '';
            wrapper.appendChild(iframe);
            trailerPlayer = iframe;
        } else if (trailerUrl.match(/\.(mp4|webm|ogg)$/i)) {
            // Video directo
            const video = document.createElement('video');
            video.src = trailerUrl;
            video.autoplay = true;
            video.muted = true;
            video.loop = true;
            video.volume = config.volume;
            video.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
            
            wrapper.innerHTML = '';
            wrapper.appendChild(video);
            trailerPlayer = video;
            
            video.play().catch(err => {
                console.warn('Error al reproducir trailer:', err);
            });
        }
    }

    /**
     * Detener trailer
     */
    function stopTrailer(card) {
        const trailerContainer = card.querySelector('.content-trailer-container');
        const wrapper = card.querySelector('.content-trailer-wrapper');
        
        if (!trailerContainer || !wrapper) {
            return;
        }

        // Ocultar contenedor de trailer
        trailerContainer.style.display = 'none';

        // Mostrar poster
        const poster = card.querySelector('.content-card-media img');
        if (poster) {
            poster.style.opacity = '1';
        }

        // Detener reproducción
        const iframe = wrapper.querySelector('iframe');
        if (iframe) {
            // Pausar YouTube
            try {
                iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
            } catch (e) {
                // Ignorar errores de cross-origin
            }
            wrapper.innerHTML = '';
        }

        const video = wrapper.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
            wrapper.innerHTML = '';
        }

        trailerPlayer = null;
    }

    /**
     * Extraer ID de YouTube de una URL
     */
    function extractYouTubeId(url) {
        if (!url) return null;
        
        const patterns = [
            /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
            /youtube\.com\/watch\?.*v=([^&\n?#]+)/
        ];

        for (const pattern of patterns) {
            const match = url.match(pattern);
            if (match && match[1]) {
                return match[1];
            }
        }

        return null;
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTrailerPreview);
    } else {
        initTrailerPreview();
    }

    // Exportar funciones para uso externo si es necesario
    window.TrailerPreview = {
        init: initTrailerPreview,
        setup: setupTrailerPreview,
        stop: stopTrailer
    };

})();








