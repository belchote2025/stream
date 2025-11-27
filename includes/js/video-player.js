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
        
        // Mostrar/ocultar controles (solo en desktop)
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
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
        } else {
            // En dispositivos táctiles, mantener controles visibles
            this.showControls();
        }
        
        // Toggle controles al tocar en móviles
        if (window.matchMedia('(hover: none) and (pointer: coarse)').matches) {
            let touchTimeout;
            this.container.addEventListener('touchstart', () => {
                clearTimeout(touchTimeout);
                this.showControls();
                touchTimeout = setTimeout(() => {
                    if (this.isPlaying) {
                        this.hideControls();
                    }
                }, 3000);
            });
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
            document.getElementById('youtubePlayerContainer').style.display = 'none';
            document.getElementById('torrentPlayerContainer').style.display = 'none';
            
            // Mostrar video HTML5
            this.videoElement.style.display = 'block';
            
            // Configurar fuente
            this.videoElement.src = url;
            
            // Si es una URL local, asegurar que sea absoluta
            if (url.startsWith('/')) {
                this.videoElement.src = window.location.origin + url;
            }
            
            // Restaurar tiempo de inicio si existe
            if (this.options.startTime > 0) {
                this.videoElement.addEventListener('loadedmetadata', () => {
                    this.videoElement.currentTime = this.options.startTime;
                }, { once: true });
            }
            
            this.videoElement.addEventListener('canplay', () => {
                this.hideLoading();
                resolve();
            }, { once: true });
            
            this.videoElement.addEventListener('error', (e) => {
                reject(new Error('Error al cargar el video: ' + (e.message || 'Error desconocido')));
            }, { once: true });
            
            this.videoElement.load();
        });
    }
    
    async loadYouTube(url) {
        return new Promise((resolve, reject) => {
            // Extraer ID de YouTube
            let videoId = null;
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
                /youtube\.com\/watch\?.*v=([^&\n?#]+)/
            ];
            
            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) {
                    videoId = match[1];
                    break;
                }
            }
            
            if (!videoId) {
                reject(new Error('ID de video de YouTube no válido'));
                return;
            }
            
            // Ocultar otros reproductores
            this.videoElement.style.display = 'none';
            document.getElementById('torrentPlayerContainer').style.display = 'none';
            
            const container = document.getElementById('youtubePlayerContainer');
            container.style.display = 'block';
            container.innerHTML = `<div id="youtubePlayer"></div>`;
            
            // Cargar API de YouTube si no está cargada
            if (!window.YT || !window.YT.Player) {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                
                window.onYouTubeIframeAPIReady = () => {
                    this.initYouTubePlayer(videoId, resolve, reject);
                };
            } else {
                this.initYouTubePlayer(videoId, resolve, reject);
            }
        });
    }
    
    initYouTubePlayer(videoId, resolve, reject) {
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
                    reject(new Error('Error al cargar video de YouTube'));
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
    }
    
    async loadTorrent(url) {
        if (!this.torrentClient) {
            throw new Error('WebTorrent no está disponible');
        }
        
        return new Promise((resolve, reject) => {
            // Ocultar otros reproductores
            this.videoElement.style.display = 'none';
            document.getElementById('youtubePlayerContainer').style.display = 'none';
            
            const container = document.getElementById('torrentPlayerContainer');
            container.style.display = 'block';
            container.innerHTML = '<video id="torrentVideo" controls style="width: 100%; height: 100%;"></video>';
            
            const torrentVideo = document.getElementById('torrentVideo');
            
            // Detener torrent anterior
            if (this.currentTorrent) {
                this.currentTorrent.destroy();
            }
            
            this.showLoading('Conectando a la red P2P...');
            
            this.currentTorrent = this.torrentClient.add(url, (torrent) => {
                const file = torrent.files.find(f => 
                    f.name.endsWith('.mp4') || 
                    f.name.endsWith('.webm') || 
                    f.name.endsWith('.mkv')
                );
                
                if (!file) {
                    reject(new Error('No se encontró archivo de video en el torrent'));
                    return;
                }
                
                file.renderTo(torrentVideo, {
                    autoplay: this.options.autoplay
                }, (err, element) => {
                    if (err) {
                        reject(err);
                        return;
                    }
                    
                    this.videoElement = element;
                    this.hideLoading();
                    resolve();
                });
            });
            
            this.currentTorrent.on('error', (err) => {
                reject(err);
            });
        });
    }
    
    initWebTorrent() {
        if (typeof WebTorrent !== 'undefined') {
            this.torrentClient = new WebTorrent();
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
