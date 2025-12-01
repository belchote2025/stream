// Configuración del reproductor de video unificado
const PlayerConfig = {
    // Rutas de los elementos del reproductor
    selectors: {
        container: '#videoPlayer',
        loading: '#videoLoading',
        error: '#videoError',
        controls: '.video-controls',
        playPauseBtn: '#playPauseBtn',
        progressBar: '#progressBar',
        volumeBtn: '#volumeBtn',
        volumeSlider: '#volumeSlider',
        currentTime: '#currentTime',
        duration: '#duration',
        fullscreenBtn: '#fullscreenBtn',
        qualitySelector: '#qualitySelector',
        settingsBtn: '#settingsBtn',
        settingsMenu: '#settingsMenu',
        playbackSpeed: '#playbackSpeed',
        youtubeContainer: '#youtubePlayerContainer',
        torrentContainer: '#torrentPlayerContainer'
    },
    
    // Opciones por defecto
    options: {
        autoplay: false,
        controls: true,
        preload: 'metadata',
        startTime: 0,
        volume: 1,
        playbackRate: 1,
        youtube: {
            autoplay: 0,
            controls: 1,
            rel: 0,
            modestbranding: 1,
            playsinline: 1
        },
        webtorrent: {
            maxConns: 55,
            nodeId: 'streaming-platform-' + Math.random().toString(36).substring(2, 15)
        }
    },
    
    // Mensajes de error
    messages: {
        invalidSource: 'La fuente de video no es válida o no se puede reproducir',
        youtubeError: 'Error al cargar el video de YouTube',
        torrentError: 'Error al cargar el torrent',
        networkError: 'Error de red. Verifica tu conexión e inténtalo de nuevo',
        unsupportedFormat: 'Formato de video no compatible',
        loadError: 'Error al cargar el video'
    },
    
    // Formatos de video soportados
    supportedFormats: [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/x-matroska',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-ms-wmv'
    ]
};

// Exportar para uso global
window.PlayerConfig = PlayerConfig;
