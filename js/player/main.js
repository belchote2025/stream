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
            
            // Cargar API de YouTube si no está cargada (usar función global si existe)
            if (!window.YT) {
                if (typeof loadYouTubeAPI === 'function') {
                    // Usar función global si está disponible
                    loadYouTubeAPI().catch(err => {
                        console.warn('Error al cargar YouTube API (global):', err);
                    });
                } else if (typeof this.loadYouTubeAPI === 'function') {
                    // Usar método de la clase si está disponible
                    this.loadYouTubeAPI().catch(err => {
                        console.warn('Error al cargar YouTube API (método):', err);
                    });
                } else {
                    // Si no hay método disponible, solo loguear advertencia
                    console.warn('YouTube API no está disponible y no se puede cargar automáticamente');
                }
            }
            
            // Configurar atajos de teclado (solo si está definida la función)
            if (typeof this.handleKeyboard === 'function') {
                this._keyboardHandler = this.handleKeyboard.bind(this);
                document.addEventListener('keydown', this._keyboardHandler);
            } else {
                console.warn('handleKeyboard no está definida, se omiten atajos');
            }
            
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
        return new Promise((resolve, reject) => {
            try {
                // Determinar el tipo de video si no se especifica
                if (!type) {
                    type = this.detectVideoType(source);
                }
                
                // Limpiar reproductor actual (si existe)
                this.cleanupCurrentPlayer();
                
                // Cargar el video según el tipo
                this.videoType = type;
                
                switch (type) {
                    case 'youtube':
                        // Para YouTube, intentar cargar con iframe
                        this.loadYouTube(source).then(resolve).catch(reject);
                        return;
                    case 'torrent':
                        // Para torrents, usar WebTorrent
                        this.loadTorrent(source).then(resolve).catch(reject);
                        return;
                    case 'local':
                    case 'url':
                    default:
                        // Para videos locales o URLs directas, usar HTML5
                        try {
                            this.loadHTML5Video(source);
                            resolve();
                        } catch (error) {
                            reject(error);
                        }
                        return;
                }
            } catch (error) {
                console.error('Error al cargar el video:', error);
                this.handleError(error);
                reject(error);
            }
        });
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
    
    // Carga un video HTML5 simple en el elemento <video>
    loadHTML5Video(source) {
        try {
            // Asegurar que el contenedor existe
            if (!this.container) {
                throw new Error('No se encontró el contenedor del reproductor');
            }
            
            // Crear estructura si no existe
            if (!this.videoElement) {
                this.createPlayerStructure();
            }
            
            if (!this.videoElement) {
                throw new Error('No se pudo crear el elemento de video HTML5');
            }

            // Normalizar la URL si es relativa
            let normalizedSource = source;
            if (source && !source.startsWith('http://') && !source.startsWith('https://') && !source.startsWith('magnet:')) {
                if (source.startsWith('/')) {
                    normalizedSource = window.location.origin + source;
                } else {
                    normalizedSource = window.location.origin + '/' + source;
                }
            }

            console.log('Cargando video HTML5:', normalizedSource);
            this.showLoading();
            
            // Ocultar otros reproductores
            const youtubeContainer = this.container.querySelector('#youtubePlayerContainer');
            const torrentContainer = this.container.querySelector('#torrentPlayerContainer');
            if (youtubeContainer) youtubeContainer.style.display = 'none';
            if (torrentContainer) torrentContainer.style.display = 'none';
            
            // Mostrar y configurar el video HTML5
            this.videoElement.style.display = 'block';
            this.videoElement.src = normalizedSource;
            this.videoElement.load(); // Forzar recarga

            this.videoElement.onloadeddata = () => {
                console.log('Video HTML5 cargado correctamente');
                this.hideLoading();
                if (this.options.autoplay) {
                    this.play().catch(err => {
                        console.warn('No se pudo reproducir automáticamente:', err);
                    });
                }
            };

            this.videoElement.onerror = (e) => {
                console.error('Error al cargar el video HTML5:', e);
                this.hideLoading();
                this.handleError(new Error('No se pudo cargar el archivo de video. Verifica que la URL sea válida.'));
            };
            
            this.videoElement.oncanplay = () => {
                console.log('Video HTML5 listo para reproducir');
            };
        } catch (error) {
            console.error('Error en loadHTML5Video():', error);
            this.hideLoading();
            this.handleError(error);
            throw error;
        }
    }

    // Carga videos de YouTube usando iframe
    loadYouTube(source) {
        return new Promise((resolve, reject) => {
            try {
                // Extraer ID de YouTube
                let videoId = '';
                if (source.includes('youtube.com/watch?v=')) {
                    videoId = source.split('v=')[1].split('&')[0];
                } else if (source.includes('youtu.be/')) {
                    videoId = source.split('youtu.be/')[1].split('?')[0];
                } else if (source.includes('youtube.com/embed/')) {
                    videoId = source.split('embed/')[1].split('?')[0];
                } else if (/^[\w-]{11}$/.test(source)) {
                    videoId = source;
                }
                
                if (!videoId) {
                    throw new Error('No se pudo extraer el ID de YouTube de la URL');
                }
                
                // Limpiar contenedor
                if (this.container) {
                    this.container.innerHTML = '';
                }
                
                // Crear iframe de YouTube
                const iframe = document.createElement('iframe');
                iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&enablejsapi=1&origin=${window.location.origin}`;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.allow = 'autoplay; encrypted-media';
                iframe.allowFullscreen = true;
                
                if (this.container) {
                    this.container.appendChild(iframe);
                }
                
                this.videoType = 'youtube';
                this.hideLoading();
                resolve();
            } catch (error) {
                console.error('Error en loadYouTube():', error);
                this.handleError(error);
                reject(error);
            }
        });
    }

    // Carga torrents usando WebTorrent
    loadTorrent(source) {
        return new Promise((resolve, reject) => {
            try {
                if (typeof WebTorrent === 'undefined') {
                    throw new Error('WebTorrent no está disponible. Por favor, recarga la página.');
                }
                
                if (!this.torrentClient) {
                    this.initWebTorrent();
                }
                
                if (!this.torrentClient) {
                    throw new Error('No se pudo inicializar el cliente WebTorrent');
                }
                
                this.showLoading();
                
                // Limpiar torrent anterior
                if (this.currentTorrent) {
                    this.currentTorrent.destroy();
                    this.currentTorrent = null;
                }
                
                // Crear elemento de video si no existe
                if (!this.videoElement) {
                    this.createPlayerStructure();
                }
                
                // Agregar torrent al cliente
                this.torrentClient.add(source, (torrent) => {
                    this.currentTorrent = torrent;
                    
                    // Buscar el archivo de video más grande
                    const videoFile = torrent.files.find(file => {
                        const name = file.name.toLowerCase();
                        return name.endsWith('.mp4') || name.endsWith('.webm') || 
                               name.endsWith('.mkv') || name.endsWith('.avi');
                    }) || torrent.files[0];
                    
                    if (!videoFile) {
                        throw new Error('No se encontró ningún archivo de video en el torrent');
                    }
                    
                    // Crear URL del blob
                    videoFile.renderTo(this.videoElement, {
                        autoplay: this.options.autoplay,
                        controls: false
                    }, (err) => {
                        if (err) {
                            console.error('Error al renderizar el video:', err);
                            this.handleError(err);
                            reject(err);
                        } else {
                            this.hideLoading();
                            this.videoType = 'torrent';
                            resolve();
                        }
                    });
                });
            } catch (error) {
                console.error('Error en loadTorrent():', error);
                this.handleError(error);
                reject(error);
            }
        });
    }

    // Inicializa listeners básicos del elemento de video (protección contra undefined)
    setupEventListeners() {
        // Si no hay elemento de video, salir silenciosamente
        if (!this.videoElement) {
            return;
        }

        // Asegurar que los handlers estén ligados al contexto correcto
        const updateTime = () => {
            if (!this.videoElement) return;
            this.currentTime = this.videoElement.currentTime;
            this.duration = this.videoElement.duration || 0;
        };

        const handleEnded = () => {
            this.isPlaying = false;
            if (this.options.onEnded) {
                try { this.options.onEnded(); } catch (e) { console.error(e); }
            }
        };

        this.videoElement.addEventListener('timeupdate', updateTime);
        this.videoElement.addEventListener('ended', handleEnded);
    }

    // Actualiza el botón de volumen (placeholder seguro)
    updateVolumeButton() {
        // Si en el futuro añadimos un botón de volumen personalizado,
        // aquí se sincronizaría su icono/estado. De momento no hace falta.
        return;
    }

    // Actualiza el botón de play/pausa (placeholder seguro)
    updatePlayPauseButton() {
        // Similar al de volumen: actualmente usamos controles nativos.
        return;
    }

    // Actualiza la barra de progreso (placeholder seguro)
    updateProgress() {
        // La UI externa ya actualiza la barra leyendo eventos de videoPlayer en watch.php.
        return;
    }

    // Inicializa controles personalizados (placeholder seguro)
    setupCustomControls() {
        // Si en el futuro se agregan controles personalizados, inicializarlos aquí.
        // De momento, usar los controles nativos ya habilitados en el <video>.
        return;
    }

    // Atajos de teclado básicos
    handleKeyboard(event) {
        if (!event) return;
        const key = event.key?.toLowerCase();

        // Si el foco está en un input/textarea, no interceptar
        const tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
        if (['input', 'textarea', 'select', 'button'].includes(tag)) return;

        switch (key) {
            case ' ':
            case 'k':
                event.preventDefault();
                this.togglePlayPause();
                break;
            case 'm':
                event.preventDefault();
                this.toggleMute();
                break;
            case 'f':
                event.preventDefault();
                this.toggleFullscreen();
                break;
            case 'arrowright':
                event.preventDefault();
                this.seek(Math.min((this.videoElement?.currentTime || 0) + 10, this.duration || Infinity));
                break;
            case 'arrowleft':
                event.preventDefault();
                this.seek(Math.max((this.videoElement?.currentTime || 0) - 10, 0));
                break;
            case 'arrowup':
                event.preventDefault();
                this.setVolume(Math.min((this.videoElement?.volume ?? this.volume) + 0.1, 1));
                break;
            case 'arrowdown':
                event.preventDefault();
                this.setVolume(Math.max((this.videoElement?.volume ?? this.volume) - 0.1, 0));
                break;
            default:
                break;
        }
    }

    // Inicializa cliente WebTorrent de forma segura
    initWebTorrent() {
        try {
            if (window.WebTorrent && !this.torrentClient) {
                this.torrentClient = new WebTorrent();
            }
        } catch (e) {
            console.warn('No se pudo inicializar WebTorrent:', e);
        }
    }

    // Limpia cualquier reproductor/carga previa antes de cargar un nuevo video
    cleanupCurrentPlayer() {
        try {
            // Pausar video HTML5 si existe
            if (this.videoElement) {
                this.videoElement.pause();
                this.videoElement.removeAttribute('src');
                this.videoElement.load();
            }

            // Detener YouTube si existe
            if (this.youtubePlayer) {
                try {
                    this.youtubePlayer.stopVideo();
                } catch (e) {}
            }

            // Detener torrent actual si existe
            if (this.currentTorrent) {
                try {
                    this.currentTorrent.destroy();
                } catch (e) {}
                this.currentTorrent = null;
            }
        } catch (e) {
            console.warn('Error en cleanupCurrentPlayer:', e);
        }
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
    
    // Cargar API de YouTube (método auxiliar)
    loadYouTubeAPI() {
        return new Promise((resolve, reject) => {
            if (window.YT && window.YT.Player) {
                resolve();
                return;
            }
            
            // Si ya se está cargando, esperar
            if (window.onYouTubeIframeAPIReady) {
                const checkReady = setInterval(() => {
                    if (window.YT && window.YT.Player) {
                        clearInterval(checkReady);
                        resolve();
                    }
                }, 100);
                
                setTimeout(() => {
                    clearInterval(checkReady);
                    if (!window.YT || !window.YT.Player) {
                        reject(new Error('Timeout al cargar YouTube API'));
                    }
                }, 10000);
                return;
            }
            
            // Cargar la API
            window.onYouTubeIframeAPIReady = () => {
                resolve();
            };
            
            const tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            const firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            
            // Timeout
            setTimeout(() => {
                if (!window.YT || !window.YT.Player) {
                    reject(new Error('Timeout al cargar YouTube API'));
                }
            }, 10000);
        });
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
