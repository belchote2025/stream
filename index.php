<?php
// Include configuration and database connection
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';
require_once __DIR__ . '/includes/image-helper.php';

// Set page title
$pageTitle = 'Inicio - ' . SITE_NAME;

// Get database connection
$db = getDbConnection();
$baseUrl = rtrim(SITE_URL, '/');

// Get content for the hero section - últimas novedades con trailers
$featuredContent = getLatestWithTrailers($db, 5);
// Si no hay contenido con trailers, usar featured como fallback
if (empty($featuredContent)) {
    $featuredContent = getFeaturedContent($db, 5);
}

// Get recently added content
$recentMovies = getRecentlyAdded($db, 'movie', 10);
$recentSeries = getRecentlyAdded($db, 'series', 10);

// Get most viewed content
$popularMovies = getMostViewed($db, 'movie', 10);
$popularSeries = getMostViewed($db, 'series', 10);

// Additional curated sections
$imdbMovies = getImdbTopContent($db, 10);
$localVideos = getLocalUploadedVideos($db, 10);

// Include header
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <?php if (!empty($featuredContent)): ?>
        <?php foreach ($featuredContent as $index => $content): ?>
            <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>" data-trailer="<?php echo htmlspecialchars($content['trailer_url'] ?? ''); ?>">
                <?php 
                $backdropUrl = getImageUrl($content['backdrop_url'] ?? $content['poster_url'] ?? '', '/assets/img/default-backdrop.svg');
                ?>
                <!-- Video trailer -->
                <div class="hero-video-container">
                    <?php if (!empty($content['trailer_url'])): ?>
                        <?php
                        // Detectar si es YouTube
                        $trailerUrl = $content['trailer_url'];
                        $isYouTube = preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $trailerUrl, $matches);
                        if ($isYouTube && isset($matches[1])) {
                            $youtubeId = $matches[1];
                        ?>
                            <div class="hero-video-wrapper">
                                <div class="hero-youtube-player" data-video-id="<?php echo htmlspecialchars($youtubeId); ?>" data-index="<?php echo $index; ?>"></div>
                            </div>
                        <?php } else { ?>
                            <video class="hero-trailer-video" muted loop playsinline data-index="<?php echo $index; ?>" preload="metadata">
                                <source src="<?php echo htmlspecialchars($trailerUrl); ?>" type="video/mp4">
                            </video>
                        <?php } ?>
                    <?php endif; ?>
                </div>
                <!-- Fallback backdrop image -->
                <div class="hero-backdrop" style="background-image: url('<?php echo htmlspecialchars($backdropUrl); ?>');"></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="hero-slide active">
            <div class="hero-backdrop" style="background-image: url('<?php echo $baseUrl; ?>/assets/img/default-backdrop.svg'); background-size: cover;"></div>
        </div>
    <?php endif; ?>
    
    <div class="hero-content">
        <?php if (!empty($featuredContent)): ?>
            <?php $firstContent = reset($featuredContent); ?>
            <h1 class="hero-title"><?php echo htmlspecialchars($firstContent['title']); ?></h1>
            <p class="hero-description"><?php echo htmlspecialchars(mb_strimwidth($firstContent['description'], 0, 200, '...')); ?></p>
            <div class="hero-actions">
                <button class="btn btn-primary" data-action="play" data-id="<?php echo $firstContent['id']; ?>">
                    <i class="fas fa-play"></i> Reproducir
                </button>
                <button class="btn btn-secondary" data-action="info" data-id="<?php echo $firstContent['id']; ?>">
                    <i class="fas fa-info-circle"></i> Más información
                </button>
            </div>
        <?php else: ?>
            <h1 class="hero-title">Bienvenido a <?php echo SITE_NAME; ?></h1>
            <p class="hero-description">Disfruta de las mejores películas y series en un solo lugar.</p>
            <div class="hero-actions">
                <a href="<?php echo $baseUrl; ?>/register" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Regístrate
                </a>
                <a href="<?php echo $baseUrl; ?>/login" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                </a>
            </div>
        <?php endif; ?>
    </div>
    
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

    <!-- Popular Movies -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Películas populares</h2>
            <a href="/movies/popular" class="row-link">Ver todo</a>
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

    <!-- Recent Series -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Series recientes</h2>
            <a href="/series/recent" class="row-link">Ver todo</a>
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

    <!-- Popular Series -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Series populares</h2>
            <a href="/series/popular" class="row-link">Ver todo</a>
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

    <!-- IMDb Highlighted Movies -->
    <?php if (!empty($imdbMovies)): ?>
        <div class="row-container">
            <div class="row-header">
                <h2 class="row-title">Películas destacadas en IMDb</h2>
                <a href="/movies/imdb" class="row-link">Ver todo</a>
            </div>
            <div class="row-nav prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="row-content" id="imdb-movies">
                <?php foreach ($imdbMovies as $movie): ?>
                    <?php echo createContentCard($movie); ?>
                <?php endforeach; ?>
            </div>
            <div class="row-nav next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    <?php endif; ?>

    <!-- Local Uploaded Videos -->
    <?php if (!empty($localVideos)): ?>
        <div class="row-container">
            <div class="row-header">
                <h2 class="row-title">Videos locales</h2>
                <a href="/videos/local" class="row-link">Ver todo</a>
            </div>
            <div class="row-nav prev">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="row-content" id="local-videos">
                <?php foreach ($localVideos as $item): ?>
                    <?php echo createContentCard($item); ?>
                <?php endforeach; ?>
            </div>
            <div class="row-nav next">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Movies -->
    <div class="row-container">
        <div class="row-header">
            <h2 class="row-title">Películas recientes</h2>
            <a href="/movies/recent" class="row-link">Ver todo</a>
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
<div class="modal fade" id="videoPlayerModal" tabindex="-1" aria-labelledby="videoPlayerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content bg-black">
            <div class="modal-header border-0">
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 d-flex align-items-center justify-content-center">
                <video id="contentPlayer" class="w-100" controls autoplay>
                    Tu navegador no soporta el elemento de video.
                </video>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>
