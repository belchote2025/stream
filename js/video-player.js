/**
 * Reproductor de video unificado y mejorado
 * Soporta: videos locales, URLs externas, YouTube, torrents
 */

class UnifiedVideoPlayer {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            autoplay: options.autoplay || false,
            controls: options.controls !== false,
            preload: options.preload || 'metadata',
            startTime: options.startTime || 0,
            onProgress: options.onProgress || null,
            onEnded: options.onEnded || null,
            onError: options.onError || null,
            ...options
        };
        
        this.videoElement = null;
        this.youtubePlayer = null;
        this.torrentClient = null;
        this.currentTorrent = null;
        this.videoType = null; // 'local', 'url', 'youtube', 'torrent'
        this.isPlaying = false;
        this.currentTime = 0;
        this.duration = 0;
        this.volume = 1;
        this.playbackRate = 1;
        
        this.init();
    }
    
    init() {
        if (!this.container) {
            console.error('Container not found');
            return;
        }
        
        // Crear estructura del reproductor
        this.createPlayerStructure();
        
        // Inicializar WebTorrent si está disponible
        this.initWebTorrent();
    }
    
    createPlayerStructure() {
        this.container.innerHTML = `
            <div class="unified-video-wrapper">
                <div class="video-loading-overlay" id="videoLoading">
                    <div class="loading-spinner"></div>
                    <p>Cargando video...</p>
                </div>
                
                <div class="video-error-overlay" id="videoError" style="display: none;">
                    <div class="error-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error al cargar el video</h3>
                        <p id="errorMessage">No se pudo cargar el video.</p>
                        <button class="btn-retry" onclick="this.closest('.unified-video-wrapper').querySelector('.video-player').player.retry()">
                            <i class="fas fa-redo"></i> Reintentar
                        </button>
                    </div>
                </div>
                
                <video 
                    id="unifiedVideoPlayer" 
                    class="video-player"
                    controls
                    playsinline
                    preload="${this.options.preload}"
                    style="display: none;"
                ></video>
                
                <div id="youtubePlayerContainer" style="display: none;"></div>
                <div id="torrentPlayerContainer" style="display: none;"></div>
                
                <!-- Controles personalizados -->
                <div class="custom-controls" id="customControls" style="display: none;">
                    <div class="controls-overlay"></div>
                    <div class="controls-bar">
                        <div class="progress-section">
                            <div class="progress-bar-container">
                                <div class="progress-bar" id="progressBar">
                                    <div class="progress-filled" id="progressFilled"></div>
                                    <div class="progress-buffer" id="progressBuffer"></div>
                                    <div class="progress-hover" id="progressHover"></div>
                                </div>
                            </div>
                            <div class="time-info">
                                <span class="current-time">0:00</span>
                                <span class="duration">/ 0:00</span>
                            </div>
                        </div>
                        
                        <div class="controls-section">
                            <div class="left-controls">
                                <button class="control-btn play-pause-btn" id="playPauseBtn">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="control-btn volume-btn" id="volumeBtn">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                                <div class="volume-slider-container">
                                    <input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.01" value="1">
                                </div>
                                <span class="time-display">
                                    <span id="currentTimeDisplay">0:00</span> / <span id="durationDisplay">0:00</span>
                                </span>
                            </div>
                            
                            <div class="right-controls">
                                <div class="speed-control">
                                    <button class="control-btn speed-btn" id="speedBtn">
                                        <span>1x</span>
                                    </button>
                                    <div class="speed-menu" id="speedMenu">
                                        <button data-speed="0.25">0.25x</button>
                                        <button data-speed="0.5">0.5x</button>
                                        <button data-speed="0.75">0.75x</button>
                                        <button data-speed="1" class="active">1x</button>
                                        <button data-speed="1.25">1.25x</button>
                                        <button data-speed="1.5">1.5x</button>
                                        <button data-speed="1.75">1.75x</button>
                                        <button data-speed="2">2x</button>
                                    </div>
                                </div>
                                <button class="control-btn quality-btn" id="qualityBtn" style="display: none;">
                                    <span>Calidad</span>
                                </button>
                                <button class="control-btn fullscreen-btn" id="fullscreenBtn">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.videoElement = document.getElementById('unifiedVideoPlayer');
        if (this.videoElement) {
            this.videoElement.player = this;
        }
        
        this.setupEventListeners();
    }
    
    setupEventListeners() {
        if (!this.videoElement) return;
        
        // Eventos del video
        this.videoElement.addEventListener('loadedmetadata', () => {
            this.duration = this.videoElement.duration;
            this.updateDurationDisplay();
            this.hideLoading();
        });
        
        this.videoElement.addEventListener('timeupdate', () => {
            this.currentTime = this.videoElement.currentTime;
            this.updateProgress();
            if (this.options.onProgress) {
                this.options.onProgress(this.currentTime, this.duration);
            }
        });
        
        this.videoElement.addEventListener('play', () => {
            this.isPlaying = true;
            this.updatePlayPauseButton();
        });
        
        this.videoElement.addEventListener('pause', () => {
            this.isPlaying = false;
            this.updatePlayPauseButton();
        });
        
        this.videoElement.addEventListener('ended', () => {
            this.isPlaying = false;
            if (this.options.onEnded) {
                this.options.onEnded();
            }
        });
        
        this.videoElement.addEventListener('error', (e) => {
            this.handleError(e);
        });
        
        this.videoElement.addEventListener('waiting', () => {
            this.showLoading('Buffering...');
        });
        
        this.videoElement.addEventListener('playing', () => {
            this.hideLoading();
        });
        
        this.videoElement.addEventListener('progress', () => {
            this.updateBuffer();
        });
        
        // Controles personalizados
        this.setupCustomControls();
    }
    
    setupCustomControls() {
        const playPauseBtn = document.getElementById('playPauseBtn');
        const volumeBtn = document.getElementById('volumeBtn');
        const volumeSlider = document.getElementById('volumeSlider');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const progressBar = document.getElementById('progressBar');
        const speedBtn = document.getElementById('speedBtn');
        const speedMenu = document.getElementById('speedMenu');
        
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', () => this.togglePlayPause());
        }
        
        if (volumeBtn) {
            volumeBtn.addEventListener('click', () => this.toggleMute());
        }
        
        if (volumeSlider) {
            volumeSlider.addEventListener('input', (e) => {
                this.setVolume(parseFloat(e.target.value));
            });
        }
        
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        }
        
        if (progressBar) {
            progressBar.addEventListener('click', (e) => {
                const rect = progressBar.getBoundingClientRect();
                const percent = (e.clientX - rect.left) / rect.width;
                this.seek(percent * this.duration);
            });
        }
        
        if (speedMenu) {
            speedMenu.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    const speed = parseFloat(btn.dataset.speed);
                    this.setPlaybackRate(speed);
                    speedMenu.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    speedBtn.querySelector('span').textContent = speed + 'x';
                });
            });
        }
        
        // Detectar si es dispositivo táctil
        const isTouchDevice = window.matchMedia('(hover: none) and (pointer: coarse)').matches;
        const isDesktop = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        
        // Mostrar/ocultar controles (solo en desktop)
        if (isDesktop) {
            let controlsTimeout;
            this.container.addEventListener('mousemove', () => {
                this.showControls();
                clearTimeout(controlsTimeout);
                controlsTimeout = setTimeout(() => {
                    if (!this.isPlaying) return;
                    this.hideControls();
                }, 3000);
            });
            this.container.addEventListener('mouseleave', () => {
                clearTimeout(controlsTimeout);
                if (this.isPlaying) {
                    this.hideControls();
                }
            });
        }
        
        // Toggle controles al tocar en móviles
        if (isTouchDevice) {
            // En móviles, mostrar controles siempre pero con auto-hide
            this.showControls();
            
            let touchTimeout;
            let lastTouchTime = 0;
            let isControlsVisible = true;
            
            const hideControlsDelayed = () => {
                clearTimeout(touchTimeout);
                touchTimeout = setTimeout(() => {
                    if (this.isPlaying && isControlsVisible) {
                        this.hideControls();
                        isControlsVisible = false;
                    }
                }, 3000);
            };
            
            // Toggle al tocar el contenedor
            this.container.addEventListener('touchstart', (e) => {
                // No hacer toggle si se toca un control
                if (e.target.closest('.custom-controls, .control-btn, .progress-bar-container')) {
                    return;
                }
                
                const now = Date.now();
                if (now - lastTouchTime < 300) {
                    // Doble toque - toggle controles
                    if (isControlsVisible) {
                        this.hideControls();
                        isControlsVisible = false;
                    } else {
                        this.showControls();
                        isControlsVisible = true;
                        hideControlsDelayed();
                    }
                } else {
                    // Tocar una vez - mostrar controles
                    this.showControls();
                    isControlsVisible = true;
                    hideControlsDelayed();
                }
                lastTouchTime = now;
            }, { passive: true });
            
            // Mantener controles visibles cuando se interactúa con ellos
            const controlsElement = document.getElementById('customControls');
            if (controlsElement) {
                controlsElement.addEventListener('touchstart', () => {
                    clearTimeout(touchTimeout);
                    this.showControls();
                    isControlsVisible = true;
                    hideControlsDelayed();
                }, { passive: true });
            }
            
            // Activar menú de velocidad con tap
            if (speedBtn) {
                speedBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isActive = speedControl.classList.contains('active');
                    if (isActive) {
                        speedControl.classList.remove('active');
                        speedMenu.style.display = 'none';
                    } else {
                        speedControl.classList.add('active');
                        speedMenu.style.display = 'block';
                    }
                });
            }
            
            // Cerrar menú de velocidad al tocar fuera
            document.addEventListener('touchstart', (e) => {
                if (speedControl && !speedControl.contains(e.target)) {
                    speedControl.classList.remove('active');
                    speedMenu.style.display = 'none';
                }
            }, { passive: true });
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }
    
    detectVideoType(url) {
        if (!url) return null;
        
        // YouTube
        if (url.includes('youtube.com') || url.includes('youtu.be') || url.includes('youtube-nocookie.com')) {
            return 'youtube';
        }
        
        // Torrent/Magnet
        if (url.startsWith('magnet:') || url.includes('.torrent')) {
            return 'torrent';
        }
        
        // Local (relativo o absoluto local)
        if (url.startsWith('/') || url.startsWith('./') || url.includes('/uploads/')) {
            return 'local';
        }
        
        // URL externa
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return 'url';
        }
        
        return 'local';
    }
    
    async loadVideo(url, type = null) {
        this.showLoading('Cargando video...');
        
        if (!type) {
            type = this.detectVideoType(url);
        }
        
        this.videoType = type;
        
        try {
            switch (type) {
                case 'youtube':
                    await this.loadYouTube(url);
                    break;
                case 'torrent':
                    await this.loadTorrent(url);
                    break;
                case 'local':
                case 'url':
                default:
                    await this.loadHTML5Video(url);
                    break;
            }
        } catch (error) {
            this.handleError(error);
        }
    }
    
    async loadHTML5Video(url) {
        return new Promise((resolve, reject) => {
            if (!this.videoElement) {
                reject(new Error('Video element not found'));
                return;
            }
            
            // Ocultar otros reproductores
            const youtubeContainer = document.getElementById('youtubePlayerContainer');
            const torrentContainer = document.getElementById('torrentPlayerContainer');
            if (youtubeContainer) youtubeContainer.style.display = 'none';
            if (torrentContainer) torrentContainer.style.display = 'none';
            
            // Mostrar video HTML5
            this.videoElement.style.display = 'block';
            
            // Normalizar la URL
            let normalizedUrl = url;
            
            // Si es una URL absoluta (http/https), usarla directamente
            if (url.startsWith('http://') || url.startsWith('https://')) {
                normalizedUrl = url;
            } 
            // Si es una ruta relativa que empieza con /, convertir a absoluta
            else if (url.startsWith('/')) {
                // Obtener el base URL del sitio
                const baseUrl = window.__APP_BASE_URL || window.location.origin;
                // Asegurar que no haya doble slash
                normalizedUrl = baseUrl.replace(/\/$/, '') + url;
            }
            // Si es una ruta relativa sin /, añadir el base path
            else {
                const baseUrl = window.__APP_BASE_URL || window.location.origin;
                const currentPath = window.location.pathname;
                // Si estamos en watch.php, el base path es la raíz
                const basePath = currentPath.includes('/watch.php') ? '' : currentPath.substring(0, currentPath.lastIndexOf('/'));
                // Asegurar que no haya doble slash
                normalizedUrl = baseUrl.replace(/\/$/, '') + (basePath ? basePath : '') + '/' + url.replace(/^\//, '');
            }
            
            console.log('Cargando video:', { 
                original: url, 
                normalized: normalizedUrl,
                baseUrl: window.__APP_BASE_URL || window.location.origin,
                currentPath: window.location.pathname
            });
            
            // Configurar fuente
            this.videoElement.src = normalizedUrl;
            
            // Restaurar tiempo de inicio si existe
            if (this.options.startTime > 0) {
                this.videoElement.addEventListener('loadedmetadata', () => {
                    this.videoElement.currentTime = this.options.startTime;
                }, { once: true });
            }
            
            let resolved = false;
            
            this.videoElement.addEventListener('canplay', () => {
                if (!resolved) {
                    resolved = true;
                    this.hideLoading();
                    // Ocultar también el mensaje de carga original de watch.php
                    const originalLoading = document.querySelector('.video-loading');
                    if (originalLoading) {
                        originalLoading.style.display = 'none';
                    }
                    resolve();
                }
            }, { once: true });
            
            this.videoElement.addEventListener('loadeddata', () => {
                if (!resolved) {
                    resolved = true;
                    this.hideLoading();
                    // Ocultar también el mensaje de carga original de watch.php
                    const originalLoading = document.querySelector('.video-loading');
                    if (originalLoading) {
                        originalLoading.style.display = 'none';
                    }
                    resolve();
                }
            }, { once: true });
            
            this.videoElement.addEventListener('error', (e) => {
                if (!resolved) {
                    resolved = true;
                    const error = this.videoElement.error;
                    let errorMessage = 'Error desconocido al cargar el video';
                    
                    if (error) {
                        switch (error.code) {
                            case error.MEDIA_ERR_ABORTED:
                                errorMessage = 'La reproducción fue abortada';
                                break;
                            case error.MEDIA_ERR_NETWORK:
                                errorMessage = 'Error de red al cargar el video. Verifica que el archivo exista y sea accesible.';
                                break;
                            case error.MEDIA_ERR_DECODE:
                                errorMessage = 'Error al decodificar el video. El formato puede no ser compatible.';
                                break;
                            case error.MEDIA_ERR_SRC_NOT_SUPPORTED:
                                errorMessage = 'El formato de video no es compatible o la URL no es válida. URL: ' + normalizedUrl;
                                break;
                        }
                    }
                    
                    // Log detallado del error
                    console.error('Error al cargar video:', {
                        errorCode: error ? error.code : 'unknown',
                        errorMessage: errorMessage,
                        originalUrl: url,
                        normalizedUrl: normalizedUrl,
                        videoElement: this.videoElement,
                        networkState: this.videoElement.networkState,
                        readyState: this.videoElement.readyState
                    });
                    
                    this.hideLoading();
                    // Ocultar también el mensaje de carga original de watch.php
                    const originalLoading = document.querySelector('.video-loading');
                    if (originalLoading) {
                        originalLoading.style.display = 'none';
                    }
                    reject(new Error(errorMessage + ' (URL: ' + normalizedUrl + ')'));
                }
            }, { once: true });
            
            // Añadir más listeners para debugging
            this.videoElement.addEventListener('loadstart', () => {
                console.log('[Video] loadstart - Iniciando carga del video');
            });
            
            this.videoElement.addEventListener('loadedmetadata', () => {
                console.log('[Video] loadedmetadata - Metadatos cargados:', {
                    duration: this.videoElement.duration,
                    videoWidth: this.videoElement.videoWidth,
                    videoHeight: this.videoElement.videoHeight,
                    readyState: this.videoElement.readyState
                });
            });
            
            this.videoElement.addEventListener('stalled', () => {
                console.warn('[Video] stalled - La descarga se ha detenido');
            });
            
            this.videoElement.addEventListener('suspend', () => {
                console.warn('[Video] suspend - La descarga se ha suspendido');
            });
            
            // Intentar cargar el video
            try {
                this.videoElement.load();
            } catch (e) {
                if (!resolved) {
                    resolved = true;
                    reject(new Error('Error al inicializar el video: ' + e.message));
                }
            }
        });
    }
    
    async loadYouTube(url) {
        return new Promise((resolve, reject) => {
            try {
                // Extraer ID de YouTube o usar directamente si ya es un ID
                let videoId = null;
                
                // Si la URL parece ser un ID de YouTube (11 caracteres alfanuméricos)
                if (url.match(/^[a-zA-Z0-9_-]{11}$/)) {
                    videoId = url;
                } else {
                    // Intentar extraer el ID de una URL de YouTube
                    const patterns = [
                        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
                        /youtube\.com\/watch\?.*v=([^&\n?#]+)/
                    ];
                    
                    for (const pattern of patterns) {
                        const match = url.match(pattern);
                        if (match && match[1]) {
                            videoId = match[1];
                            // Limpiar parámetros adicionales en el ID
                            videoId = videoId.split('&')[0].split('#')[0];
                            break;
                        }
                    }
                }
                
                if (!videoId) {
                    throw new Error('ID de video de YouTube no válido. Usa el formato: https://www.youtube.com/watch?v=ID o solo el ID del video');
                }
                
                // Mostrar contenedor de YouTube
                if (this.videoElement) this.videoElement.style.display = 'none';
                const torrentContainer = document.getElementById('torrentPlayerContainer');
                if (torrentContainer) torrentContainer.style.display = 'none';
                
                const container = document.getElementById('youtubePlayerContainer');
                if (!container) {
                    throw new Error('No se encontró el contenedor de YouTube');
                }
                
                container.style.display = 'block';
                container.innerHTML = '<div id="youtubePlayer"></div>';
                
                // Función para inicializar el reproductor
                const initPlayer = () => {
                    try {
                        if (!window.YT || !window.YT.Player) {
                            throw new Error('La API de YouTube no está disponible');
                        }
                        
                        this.youtubePlayer = new YT.Player('youtubePlayer', {
                            height: '100%',
                            width: '100%',
                            videoId: videoId,
                            playerVars: {
                                autoplay: this.options.autoplay ? 1 : 0,
                                controls: 1,
                                rel: 0,
                                modestbranding: 1,
                                playsinline: 1
                            },
                            events: {
                                'onReady': (event) => {
                                    this.hideLoading();
                                    if (this.options.startTime > 0) {
                                        event.target.seekTo(this.options.startTime, true);
                                    }
                                    resolve();
                                },
                                'onError': (event) => {
                                    reject(new Error('Error al cargar el video de YouTube'));
                                },
                                'onStateChange': (event) => {
                                    if (event.data === YT.PlayerState.PLAYING) {
                                        this.isPlaying = true;
                                    } else if (event.data === YT.PlayerState.PAUSED) {
                                        this.isPlaying = false;
                                    } else if (event.data === YT.PlayerState.ENDED) {
                                        this.isPlaying = false;
                                        if (this.options.onEnded) {
                                            this.options.onEnded();
                                        }
                                    }
                                }
                            }
                        });
                    } catch (e) {
                        reject(e);
                    }
                };
                
                // Cargar API de YouTube si es necesario
                if (window.YT && window.YT.Player) {
                    // La API ya está cargada
                    initPlayer();
                } else if (window.YT && window.YT.loaded) {
                    // La API se está cargando, esperar a que esté lista
                    const checkReady = setInterval(() => {
                        if (window.YT && window.YT.Player) {
                            clearInterval(checkReady);
                            initPlayer();
                        }
                    }, 100);
                    
                    // Timeout después de 5 segundos
                    setTimeout(() => {
                        clearInterval(checkReady);
                        if (!window.YT || !window.YT.Player) {
                            reject(new Error('Tiempo de espera agotado al cargar la API de YouTube'));
                        }
                    }, 5000);
                } else {
                    // Cargar la API
                    window.onYouTubeIframeAPIReady = () => {
                        initPlayer();
                    };
                    
                    const tag = document.createElement('script');
                    tag.src = 'https://www.youtube.com/iframe_api';
                    const firstScriptTag = document.getElementsByTagName('script')[0];
                    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                    
                    // Timeout después de 5 segundos
                    setTimeout(() => {
                        if (!window.YT || !window.YT.Player) {
                            reject(new Error('Tiempo de espera agotado al cargar la API de YouTube'));
                        }
                    }, 5000);
                }
            } catch (error) {
                console.error('Error en loadYouTube:', error);
                reject(error);
            }
        });
    }
    
    async loadTorrent(url) {
        // Verificar que WebTorrent esté disponible
        if (typeof WebTorrent === 'undefined') {
            // Intentar cargar WebTorrent dinámicamente
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/webtorrent@latest/webtorrent.min.js';
                script.onload = () => {
                    this.initWebTorrent();
                    this.loadTorrentContent(url).then(resolve).catch(reject);
                };
                script.onerror = () => {
                    reject(new Error('No se pudo cargar WebTorrent. Por favor, verifica tu conexión a internet.'));
                };
                document.head.appendChild(script);
            });
        }
        
        if (!this.torrentClient) {
            this.initWebTorrent();
        }
        
        return this.loadTorrentContent(url);
    }
    
    async loadTorrentContent(url) {
        if (!this.torrentClient) {
            throw new Error('WebTorrent no está disponible');
        }
        
        return new Promise((resolve, reject) => {
            // Ocultar otros reproductores
            const youtubeContainer = document.getElementById('youtubePlayerContainer');
            if (youtubeContainer) youtubeContainer.style.display = 'none';
            if (this.videoElement) this.videoElement.style.display = 'none';
            
            const container = document.getElementById('torrentPlayerContainer');
            if (!container) {
                reject(new Error('Contenedor de torrent no encontrado'));
                return;
            }
            
            container.style.display = 'block';
            container.innerHTML = `
                <video id="torrentVideo" controls style="width: 100%; height: 100%; object-fit: contain;"></video>
                <div id="torrentProgress" style="position: absolute; bottom: 10px; left: 10px; right: 10px; background: rgba(0,0,0,0.7); padding: 0.5rem; border-radius: 4px; color: #fff; font-size: 0.85rem;">
                    <div>Conectando a la red P2P...</div>
                    <div style="margin-top: 0.25rem;">
                        <div style="background: rgba(255,255,255,0.2); height: 4px; border-radius: 2px; overflow: hidden;">
                            <div id="torrentProgressBar" style="background: #e50914; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    <div id="torrentStats" style="margin-top: 0.25rem; font-size: 0.75rem; color: #999;"></div>
                </div>
            `;
            
            const torrentVideo = document.getElementById('torrentVideo');
            const progressBar = document.getElementById('torrentProgressBar');
            const torrentStats = document.getElementById('torrentStats');
            const torrentProgress = document.getElementById('torrentProgress');
            
            // Detener torrent anterior
            if (this.currentTorrent) {
                this.currentTorrent.destroy();
            }
            
            this.showLoading('Conectando a la red P2P...');
            
            // Timeout para evitar esperas infinitas
            const timeout = setTimeout(() => {
                if (this.currentTorrent && !this.currentTorrent.ready) {
                    reject(new Error('Tiempo de espera agotado. El torrent puede no tener suficientes seeds o la conexión es lenta.'));
                }
            }, 30000); // 30 segundos
            
            this.currentTorrent = this.torrentClient.add(url, (torrent) => {
                clearTimeout(timeout);
                
                // Actualizar estadísticas del torrent
                const updateStats = () => {
                    if (torrentStats && torrent) {
                        const downloaded = (torrent.downloaded / 1024 / 1024).toFixed(2);
                        const total = (torrent.length / 1024 / 1024).toFixed(2);
                        const progress = torrent.progress * 100;
                        const downloadSpeed = (torrent.downloadSpeed / 1024 / 1024).toFixed(2);
                        const uploadSpeed = (torrent.uploadSpeed / 1024 / 1024).toFixed(2);
                        
                        torrentStats.innerHTML = `
                            Descargado: ${downloaded} MB / ${total} MB (${progress.toFixed(1)}%) | 
                            Velocidad: ↓ ${downloadSpeed} MB/s ↑ ${uploadSpeed} MB/s | 
                            Seeds: ${torrent.numPeers}
                        `;
                        
                        if (progressBar) {
                            progressBar.style.width = progress + '%';
                        }
                    }
                };
                
                // Actualizar estadísticas periódicamente
                const statsInterval = setInterval(updateStats, 500);
                
                torrent.on('done', () => {
                    clearInterval(statsInterval);
                    if (torrentProgress) {
                        torrentProgress.style.display = 'none';
                    }
                });
                
                // Buscar archivo de video
                const file = torrent.files.find(f => {
                    const name = f.name.toLowerCase();
                    return name.endsWith('.mp4') || 
                           name.endsWith('.webm') || 
                           name.endsWith('.mkv') ||
                           name.endsWith('.avi') ||
                           name.endsWith('.mov');
                });
                
                if (!file) {
                    clearInterval(statsInterval);
                    reject(new Error('No se encontró archivo de video en el torrent'));
                    return;
                }
                
                this.showLoading('Cargando video desde torrent...');
                
                // Renderizar el archivo de video
                file.renderTo(torrentVideo, {
                    autoplay: this.options.autoplay
                }, (err, element) => {
                    clearInterval(statsInterval);
                    
                    if (err) {
                        reject(err);
                        return;
                    }
                    
                    this.videoElement = element;
                    
                    // Configurar eventos del video
                    element.addEventListener('canplay', () => {
                        this.hideLoading();
                        if (torrentProgress) {
                            torrentProgress.style.display = 'none';
                        }
                        resolve();
                    }, { once: true });
                    
                    element.addEventListener('error', (e) => {
                        clearInterval(statsInterval);
                        reject(new Error('Error al reproducir el video del torrent: ' + (e.message || 'Error desconocido')));
                    }, { once: true });
                    
                    // Si ya puede reproducir, resolver inmediatamente
                    if (element.readyState >= 3) {
                        this.hideLoading();
                        if (torrentProgress) {
                            torrentProgress.style.display = 'none';
                        }
                        resolve();
                    }
                });
            });
            
            this.currentTorrent.on('error', (err) => {
                clearTimeout(timeout);
                reject(new Error('Error al cargar el torrent: ' + (err.message || 'Error desconocido')));
            });
            
            // Actualizar progreso mientras se descarga
            if (this.currentTorrent) {
                const progressInterval = setInterval(() => {
                    if (this.currentTorrent && progressBar) {
                        const progress = this.currentTorrent.progress * 100;
                        progressBar.style.width = progress + '%';
                        
                        if (torrentStats && this.currentTorrent.downloaded) {
                            const downloaded = (this.currentTorrent.downloaded / 1024 / 1024).toFixed(2);
                            const total = this.currentTorrent.length ? (this.currentTorrent.length / 1024 / 1024).toFixed(2) : '?';
                            torrentStats.textContent = `Descargando: ${downloaded} MB / ${total} MB`;
                        }
                    }
                }, 1000);
                
                this.currentTorrent.on('done', () => {
                    clearInterval(progressInterval);
                });
            }
        });
    }
    
    initWebTorrent() {
        if (typeof WebTorrent === 'undefined') {
            console.warn('⚠️ WebTorrent no está disponible aún');
            return;
        }
        
        if (this.torrentClient) {
            console.log('ℹ️ Cliente WebTorrent ya existe');
            return;
        }
        
        try {
            console.log('🔧 Inicializando cliente WebTorrent...');
            this.torrentClient = new WebTorrent();
            console.log('✅ Cliente WebTorrent creado:', this.torrentClient);
            
            // Hacer disponible globalmente para el debugger
            window.webtorrentClient = this.torrentClient;
            window.client = this.torrentClient;
        } catch (error) {
            console.error('❌ Error al crear cliente WebTorrent:', error);
        }
    }
    
    // Métodos de control
    play() {
        if (this.videoType === 'youtube' && this.youtubePlayer) {
            this.youtubePlayer.playVideo();
        } else if (this.videoElement) {
            this.videoElement.play();
        }
    }
    
    pause() {
        if (this.videoType === 'youtube' && this.youtubePlayer) {
            this.youtubePlayer.pauseVideo();
        } else if (this.videoElement) {
            this.videoElement.pause();
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
        if (this.videoType === 'youtube' && this.youtubePlayer) {
            this.youtubePlayer.seekTo(time, true);
        } else if (this.videoElement) {
            this.videoElement.currentTime = time;
        }
    }
    
    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        if (this.videoElement) {
            this.videoElement.volume = this.volume;
        }
        this.updateVolumeButton();
    }
    
    toggleMute() {
        if (this.volume > 0) {
            this.lastVolume = this.volume;
            this.setVolume(0);
        } else {
            this.setVolume(this.lastVolume || 0.5);
        }
    }
    
    setPlaybackRate(rate) {
        this.playbackRate = rate;
        if (this.videoElement) {
            this.videoElement.playbackRate = rate;
        }
    }
    
    toggleFullscreen() {
        const container = this.container.closest('.video-container-full') || this.container;
        
        if (!document.fullscreenElement && 
            !document.webkitFullscreenElement && 
            !document.mozFullScreenElement && 
            !document.msFullscreenElement) {
            if (container.requestFullscreen) {
                container.requestFullscreen().catch(err => {
                    console.error('Error al entrar en pantalla completa:', err);
                });
            } else if (container.webkitRequestFullscreen) {
                container.webkitRequestFullscreen();
            } else if (container.mozRequestFullScreen) {
                container.mozRequestFullScreen();
            } else if (container.msRequestFullscreen) {
                container.msRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }
    
    retry() {
        if (this.videoElement && this.videoElement.src) {
            this.videoElement.load();
            this.videoElement.play().catch(e => console.error(e));
        }
    }
    
    // Actualización de UI
    updateProgress() {
        if (!this.duration) return;
        
        const percent = (this.currentTime / this.duration) * 100;
        const progressFilled = document.getElementById('progressFilled');
        const currentTimeDisplay = document.getElementById('currentTimeDisplay');
        
        if (progressFilled) {
            progressFilled.style.width = percent + '%';
        }
        
        if (currentTimeDisplay) {
            currentTimeDisplay.textContent = this.formatTime(this.currentTime);
        }
    }
    
    updateBuffer() {
        if (!this.videoElement || !this.duration) return;
        
        const buffered = this.videoElement.buffered;
        if (buffered.length > 0) {
            const bufferedEnd = buffered.end(buffered.length - 1);
            const percent = (bufferedEnd / this.duration) * 100;
            const progressBuffer = document.getElementById('progressBuffer');
            if (progressBuffer) {
                progressBuffer.style.width = percent + '%';
            }
        }
    }
    
    updateDurationDisplay() {
        const durationDisplay = document.getElementById('durationDisplay');
        if (durationDisplay) {
            durationDisplay.textContent = this.formatTime(this.duration);
        }
    }
    
    updatePlayPauseButton() {
        const btn = document.getElementById('playPauseBtn');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = this.isPlaying ? 'fas fa-pause' : 'fas fa-play';
            }
        }
    }
    
    updateVolumeButton() {
        const btn = document.getElementById('volumeBtn');
        const slider = document.getElementById('volumeSlider');
        if (btn && slider) {
            const icon = btn.querySelector('i');
            if (icon) {
                if (this.volume === 0) {
                    icon.className = 'fas fa-volume-mute';
                } else if (this.volume < 0.5) {
                    icon.className = 'fas fa-volume-down';
                } else {
                    icon.className = 'fas fa-volume-up';
                }
            }
            slider.value = this.volume;
        }
    }
    
    showLoading(message = 'Cargando...') {
        const loading = document.getElementById('videoLoading');
        if (loading) {
            loading.style.display = 'flex';
            const p = loading.querySelector('p');
            if (p) p.textContent = message;
        }
    }
    
    hideLoading() {
        const loading = document.getElementById('videoLoading');
        if (loading) {
            loading.style.display = 'none';
        }
    }
    
    showControls() {
        const controls = document.getElementById('customControls');
        if (controls) {
            controls.style.display = 'block';
        }
    }
    
    hideControls() {
        const controls = document.getElementById('customControls');
        if (controls) {
            controls.style.display = 'none';
        }
    }
    
    handleError(error) {
        this.hideLoading();
        const errorOverlay = document.getElementById('videoError');
        const errorMessage = document.getElementById('errorMessage');
        
        if (errorOverlay) {
            errorOverlay.style.display = 'flex';
        }
        
        if (errorMessage) {
            errorMessage.textContent = error.message || 'Error desconocido al cargar el video';
        }
        
        if (this.options.onError) {
            this.options.onError(error);
        }
    }
    
    handleKeyboard(e) {
        if (document.activeElement.tagName === 'INPUT' || 
            document.activeElement.tagName === 'TEXTAREA') {
            return;
        }
        
        switch(e.key) {
            case ' ':
                e.preventDefault();
                this.togglePlayPause();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                this.seek(this.currentTime - 10);
                break;
            case 'ArrowRight':
                e.preventDefault();
                this.seek(this.currentTime + 10);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.setVolume(this.volume + 0.1);
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.setVolume(this.volume - 0.1);
                break;
            case 'f':
            case 'F':
                e.preventDefault();
                this.toggleFullscreen();
                break;
            case 'm':
            case 'M':
                e.preventDefault();
                this.toggleMute();
                break;
        }
    }
    
    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
    
    destroy() {
        if (this.currentTorrent) {
            this.currentTorrent.destroy();
        }
        if (this.youtubePlayer) {
            this.youtubePlayer.destroy();
        }
        if (this.videoElement) {
            this.videoElement.pause();
            this.videoElement.src = '';
        }
    }
}

// Exportar para uso global
window.UnifiedVideoPlayer = UnifiedVideoPlayer;
