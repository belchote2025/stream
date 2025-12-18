<?php
// Start output buffering
ob_start();

// Include configuration and database connection
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';
require_once __DIR__ . '/includes/image-helper.php';
require_once __DIR__ . '/includes/imdb-helper.php';

// Set page title
$pageTitle = 'Inicio - ' . SITE_NAME;

// Get database connection
$db = getDbConnection();
$baseUrl = rtrim(SITE_URL, '/');

// Enable GZIP compression if not already enabled
if (!headers_sent() && !in_array('ob_gzhandler', ob_list_handlers())) {
    ob_start('ob_gzhandler');
}

// Start measuring execution time
$startTime = microtime(true);

// Function to get content with caching
function getCachedContent($callback, $cacheKey, $params = [], $ttl = 3600) {
    $cacheFile = __DIR__ . '/cache/' . md5($cacheKey) . '.cache';
    
    // Check if cache exists and is still valid
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cachedData = file_get_contents($cacheFile);
        $decoded = json_decode($cachedData, true);
        
        // Validar que la decodificación fue exitosa y que el contenido es válido
        if ($decoded !== null && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        // Si el cache está corrupto, eliminarlo
        @unlink($cacheFile);
    }
    
    // If not, generate new content
    $content = call_user_func_array($callback, $params);
    
    // Cache the result
    if (!is_dir(__DIR__ . '/cache')) {
        mkdir(__DIR__ . '/cache', 0755, true);
    }
    file_put_contents($cacheFile, json_encode($content));
    
    return $content;
}

// Get all content in parallel using caching
$contentTypes = [
    'featuredContent' => function() use ($db) {
        $content = getLatestWithTrailers($db, 5);
        $data = empty($content) ? getFeaturedContent($db, 5) : $content;
        return addImdbImagesToContent($data);
    },
    'recentMovies' => function() use ($db) {
        return addImdbImagesToContent(getRecentlyAdded($db, 'movie', 10));
    },
    'recentSeries' => function() use ($db) {
        return addImdbImagesToContent(getRecentlyAdded($db, 'series', 10));
    },
    'popularMovies' => function() use ($db) {
        return addImdbImagesToContent(getMostViewed($db, 'movie', 10));
    },
    'popularSeries' => function() use ($db) {
        return addImdbImagesToContent(getMostViewed($db, 'series', 10));
    },
    'imdbMovies' => function() use ($db) {
        return addImdbImagesToContent(getImdbTopContent($db, 10));
    },
    'localVideos' => function() use ($db) {
        return addImdbImagesToContent(getLocalUploadedVideos($db, 10));
    }
];

$results = [];
foreach ($contentTypes as $key => $callback) {
    // Cache for 1 hour
    $results[$key] = getCachedContent($callback, $key . '_' . date('Y-m-d-H'));
}

extract($results);

// Si el usuario no está autenticado, mostrar página splash
if (!isLoggedIn()) {
    include __DIR__ . '/splash.php';
    exit;
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <?php if (!empty($featuredContent)): ?>
        <?php foreach ($featuredContent as $index => $content): 
            // Procesar poster_url primero
            $posterUrl = getImageUrl($content['poster_url'] ?? '', '/assets/img/default-poster.svg');
            
            // Procesar backdrop_url: priorizar backdrop_url, luego poster_url, luego default
            $backdropUrl = null;
            
            // Si hay backdrop_url y no es default, usarlo
            if (!empty($content['backdrop_url']) && strpos($content['backdrop_url'], 'default-') === false) {
                $backdropUrl = getImageUrl($content['backdrop_url'], '/assets/img/default-backdrop.svg');
            }
            // Si no hay backdrop válido pero hay poster válido, usar el poster como backdrop
            elseif (!empty($posterUrl) && strpos($posterUrl, 'default-poster.svg') === false) {
                $backdropUrl = $posterUrl;
            }
            // Si todo falla, usar el default backdrop
            else {
                $backdropUrl = getImageUrl('/assets/img/default-backdrop.svg', '/assets/img/default-backdrop.svg');
            }
            
            $title = htmlspecialchars($content['title'] ?? '');
            $description = $content['description'] ?? $content['overview'] ?? '';
            $overview = htmlspecialchars(substr($description, 0, 200) . (strlen($description) > 200 ? '...' : ''));
            $trailerUrl = htmlspecialchars($content['trailer_url'] ?? '');
            $contentId = $content['id'] ?? '';
        ?>
            <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>" data-trailer="<?php echo $trailerUrl; ?>">
                <div class="hero-backdrop" style="background-image: url('<?php echo htmlspecialchars($backdropUrl, ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center center; background-repeat: no-repeat;"></div>
                <div class="hero-content">
                    <h1 class="hero-title"><?php echo $title; ?></h1>
                    <p class="hero-description"><?php echo $overview; ?></p>
                    <div class="hero-actions">
                        <a href="<?php echo $baseUrl; ?>/watch.php?id=<?php echo $contentId; ?>" class="btn btn-primary" aria-label="Reproducir <?php echo $title; ?>">
                            <i class="fas fa-play" aria-hidden="true"></i> Reproducir
                        </a>
                        <button class="btn btn-outline" data-action="info" data-id="<?php echo $contentId; ?>" aria-label="Más información sobre <?php echo $title; ?>">
                            <i class="fas fa-info-circle" aria-hidden="true"></i> Más información
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="hero-slide active">
            <div class="hero-backdrop" style="background-image: url('<?php echo $baseUrl; ?>/assets/img/default-backdrop.svg'); background-size: cover;"></div>
        </div>
    <?php endif; ?>
    <div class="hero-overlay"></div>
</section>

    <!-- Content Rows -->
<main class="content-rows fade-in">
    <!-- Continue Watching (for logged-in users) -->
    <?php if (isLoggedIn()): ?>
        <?php 
        // Obtener contenido para continuar viendo
        $continueQuery = "
            SELECT 
                c.id,
                c.title,
                c.type,
                c.poster_url,
                c.backdrop_url,
                c.release_year,
                c.duration,
                c.rating,
                ph.progress,
                ph.duration as total_duration,
                ph.episode_id,
                e.title as episode_title,
                e.season_number,
                e.episode_number
            FROM playback_history ph
            INNER JOIN content c ON ph.content_id = c.id
            LEFT JOIN episodes e ON ph.episode_id = e.id
            WHERE ph.user_id = :user_id
            AND ph.completed = 0
            AND ph.progress > 0
            ORDER BY ph.updated_at DESC
            LIMIT 10
        ";
        
        $continueStmt = $db->prepare($continueQuery);
        $continueStmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $continueStmt->execute();
        $continueWatching = $continueStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($continueWatching)): 
        ?>
            <div class="row-container">
                <div class="row-header">
                    <h2 class="row-title">Continuar viendo</h2>
                    <a href="<?php echo $baseUrl; ?>/my-list" class="row-link">Ver todo</a>
                </div>
                <div class="row-nav prev">
                    <i class="fas fa-chevron-left"></i>
                </div>
                <div class="row-content" id="continue-watching">
                    <?php foreach ($continueWatching as $item): 
                        $progressPercent = $item['total_duration'] > 0 
                            ? round(($item['progress'] / $item['total_duration']) * 100) 
                            : 0;
                        $watchUrl = $item['episode_id'] 
                            ? $baseUrl . "/watch.php?id={$item['id']}&episode_id={$item['episode_id']}"
                            : $baseUrl . "/watch.php?id={$item['id']}";
                    ?>
                        <div class="content-card" data-id="<?php echo $item['id']; ?>" data-type="<?php echo $item['type']; ?>" onclick="window.location.href='<?php echo $watchUrl; ?>';" style="cursor: pointer;">
                            <?php if ($progressPercent > 0): ?>
                                <div class="progress-bar" style="position: absolute; bottom: 0; left: 0; right: 0; height: 3px; background: rgba(255,255,255,0.3); z-index: 2;">
                                    <div class="progress" style="height: 100%; background: #e50914; width: <?php echo $progressPercent; ?>%;"></div>
                                </div>
                            <?php endif; ?>
                            <img 
                                <?php 
                                $itemPosterUrl = getImageUrl($item['poster_url'] ?? '', '/assets/img/default-poster.svg');
                                ?>
                                src="<?php echo htmlspecialchars($itemPosterUrl); ?>" 
                                alt="<?php echo htmlspecialchars($item['title']); ?>"
                                loading="lazy"
                                onerror="this.onerror=null; this.src='<?php echo $baseUrl; ?>/assets/img/default-poster.svg'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
                                style="background: linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%);"
                            >
                            <div class="content-info">
                                <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                <?php if ($item['episode_id']): ?>
                                    <div style="font-size: 0.8rem; color: #999; margin-bottom: 0.5rem;">
                                        T<?php echo $item['season_number']; ?>E<?php echo $item['episode_number']; ?>: <?php echo htmlspecialchars($item['episode_title']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="content-actions">
                                    <button class="action-btn" data-action="play" data-id="<?php echo $item['id']; ?>" title="Continuar viendo" onclick="event.stopPropagation(); window.location.href='<?php echo $watchUrl; ?>';">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="row-nav next">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Películas populares -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Películas populares</h2>
            <a href="<?php echo $baseUrl; ?>/movies.php?sort=popular" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="popular-movies" data-dynamic="true" data-type="movie" data-sort="popular" data-limit="12" data-cache-key="movies">
            <p class="loading-placeholder">Cargando películas populares...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Series populares -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Series populares</h2>
            <a href="<?php echo $baseUrl; ?>/series.php?sort=popular" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="popular-series" data-dynamic="true" data-type="series" data-sort="popular" data-limit="12" data-cache-key="series">
            <p class="loading-placeholder">Cargando series populares...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Películas recientes -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Películas recientes</h2>
            <a href="<?php echo $baseUrl; ?>/movies.php?sort=recent" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="recent-movies" data-dynamic="true" data-type="movie" data-sort="recent" data-limit="12">
            <p class="loading-placeholder">Cargando películas recientes...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Series recientes -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Series recientes</h2>
            <a href="<?php echo $baseUrl; ?>/series.php?sort=recent" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="recent-series" data-dynamic="true" data-type="series" data-sort="recent" data-limit="12">
            <p class="loading-placeholder">Cargando series recientes...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Películas destacadas en IMDb -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Películas destacadas en IMDb</h2>
            <a href="<?php echo $baseUrl; ?>/movies.php?source=imdb" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="imdb-movies" data-dynamic="true" data-type="movie" data-source="imdb" data-sort="popular" data-limit="12" data-cache-key="imdb-movies">
            <p class="loading-placeholder">Cargando películas destacadas en IMDb...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Videos locales -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Videos locales</h2>
            <a href="<?php echo $baseUrl; ?>/content.php?source=local" class="row-link">Ver todo</a>
        </div>
        <div class="row-nav prev">
            <i class="fas fa-chevron-left"></i>
        </div>
        <div class="row-content" id="local-videos" data-dynamic="true" data-type="movie" data-source="local" data-sort="recent" data-limit="12" data-cache-key="local-videos">
            <p class="loading-placeholder">Cargando videos locales...</p>
        </div>
        <div class="row-nav next">
            <i class="fas fa-chevron-right"></i>
        </div>
    </div>

    <!-- Recommended For You (for logged-in users) -->
    <?php if (isLoggedIn()): ?>
        <div class="row-container">
            <div class="row-header">
                <h2 class="row-title">Recomendado para ti</h2>
                <a href="/recommended" class="row-link">Ver todo</a>
            </div>
            <div class="row-nav prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="row-content" id="recommended" data-dynamic="true" data-endpoint="/api/content/recommended.php" data-limit="12" data-cache-key="recommended">
                <p class="loading-placeholder">Buscando recomendaciones personalizadas...</p>
            </div>
            <div class="row-nav next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- Content Modal -->
<div class="modal fade" id="contentModal" tabindex="-1" aria-labelledby="contentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contentModalLabel">Detalles del contenido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be loaded dynamically -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
        </div>
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
                <div id="videoEpisodeSelector" class="video-episode-selector" style="display:none;">
                    <label for="videoEpisodeSelect" class="form-label text-muted mb-1">Episodios</label>
                    <select id="videoEpisodeSelect" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <!-- Opciones generadas dinámicamente -->
                    </select>
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
    .video-episode-selector {
        margin-top: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .video-episode-selector .form-select {
        max-width: 260px;
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
// Verificar carga de imágenes del hero y posters
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 Verificando carga de imágenes...');
    
    // Verificar backdrops del hero
    const heroBackdrops = document.querySelectorAll('.hero-backdrop');
    console.log(`📸 Backdrops del hero encontrados: ${heroBackdrops.length}`);
    
    let backdropLoaded = 0;
    let backdropErrors = 0;
    
    heroBackdrops.forEach((backdrop, index) => {
        const bgImage = window.getComputedStyle(backdrop).backgroundImage;
        const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
        const imageUrl = urlMatch ? urlMatch[1] : null;
        
        if (imageUrl && imageUrl !== 'none' && imageUrl !== 'null') {
            console.log(`  Backdrop ${index + 1}: ${imageUrl}`);
            
            // Verificar si la imagen se carga
            const img = new Image();
            img.onload = function() {
                backdropLoaded++;
                console.log(`  ✅ Backdrop ${index + 1} cargado correctamente`);
            };
            img.onerror = function() {
                backdropErrors++;
                console.error(`  ❌ Backdrop ${index + 1} NO se pudo cargar: ${imageUrl}`);
            };
            img.src = imageUrl;
        } else {
            console.warn(`  ⚠️ Backdrop ${index + 1}: Sin URL configurada`);
        }
    });
    
    // Esperar a que las fichas se carguen dinámicamente
    function checkPosters() {
        const contentPosters = document.querySelectorAll('.content-card img, .content-poster img, .content-item img');
        
        if (contentPosters.length === 0) {
            // Reintentar después de 1 segundo
            setTimeout(checkPosters, 1000);
            return;
        }
        
        console.log(`\n📸 Posters de fichas encontrados: ${contentPosters.length}`);
        
        let loadedCount = 0;
        let errorCount = 0;
        
        contentPosters.forEach((img, index) => {
            if (index < 10) { // Verificar los primeros 10
                const imgUrl = img.src || img.dataset.src;
                
                if (imgUrl && imgUrl !== 'no encontrada' && !imgUrl.includes('data:')) {
                    console.log(`  Poster ${index + 1}: ${imgUrl.substring(0, 80)}...`);
                    
                    if (img.complete && img.naturalHeight > 0) {
                        loadedCount++;
                        console.log(`  ✅ Poster ${index + 1} ya estaba cargado`);
                    } else {
                        img.onload = function() {
                            loadedCount++;
                            console.log(`  ✅ Poster ${index + 1} cargado`);
                        };
                        img.onerror = function() {
                            errorCount++;
                            console.error(`  ❌ Poster ${index + 1} NO se pudo cargar`);
                        };
                    }
                }
            }
        });
        
        setTimeout(() => {
            console.log(`\n📊 Resumen de carga de imágenes:`);
            console.log(`  Backdrops: ✅ ${backdropLoaded} | ❌ ${backdropErrors}`);
            console.log(`  Posters: ✅ ${loadedCount} | ❌ ${errorCount}`);
            console.log(`  📸 Total posters verificados: ${Math.min(contentPosters.length, 10)}`);
        }, 2000);
    }
    
    // Iniciar verificación de posters después de un breve delay
    setTimeout(checkPosters, 2000);
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>
