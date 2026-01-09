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
                    case 'embed':
                        // Para embeds (vidsrc, upstream, etc.), usar iframe
                        this.loadEmbed(source).then(resolve).catch(reject);
                        return;
                    case 'torrent':
                        // Para torrents, usar WebTorrent
                        // Asegurar que WebTorrent esté inicializado antes de cargar
                        if (!this.torrentClient) {
                            console.log('🔧 Inicializando WebTorrent antes de cargar torrent...');
                            this.initWebTorrent();
                        }
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
            // Limitar velocidad entre 0.25x y 4x
            rate = Math.max(0.25, Math.min(4, rate));
            this.playbackRate = rate;
            
            if (this.videoType === 'youtube' && this.youtubePlayer) {
                this.youtubePlayer.setPlaybackRate(rate);
            } else if (this.videoElement) {
                this.videoElement.playbackRate = rate;
            }
            
            // Callback si está definido
            if (this.options.onPlaybackRateChange) {
                try {
                    this.options.onPlaybackRateChange(rate);
                } catch (e) {
                    console.error('Error en callback onPlaybackRateChange:', e);
                }
            }
        } catch (error) {
            console.error('Error en setPlaybackRate():', error);
            this.handleError(error);
        }
    }
    
    // Obtener velocidad de reproducción actual
    getPlaybackRate() {
        return this.playbackRate;
    }
    
    // Obtener velocidades disponibles
    getAvailablePlaybackRates() {
        return [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 2.5, 3, 3.5, 4];
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
        
        // Verificar si es un embed (vidsrc, upstream, etc.)
        if (url.includes('/embed/') || 
            url.includes('vidsrc.to') || 
            url.includes('vidsrc.cc') || 
            url.includes('embed.smashystream.com') ||
            url.includes('upstream.to') ||
            url.includes('filemoon.sx') ||
            url.includes('streamtape.com') ||
            url.includes('streamwish.to') ||
            url.includes('powvideo.net')) {
            return 'embed';
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
    
    // Carga embeds genéricos (vidsrc, upstream, etc.) usando iframe
    loadEmbed(source) {
        return new Promise((resolve, reject) => {
            try {
                // Limpiar contenedor
                if (this.container) {
                    this.container.innerHTML = '';
                }
                
                // Crear iframe para el embed
                const iframe = document.createElement('iframe');
                iframe.src = source;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                iframe.allow = 'autoplay; encrypted-media; fullscreen';
                iframe.allowFullscreen = true;
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('scrolling', 'no');
                
                // Añadir referrerpolicy para algunos servicios
                if (source.includes('vidsrc') || source.includes('upstream') || source.includes('filemoon')) {
                    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
                }
                
                if (this.container) {
                    this.container.appendChild(iframe);
                }
                
                // Esperar a que el iframe cargue
                iframe.onload = () => {
                    console.log('✅ Embed cargado correctamente');
                    this.videoType = 'embed';
                    this.hideLoading();
                    resolve();
                };
                
                iframe.onerror = (error) => {
                    console.error('❌ Error al cargar el embed:', error);
                    this.handleError(new Error('No se pudo cargar el reproductor embed. Verifica que la URL sea válida.'));
                    reject(error);
                };
                
                // Timeout de seguridad
                setTimeout(() => {
                    if (this.videoType !== 'embed') {
                        console.warn('⚠️ Timeout al cargar embed, pero continuando...');
                        this.videoType = 'embed';
                        this.hideLoading();
                        resolve();
                    }
                }, 5000);
                
            } catch (error) {
                console.error('Error en loadEmbed():', error);
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
                
                try {
                console.log('🔗 Agregando torrent al cliente...');
                console.log('🔍 DEBUG: Llegamos a la línea 441');
                console.log('📋 Cliente WebTorrent:', this.torrentClient);
                    console.log('📋 Source (magnet):', source ? source.substring(0, 100) + '...' : 'null');
                    console.log('📋 Tipo de source:', typeof source);
                    console.log('📋 Source startsWith magnet?:', source ? source.startsWith('magnet:') : false);
                    
                    // Verificar que el cliente esté inicializado
                    if (!this.torrentClient) {
                        const error = new Error('Cliente WebTorrent no está inicializado');
                        console.error('❌', error.message);
                        this.handleError(error);
                        reject(error);
                        return;
                    }
                    
                    // Verificar que el source sea válido
                    if (!source || typeof source !== 'string' || !source.startsWith('magnet:')) {
                        const error = new Error('Enlace magnet no válido: ' + (source || 'null'));
                        console.error('❌', error.message);
                        this.handleError(error);
                        reject(error);
                        return;
                    }
                } catch (validationError) {
                    console.error('❌ Error en validaciones:', validationError);
                    this.handleError(validationError);
                    reject(validationError);
                    return;
                }
                
                // Timeout para detectar si el torrent nunca se agrega
                const addTimeout = setTimeout(() => {
                    console.error('❌ Timeout: El torrent no se agregó después de 30 segundos');
                    console.log('📊 Estado del cliente:', {
                        torrents: this.torrentClient.torrents.length,
                        torrentsList: this.torrentClient.torrents.map(t => ({
                            name: t.name,
                            infoHash: t.infoHash,
                            ready: t.ready,
                            progress: (t.progress * 100).toFixed(2) + '%',
                            numPeers: t.numPeers || 0
                        }))
                    });
                    
                    // Si hay torrents pero no se ejecutó el callback, intentar usar el primero
                    if (this.torrentClient.torrents.length > 0) {
                        console.log('⚠️ Usando torrent existente en el cliente (el callback no se ejecutó)...');
                        const existingTorrent = this.torrentClient.torrents[0];
                        // Verificar que el torrent tenga el mismo infoHash que el source
                        const sourceInfoHash = source.match(/btih:([a-fA-F0-9]{40})/i);
                        if (sourceInfoHash) {
                            const expectedHash = sourceInfoHash[1].toUpperCase();
                            const actualHash = existingTorrent.infoHash ? existingTorrent.infoHash.toUpperCase() : '';
                            console.log('🔍 Comparando hashes:', { expected: expectedHash, actual: actualHash });
                            if (actualHash === expectedHash || actualHash.includes(expectedHash) || expectedHash.includes(actualHash)) {
                                console.log('✅ Hash coincide, usando este torrent');
                                handleTorrent(existingTorrent);
                                return;
                            }
                        }
                        // Si no coincide el hash, usar el primero de todas formas
                        console.log('⚠️ Hash no coincide, pero usando el torrent disponible');
                        handleTorrent(existingTorrent);
                    } else {
                        const error = new Error('El torrent no se pudo agregar. Verifica el enlace magnet y tu conexión.');
                        this.handleError(error);
                        reject(error);
                    }
                }, 30000); // 30 segundos
                
                // Función para manejar el torrent una vez agregado
                const handleTorrent = (torrent) => {
                    clearTimeout(addTimeout);
                    
                    console.log('✅ Torrent agregado:', torrent.name || 'Sin nombre');
                    console.log('📊 Info del torrent:', {
                        name: torrent.name,
                        infoHash: torrent.infoHash,
                        files: torrent.files ? torrent.files.length : 0,
                        length: torrent.length,
                        ready: torrent.ready,
                        progress: (torrent.progress * 100).toFixed(2) + '%',
                        numPeers: torrent.numPeers || 0
                    });
                    
                    this.currentTorrent = torrent;
                    
                    // Monitorear eventos del torrent
                    torrent.on('ready', () => {
                        console.log('✅ Torrent listo, buscando archivos de video...');
                    });
                    
                    torrent.on('download', () => {
                        const progress = (torrent.progress * 100).toFixed(2);
                        const speed = this.formatBytes(torrent.downloadSpeed);
                        const peers = torrent.numPeers || 0;
                        
                        // Log cada 5% o si hay actividad
                        if (parseFloat(progress) % 5 < 0.1 || torrent.downloadSpeed > 0) {
                            console.log(`📥 Descargando: ${progress}% (${speed}/s) - Peers: ${peers}`);
                        }
                        
                        // Actualizar indicador de progreso si existe
                        this.updateTorrentProgress(torrent);
                    });
                    
                    torrent.on('upload', () => {
                        const speed = this.formatBytes(torrent.uploadSpeed);
                        if (parseFloat(speed) > 0) {
                            console.log(`📤 Subiendo: ${speed}/s`);
                        }
                    });
                    
                    torrent.on('done', () => {
                        console.log('✅ Torrent descargado completamente');
                    });
                    
                    torrent.on('error', (err) => {
                        console.error('❌ Error en torrent:', err);
                        this.handleError(err);
                        reject(err);
                    });
                    
                    torrent.on('wire', (wire) => {
                        console.log('🔌 Conectado a peer:', wire.remoteAddress || 'N/A');
                        console.log('📊 Total de peers:', torrent.numPeers);
                    });
                    
                    // Evento cuando no hay peers disponibles
                    torrent.on('noPeers', () => {
                        console.warn('⚠️ No hay peers disponibles. El torrent puede tardar en iniciar.');
                        // Mostrar mensaje al usuario
                        this.showTorrentMessage('Buscando peers... Esto puede tardar unos momentos.');
                    });
                    
                    // Esperar a que el torrent esté listo antes de buscar archivos
                    const checkFiles = () => {
                        if (!torrent.ready) {
                            console.log('⏳ Esperando a que el torrent esté listo...');
                            setTimeout(checkFiles, 500);
                            return;
                        }
                        
                        console.log('📁 Archivos en el torrent:', torrent.files.length);
                        torrent.files.forEach((file, index) => {
                            console.log(`  ${index + 1}. ${file.name} (${this.formatBytes(file.length)})`);
                        });
                        
                        // Buscar el archivo de video más grande
                        const videoFile = torrent.files.find(file => {
                            const name = file.name.toLowerCase();
                            return name.endsWith('.mp4') || name.endsWith('.webm') || 
                                   name.endsWith('.mkv') || name.endsWith('.avi') ||
                                   name.endsWith('.m4v');
                        }) || torrent.files[0];
                        
                        if (!videoFile) {
                            const error = new Error('No se encontró ningún archivo de video en el torrent');
                            console.error('❌', error.message);
                            this.handleError(error);
                            reject(error);
                            return;
                        }
                        
                        console.log('🎬 Archivo de video seleccionado:', videoFile.name);
                        console.log('📏 Tamaño:', this.formatBytes(videoFile.length));
                        
                        // Renderizar el video inmediatamente si el torrent está listo
                        // WebTorrent puede reproducir mientras descarga (streaming)
                        const renderVideo = () => {
                            // Prevenir renderizado múltiple
                            if (this._isRenderingTorrent) {
                                console.warn('⚠️ Ya se está renderizando un torrent, omitiendo...');
                                return;
                            }
                            
                            this._isRenderingTorrent = true;
                            console.log('🎥 Renderizando video...');
                            
                            // Asegurar que el elemento de video existe y está visible
                            if (!this.videoElement) {
                                this._isRenderingTorrent = false;
                                console.error('❌ Elemento de video no encontrado');
                                const error = new Error('Elemento de video no encontrado');
                                this.handleError(error);
                                reject(error);
                                return;
                            }
                            
                            // Limpiar cualquier renderizado anterior del mismo archivo
                            try {
                                if (this.videoElement.srcObject) {
                                    const mediaSource = this.videoElement.srcObject;
                                    if (mediaSource.readyState === 'open') {
                                        mediaSource.endOfStream();
                                    }
                                    this.videoElement.srcObject = null;
                                }
                                if (this.videoElement.src && this.videoElement.src.startsWith('blob:')) {
                                    URL.revokeObjectURL(this.videoElement.src);
                                }
                                this.videoElement.src = '';
                                this.videoElement.load();
                            } catch (cleanError) {
                                console.warn('Advertencia al limpiar video anterior:', cleanError);
                            }
                            
                            // Mostrar el elemento de video
                            this.videoElement.style.display = 'block';
                            this.videoElement.style.width = '100%';
                            this.videoElement.style.height = '100%';
                            
                            // Configurar el elemento de video para streaming
                            this.videoElement.setAttribute('playsinline', '');
                            this.videoElement.setAttribute('webkit-playsinline', '');
                            
                            // Renderizar el archivo de video en el elemento
                            // Usar una marca para evitar renderizado múltiple
                            let renderCompleted = false;
                            
                            try {
                                videoFile.renderTo(this.videoElement, {
                                    autoplay: this.options.autoplay !== false,
                                    controls: this.options.controls !== false
                                }, (err) => {
                                    if (renderCompleted) {
                                        console.warn('⚠️ Callback de renderizado duplicado, ignorando...');
                                        return;
                                    }
                                    renderCompleted = true;
                                    this._isRenderingTorrent = false;
                                    
                                    if (err) {
                                        console.error('❌ Error al renderizar el video:', err);
                                        this.handleError(err);
                                        reject(err);
                                    } else {
                                        console.log('✅ Video renderizado correctamente');
                                        
                                        // Configurar eventos del video
                                        this.setupTorrentVideoEvents();
                                        
                                        // Actualizar UI
                                        this.hideLoading();
                                        this.videoType = 'torrent';
                                        
                                        // Mostrar información del torrent
                                        this.showTorrentInfo(torrent, videoFile);
                                        
                                        // Intentar reproducir
                                        if (this.videoElement) {
                                            this.videoElement.play().then(() => {
                                                console.log('▶️ Reproducción iniciada');
                                                this.isPlaying = true;
                                                if (this.options.onPlay) {
                                                    try { this.options.onPlay(); } catch (e) { console.error(e); }
                                                }
                                            }).catch(e => {
                                                console.warn('⚠️ No se pudo reproducir automáticamente:', e);
                                                // Mostrar botón de play manual
                                                this.showPlayButton();
                                            });
                                        }
                                        
                                        resolve();
                                    }
                                });
                            } catch (renderError) {
                                this._isRenderingTorrent = false;
                                console.error('❌ Error al intentar renderizar:', renderError);
                                this.handleError(renderError);
                                reject(renderError);
                            }
                        };
                        
                        // Si el torrent ya está listo, renderizar inmediatamente
                        if (torrent.ready) {
                            renderVideo();
                        } else {
                            // Esperar a que esté listo (máximo 30 segundos)
                            let waitCount = 0;
                            const maxWait = 60; // 30 segundos (60 * 500ms)
                            
                            const waitForReady = () => {
                                if (torrent.ready) {
                                    renderVideo();
                                } else if (waitCount < maxWait) {
                                    waitCount++;
                                    setTimeout(waitForReady, 500);
                                } else {
                                    // Timeout: intentar renderizar de todas formas
                                    console.warn('⚠️ Timeout esperando torrent ready, intentando renderizar de todas formas...');
                                    renderVideo();
                                }
                            };
                            
                            waitForReady();
                        }
                    };
                    
                    // Iniciar verificación de archivos
                    checkFiles();
                };
                
                // Agregar torrent al cliente
                try {
                    console.log('🔄 Llamando a torrentClient.add()...');
                    console.log('📋 Torrents actuales en el cliente:', this.torrentClient.torrents.length);
                    
                    // Verificar si el torrent ya existe en el cliente (por infoHash)
                    const sourceInfoHash = source.match(/btih:([a-fA-F0-9]{40})/i);
                    if (sourceInfoHash) {
                        const expectedHash = sourceInfoHash[1].toUpperCase();
                        console.log('🔍 Buscando torrent con hash:', expectedHash);
                        
                        const existingTorrent = this.torrentClient.torrents.find(t => {
                            if (!t.infoHash) return false;
                            const torrentHash = t.infoHash.toUpperCase();
                            return torrentHash === expectedHash || torrentHash.includes(expectedHash) || expectedHash.includes(torrentHash);
                        });
                        
                        if (existingTorrent) {
                            console.log('✅ Torrent ya existe en el cliente, usando el existente');
                            clearTimeout(addTimeout);
                            handleTorrent(existingTorrent);
                            return;
                        }
                    }
                    
                    // Agregar el torrent
                    const torrent = this.torrentClient.add(source, handleTorrent);
                    console.log('✅ torrentClient.add() llamado, esperando callback...');
                    console.log('📋 Torrent objeto retornado:', torrent);
                    console.log('📋 Torrents después de add:', this.torrentClient.torrents.length);
                    
                    // Si el torrent se agregó inmediatamente pero el callback no se ejecutó, esperar un poco y verificar
                    setTimeout(() => {
                        if (this.torrentClient.torrents.length > 0 && !this.currentTorrent) {
                            const addedTorrent = this.torrentClient.torrents[this.torrentClient.torrents.length - 1];
                            console.log('⚠️ Torrent agregado pero callback no ejecutado después de 2s, usando torrent directamente');
                            console.log('📋 Torrent encontrado:', {
                                name: addedTorrent.name,
                                infoHash: addedTorrent.infoHash,
                                ready: addedTorrent.ready
                            });
                            handleTorrent(addedTorrent);
                        }
                    }, 2000); // Esperar 2 segundos
                    
                    // También escuchar errores del cliente
                    this.torrentClient.on('error', (err) => {
                        clearTimeout(addTimeout);
                        console.error('❌ Error en cliente WebTorrent:', err);
                        this.handleError(err);
                        reject(err);
                    });
                    
                } catch (addError) {
                    clearTimeout(addTimeout);
                    console.error('❌ Error al llamar torrentClient.add():', addError);
                    this.handleError(addError);
                    reject(addError);
                }
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

    // Atajos de teclado mejorados
    handleKeyboard(event) {
        if (!event) return;
        const key = event.key?.toLowerCase();
        const code = event.code;

        // Si el foco está en un input/textarea, no interceptar (excepto si es Escape)
        const tag = (event.target && event.target.tagName) ? event.target.tagName.toLowerCase() : '';
        if (['input', 'textarea', 'select'].includes(tag) && key !== 'escape') return;

        // No interceptar si se está escribiendo en un input
        if (tag === 'input' && event.target.type === 'text' && key !== 'escape') return;

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
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                // Ir al porcentaje del video (0 = inicio, 9 = 90%)
                if (this.duration) {
                    event.preventDefault();
                    const percentage = parseInt(key) * 10;
                    this.seek((this.duration * percentage) / 100);
                }
                break;
            case '>':
            case '.':
                // Aumentar velocidad
                event.preventDefault();
                this.increasePlaybackRate();
                break;
            case '<':
            case ',':
                // Disminuir velocidad
                event.preventDefault();
                this.decreasePlaybackRate();
                break;
            case '=':
            case '+':
                // Resetear velocidad a 1x
                event.preventDefault();
                this.setPlaybackRate(1);
                break;
            case 'escape':
                // Salir de pantalla completa
                if (this.isFullscreen) {
                    event.preventDefault();
                    this.toggleFullscreen();
                }
                break;
            default:
                break;
        }
    }
    
    // Aumentar velocidad de reproducción
    increasePlaybackRate() {
        const rates = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
        const currentIndex = rates.indexOf(this.playbackRate);
        const nextIndex = Math.min(currentIndex + 1, rates.length - 1);
        this.setPlaybackRate(rates[nextIndex]);
        this.showPlaybackRateNotification();
    }
    
    // Disminuir velocidad de reproducción
    decreasePlaybackRate() {
        const rates = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
        const currentIndex = rates.indexOf(this.playbackRate);
        const prevIndex = Math.max(currentIndex - 1, 0);
        this.setPlaybackRate(rates[prevIndex]);
        this.showPlaybackRateNotification();
    }
    
    // Mostrar notificación de velocidad
    showPlaybackRateNotification() {
        if (!this.container) return;
        
        let notification = this.container.querySelector('.playback-rate-notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.className = 'playback-rate-notification';
            notification.style.cssText = `
                position: absolute;
                top: 20px;
                right: 20px;
                background: rgba(0, 0, 0, 0.8);
                color: #fff;
                padding: 1rem 1.5rem;
                border-radius: 4px;
                font-size: 1.2rem;
                font-weight: bold;
                z-index: 1000;
                pointer-events: none;
                transition: opacity 0.3s;
            `;
            this.container.appendChild(notification);
        }
        
        notification.textContent = `${this.playbackRate}x`;
        notification.style.opacity = '1';
        
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 2000);
    }

    // Inicializa cliente WebTorrent de forma segura
    initWebTorrent() {
        try {
            if (typeof WebTorrent === 'undefined') {
                console.warn('⚠️ WebTorrent no está disponible aún');
                return;
            }
            
            if (this.torrentClient) {
                console.log('ℹ️ Cliente WebTorrent ya existe');
                return;
            }
            
            console.log('🔧 Inicializando cliente WebTorrent...');
            this.torrentClient = new WebTorrent();
            console.log('✅ Cliente WebTorrent creado:', this.torrentClient);
            
            // Hacer disponible globalmente para el debugger
            window.webtorrentClient = this.torrentClient;
            window.client = this.torrentClient;
        } catch (e) {
            console.error('❌ No se pudo inicializar WebTorrent:', e);
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
    
    // Función auxiliar para formatear bytes
    formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    // Actualizar indicador de progreso del torrent
    updateTorrentProgress(torrent) {
        if (!torrent || !this.container) return;
        
        const progress = (torrent.progress * 100).toFixed(1);
        const loadingElement = this.container.querySelector('#videoLoading');
        
        if (loadingElement) {
            const progressText = loadingElement.querySelector('p');
            if (progressText) {
                const speed = this.formatBytes(torrent.downloadSpeed);
                const peers = torrent.numPeers || 0;
                progressText.textContent = `Cargando video... ${progress}% (${speed}/s) - ${peers} peers`;
            }
        }
    }
    
    // Mostrar mensaje del torrent
    showTorrentMessage(message) {
        if (!this.container) return;
        
        let messageDiv = this.container.querySelector('.torrent-message');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.className = 'torrent-message';
            messageDiv.style.cssText = `
                position: absolute;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: #fff;
                padding: 10px 20px;
                border-radius: 5px;
                font-size: 14px;
                z-index: 1000;
                max-width: 80%;
                text-align: center;
            `;
            this.container.appendChild(messageDiv);
        }
        
        messageDiv.textContent = message;
        messageDiv.style.display = 'block';
        
        // Ocultar después de 5 segundos
        setTimeout(() => {
            if (messageDiv) {
                messageDiv.style.opacity = '0';
                messageDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (messageDiv) messageDiv.style.display = 'none';
                }, 500);
            }
        }, 5000);
    }
    
    // Configurar eventos específicos para video de torrent
    setupTorrentVideoEvents() {
        if (!this.videoElement) return;
        
        // Evento de progreso de carga
        this.videoElement.addEventListener('progress', () => {
            if (this.videoElement.buffered.length > 0) {
                const bufferedEnd = this.videoElement.buffered.end(this.videoElement.buffered.length - 1);
                const duration = this.videoElement.duration || 0;
                if (duration > 0) {
                    this.buffered = (bufferedEnd / duration) * 100;
                }
            }
        });
        
        // Evento de tiempo actualizado
        this.videoElement.addEventListener('timeupdate', () => {
            this.currentTime = this.videoElement.currentTime;
            this.duration = this.videoElement.duration || 0;
            
            // Callback de progreso si está configurado
            if (this.options.onProgress && this.duration > 0) {
                try {
                    this.options.onProgress(this.currentTime, this.duration);
                } catch (e) {
                    console.error('Error en callback onProgress:', e);
                }
            }
        });
        
        // Evento de reproducción
        this.videoElement.addEventListener('play', () => {
            this.isPlaying = true;
            if (this.options.onPlay) {
                try { this.options.onPlay(); } catch (e) { console.error(e); }
            }
        });
        
        // Evento de pausa
        this.videoElement.addEventListener('pause', () => {
            this.isPlaying = false;
            if (this.options.onPause) {
                try { this.options.onPause(); } catch (e) { console.error(e); }
            }
        });
        
        // Evento de finalización
        this.videoElement.addEventListener('ended', () => {
            this.isPlaying = false;
            if (this.options.onEnded) {
                try { this.options.onEnded(); } catch (e) { console.error(e); }
            }
        });
        
        // Evento de error
        this.videoElement.addEventListener('error', (e) => {
            console.error('Error en elemento de video:', e);
            if (this.options.onError) {
                try { this.options.onError(e); } catch (err) { console.error(err); }
            }
            this.handleError(e);
        });
        
        // Evento de volumen
        this.videoElement.addEventListener('volumechange', () => {
            this.volume = this.videoElement.volume;
            this.isMuted = this.videoElement.muted;
            if (this.options.onVolumeChange) {
                try { this.options.onVolumeChange(this.volume); } catch (e) { console.error(e); }
            }
        });
        
        // Evento de waiting (buffering)
        this.videoElement.addEventListener('waiting', () => {
            console.log('⏳ Video buffering...');
            this.showTorrentMessage('Buffering... Por favor espera');
        });
        
        // Evento de canplay (listo para reproducir)
        this.videoElement.addEventListener('canplay', () => {
            console.log('✅ Video listo para reproducir');
            const messageDiv = this.container.querySelector('.torrent-message');
            if (messageDiv) messageDiv.style.display = 'none';
        });
    }
    
    // Mostrar información del torrent
    showTorrentInfo(torrent, videoFile) {
        if (!this.container) return;
        
        // Crear o actualizar indicador de información del torrent
        let infoDiv = this.container.querySelector('.torrent-info-overlay');
        if (!infoDiv) {
            infoDiv = document.createElement('div');
            infoDiv.className = 'torrent-info-overlay';
            infoDiv.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(0, 0, 0, 0.8);
                color: #fff;
                padding: 10px 15px;
                border-radius: 5px;
                font-size: 12px;
                z-index: 1000;
                max-width: 300px;
            `;
            this.container.appendChild(infoDiv);
        }
        
        const updateInfo = () => {
            if (!torrent || !infoDiv) return;
            
            const progress = (torrent.progress * 100).toFixed(1);
            const downloadSpeed = this.formatBytes(torrent.downloadSpeed);
            const uploadSpeed = this.formatBytes(torrent.uploadSpeed);
            const peers = torrent.numPeers || 0;
            const ready = torrent.ready ? '✅' : '⏳';
            
            infoDiv.innerHTML = `
                <div style="margin-bottom: 5px;"><strong>${ready} ${torrent.name || 'Torrent'}</strong></div>
                <div>Progreso: ${progress}%</div>
                <div>Descarga: ${downloadSpeed}/s</div>
                <div>Subida: ${uploadSpeed}/s</div>
                <div>Peers: ${peers}</div>
                <div style="margin-top: 5px; font-size: 10px; opacity: 0.8;">${videoFile.name}</div>
            `;
        };
        
        // Actualizar información cada segundo
        updateInfo();
        const infoInterval = setInterval(() => {
            if (torrent && !torrent.destroyed) {
                updateInfo();
            } else {
                clearInterval(infoInterval);
                if (infoDiv) infoDiv.remove();
            }
        }, 1000);
        
        // Ocultar después de 10 segundos si está reproduciendo
        setTimeout(() => {
            if (infoDiv && this.isPlaying) {
                infoDiv.style.opacity = '0';
                infoDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (infoDiv) infoDiv.style.display = 'none';
                }, 500);
            }
        }, 10000);
    }
    
    // Mostrar botón de play manual
    showPlayButton() {
        if (!this.container) return;
        
        let playBtn = this.container.querySelector('.torrent-play-button');
        if (!playBtn) {
            playBtn = document.createElement('button');
            playBtn.className = 'torrent-play-button';
            playBtn.innerHTML = '<i class="fas fa-play"></i>';
            playBtn.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: rgba(229, 9, 20, 0.9);
                color: #fff;
                border: none;
                font-size: 30px;
                cursor: pointer;
                z-index: 1001;
                transition: all 0.3s;
            `;
            playBtn.addEventListener('click', () => {
                if (this.videoElement) {
                    this.videoElement.play().then(() => {
                        playBtn.remove();
                    }).catch(e => {
                        console.error('Error al reproducir:', e);
                    });
                }
            });
            playBtn.addEventListener('mouseenter', () => {
                playBtn.style.background = 'rgba(229, 9, 20, 1)';
                playBtn.style.transform = 'translate(-50%, -50%) scale(1.1)';
            });
            playBtn.addEventListener('mouseleave', () => {
                playBtn.style.background = 'rgba(229, 9, 20, 0.9)';
                playBtn.style.transform = 'translate(-50%, -50%) scale(1)';
            });
            this.container.appendChild(playBtn);
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
