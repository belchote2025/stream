<?php
/**
 * Página de reproducción de video
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

// Cargar configuración del reproductor
$playerConfig = [
    'autoplay' => true,
    'controls' => true,
    'preload' => 'metadata',
    'startTime' => 0
];

$contentId = $_GET['id'] ?? 0;
$episodeId = $_GET['episode_id'] ?? null;
$contentId = (int)$contentId;

if (!$contentId) {
    header('Location: /');
    exit;
}

$db = getDbConnection();

// Obtener información del contenido
$query = "SELECT * FROM content WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
$stmt->execute();
$content = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$content) {
    header('Location: /');
    exit;
}

// Si es serie y hay episode_id, obtener información del episodio
$episode = null;
if ($content['type'] === 'series' && $episodeId) {
    $episodeQuery = "SELECT * FROM episodes WHERE id = :id AND series_id = :series_id";
    $episodeStmt = $db->prepare($episodeQuery);
    $episodeStmt->bindValue(':id', $episodeId, PDO::PARAM_INT);
    $episodeStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
    $episodeStmt->execute();
    $episode = $episodeStmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener progreso guardado si el usuario está logueado
$savedProgress = null;
if (isLoggedIn() && $content) {
    $progressQuery = "
        SELECT progress, duration 
        FROM playback_history 
        WHERE user_id = :user_id AND content_id = :content_id
        " . ($episodeId ? "AND episode_id = :episode_id" : "AND episode_id IS NULL");
    
    $progressStmt = $db->prepare($progressQuery);
    $progressStmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $progressStmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
    if ($episodeId) {
        $progressStmt->bindValue(':episode_id', $episodeId, PDO::PARAM_INT);
    }
    $progressStmt->execute();
    $savedProgress = $progressStmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Reproducir: ' . htmlspecialchars($content['title']) . ' - ' . SITE_NAME;

include __DIR__ . '/includes/header.php';
$baseUrl = rtrim(SITE_URL, '/');
?>

<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/unified-video-player.css">

<style>
.watch-page {
    padding-top: 70px;
    min-height: 100vh;
    background: #000;
}

.video-container-full {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%; /* 16:9 */
    background: #000;
    min-height: 300px;
}

.video-container-full #unifiedVideoContainer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.video-container-full video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.video-info {
    padding: 2rem 4%;
    max-width: 1400px;
    margin: 0 auto;
}

.video-title-section {
    margin-bottom: 2rem;
}

.video-title-section h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.video-meta {
    display: flex;
    gap: 1rem;
    color: #999;
    font-size: 0.9rem;
}

.episode-selector {
    margin-top: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.episode-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.episode-item {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.episode-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.episode-item.active {
    background: rgba(229, 9, 20, 0.2);
    border: 1px solid #e50914;
}

.video-error, .no-video-message {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.error-content, .no-video-content {
    text-align: center;
    color: #fff;
    padding: 2rem;
    max-width: 500px;
}

.error-content i, .no-video-content i {
    font-size: 4rem;
    color: #e50914;
    margin-bottom: 1rem;
}

.error-content h3, .no-video-content h3 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.error-content p, .no-video-content p {
    color: #999;
    margin-bottom: 2rem;
}

.error-content .btn, .no-video-content .btn {
    margin: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.error-content .btn-primary {
    background: #e50914;
    color: #fff;
    border: none;
}

.error-content .btn-primary:hover {
    background: #f40612;
}

.no-video-content .btn-outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: #fff;
}

.no-video-content .btn-outline:hover {
    border-color: #e50914;
    color: #e50914;
}

.video-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    z-index: 5;
}

.video-loading i {
    font-size: 3rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ============================================
   RESPONSIVE DESIGN PARA REPRODUCTOR
   ============================================ */
@media (max-width: 768px) {
    .watch-page {
        padding-top: 56px;
    }
    
    .video-container-full {
        padding-bottom: 56.25%;
    }
    
    .video-container-full video {
        object-fit: contain;
    }
    
    .video-info {
        padding: 1.5rem 1rem;
    }
    
    .video-title-section h1 {
        font-size: 1.5rem;
    }
    
    .video-meta {
        font-size: 0.85rem;
        flex-wrap: wrap;
    }
    
    .episode-selector {
        padding: 1rem;
    }
    
    .episode-list {
        grid-template-columns: 1fr;
    }
    
    .error-content,
    .no-video-content {
        padding: 1.5rem;
        max-width: 90%;
    }
    
    .error-content i,
    .no-video-content i {
        font-size: 3rem;
    }
    
    .error-content h3,
    .no-video-content h3 {
        font-size: 1.25rem;
    }
}

@media (max-width: 576px) {
    .watch-page {
        padding-top: 52px;
    }
    
    .video-container-full {
        padding-bottom: 56.25%;
    }
    
    .video-container-full video {
        object-fit: contain;
    }
    
    .video-info {
        padding: 1rem 0.75rem;
    }
    
    .video-title-section h1 {
        font-size: 1.25rem;
    }
    
    .episode-item {
        padding: 0.75rem;
    }
    
    .error-content,
    .no-video-content {
        padding: 1rem;
        max-width: 95%;
    }
    
    .error-content i,
    .no-video-content i {
        font-size: 2.5rem;
    }
    
    .error-content h3,
    .no-video-content h3 {
        font-size: 1.1rem;
    }
    
    .error-content p,
    .no-video-content p {
        font-size: 0.9rem;
    }
}

/* Orientación horizontal en móviles */
@media (max-width: 992px) and (orientation: landscape) {
    .watch-page {
        padding-top: 0;
    }
    
    .video-container-full {
        padding-bottom: 0;
        height: 100vh;
        max-height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1000;
    }
    
    .video-container-full #unifiedVideoContainer {
        height: 100%;
    }
    
    .video-info {
        padding: 1rem 2%;
        margin-top: 100vh;
    }
}

/* Ajustes para pantalla completa */
.video-container-full:fullscreen,
.video-container-full:-webkit-full-screen,
.video-container-full:-moz-full-screen,
.video-container-full:-ms-fullscreen {
    padding-bottom: 0;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}
</style>

<div class="watch-page">
    <div id="videoPlayer" class="video-player">
    <!-- Contenedor principal del reproductor -->
    <div class="video-container">
        <!-- Contenedor para YouTube -->
        <div id="youtubePlayerContainer" style="display: none;"></div>
        
        <!-- Contenedor para WebTorrent -->
        <div id="torrentPlayerContainer" style="display: none;">
            <video id="torrentPlayer" controls></video>
        </div>
        
        <!-- Reproductor HTML5 nativo -->
        <video id="html5Player" style="display: none;" controls></video>
        
        <!-- Indicador de carga -->
        <div id="videoLoading" class="loading-indicator" style="display: none;">
            <div class="spinner"></div>
            <p>Cargando video...</p>
        </div>
        
        <!-- Mensaje de error -->
        <div id="videoError" class="error-message" style="display: none;">
            <i class="fas fa-exclamation-triangle"></i>
            <p id="errorMessage">Error al cargar el video</p>
        </div>
        
        <!-- Controles personalizados -->
        <div class="video-controls">
            <!-- Barra de progreso -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="buffer"></div>
                    <div class="progress"></div>
                    <div class="hover-time">
                        <div class="hover-time-text">0:00</div>
                        <div class="hover-thumbnail"></div>
                    </div>
                </div>
                <div class="time-display">
                    <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                </div>
            </div>
            
            <!-- Controles inferiores -->
            <div class="controls-bottom">
                <div class="controls-left">
                    <button id="playPauseBtn" class="control-btn" title="Reproducir/Pausar">
                        <i class="fas fa-play"></i>
                    </button>
                    <div class="volume-control">
                        <button id="volumeBtn" class="control-btn" title="Silenciar/Activar sonido">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <div class="volume-slider">
                            <div class="slider-track">
                                <div class="slider-fill" style="width: 100%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="time-display">
                        <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                    </div>
                </div>
                
                <div class="controls-right">
                    <div class="settings-menu">
                        <button id="settingsBtn" class="control-btn" title="Ajustes">
                            <i class="fas fa-cog"></i>
                        </button>
                        <div id="settingsMenu" class="settings-dropdown">
                            <div class="settings-section">
                                <label>Velocidad de reproducción</label>
                                <select id="playbackSpeed">
                                    <option value="0.5">0.5x</option>
                                    <option value="0.75">0.75x</option>
                                    <option value="1" selected>Normal</option>
                                    <option value="1.25">1.25x</option>
                                    <option value="1.5">1.5x</option>
                                    <option value="2">2x</option>
                                </select>
                            </div>
                            <div class="settings-section">
                                <label>Calidad</label>
                                <select id="qualitySelector">
                                    <option value="auto" selected>Auto</option>
                                    <option value="1080">1080p</option>
                                    <option value="720">720p</option>
                                    <option value="480">480p</option>
                                    <option value="360">360p</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button id="fullscreenBtn" class="control-btn" title="Pantalla completa">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contenido adicional -->
<div class="content-details">
    <h1><?php echo htmlspecialchars($content['title']); ?></h1>
    <?php if ($content['type'] === 'series' && $episode): ?>
        <h2>Capítulo <?php echo $episode['episode_number'] . ': ' . htmlspecialchars($episode['title']); ?></h2>
    <?php endif; ?>
    
    <div class="content-meta">
        <?php if ($content['release_year']): ?>
            <span class="meta-item"><?php echo $content['release_year']; ?></span>
        <?php endif; ?>
        
        <?php if ($content['duration']): ?>
            <span class="meta-item"><?php echo formatDuration($content['duration']); ?></span>
        <?php endif; ?>
        
        <?php if ($content['rating']): ?>
            <span class="meta-item">
                <i class="fas fa-star"></i> <?php echo number_format($content['rating'], 1); ?>/10
            </span>
        <?php endif; ?>
    </div>
    
    <div class="content-description">
        <p><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
    </div>
    
    <?php if ($content['type'] === 'series'): ?>
        <div class="episodes-container">
            <h3>Temporadas y Episodios</h3>
            <?php
            // Obtener todas las temporadas
            $seasonsQuery = "SELECT DISTINCT season_number FROM episodes 
                            WHERE series_id = :series_id 
                            ORDER BY season_number ASC";
            $seasonsStmt = $db->prepare($seasonsQuery);
            $seasonsStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
            $seasonsStmt->execute();
            $seasons = $seasonsStmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($seasons as $season):
                // Obtener episodios de esta temporada
                $episodesQuery = "SELECT * FROM episodes 
                                WHERE series_id = :series_id AND season_number = :season_number 
                                ORDER BY episode_number ASC";
                $episodesStmt = $db->prepare($episodesQuery);
                $episodesStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
                $episodesStmt->bindValue(':season_number', $season, PDO::PARAM_INT);
                $episodesStmt->execute();
                $episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($episodes) > 0):
            ?>
                    <div class="season">
                        <h4>Temporada <?php echo $season; ?></h4>
                        <div class="episodes-list">
                            <?php foreach ($episodes as $ep): ?>
                                <a href="watch.php?id=<?php echo $contentId; ?>&episode_id=<?php echo $ep['id']; ?>" 
                                   class="episode-card <?php echo ($episodeId == $ep['id']) ? 'active' : ''; ?>">
                                    <div class="episode-number"><?php echo $ep['episode_number']; ?></div>
                                    <div class="episode-info">
                                        <div class="episode-title"><?php echo htmlspecialchars($ep['title']); ?></div>
                                        <div class="episode-meta">
                                            <?php if ($ep['duration']): ?>
                                                <span><?php echo formatDuration($ep['duration']); ?></span>
                                            <?php endif; ?>
                                            <?php if ($ep['release_date']): ?>
                                                <span><?php echo date('d M, Y', strtotime($ep['release_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($episodeId == $ep['id']): ?>
                                        <div class="now-playing">
                                            <i class="fas fa-play"></i> Reproduciendo
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                endif;
            endforeach;
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Scripts del reproductor -->
<script src="<?php echo $baseUrl; ?>/js/player/config.js"></script>
<script src="<?php echo $baseUrl; ?>/js/player/main.js"></script>
<script src="<?php echo $baseUrl; ?>/js/player/init.js"></script>

<script>
// Inicializar el reproductor cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Obtener la URL del video desde PHP
    const videoUrl = <?php 
        if ($content['type'] === 'series' && $episode) {
            echo json_encode($episode['video_url'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } else {
            echo json_encode($content['video_url'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    ?>;
    
    // Determinar el tipo de video
    let videoType = '<?php echo $content['video_type'] ?? 'local'; ?>';
    
    // Si no se especificó el tipo, intentar detectarlo
    if (!videoType || videoType === 'auto') {
        if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be')) {
            videoType = 'youtube';
        } else if (videoUrl.startsWith('magnet:') || videoUrl.endsWith('.torrent')) {
            videoType = 'torrent';
        } else if (videoUrl.startsWith('http')) {
            videoType = 'url';
        } else {
            videoType = 'local';
        }
    }
    
    // Inicializar el reproductor
    if (window.videoPlayer && videoUrl) {
        window.videoPlayer.loadVideo(videoUrl, videoType);
        
        // Configurar eventos
        window.videoPlayer.on('ready', function() {
            console.log('Reproductor listo');
            // Ocultar indicador de carga
            const loadingElement = document.getElementById('videoLoading');
            if (loadingElement) loadingElement.style.display = 'none';
        });
        
        window.videoPlayer.on('error', function(error) {
            console.error('Error en el reproductor:', error);
            // Mostrar mensaje de error
            const errorElement = document.getElementById('videoError');
            if (errorElement) {
                const errorMessage = errorElement.querySelector('#errorMessage') || errorElement;
                errorMessage.textContent = error.message || 'Error al cargar el video';
                errorElement.style.display = 'flex';
            }
        });
        
        // Configurar controles personalizados
        const playPauseBtn = document.getElementById('playPauseBtn');
        const volumeBtn = document.getElementById('volumeBtn');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const progressBar = document.querySelector('.progress-bar');
        const progress = document.querySelector('.progress-bar .progress');
        const currentTimeElement = document.querySelector('.time-display .current-time');
        const durationElement = document.querySelector('.time-display .duration');
        const volumeSlider = document.querySelector('.volume-slider .slider-track');
        const playbackSpeed = document.getElementById('playbackSpeed');
        
        // Eventos de los controles
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', function() {
                if (window.videoPlayer) {
                    window.videoPlayer.togglePlayPause();
                }
            });
        }
        
        if (volumeBtn) {
            volumeBtn.addEventListener('click', function() {
                if (window.videoPlayer) {
                    window.videoPlayer.toggleMute();
                }
            });
        }
        
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function() {
                if (window.videoPlayer) {
                    window.videoPlayer.toggleFullscreen();
                }
            });
        }
        
        if (progressBar) {
            progressBar.addEventListener('click', function(e) {
                if (window.videoPlayer) {
                    const rect = this.getBoundingClientRect();
                    const pos = (e.clientX - rect.left) / rect.width;
                    window.videoPlayer.seek(pos * window.videoPlayer.duration);
                }
            });
        }
        
        if (volumeSlider) {
            volumeSlider.addEventListener('click', function(e) {
                if (window.videoPlayer) {
                    const rect = this.getBoundingClientRect();
                    let volume = (e.clientX - rect.left) / rect.width;
                    volume = Math.max(0, Math.min(1, volume));
                    window.videoPlayer.setVolume(volume);
                }
            });
        }
        
        if (playbackSpeed) {
            playbackSpeed.addEventListener('change', function() {
                if (window.videoPlayer) {
                    window.videoPlayer.setPlaybackRate(parseFloat(this.value));
                }
            });
        }
        
        // Actualizar la interfaz de usuario cuando cambia el tiempo
        window.videoPlayer.on('timeupdate', function(time) {
            if (progress) {
                const percentage = (time.currentTime / time.duration) * 100;
                progress.style.width = percentage + '%';
            }
            
            if (currentTimeElement) {
                currentTimeElement.textContent = formatTime(time.currentTime);
            }
            
            if (durationElement && time.duration) {
                durationElement.textContent = formatTime(time.duration);
            }
        });
        
        // Actualizar el botón de reproducción/pausa
        window.videoPlayer.on('play', function() {
            if (playPauseBtn) {
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                playPauseBtn.setAttribute('title', 'Pausar');
            }
        });
        
        window.videoPlayer.on('pause', function() {
            if (playPauseBtn) {
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                playPauseBtn.setAttribute('title', 'Reproducir');
            }
        });
        
        // Actualizar el botón de volumen
        window.videoPlayer.on('volumechange', function(volume) {
            if (volumeBtn) {
                if (volume.muted || volume.volume === 0) {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-mute"></i>';
                    volumeBtn.setAttribute('title', 'Activar sonido');
                } else if (volume.volume < 0.5) {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-down"></i>';
                    volumeBtn.setAttribute('title', 'Silenciar');
                } else {
                    volumeBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
                    volumeBtn.setAttribute('title', 'Silenciar');
                }
            }
            
            if (volumeSlider) {
                const fill = volumeSlider.querySelector('.slider-fill');
                if (fill) {
                    fill.style.width = (volume.volume * 100) + '%';
                }
            }
        });
        
        // Función para formatear el tiempo
        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const minutes = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }
});
</script>
        $hasVideo = false;
        $torrentMagnet = null;
        
        // Verificar si hay un enlace torrent en la URL
        if (isset($_GET['torrent']) && !empty($_GET['torrent'])) {
            $torrentMagnet = urldecode($_GET['torrent']);
            $hasVideo = true;
        } elseif ($episode && !empty($episode['video_url'])) {
            $videoUrl = $episode['video_url'];
            $hasVideo = true;
        } elseif (!empty($content['video_url'])) {
            $videoUrl = $content['video_url'];
            $hasVideo = true;
        } elseif (!empty($content['trailer_url'])) {
            $videoUrl = $content['trailer_url'];
            $hasVideo = true;
        } elseif (!empty($content['torrent_magnet'])) {
            $torrentMagnet = $content['torrent_magnet'];
            $hasVideo = true;
        }
        
        // Convertir URL relativa a absoluta si es necesario
        if ($videoUrl && !empty($videoUrl)) {
            // Si es una ruta relativa que empieza con /uploads/, convertirla a URL absoluta
            if (strpos($videoUrl, '/uploads/') === 0 || strpos($videoUrl, '/streaming-platform/uploads/') === 0) {
                // Remover /streaming-platform si existe
                $videoUrl = str_replace('/streaming-platform', '', $videoUrl);
                // Si no empieza con http, convertir a URL absoluta
                if (strpos($videoUrl, 'http://') !== 0 && strpos($videoUrl, 'https://') !== 0) {
                    $baseUrl = rtrim(SITE_URL, '/');
                    $videoUrl = $baseUrl . $videoUrl;
                }
            } elseif (strpos($videoUrl, '/') === 0 && strpos($videoUrl, 'http') !== 0) {
                // Otra ruta relativa que empieza con /
                $baseUrl = rtrim(SITE_URL, '/');
                $videoUrl = $baseUrl . $videoUrl;
            } elseif (strpos($videoUrl, 'http://') !== 0 && strpos($videoUrl, 'https://') !== 0 && strpos($videoUrl, '/') !== 0) {
                // Si es una ruta relativa sin / al inicio, añadirla
                $baseUrl = rtrim(SITE_URL, '/');
                $videoUrl = $baseUrl . '/' . ltrim($videoUrl, '/');
            }
        }
        ?>
        
        <?php if ($hasVideo): ?>
            <!-- Reproductor de video -->
<div id="unifiedVideoContainer">
    <div class="video-loading">
        <i class="fas fa-spinner"></i>
        <p>Cargando reproductor...</p>
    </div>
</div>

<!-- Mensaje de error (oculto por defecto) -->
<div id="videoError" class="video-error" style="display: none;">
    <i class="fas fa-exclamation-triangle"></i>
    <h3>Error al cargar el video</h3>
    <p id="errorMessage">No se pudo cargar el reproductor de video.</p>
    <button class="btn btn-primary" onclick="window.location.reload()">
        <i class="fas fa-sync-alt"></i> Reintentar
    </button>
</div>
        <?php else: ?>
            <div class="no-video-message">
                <div class="no-video-content">
                    <i class="fas fa-video-slash"></i>
                    <h3>Video no disponible</h3>
                    <p>Este contenido no tiene un video disponible para reproducir.</p>
                    <?php if (!empty($content['trailer_url'])): ?>
                        <button class="btn btn-primary" onclick="playTrailer()">
                            <i class="fas fa-play"></i> Ver Tráiler
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo rtrim(SITE_URL, '/'); ?>/content-detail.php?id=<?php echo $contentId; ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver a detalles
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="video-info">
        <div class="video-title-section">
            <h1><?php echo htmlspecialchars($content['title']); ?></h1>
            <?php if ($episode): ?>
                <h2 style="font-size: 1.2rem; color: #999; margin-top: 0.5rem;">
                    Temporada <?php echo $episode['season_number']; ?>, Episodio <?php echo $episode['episode_number']; ?>: <?php echo htmlspecialchars($episode['title']); ?>
                </h2>
            <?php endif; ?>
            <div class="video-meta">
                <span><?php echo $content['release_year']; ?></span>
                <span>•</span>
                <span><?php echo $content['type'] === 'movie' ? floor($content['duration'] / 60) . 'h ' . ($content['duration'] % 60) . 'm' : $content['duration'] . ' min'; ?></span>
                <?php if ($content['rating']): ?>
                    <span>•</span>
                    <span>⭐ <?php echo number_format($content['rating'], 1); ?>/10</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($content['type'] === 'series'): ?>
            <?php
            // Obtener todos los episodios
            $episodesQuery = "
                SELECT * FROM episodes 
                WHERE series_id = :series_id 
                ORDER BY season_number ASC, episode_number ASC
            ";
            $episodesStmt = $db->prepare($episodesQuery);
            $episodesStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
            $episodesStmt->execute();
            $allEpisodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($allEpisodes)):
                $currentSeason = 0;
            ?>
                <div class="episode-selector">
                    <h3>Episodios</h3>
                    <div class="episode-list">
                        <?php foreach ($allEpisodes as $ep): ?>
                            <?php if ($ep['season_number'] != $currentSeason): 
                                $currentSeason = $ep['season_number'];
                            ?>
                                <div style="grid-column: 1 / -1; margin-top: 1rem; margin-bottom: 0.5rem;">
                                    <h4 style="color: #fff; font-size: 1.1rem;">Temporada <?php echo $currentSeason; ?></h4>
                                </div>
                            <?php endif; ?>
                            <div 
                                class="episode-item <?php echo $episodeId == $ep['id'] ? 'active' : ''; ?>"
                                onclick="playEpisode(<?php echo $ep['id']; ?>)"
                            >
                                <div style="font-weight: 600; margin-bottom: 0.5rem;">
                                    Episodio <?php echo $ep['episode_number']; ?>: <?php echo htmlspecialchars($ep['title']); ?>
                                </div>
                                <?php if ($ep['description']): ?>
                                    <div style="font-size: 0.85rem; color: #999;">
                                        <?php echo mb_strimwidth(htmlspecialchars($ep['description']), 0, 100, '...'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Configuración global
const BASE_URL = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';

// Verificar si WebTorrent está disponible
if (typeof WebTorrent === 'undefined') {
    console.error('WebTorrent no está disponible');
    showVideoError('El reproductor de video no pudo cargarse correctamente. Por favor, recarga la página.');
}

// Función para mostrar errores de video
function showVideoError(message) {
    const errorDiv = document.getElementById('videoError');
    const errorMessage = document.getElementById('errorMessage');
    
    if (errorDiv && errorMessage) {
        errorMessage.textContent = message || 'Ocurrió un error al cargar el video.';
        errorDiv.style.display = 'flex';
        
        // Ocultar el indicador de carga
        const loadingDiv = document.querySelector('.video-loading');
        if (loadingDiv) {
            loadingDiv.style.display = 'none';
        }
    }
    
    console.error('Error en el reproductor:', message);
}
const contentId = <?php echo $contentId; ?>;
const episodeId = <?php echo $episodeId ? $episodeId : 'null'; ?>;
const duration = <?php echo $episode ? ($episode['duration'] * 60) : ($content['duration'] * 60); ?>;
const videoUrlGlobal = <?php echo ($hasVideo && $videoUrl) ? json_encode($videoUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
const torrentMagnet = <?php echo ($hasVideo && $torrentMagnet) ? json_encode($torrentMagnet, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;

let player = null;
let saveInterval;
let hasStartedPlaying = false;

// Solo inicializar si hay video
<?php if ($hasVideo): ?>
// Esperar a que el reproductor esté disponible
// Esta función ha sido reemplazada por el nuevo sistema de inicialización
// en los archivos de configuración del reproductor
        return;
    }
    
    const container = document.getElementById('unifiedVideoContainer');
    if (!container || !videoUrl) {
        console.error('Contenedor de video o URL no encontrados', { container, videoUrl });
        return;
    }
    
    // Inicializar el reproductor unificado
    const startTime = <?php echo ($savedProgress && $savedProgress['progress'] > 10) ? $savedProgress['progress'] : 0; ?>;
    
    try {
        player = new UnifiedVideoPlayer('unifiedVideoContainer', {
            autoplay: false,
            controls: true,
            startTime: startTime > 0 && startTime < duration * 0.9 ? startTime : 0,
            onProgress: (currentTime, totalDuration) => {
                if (currentTime && totalDuration) {
                    clearTimeout(saveInterval);
                    saveInterval = setTimeout(() => {
                        saveProgress(currentTime, totalDuration);
                    }, 5000); // Guardar cada 5 segundos
                }
            },
            onEnded: () => {
                saveProgress(duration, duration, true);
                incrementViews();
            },
            onError: (error) => {
                console.error('Error en el reproductor:', error);
                showVideoError(error);
            }
        });
        
        // Determinar qué cargar: torrent o video normal
        const urlToLoad = torrentMagnet || videoUrl;
        
        if (!urlToLoad) {
            throw new Error('No hay URL de video o enlace torrent disponible');
        }
        
        // Cargar el video o torrent
        player.loadVideo(urlToLoad).then(() => {
            console.log('Video cargado correctamente');
            
            // Restaurar progreso guardado si existe
            <?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
                const progressPercent = Math.round((startTime / duration) * 100);
                if (progressPercent < 90) {
                    // Esperar a que el video tenga metadata antes de hacer seek
                    if (player.videoElement) {
                        player.videoElement.addEventListener('loadedmetadata', function() {
                            if (confirm(`¿Continuar desde ${formatTime(startTime)}?`)) {
                                player.seek(startTime);
                            }
                        }, { once: true });
                    } else {
                        // Si no hay videoElement, intentar después de un delay
                        setTimeout(() => {
                            if (player && player.seek) {
                                if (confirm(`¿Continuar desde ${formatTime(startTime)}?`)) {
                                    player.seek(startTime);
                                }
                            }
                        }, 1000);
                    }
                }
            <?php endif; ?>
            
            // Incrementar contador de vistas cuando el video comience
            if (player.videoElement) {
                player.videoElement.addEventListener('play', function() {
                    if (!hasStartedPlaying) {
                        hasStartedPlaying = true;
                        incrementViews();
                    }
                }, { once: true });
            }
        }).catch(error => {
            console.error('Error al cargar el video:', error);
            showVideoError(error);
        });
    } catch (error) {
        console.error('Error al inicializar el reproductor:', error);
        showVideoError(error);
    }
}

function showVideoError(error) {
    const container = document.getElementById('unifiedVideoContainer');
    if (container) {
        container.innerHTML = `
            <div class="video-error">
                <div class="error-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error al cargar el video</h3>
                    <p>${error.message || 'No se pudo cargar el video. Por favor, verifica la URL del video.'}</p>
                    <button class="btn btn-primary" onclick="retryVideo()">
                        <i class="fas fa-redo"></i> Reintentar
                    </button>
                    <a href="${BASE_URL}/content-detail.php?id=${contentId}" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        `;
    }
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVideoPlayer);
} else {
    // DOM ya está listo
    initVideoPlayer();
}

function saveProgress(currentTime, duration, completed = false) {
    if (!currentTime || !duration) return;
    
    fetch(`${BASE_URL}/api/playback/save.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            content_id: contentId,
            episode_id: episodeId,
            progress: Math.floor(currentTime),
            duration: Math.floor(duration),
            completed: completed
        })
    }).catch(error => console.error('Error guardando progreso:', error));
}

function incrementViews() {
    fetch(`${BASE_URL}/api/content/view.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            content_id: contentId,
            episode_id: episodeId
        })
    }).catch(error => console.error('Error incrementando vistas:', error));
}

function formatTime(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function playEpisode(epId) {
    window.location.href = `${BASE_URL}/watch.php?id=${contentId}&episode_id=${epId}`;
}

function retryVideo() {
    if (player && videoUrl) {
        player.loadVideo(videoUrl).catch(error => {
            console.error('Error al reintentar:', error);
        });
    }
}

function toggleFullscreen() {
    if (player) {
        player.toggleFullscreen();
    }
}

function playTrailer() {
    <?php if (!empty($content['trailer_url'])): ?>
        window.location.href = `${BASE_URL}/watch.php?id=<?php echo $contentId; ?>&trailer=1`;
    <?php endif; ?>
}

    // Guardar al cerrar la página
    window.addEventListener('beforeunload', function() {
        if (player && player.currentTime && player.duration) {
            saveProgress(player.currentTime, player.duration);
        }
    });

    // Manejar cambios de visibilidad de la página
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && player && player.isPlaying && player.currentTime && player.duration) {
            saveProgress(player.currentTime, player.duration);
        }
    });
<?php endif; ?>
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

