<?php
/**
 * Página de reproducción de video
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

$contentId = $_GET['id'] ?? 0;
$episodeId = $_GET['episode_id'] ?? null;
$contentId = (int)$contentId;

if (!$contentId) {
    header('Location: /streaming-platform/');
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
    header('Location: /streaming-platform/');
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
?>

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
    .video-container-full {
        padding-bottom: 56.25%;
        max-height: 100vh;
    }
    
    .watch-page {
        padding-top: 0;
    }
    
    .video-info {
        padding: 1rem 2%;
    }
}
</style>

<div class="watch-page">
    <div class="video-container-full">
        <?php
        // Determinar la URL del video a reproducir
        $videoUrl = null;
        $hasVideo = false;
        
        if ($episode && !empty($episode['video_url'])) {
            $videoUrl = $episode['video_url'];
            $hasVideo = true;
        } elseif (!empty($content['video_url'])) {
            $videoUrl = $content['video_url'];
            $hasVideo = true;
        } elseif (!empty($content['trailer_url'])) {
            $videoUrl = $content['trailer_url'];
            $hasVideo = true;
        }
        ?>
        
        <?php if ($hasVideo): ?>
            <video 
                id="videoPlayer" 
                controls 
                autoplay
                preload="metadata"
                playsinline
                <?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
                    data-start-time="<?php echo $savedProgress['progress']; ?>"
                <?php endif; ?>
            >
                <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/mp4">
                <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/webm">
                <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/ogg">
                Tu navegador no soporta el elemento de video HTML5.
            </video>
            <div id="videoError" class="video-error" style="display: none;">
                <div class="error-content">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error al cargar el video</h3>
                    <p>No se pudo cargar el video. Por favor, verifica tu conexión a internet o intenta más tarde.</p>
                    <button class="btn btn-primary" onclick="retryVideo()">
                        <i class="fas fa-redo"></i> Reintentar
                    </button>
                </div>
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
                    <a href="/streaming-platform/content-detail.php?id=<?php echo $contentId; ?>" class="btn btn-outline">
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
const video = document.getElementById('videoPlayer');
const contentId = <?php echo $contentId; ?>;
const episodeId = <?php echo $episodeId ? $episodeId : 'null'; ?>;
const duration = <?php echo $episode ? ($episode['duration'] * 60) : ($content['duration'] * 60); ?>;
const videoUrl = <?php echo $hasVideo ? "'" . htmlspecialchars($videoUrl, ENT_QUOTES) . "'" : 'null'; ?>;

let saveInterval;
let hasStartedPlaying = false;

// Solo inicializar si hay video
<?php if ($hasVideo): ?>
if (video) {
    // Mostrar indicador de carga
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'video-loading';
    loadingIndicator.innerHTML = '<i class="fas fa-spinner"></i>';
    video.parentElement.appendChild(loadingIndicator);
    
    // Manejo de errores del video
    video.addEventListener('error', function(e) {
        console.error('Error en el video:', e);
        loadingIndicator.style.display = 'none';
        const errorDiv = document.getElementById('videoError');
        if (errorDiv) {
            errorDiv.style.display = 'flex';
        }
        video.style.display = 'none';
    });
    
    // Ocultar indicador cuando el video esté listo
    video.addEventListener('loadedmetadata', function() {
        loadingIndicator.style.display = 'none';
        
        // Restaurar progreso guardado
        <?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
            const startTime = <?php echo $savedProgress['progress']; ?>;
            const progressPercent = Math.round((startTime / video.duration) * 100);
            
            if (progressPercent < 90) { // Solo restaurar si no está casi al final
                if (confirm(`¿Continuar desde ${formatTime(startTime)}?`)) {
                    video.currentTime = startTime;
                }
            }
        <?php endif; ?>
    });
    
    // Incrementar contador de vistas cuando el video comience
    video.addEventListener('play', function() {
        if (!hasStartedPlaying) {
            hasStartedPlaying = true;
            incrementViews();
        }
        
        // Guardar progreso cada 10 segundos
        saveInterval = setInterval(saveProgress, 10000);
    });
    
    video.addEventListener('pause', function() {
        clearInterval(saveInterval);
        saveProgress();
    });
    
    // Guardar progreso cuando el video termine
    video.addEventListener('ended', function() {
        clearInterval(saveInterval);
        saveProgress(true); // Marcar como completado
    });
    
    // Manejar teclas de atajos
    document.addEventListener('keydown', function(e) {
        if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') {
            return;
        }
        
        switch(e.key) {
            case ' ': // Espacio para play/pause
                e.preventDefault();
                if (video.paused) {
                    video.play();
                } else {
                    video.pause();
                }
                break;
            case 'ArrowLeft': // Retroceder 10 segundos
                e.preventDefault();
                video.currentTime = Math.max(0, video.currentTime - 10);
                break;
            case 'ArrowRight': // Avanzar 10 segundos
                e.preventDefault();
                video.currentTime = Math.min(video.duration, video.currentTime + 10);
                break;
            case 'ArrowUp': // Subir volumen
                e.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                break;
            case 'ArrowDown': // Bajar volumen
                e.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                break;
            case 'f': // Pantalla completa
            case 'F':
                e.preventDefault();
                toggleFullscreen();
                break;
        }
    });
}

function saveProgress(completed = false) {
    if (!video || !video.duration) return;
    
    fetch('/streaming-platform/api/playback/save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            content_id: contentId,
            episode_id: episodeId,
            progress: Math.floor(video.currentTime),
            duration: Math.floor(video.duration),
            completed: completed
        })
    }).catch(error => console.error('Error guardando progreso:', error));
}

function incrementViews() {
    fetch('/streaming-platform/api/content/view.php', {
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
    window.location.href = `/streaming-platform/watch.php?id=${contentId}&episode_id=${epId}`;
}

function retryVideo() {
    const errorDiv = document.getElementById('videoError');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
    if (video) {
        video.style.display = 'block';
        video.load();
        video.play().catch(e => console.error('Error al reproducir:', e));
    }
}

function toggleFullscreen() {
    const container = document.querySelector('.video-container-full');
    if (!document.fullscreenElement) {
        container.requestFullscreen().catch(err => {
            console.error('Error al entrar en pantalla completa:', err);
        });
    } else {
        document.exitFullscreen();
    }
}

function playTrailer() {
    <?php if (!empty($content['trailer_url'])): ?>
        window.location.href = '/streaming-platform/watch.php?id=<?php echo $contentId; ?>&trailer=1';
    <?php endif; ?>
}

// Guardar al cerrar la página
window.addEventListener('beforeunload', function() {
    if (video) {
        saveProgress();
    }
});

// Manejar cambios de visibilidad de la página
document.addEventListener('visibilitychange', function() {
    if (document.hidden && video && !video.paused) {
        saveProgress();
    }
});
<?php endif; ?>
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

