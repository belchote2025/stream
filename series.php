<?php
// Incluir configuración y funciones
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';
require_once __DIR__ . '/includes/image-helper.php';

// Establecer el título de la página
$pageTitle = 'Series - ' . SITE_NAME;

// Obtener conexión a la base de datos
$db = getDbConnection();

// Obtener series recientes
$recentSeries = getRecentlyAdded($db, 'series', 50);

// Incluir encabezado
include __DIR__ . '/includes/header.php';
?>

<style>
.series-page {
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
}

.series-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 2rem 4%;
}

.series-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
}

.series-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #e50914;
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
}

.series-poster {
    width: 100%;
    height: 300px;
    object-fit: cover;
    display: block;
}

.series-info {
    padding: 1rem;
}

.series-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.series-meta {
    color: #999;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.series-badge {
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

.series-badge.premium {
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

<div class="series-page">
    <div class="page-header">
        <h1 class="page-title">Series</h1>
        <p class="page-subtitle" style="color: #999; font-size: 1.1rem;">Explora nuestra colección de series</p>
    </div>
    
    <div class="series-grid">
        <?php if (!empty($recentSeries)): ?>
            <?php foreach ($recentSeries as $series): 
                $posterUrl = getImageUrl($series['poster_url'] ?? $series['backdrop_url'] ?? '', '/streaming-platform/assets/img/default-poster.svg');
                $isPremium = isset($series['is_premium']) && $series['is_premium'];
                $year = $series['year'] ?? $series['release_year'] ?? '';
                $rating = isset($series['rating']) && $series['rating'] > 0 ? number_format($series['rating'], 1) : '';
                
                // Obtener número de temporadas
                $seasonsQuery = "SELECT COUNT(DISTINCT season_number) as seasons FROM episodes WHERE series_id = :id";
                $seasonsStmt = $db->prepare($seasonsQuery);
                $seasonsStmt->bindValue(':id', $series['id'], PDO::PARAM_INT);
                $seasonsStmt->execute();
                $seasonsData = $seasonsStmt->fetch(PDO::FETCH_ASSOC);
                $seasons = $seasonsData['seasons'] ?? 0;
            ?>
                <div class="series-card" onclick="window.location.href='/streaming-platform/content.php?id=<?php echo $series['id']; ?>'">
                    <?php if ($isPremium): ?>
                        <span class="series-badge premium">
                            <i class="fas fa-crown"></i> PREMIUM
                        </span>
                    <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($posterUrl); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="series-poster" loading="lazy">
                    <div class="series-info">
                        <div class="series-title"><?php echo htmlspecialchars($series['title']); ?></div>
                        <div class="series-meta">
                            <?php if ($year): ?>
                                <span><?php echo $year; ?></span>
                            <?php endif; ?>
                            <?php if ($seasons > 0): ?>
                                <span>•</span>
                                <span><?php echo $seasons; ?> Temporada<?php echo $seasons > 1 ? 's' : ''; ?></span>
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
                <i class="fas fa-tv"></i>
                <h3>No hay series disponibles</h3>
                <p>Próximamente agregaremos más contenido</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Incluir pie de página
include __DIR__ . '/includes/footer.php';
?>
