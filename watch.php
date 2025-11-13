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
</style>

<div class="watch-page">
    <div class="video-container-full">
        <video 
            id="videoPlayer" 
            controls 
            autoplay
            <?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
                data-start-time="<?php echo $savedProgress['progress']; ?>"
            <?php endif; ?>
        >
            <?php if ($episode && $episode['video_url']): ?>
                <source src="<?php echo htmlspecialchars($episode['video_url']); ?>" type="video/mp4">
            <?php elseif ($content['video_url']): ?>
                <source src="<?php echo htmlspecialchars($content['video_url']); ?>" type="video/mp4">
            <?php elseif ($content['trailer_url']): ?>
                <source src="<?php echo htmlspecialchars($content['trailer_url']); ?>" type="video/mp4">
            <?php endif; ?>
            Tu navegador no soporta el elemento de video.
        </video>
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

// Restaurar progreso guardado
<?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
    video.addEventListener('loadedmetadata', function() {
        const startTime = <?php echo $savedProgress['progress']; ?>;
        if (confirm(`¿Continuar desde ${formatTime(startTime)}?`)) {
            video.currentTime = startTime;
        }
    });
<?php endif; ?>

// Guardar progreso cada 10 segundos
let saveInterval;
video.addEventListener('play', function() {
    saveInterval = setInterval(saveProgress, 10000);
});

video.addEventListener('pause', function() {
    clearInterval(saveInterval);
    saveProgress();
});

function saveProgress() {
    if (!video.duration) return;
    
    fetch('/streaming-platform/api/playback/save.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            content_id: contentId,
            episode_id: episodeId,
            progress: Math.floor(video.currentTime),
            duration: Math.floor(video.duration)
        })
    }).catch(error => console.error('Error guardando progreso:', error));
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

// Guardar al cerrar la página
window.addEventListener('beforeunload', saveProgress);
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

