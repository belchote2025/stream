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
$query = "SELECT 
    id, title, type, poster_url, backdrop_url, video_url, trailer_url,
    description, duration, release_year, rating, 
    is_premium, is_featured, torrent_magnet, created_at, updated_at
FROM content WHERE id = :id";
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
    <div class="video-container-full">
        <?php
        // Determinar si tenemos algo reproducible
        $hasVideo = false;
        $torrentMagnet = null;
        $videoUrl = $videoUrl ?? '';
        $addonStreams = [];
        
        // Verificar si hay un enlace torrent en la URL (soporta 'magnet' y 'torrent')
        if (isset($_GET['magnet']) && !empty($_GET['magnet'])) {
            $torrentMagnet = urldecode($_GET['magnet']);
            $hasVideo = true;
        } elseif (isset($_GET['torrent']) && !empty($_GET['torrent'])) {
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
        } else {
            // Si no hay video local, intentar obtener desde addons
            try {
                require_once __DIR__ . '/includes/addons/AddonManager.php';
                $addonManager = AddonManager::getInstance();
                $addonManager->loadAddons();
                
                $contentType = $content['type'] === 'series' ? 'series' : 'movie';
                $allStreams = $addonManager->getStreams($contentId, $contentType, $episodeId);
                
                // Combinar todos los streams de addons
                foreach ($allStreams as $addonId => $streams) {
                    if (is_array($streams)) {
                        foreach ($streams as $stream) {
                            if (!empty($stream['url'])) {
                                $addonStreams[] = $stream;
                                
                                // Usar el primer stream disponible como fallback
                                if (!$hasVideo && !empty($stream['url'])) {
                                    if ($stream['type'] === 'torrent' || strpos($stream['url'], 'magnet:') === 0) {
                                        $torrentMagnet = $stream['url'];
                                    } else {
                                        $videoUrl = $stream['url'];
                                    }
                                    $hasVideo = true;
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error obteniendo streams de addons: " . $e->getMessage());
            }
        }
        
        // Convertir URL relativa a absoluta si es necesario
        if ($videoUrl && !empty($videoUrl)) {
            // Si es una ruta relativa que empieza con /uploads/, convertirla a URL absoluta
            // Remover cualquier referencia a /streaming-platform en la ruta (funciona en local y producción)
            if (strpos($videoUrl, '/streaming-platform/uploads/') === 0) {
                $videoUrl = str_replace('/streaming-platform', '', $videoUrl);
            }
            
            if (strpos($videoUrl, '/uploads/') === 0) {
                // Si no empieza con http, convertir a URL absoluta usando SITE_URL
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
                    <i class="fas fa-spinner fa-spin"></i>
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
            <h1><?php echo htmlspecialchars($content['title']); ?><?php if ($episode): ?> - <?php echo htmlspecialchars($episode['title']); ?><?php endif; ?></h1>
            <div class="video-meta">
                <?php if ($content['release_year']): ?>
                    <span><?php echo $content['release_year']; ?></span>
                <?php endif; ?>
                <?php if ($content['rating']): ?>
                    <span>⭐ <?php echo number_format($content['rating'], 1); ?>/10</span>
                <?php endif; ?>
                <?php if ($episode && $episode['duration']): ?>
                    <span><?php echo formatDuration($episode['duration']); ?></span>
                <?php elseif ($content['duration']): ?>
                    <span><?php echo formatDuration($content['duration']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (isLoggedIn()): ?>
            <div class="watch-party-actions" style="margin-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                <button class="btn btn-primary" id="createWatchPartyBtn" onclick="createWatchParty()">
                    <i class="fas fa-users"></i> Crear Watch Party
                </button>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="text" id="joinPartyCode" placeholder="Código del party" maxlength="8" style="padding: 0.5rem; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.1); color: #fff; width: 150px;">
                    <button class="btn btn-secondary" onclick="joinWatchParty()">
                        <i class="fas fa-sign-in-alt"></i> Unirse
                    </button>
                </div>
            </div>
            <?php endif; ?>
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
            
            if (!$seasons || count($seasons) === 0): ?>
                <div class="episodes-empty">
                    <p>No hay episodios cargados para esta serie.</p>
                </div>
            <?php
            else:
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
            endif;
            ?>
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Scripts del reproductor -->
<?php
// Versión de cache-busting basada en la fecha de modificación de main.js
$playerVersion = @filemtime(__DIR__ . '/js/player/main.js') ?: time();
?>
<!-- Cargar scripts del reproductor en orden correcto -->
<script src="<?php echo $baseUrl; ?>/js/player/config.js?v=<?php echo $playerVersion; ?>"></script>
<script src="<?php echo $baseUrl; ?>/js/player/main.js?v=<?php echo $playerVersion; ?>" onerror="console.error('Error al cargar main.js')"></script>
<!-- init.js se carga después para asegurar que UnifiedVideoPlayer esté definido -->
<script>
// Verificar que UnifiedVideoPlayer esté disponible antes de cargar init.js
if (typeof UnifiedVideoPlayer === 'undefined') {
    console.error('❌ UnifiedVideoPlayer no está definido. Verifica que main.js se cargó correctamente.');
} else {
    console.log('✅ UnifiedVideoPlayer está disponible');
}
</script>
<script src="<?php echo $baseUrl; ?>/js/player/init.js?v=<?php echo $playerVersion; ?>"></script>
<!-- Script de diagnóstico de torrents (solo en local o con ?debug=1) -->
<?php if ((defined('APP_ENV') && APP_ENV === 'local') || isset($_GET['debug'])): ?>
<script src="<?php echo $baseUrl; ?>/js/torrent-debugger.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<script>
// Configuración global
const BASE_URL = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
// Definir __APP_BASE_URL si no está definido
if (typeof window !== 'undefined' && !window.__APP_BASE_URL) {
    window.__APP_BASE_URL = '<?php echo rtrim(SITE_URL, '/'); ?>';
}
// Solo declarar APP_BASE_URL si no existe (evitar redeclaración)
// Usar window.APP_BASE_URL para evitar conflictos con main.js
if (typeof window.APP_BASE_URL === 'undefined') {
    window.APP_BASE_URL = window.__APP_BASE_URL || '<?php echo rtrim(SITE_URL, '/'); ?>';
}
// Crear alias para compatibilidad (solo si no existe)
if (typeof APP_BASE_URL === 'undefined') {
    // Usar una función para obtener el valor sin redeclarar
    (function() {
        var baseUrl = window.__APP_BASE_URL || window.APP_BASE_URL || '<?php echo rtrim(SITE_URL, '/'); ?>';
        try {
            Object.defineProperty(window, 'APP_BASE_URL', {
                value: baseUrl,
                writable: false,
                configurable: true,
                enumerable: true
            });
        } catch (e) {
            // Si falla, simplemente asignar
            window.APP_BASE_URL = baseUrl;
        }
    })();
}

// WebTorrent se cargará de forma asíncrona, no verificar aquí
// La verificación se hará cuando realmente se necesite usar WebTorrent

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
var contentId = <?php echo $contentId; ?>;
var episodeId = <?php echo $episodeId ? $episodeId : 'null'; ?>;
var duration = <?php echo $episode ? ($episode['duration'] * 60) : ($content['duration'] * 60); ?>;
var videoUrlGlobal = <?php echo ($hasVideo && $videoUrl) ? json_encode($videoUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;
var torrentMagnet = <?php echo ($hasVideo && $torrentMagnet) ? json_encode($torrentMagnet, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null'; ?>;

var player = null;
var saveInterval;
var hasStartedPlaying = false;

<?php if ($hasVideo): ?>
// Inicializar el reproductor una vez que el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('unifiedVideoContainer');
    const videoUrl = videoUrlGlobal;

    if (!container) {
        console.error('Contenedor de video no encontrado');
        showVideoError('No se pudo encontrar el reproductor de video.');
        return;
    }
    
    if (!videoUrl && !torrentMagnet) {
        console.warn('No hay URL de video ni torrent disponible');
        showVideoError('Este contenido no tiene una fuente de video disponible. Por favor, contacta al administrador.');
        return;
    }

    const startTime = <?php echo ($savedProgress && $savedProgress['progress'] > 10) ? $savedProgress['progress'] : 0; ?>;

    try {
        // Ocultar el mensaje de carga inicial cuando se crea el reproductor
        const loadingDiv = document.querySelector('.video-loading');
        
        player = new UnifiedVideoPlayer('unifiedVideoContainer', {
            autoplay: false,
            controls: true,
            startTime: startTime > 0 && startTime < duration * 0.9 ? startTime : 0,
            onProgress: (currentTime, totalDuration) => {
                if (currentTime && totalDuration) {
                    clearTimeout(saveInterval);
                    saveInterval = setTimeout(() => {
                        saveProgress(currentTime, totalDuration);
                    }, 5000);
                }
            },
            onEnded: () => {
                saveProgress(duration, duration, true);
                incrementViews();
            },
            onError: (error) => {
                console.error('Error en el reproductor:', error);
                // Ocultar el mensaje de carga en caso de error
                if (loadingDiv) {
                    loadingDiv.style.display = 'none';
                }
                showVideoError(error);
            }
        });
        
        // Ocultar el mensaje de carga cuando el reproductor está listo
        if (loadingDiv && player) {
            // Esperar un momento para que el reproductor se inicialice
            setTimeout(() => {
                if (loadingDiv) {
                    loadingDiv.style.display = 'none';
                }
            }, 100);
        }

        const urlToLoad = torrentMagnet || videoUrl;

        if (!urlToLoad) {
            console.error('No hay URL de video ni torrent disponible');
            showVideoError('Este contenido no tiene una fuente de video disponible. Por favor, contacta al administrador o intenta más tarde.');
            return;
        }

        console.log('Inicializando reproductor con URL:', urlToLoad);
        console.log('Tipo de URL:', torrentMagnet ? 'torrent' : 'video');
        
        // Si es un torrent, esperar a que WebTorrent se cargue
        if (torrentMagnet) {
            console.log('Esperando a que WebTorrent se cargue...');
            // Esperar a que WebTorrent esté disponible
            const checkWebTorrent = setInterval(() => {
                if (typeof WebTorrent !== 'undefined') {
                    clearInterval(checkWebTorrent);
                    console.log('WebTorrent cargado, iniciando reproducción de torrent...');
                    try {
                        // Verificar que el reproductor esté inicializado
                        if (!player) {
                            console.error('❌ El reproductor no está inicializado');
                            showVideoError('El reproductor no está inicializado. Por favor, recarga la página.');
                            return;
                        }
                        
                        console.log('✅ Reproductor disponible, cargando torrent...');
                        console.log('🔗 URL del torrent:', urlToLoad.substring(0, 100) + '...');
                        
                        // Verificar que loadVideo existe y es una función
                        if (typeof player.loadVideo !== 'function') {
                            console.error('❌ player.loadVideo no es una función');
                            console.log('Tipo de player:', typeof player);
                            console.log('Métodos disponibles:', Object.keys(player));
                            showVideoError('El reproductor no tiene el método loadVideo. Por favor, recarga la página.');
                            return;
                        }
                        
                        // Intentar cargar el torrent
                        const loadPromise = player.loadVideo(urlToLoad, 'torrent');
                        
                        if (loadPromise && typeof loadPromise.then === 'function') {
                            loadPromise.then(() => {
                                console.log('✅ Torrent cargado correctamente');
                            }).catch(error => {
                                console.error('❌ Error al cargar el torrent:', error);
                                console.error('📋 Detalles del error:', {
                                    message: error.message,
                                    stack: error.stack,
                                    name: error.name,
                                    toString: error.toString()
                                });
                                showVideoError('Error al cargar el torrent: ' + (error.message || 'Error desconocido'));
                            });
                        } else {
                            console.warn('⚠️ loadVideo no devolvió una promesa');
                            console.log('Valor devuelto:', loadPromise);
                            // Intentar de todas formas
                            try {
                                player.loadVideo(urlToLoad, 'torrent');
                            } catch (e) {
                                console.error('❌ Error al ejecutar loadVideo:', e);
                                showVideoError('Error al cargar el torrent: ' + (e.message || 'Error desconocido'));
                            }
                        }
                    } catch (error) {
                        console.error('❌ Error al cargar el torrent (catch):', error);
                        console.error('📋 Detalles del error:', {
                            message: error.message,
                            stack: error.stack,
                            name: error.name,
                            toString: error.toString()
                        });
                        showVideoError('Error al cargar el torrent: ' + (error.message || 'Error desconocido'));
                    }
                }
            }, 100);
            
            // Timeout después de 10 segundos
            setTimeout(() => {
                clearInterval(checkWebTorrent);
                if (typeof WebTorrent === 'undefined') {
                    console.error('WebTorrent no se cargó a tiempo');
                    showVideoError('El reproductor de torrents no pudo cargarse. Por favor, recarga la página.');
                }
            }, 10000);
        } else {
            // Video directo, cargar inmediatamente
            console.log('Cargando video directo...');
            try {
                player.loadVideo(urlToLoad).then(() => {
                    console.log('Video cargado correctamente');
                    // Ocultar el mensaje de carga original
                    const loadingDiv = document.querySelector('.video-loading');
                    if (loadingDiv) {
                        loadingDiv.style.display = 'none';
                    }
                }).catch(error => {
                    console.error('Error al cargar el video:', error);
                    // Ocultar el mensaje de carga en caso de error también
                    const loadingDiv = document.querySelector('.video-loading');
                    if (loadingDiv) {
                        loadingDiv.style.display = 'none';
                    }
                    showVideoError('Error al cargar el video. Por favor, intenta más tarde.');
                });
            } catch (error) {
                console.error('Error al cargar el video:', error);
                // Ocultar el mensaje de carga en caso de error también
                const loadingDiv = document.querySelector('.video-loading');
                if (loadingDiv) {
                    loadingDiv.style.display = 'none';
                }
                showVideoError('Error al cargar el video. Por favor, intenta más tarde.');
                return;
            }
        }

        <?php if ($savedProgress && $savedProgress['progress'] > 10): ?>
        const progressPercent = Math.round((startTime / duration) * 100);
        if (progressPercent < 90) {
            if (player.videoElement) {
                player.videoElement.addEventListener('loadedmetadata', function() {
                    if (confirm(`¿Continuar desde ${formatTime(startTime)}?`)) {
                        player.seek(startTime);
                    }
                }, { once: true });
            } else {
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

        if (player.videoElement) {
            player.videoElement.addEventListener('play', function() {
                if (!hasStartedPlaying) {
                    hasStartedPlaying = true;
                    incrementViews();
                }
            }, { once: true });
        }
    } catch (error) {
        console.error('Error al inicializar el reproductor:', error);
        showVideoError(error);
    }
});
<?php else: ?>
console.warn('Este contenido no tiene video ni torrent configurado para reproducir.');
<?php endif; ?>

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
    const urlToLoad = torrentMagnet || videoUrlGlobal;
    if (player && urlToLoad) {
        player.loadVideo(urlToLoad).catch(error => {
            console.error('Error al reintentar:', error);
        });
    } else {
        window.location.reload();
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

// Funciones de Watch Party
async function createWatchParty() {
    const btn = document.getElementById('createWatchPartyBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';
    }
    
    try {
        const response = await fetch(`${BASE_URL}/api/watch-party/create.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                content_id: <?php echo $contentId; ?>,
                content_type: '<?php echo $content['type']; ?>',
                episode_id: <?php echo $episodeId ? $episodeId : 'null'; ?>,
                party_name: '<?php echo htmlspecialchars($content['title']); ?>' + (<?php echo $episodeId ? 'true' : 'false'; ?> ? ' - <?php echo $episode ? htmlspecialchars($episode['title']) : ''; ?>' : '')
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirigir a la página del watch party
            window.location.href = data.data.url;
        } else {
            alert('Error al crear Watch Party: ' + (data.error || 'Error desconocido'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-users"></i> Crear Watch Party';
            }
        }
    } catch (error) {
        console.error('Error al crear Watch Party:', error);
        alert('Error al crear Watch Party. Por favor, intenta de nuevo.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-users"></i> Crear Watch Party';
        }
    }
}

function joinWatchParty() {
    const codeInput = document.getElementById('joinPartyCode');
    if (!codeInput) return;
    
    const code = codeInput.value.trim().toUpperCase();
    
    if (!code || code.length !== 8) {
        alert('Por favor, ingresa un código válido de 8 caracteres');
        return;
    }
    
    window.location.href = `${BASE_URL}/watch-party.php?code=${code}`;
}

// Permitir unirse con Enter
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('joinPartyCode');
    if (codeInput) {
        codeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                joinWatchParty();
            }
        });
    }
});

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
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

