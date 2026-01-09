/**
 * Inicialización del reproductor de video unificado
 * Este archivo se encarga de cargar todos los componentes necesarios
 * y exponer la API del reproductor para su uso en la aplicación
 */

// Esperar a que el DOM esté completamente cargado
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si el contenedor del reproductor existe (puede tener diferentes nombres)
    const container = document.getElementById('videoPlayer') || 
                     document.getElementById('unifiedVideoContainer') ||
                     document.querySelector('.video-player-container');
    
    if (!container) {
        // No es un error crítico, solo un warning si no se encuentra
        // El reproductor puede inicializarse desde otro lugar
        return;
    }
    
    // Cargar estilos si no están ya cargados
    if (!document.querySelector('link[href*="video-player.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        // Intentar diferentes rutas posibles
        const baseUrl = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const cssPaths = [
            baseUrl + '/css/video-player.css',
            '/css/video-player.css',
            'css/video-player.css'
        ];
        
        // Intentar cargar el CSS
        let cssLoaded = false;
        cssPaths.forEach((path, index) => {
            if (cssLoaded) return;
            const testLink = document.createElement('link');
            testLink.rel = 'stylesheet';
            testLink.href = path;
            testLink.onerror = () => {
                if (index === cssPaths.length - 1) {
                    console.warn('No se pudo cargar video-player.css desde ninguna ruta');
                }
            };
            testLink.onload = () => {
                cssLoaded = true;
            };
            document.head.appendChild(testLink);
        });
    }
    
    // Inicializar el reproductor con el contenedor encontrado
    const containerId = container.id || 'videoPlayer';
    window.videoPlayer = new UnifiedVideoPlayer(containerId, {
        autoplay: false,
        controls: true,
        onPlay: function() {
            console.log('Reproduciendo video');
        },
        onPause: function() {
            console.log('Video pausado');
        },
        onEnded: function() {
            console.log('Video finalizado');
        },
        onError: function(error) {
            console.error('Error en el reproductor:', error);
        },
        onTimeUpdate: function(time) {
            // Actualizar la barra de progreso
            const progressBar = document.querySelector('.progress-bar .progress');
            if (progressBar) {
                const percentage = (time.currentTime / time.duration) * 100;
                progressBar.style.width = percentage + '%';
            }
            
            // Actualizar el tiempo actual
            const currentTimeElement = document.querySelector('.time-display .current-time');
            if (currentTimeElement) {
                currentTimeElement.textContent = formatTime(time.currentTime);
            }
        },
        onVolumeChange: function(volume) {
            // Actualizar el control de volumen
            const volumeSlider = document.querySelector('.volume-slider .slider-fill');
            if (volumeSlider) {
                volumeSlider.style.width = (volume * 100) + '%';
            }
        }
    });
    
    // Función auxiliar para formatear el tiempo
    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
    
    // Exponer la API del reproductor globalmente
    window.playVideo = function(source, type = null) {
        if (window.videoPlayer) {
            window.videoPlayer.loadVideo(source, type);
            window.videoPlayer.play();
        }
    };
    
    console.log('Reproductor de video inicializado correctamente');
});

// Función para cargar la API de YouTube de forma asíncrona
function loadYouTubeAPI() {
    if (window.YT && window.YT.Player) {
        return Promise.resolve();
    }
    
    return new Promise((resolve) => {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
        
        window.onYouTubeIframeAPIReady = function() {
            console.log('YouTube API cargada correctamente');
            resolve();
        };
    });
}

// Cargar WebTorrent de forma asíncrona
function loadWebTorrent() {
    if (window.WebTorrent) {
        return Promise.resolve();
    }
    
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/webtorrent@latest/webtorrent.min.js';
        script.onload = () => {
            console.log('WebTorrent cargado correctamente');
            resolve();
        };
        script.onerror = (error) => {
            console.error('Error al cargar WebTorrent:', error);
            reject(error);
        };
        document.head.appendChild(script);
    });
}

// Cargar dependencias
Promise.all([
    loadYouTubeAPI(),
    loadWebTorrent()
]).catch(error => {
    console.error('Error al cargar dependencias del reproductor:', error);
});
