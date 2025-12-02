/**
 * Reproductor de video unificado para la plataforma de streaming
 * Soporta: videos locales, URLs externas, YouTube, torrents
 */

class UnifiedVideoPlayer {
    constructor(containerId, options = {}) {
        // Configuración inicial
        this.container = document.getElementById(containerId);
        this.options = { ...PlayerConfig.options, ...options };
        this.selectors = PlayerConfig.selectors;
        this.messages = PlayerConfig.messages;
        
        // Elementos del DOM
        this.elements = {};
        this.videoElement = null;
        this.youtubePlayer = null;
        this.torrentClient = null;
        this.currentTorrent = null;
        
        // Estado del reproductor
        this.videoType = null; // 'local', 'url', 'youtube', 'torrent'
        this.isPlaying = false;
        this.isMuted = false;
        this.isFullscreen = false;
        this.currentTime = 0;
        this.duration = 0;
        this.buffered = 0;
        this.volume = this.options.volume;
        this.playbackRate = this.options.playbackRate;
        this.qualityLevels = [];
        
        // Inicializar
        this.init();
    }
    
    // Inicialización
    init() {
        try {
            this.createPlayerStructure();
            this.setupEventListeners();
            this.setupCustomControls();
            this.initWebTorrent();
            
            // Cargar API de YouTube si no está cargada
            if (!window.YT) {
                this.loadYouTubeAPI();
            }
            
            // Configurar atajos de teclado
            document.addEventListener('keydown', this.handleKeyboard.bind(this));
            
            // Inicializar UI
            this.updateVolumeButton();
            this.updatePlayPauseButton();
            
        } catch (error) {
            console.error('Error al inicializar el reproductor:', error);
            this.handleError(error);
        }
    }
    
    // Métodos principales
    loadVideo(source, type = null) {
        try {
            // Determinar el tipo de video si no se especifica
            if (!type) {
                type = this.detectVideoType(source);
            }
            
            // Limpiar reproductor actual
            this.cleanupCurrentPlayer();
            
            // Cargar el video según el tipo
            this.videoType = type;
            
            switch (type) {
                case 'youtube':
                    this.loadYouTube(source);
                    break;
                case 'torrent':
                    this.loadTorrent(source);
                    break;
                case 'local':
                case 'url':
                default:
                    this.loadHTML5Video(source);
                    break;
            }
            
        } catch (error) {
            console.error('Error al cargar el video:', error);
            this.handleError(error);
        }
    }
    
    // Métodos de control
    play() {
        try {
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.playVideo();
            } else if (this.videoElement) {
                this.videoElement.play().catch(error => {
                    console.error('Error al reproducir:', error);
                    this.handleError(error);
                });
            }
            this.isPlaying = true;
            this.updatePlayPauseButton();
        } catch (error) {
            console.error('Error en play():', error);
            this.handleError(error);
        }
    }
    
    pause() {
        try {
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.pauseVideo();
            } else if (this.videoElement) {
                this.videoElement.pause();
            }
            this.isPlaying = false;
            this.updatePlayPauseButton();
        } catch (error) {
            console.error('Error en pause():', error);
            this.handleError(error);
        }
    }
    
    togglePlayPause() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }
    
    seek(time) {
        try {
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.seekTo(time, true);
            } else if (this.videoElement) {
                this.videoElement.currentTime = time;
            }
            this.currentTime = time;
            this.updateProgress();
        } catch (error) {
            console.error('Error en seek():', error);
            this.handleError(error);
        }
    }
    
    setVolume(volume) {
        try {
            this.volume = Math.max(0, Math.min(1, volume));
            
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.setVolume(this.volume * 100);
            } else if (this.videoElement) {
                this.videoElement.volume = this.volume;
            }
            
            this.updateVolumeButton();
        } catch (error) {
            console.error('Error en setVolume():', error);
            this.handleError(error);
        }
    }
    
    toggleMute() {
        this.isMuted = !this.isMuted;
        
        if (this.videoType === 'youtube' && this.youtubePlayer) {
            if (this.isMuted) {
                this.youtubePlayer.mute();
            } else {
                this.youtubePlayer.unMute();
            }
        } else if (this.videoElement) {
            this.videoElement.muted = this.isMuted;
        }
        
        this.updateVolumeButton();
    }
    
    setPlaybackRate(rate) {
        try {
            this.playbackRate = rate;
            
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.setPlaybackRate(rate);
            } else if (this.videoElement) {
                this.videoElement.playbackRate = rate;
            }
        } catch (error) {
            console.error('Error en setPlaybackRate():', error);
            this.handleError(error);
        }
    }
    
    toggleFullscreen() {
        try {
            if (!this.isFullscreen) {
                if (this.container.requestFullscreen) {
                    this.container.requestFullscreen();
                } else if (this.container.webkitRequestFullscreen) {
                    this.container.webkitRequestFullscreen();
                } else if (this.container.msRequestFullscreen) {
                    this.container.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
            
            this.isFullscreen = !this.isFullscreen;
        } catch (error) {
            console.error('Error en toggleFullscreen():', error);
            this.handleError(error);
        }
    }
    
    // Métodos de utilidad
    detectVideoType(url) {
        if (!url) return 'url';
        
        // Verificar si es YouTube
        if (url.includes('youtube.com') || url.includes('youtu.be') || 
            (url.match(/^[a-zA-Z0-9_-]{11}$/) && !url.includes('.'))) {
            return 'youtube';
        }
        
        // Verificar si es un torrent
        if (url.startsWith('magnet:') || url.endsWith('.torrent')) {
            return 'torrent';
        }
        
        // Verificar si es una URL local
        if (url.startsWith('/') || !url.includes('://') || url.startsWith(window.location.origin)) {
            return 'local';
        }
        
        // Por defecto, asumir que es una URL externa
        return 'url';
    }
    
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        
        const minutes = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
    
    // Crear estructura del reproductor
    createPlayerStructure() {
        if (!this.container) {
            console.error('Container not found');
            return;
        }
        
        // Si el contenedor ya tiene contenido, no recrear
        if (this.container.querySelector('video, #youtubePlayerContainer, #torrentPlayerContainer')) {
            return;
        }
        
        // Crear estructura básica si no existe
        const wrapper = document.createElement('div');
        wrapper.className = 'unified-video-wrapper';
        wrapper.innerHTML = `
            <div class="video-loading-overlay" id="videoLoading">
                <div class="loading-spinner"></div>
                <p>Cargando video...</p>
            </div>
            <div class="video-error-overlay" id="videoError" style="display: none;">
                <div class="error-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error al cargar el video</h3>
                    <p id="errorMessage">No se pudo cargar el video.</p>
                </div>
            </div>
            <video id="unifiedVideoPlayer" class="video-player" controls playsinline preload="${this.options.preload || 'metadata'}" style="display: none;"></video>
            <div id="youtubePlayerContainer" style="display: none;"></div>
            <div id="torrentPlayerContainer" style="display: none;"></div>
        `;
        
        this.container.appendChild(wrapper);
        this.videoElement = document.getElementById('unifiedVideoPlayer');
    }
    
    // Mostrar indicador de carga
    showLoading() {
        const loadingElement = this.container.querySelector('#videoLoading');
        if (loadingElement) {
            loadingElement.style.display = 'flex';
        }
    }
    
    // Ocultar indicador de carga
    hideLoading() {
        const loadingElement = this.container.querySelector('#videoLoading');
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }
    }
    
    // Manejo de errores
    handleError(error) {
        console.error('Error en el reproductor:', error);
        
        // Mostrar mensaje de error en la interfaz
        const errorElement = this.container.querySelector(this.selectors.error);
        if (errorElement) {
            const errorMessage = errorElement.querySelector('#errorMessage') || errorElement;
            errorMessage.textContent = error.message || this.messages.loadError;
            errorElement.style.display = 'flex';
        }
        
        // Ocultar indicador de carga
        this.hideLoading();
        
        // Llamar al manejador de errores personalizado si existe
        if (this.options.onError && typeof this.options.onError === 'function') {
            this.options.onError(error);
        }
    }
    
    // Limpieza
    destroy() {
        try {
            // Limpiar eventos
            document.removeEventListener('keydown', this.handleKeyboard);
            
            // Detener y limpiar YouTube
            if (this.youtubePlayer) {
                this.youtubePlayer.destroy();
                this.youtubePlayer = null;
            }
            
            // Detener y limpiar WebTorrent
            if (this.torrentClient) {
                if (this.currentTorrent) {
                    this.currentTorrent.destroy();
                    this.currentTorrent = null;
                }
                this.torrentClient.destroy();
                this.torrentClient = null;
            }
            
            // Limpiar elementos del DOM
            if (this.videoElement) {
                this.videoElement.pause();
                this.videoElement.src = '';
                this.videoElement.load();
                this.videoElement = null;
            }
            
            // Limpiar contenedores
            const containers = [
                this.selectors.youtubeContainer,
                this.selectors.torrentContainer,
                this.selectors.loading,
                this.selectors.error
            ];
            
            containers.forEach(selector => {
                const element = document.querySelector(selector);
                if (element) {
                    element.style.display = 'none';
                    element.innerHTML = '';
                }
            });
            
        } catch (error) {
            console.error('Error al destruir el reproductor:', error);
        }
    }
}

// Exportar para uso global
window.UnifiedVideoPlayer = UnifiedVideoPlayer;
