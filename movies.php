<?php
// Incluir configuración y funciones
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';
require_once __DIR__ . '/includes/image-helper.php';

// Establecer el título de la página
$pageTitle = 'Películas - ' . SITE_NAME;

// Obtener conexión a la base de datos
$db = getDbConnection();
$baseUrl = rtrim(SITE_URL, '/');

// Obtener películas
$movies = getRecentlyAdded($db, 'movie', 50);

// Incluir encabezado
include __DIR__ . '/includes/header.php';
?>

<style>
.movies-page {
    padding-top: 100px;
    min-height: 100vh;
    background: linear-gradient(180deg, #141414 0%, #1a1a1a 100%);
}

.page-header {
    padding: 3rem 4% 2rem;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
}

.page-title {
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 900;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #e50914 50%, #f5f5f5 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 10px rgba(229, 9, 20, 0.3);
    color: #fff;
}

.movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 2rem 4%;
}

.movie-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
}

.movie-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #e50914;
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
}

.movie-poster {
    width: 100%;
    height: 300px;
    object-fit: cover;
    display: block;
}

.movie-info {
    padding: 1rem;
}

.movie-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.movie-meta {
    color: #999;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.movie-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.movie-badge.premium {
    background: rgba(255, 193, 7, 0.9);
    color: #000;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    display: block;
}
</style>

<div class="movies-page">
    <div class="page-header">
        <h1 class="page-title">Películas</h1>
        <p class="page-subtitle" style="color: #999; font-size: 1.1rem;">Explora nuestra colección de películas</p>
    </div>
    
    <div class="movies-grid">
        <?php if (!empty($movies)): ?>
            <?php foreach ($movies as $movie): 
                $posterUrl = getImageUrl($movie['poster_url'] ?? $movie['backdrop_url'] ?? '', '/assets/img/default-poster.svg');
                $isPremium = isset($movie['is_premium']) && $movie['is_premium'];
                $year = $movie['year'] ?? $movie['release_year'] ?? '';
                $duration = $movie['duration'] ?? '';
                $rating = isset($movie['rating']) && $movie['rating'] > 0 ? number_format($movie['rating'], 1) : '';
            ?>
                <div class="movie-card" onclick="window.location.href='<?php echo $baseUrl; ?>/content.php?id=<?php echo $movie['id']; ?>'">
                    <?php if ($isPremium): ?>
                        <span class="movie-badge premium">
                            <i class="fas fa-crown"></i> PREMIUM
                        </span>
                    <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($posterUrl); ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                         class="movie-poster poster-clickable" 
                         loading="lazy"
                         onclick="handleMoviePosterClick(<?php echo $movie['id']; ?>, '<?php echo htmlspecialchars($movie['title'], ENT_QUOTES); ?>', <?php echo $year ?: 'null'; ?>)"
                         style="cursor: pointer;"
                         title="Clic para buscar torrents">
                    <div class="movie-info">
                        <div class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="movie-meta">
                            <?php if ($year): ?>
                                <span><?php echo $year; ?></span>
                            <?php endif; ?>
                            <?php if ($duration): ?>
                                <span>•</span>
                                <span><?php echo $duration; ?> min</span>
                            <?php endif; ?>
                            <?php if ($rating): ?>
                                <span>•</span>
                                <span><i class="fas fa-star" style="color: #ffc107;"></i> <?php echo $rating; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-film"></i>
                <h3>No hay películas disponibles</h3>
                <p>Próximamente agregaremos más contenido</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir pie de página
include __DIR__ . '/includes/footer.php';
?>
