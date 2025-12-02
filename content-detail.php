<?php
/**
 * Página de detalles de contenido estilo Netflix
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

$contentId = $_GET['id'] ?? 0;
$contentId = (int)$contentId;

if (!$contentId) {
    header('Location: /');
    exit;
}

$db = getDbConnection();

// Obtener información del contenido
$query = "
    SELECT 
        c.*,
        GROUP_CONCAT(DISTINCT g.id ORDER BY g.name SEPARATOR ',') as genre_ids,
        GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
    FROM content c
    LEFT JOIN content_genres cg ON c.id = cg.content_id
    LEFT JOIN genres g ON cg.genre_id = g.id
    WHERE c.id = :id
    GROUP BY c.id
";

$stmt = $db->prepare($query);
$stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
$stmt->execute();
$content = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$content) {
    header('Location: /');
    exit;
}

// Obtener episodios si es una serie
$episodes = [];
if ($content['type'] === 'series') {
    $episodesQuery = "
        SELECT 
            id,
            season_number,
            episode_number,
            title,
            description,
            duration,
            video_url,
            thumbnail_url,
            release_date,
            views
        FROM episodes
        WHERE series_id = :series_id
        ORDER BY season_number ASC, episode_number ASC
    ";
    
    $episodesStmt = $db->prepare($episodesQuery);
    $episodesStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
    $episodesStmt->execute();
    $episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener contenido similar
$similarQuery = "
    SELECT c.*
    FROM content c
    INNER JOIN content_genres cg1 ON c.id = cg1.content_id
    INNER JOIN content_genres cg2 ON cg1.genre_id = cg2.genre_id
    WHERE cg2.content_id = :content_id
    AND c.id != :content_id_exclude
    GROUP BY c.id
    ORDER BY COUNT(*) DESC, c.rating DESC
    LIMIT 10
";

$similarStmt = $db->prepare($similarQuery);
$similarStmt->bindValue(':content_id', $contentId, PDO::PARAM_INT);
$similarStmt->bindValue(':content_id_exclude', $contentId, PDO::PARAM_INT);
$similarStmt->execute();
$similarContent = $similarStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = htmlspecialchars($content['title']) . ' - ' . SITE_NAME;

include __DIR__ . '/includes/header.php';
?>

<style>
.content-detail-page {
    padding-top: 70px;
    min-height: 100vh;
}

.hero-detail {
    position: relative;
    height: 80vh;
    min-height: 500px;
    overflow: hidden;
}

.hero-backdrop-detail {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    z-index: 1;
}

.hero-backdrop-detail::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 200px;
    background: linear-gradient(180deg, transparent 0%, rgba(20, 20, 20, 0.7) 50%, #141414 100%);
    z-index: 2;
}

.hero-content-detail {
    position: relative;
    z-index: 3;
    height: 100%;
    display: flex;
    align-items: flex-end;
    padding: 0 4% 8%;
    max-width: 1400px;
    margin: 0 auto;
}

.hero-info {
    max-width: 50%;
}

.hero-title-detail {
    font-size: clamp(2.5rem, 5vw, 4.5rem);
    font-weight: 900;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8);
}

.hero-meta {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.hero-meta span {
    color: #999;
    font-size: 0.9rem;
}

.hero-meta .rating {
    color: #46d369;
    font-weight: 600;
}

.hero-description-detail {
    font-size: clamp(1rem, 1.2vw, 1.2rem);
    line-height: 1.6;
    margin-bottom: 2rem;
    text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.8);
}

.hero-actions-detail {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.content-detail-body {
    padding: 3rem 4%;
    max-width: 1400px;
    margin: 0 auto;
}

.detail-section {
    margin-bottom: 3rem;
}

.detail-section h3 {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    color: #fff;
}

.episodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.episode-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.episode-card:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-5px);
}

.episode-thumbnail {
    width: 100%;
    aspect-ratio: 16/9;
    background: #1f1f1f;
    position: relative;
    overflow: hidden;
}

.episode-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.episode-play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.episode-card:hover .episode-play-overlay {
    opacity: 1;
}

.episode-info {
    padding: 1rem;
}

.episode-number {
    font-size: 0.85rem;
    color: #999;
    margin-bottom: 0.5rem;
}

.episode-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.episode-description {
    font-size: 0.85rem;
    color: #999;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.info-item {
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.info-item-label {
    font-size: 0.85rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.info-item-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #fff;
}

.genres-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.genre-badge {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    font-size: 0.85rem;
    color: #fff;
    transition: all 0.3s ease;
}

.genre-badge:hover {
    background: #e50914;
    transform: scale(1.05);
}
</style>

<div class="content-detail-page">
    <!-- Hero Section -->
    <div class="hero-detail">
        <?php 
        require_once __DIR__ . '/includes/image-helper.php';
        $backdropUrl = getImageUrl($content['backdrop_url'] ?? $content['poster_url'] ?? '', '/assets/img/default-backdrop.svg');
        ?>
        <div class="hero-backdrop-detail" style="background-image: url('<?php echo htmlspecialchars($backdropUrl); ?>');"></div>
        
        <div class="hero-content-detail">
            <div class="hero-info">
                <h1 class="hero-title-detail"><?php echo htmlspecialchars($content['title']); ?></h1>
                
                <div class="hero-meta">
                    <span><?php echo $content['release_year']; ?></span>
                    <span>•</span>
                    <span><?php echo $content['type'] === 'movie' ? floor($content['duration'] / 60) . 'h ' . ($content['duration'] % 60) . 'm' : $content['duration'] . ' min'; ?></span>
                    <?php if ($content['rating']): ?>
                        <span class="rating">⭐ <?php echo number_format($content['rating'], 1); ?>/10</span>
                    <?php endif; ?>
                    <?php if ($content['age_rating']): ?>
                        <span class="age-rating"><?php echo htmlspecialchars($content['age_rating']); ?></span>
                    <?php endif; ?>
                </div>
                
                <p class="hero-description-detail">
                    <?php echo htmlspecialchars($content['description'] ?: 'Sin descripción disponible.'); ?>
                </p>
                
                <div class="hero-actions-detail">
                    <button class="btn btn-primary" onclick="playContent(<?php echo $content['id']; ?>, '<?php echo $content['type']; ?>')">
                        <i class="fas fa-play"></i> Reproducir
                    </button>
                    <button class="btn btn-secondary" onclick="addToMyList(<?php echo $content['id']; ?>)">
                        <i class="fas fa-plus"></i> Mi lista
                    </button>
                    <button class="btn btn-secondary" onclick="shareContent()">
                        <i class="fas fa-share-alt"></i> Compartir
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Details -->
    <div class="content-detail-body">
        <!-- Info Grid -->
        <div class="info-grid">
            <div class="info-item">
                <div class="info-item-label">Tipo</div>
                <div class="info-item-value"><?php echo $content['type'] === 'movie' ? 'Película' : 'Serie'; ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-item-label">Año</div>
                <div class="info-item-value"><?php echo $content['release_year']; ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-item-label">Duración</div>
                <div class="info-item-value">
                    <?php 
                    if ($content['type'] === 'movie') {
                        echo floor($content['duration'] / 60) . 'h ' . ($content['duration'] % 60) . 'm';
                    } else {
                        echo $content['duration'] . ' min por episodio';
                    }
                    ?>
                </div>
            </div>
            
            <?php if ($content['rating']): ?>
            <div class="info-item">
                <div class="info-item-label">Calificación</div>
                <div class="info-item-value">⭐ <?php echo number_format($content['rating'], 1); ?>/10</div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Genres -->
        <?php if (!empty($content['genres'])): ?>
        <div class="detail-section">
            <h3>Géneros</h3>
            <div class="genres-list">
                <?php 
                $genresArray = explode(', ', $content['genres']);
                foreach ($genresArray as $genre): 
                ?>
                    <a href="search.php?genre=<?php echo urlencode($genre); ?>" class="genre-badge">
                        <?php echo htmlspecialchars($genre); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Episodes (for series) -->
        <?php if ($content['type'] === 'series' && !empty($episodes)): ?>
        <div class="detail-section">
            <h3>Episodios</h3>
            <div class="episodes-grid">
                <?php 
                $currentSeason = 0;
                foreach ($episodes as $episode): 
                    if ($episode['season_number'] != $currentSeason):
                        $currentSeason = $episode['season_number'];
                ?>
                    <div style="grid-column: 1 / -1; margin-top: 2rem; margin-bottom: 1rem;">
                        <h4 style="color: #fff; font-size: 1.3rem;">Temporada <?php echo $currentSeason; ?></h4>
                    </div>
                <?php endif; ?>
                <div class="episode-card" onclick="playEpisode(<?php echo $episode['id']; ?>)">
                    <div class="episode-thumbnail">
                        <?php if ($episode['thumbnail_url']): ?>
                            <img src="<?php echo htmlspecialchars($episode['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($episode['title']); ?>">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">
                                <i class="fas fa-image fa-3x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="episode-play-overlay">
                            <i class="fas fa-play fa-3x" style="color: #fff;"></i>
                        </div>
                    </div>
                    <div class="episode-info">
                        <div class="episode-number">
                            Episodio <?php echo $episode['episode_number']; ?>
                            <?php if ($episode['duration']): ?>
                                • <?php echo $episode['duration']; ?> min
                            <?php endif; ?>
                        </div>
                        <div class="episode-title"><?php echo htmlspecialchars($episode['title']); ?></div>
                        <?php if ($episode['description']): ?>
                            <div class="episode-description"><?php echo htmlspecialchars($episode['description']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Similar Content -->
        <?php if (!empty($similarContent)): ?>
        <div class="detail-section">
            <h3>Contenido similar</h3>
            <div class="row-content" style="display: flex; gap: 1rem; overflow-x: auto; padding: 1rem 0;">
                <?php foreach ($similarContent as $item): ?>
                    <?php echo createContentCard($item); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Video Player Modal -->
<div class="modal fade video-player-overlay" id="videoPlayerModal" tabindex="-1" aria-labelledby="videoPlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl video-player-dialog">
        <div class="modal-content bg-black text-white">
            <div class="modal-header border-0 justify-content-end">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-0 pb-4 px-4">
                <div class="video-player-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h3 class="mb-1" id="videoPlayerTitle">Reproduciendo...</h3>
                        <div class="video-player-meta text-muted" id="videoPlayerMeta">Preparando video</div>
                    </div>
                    <div class="d-flex gap-2 video-player-actions">
                        <button type="button" class="btn btn-sm btn-outline-light" id="videoOpenPageBtn">
                            <i class="fas fa-info-circle me-1"></i> Ver ficha completa
                        </button>
                    </div>
                </div>
                <div class="video-player-wrapper">
                    <div class="video-player-loading" id="videoPlayerLoading">
                        <div class="spinner-border text-light" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2 mb-0">Cargando video...</p>
                    </div>
                    <video id="contentPlayer" controls autoplay playsinline controlslist="nodownload" preload="metadata">
                        Tu navegador no soporta el elemento de video.
                    </video>
                </div>
                <p class="video-player-description text-muted mt-3" id="videoPlayerDescription"></p>
            </div>
        </div>
    </div>
</div>

<style>
    .video-player-overlay .modal-dialog {
        max-width: min(1200px, 92vw);
    }
    .video-player-dialog .modal-content {
        border-radius: 12px;
        background: linear-gradient(180deg, #050505 0%, #101010 100%);
        box-shadow: 0 25px 60px rgba(0,0,0,0.6);
    }
    .video-player-wrapper {
        position: relative;
        width: 100%;
        padding-top: 56.25%;
        border-radius: 10px;
        overflow: hidden;
        background: #000;
    }
    .video-player-wrapper video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #000;
        border-radius: 10px;
    }
    .video-player-header h3 {
        font-weight: 600;
    }
    .video-player-meta {
        font-size: 0.95rem;
    }
    .video-player-description {
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .video-player-loading {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 0.75rem;
        background: rgba(0, 0, 0, 0.6);
        z-index: 2;
    }
    .video-player-loading p {
        margin: 0;
        font-weight: 500;
    }
    @media (max-width: 768px) {
        .video-player-dialog .modal-content {
            border-radius: 0;
        }
        .video-player-wrapper {
            padding-top: 56.25%;
        }
    }
</style>

<script>
const CONTENT_BASE_URL = '<?php echo rtrim(SITE_URL, '/'); ?>';

function playContent(id, type) {
    // Esperar a que netflix-gallery.js se cargue y luego usar su función
    // o usar directamente si el modal está disponible
    const videoModal = document.getElementById('videoPlayerModal');
    const modalPlayer = document.getElementById('contentPlayer');
    
    if (videoModal && modalPlayer) {
        // El modal está disponible, usar la función de netflix-gallery.js
        if (typeof window.playContentFromGallery === 'function') {
            window.playContentFromGallery(id, type);
        } else {
            // Si netflix-gallery.js aún no se ha cargado, esperar un momento
            setTimeout(() => {
                if (typeof window.playContentFromGallery === 'function') {
                    window.playContentFromGallery(id, type);
                } else {
                    // Fallback: redirigir a watch.php
                    window.location.href = `${CONTENT_BASE_URL}/watch.php?id=${id}&type=${type}`;
                }
            }, 100);
        }
    } else {
        // No hay modal disponible, redirigir directamente a watch.php
        window.location.href = `${CONTENT_BASE_URL}/watch.php?id=${id}&type=${type}`;
    }
}

function playEpisode(episodeId) {
    window.location.href = `${CONTENT_BASE_URL}/watch.php?episode_id=${episodeId}`;
}

function addToMyList(id) {
    fetch(`${CONTENT_BASE_URL}/api/watchlist/add.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ content_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Añadido a Mi lista');
        } else {
            alert(data.message || 'Error al añadir');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al añadir a la lista');
    });
}

function shareContent() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo htmlspecialchars($content['title'], ENT_QUOTES); ?>',
            text: '<?php echo htmlspecialchars($content['description'], ENT_QUOTES); ?>',
            url: window.location.href
        });
    } else {
        // Fallback: copiar al portapapeles
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Enlace copiado al portapapeles');
        });
    }
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

