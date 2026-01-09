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

// Optimización: Solo cargar contenido destacado en el servidor, el resto se carga asíncronamente
// Esto reduce significativamente el tiempo de carga inicial
$featuredContent = getCachedContent(function() use ($db) {
    $content = getLatestWithTrailers($db, 5);
    $data = empty($content) ? getFeaturedContent($db, 5) : $content;
    // No llamar addImdbImagesToContent aquí para evitar latencia - se hace en el cliente si es necesario
    return $data;
}, 'featured_' . date('Y-m-d-H'), [], 1800); // Cache de 30 minutos

// Si el usuario no está autenticado, mostrar página splash
if (!isLoggedIn()) {
    include __DIR__ . '/splash.php';
    exit;
}

// Include header
include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/modern-home.css">

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

    <!-- Content Rows - Carga asíncrona optimizada -->
<main class="content-rows fade-in">
    <!-- Continue Watching (for logged-in users) - Carga asíncrona -->
    <?php if (isLoggedIn()): ?>
        <div class="row-container" id="continue-watching-container">
            <div class="row-header">
                <h2 class="row-title">Continuar viendo</h2>
                <a href="<?php echo $baseUrl; ?>/my-list" class="row-link">Ver todo</a>
            </div>
            <div class="row-nav prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="row-content" id="continue-watching" data-dynamic="true" data-endpoint="/api/continue-watching.php" data-limit="12">
                <div class="row-items" style="display: flex; gap: 0.75rem;">
                    <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="skeleton-card"></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="row-nav next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
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
        <div class="row-content" id="popular-movies" data-dynamic="true" data-type="movie" data-sort="popular" data-limit="12">
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
        <div class="row-content" id="popular-series" data-dynamic="true" data-type="series" data-sort="popular" data-limit="12">
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
        <div class="row-content" id="imdb-movies" data-dynamic="true" data-type="movie" data-source="imdb" data-sort="popular" data-limit="12">
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
        <div class="row-content" id="local-videos" data-dynamic="true" data-type="movie" data-source="local" data-sort="recent" data-limit="12">
            <div class="row-items" style="display: flex; gap: 0.75rem;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton-card"></div>
                <?php endfor; ?>
            </div>
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
            <div class="row-content" id="recommended" data-dynamic="true" data-endpoint="/api/recommendations/improved.php" data-limit="12">
                <div class="row-items" style="display: flex; gap: 0.75rem;">
                    <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="skeleton-card"></div>
                    <?php endfor; ?>
                </div>
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

<script src="<?php echo $baseUrl; ?>/js/modern-home-loader.js?v=<?php echo time(); ?>"></script>
<script>
// Optimización: Precargar imágenes del hero de forma inteligente
document.addEventListener('DOMContentLoaded', function() {
    // Precargar solo la primera imagen del hero para carga rápida
    const firstHeroBackdrop = document.querySelector('.hero-slide.active .hero-backdrop');
    if (firstHeroBackdrop) {
        const bgImage = window.getComputedStyle(firstHeroBackdrop).backgroundImage;
        const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
        if (urlMatch && urlMatch[1] && urlMatch[1] !== 'none') {
            const img = new Image();
            img.src = urlMatch[1];
        }
    }
    
    // Precargar las siguientes imágenes del hero en segundo plano
    setTimeout(() => {
        const heroBackdrops = document.querySelectorAll('.hero-slide:not(.active) .hero-backdrop');
        heroBackdrops.forEach(backdrop => {
            const bgImage = window.getComputedStyle(backdrop).backgroundImage;
            const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
            if (urlMatch && urlMatch[1] && urlMatch[1] !== 'none') {
                const img = new Image();
                img.src = urlMatch[1];
            }
        });
    }, 1000);
});
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>
