<?php
/**
 * Página de categorías/géneros
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

$pageTitle = 'Categorías - ' . SITE_NAME;
$db = getDbConnection();

// Obtener todos los géneros con contenido
$genresQuery = "
    SELECT 
        g.id,
        g.name,
        g.slug,
        COUNT(DISTINCT c.id) as content_count
    FROM genres g
    LEFT JOIN content_genres cg ON g.id = cg.genre_id
    LEFT JOIN content c ON cg.content_id = c.id
    GROUP BY g.id
    HAVING content_count > 0
    ORDER BY content_count DESC, g.name ASC
";

$genresStmt = $db->query($genresQuery);
$genres = $genresStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<style>
.categories-page {
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

.genres-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 2rem;
    padding: 2rem 4%;
}

.genre-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
}

.genre-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #e50914;
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
}

.genre-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #e50914;
}

.genre-name {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
}

.genre-count {
    color: #999;
    font-size: 0.9rem;
}
</style>

<div class="categories-page">
    <div class="page-header">
        <h1 class="page-title">Explorar por Género</h1>
        <p class="page-subtitle" style="color: #999; font-size: 1.1rem;">Descubre contenido por categorías</p>
    </div>
    
    <div class="genres-grid">
        <?php foreach ($genres as $genre): ?>
            <div class="genre-card" onclick="window.location.href='search.php?genre=<?php echo $genre['id']; ?>';">
                <div class="genre-icon">
                    <?php
                    $icons = [
                        'Acción' => 'fa-fist-raised',
                        'Aventura' => 'fa-mountain',
                        'Comedia' => 'fa-laugh',
                        'Drama' => 'fa-theater-masks',
                        'Ciencia Ficción' => 'fa-rocket',
                        'Terror' => 'fa-ghost',
                        'Romance' => 'fa-heart',
                        'Documental' => 'fa-video',
                        'Animación' => 'fa-magic',
                        'Crimen' => 'fa-user-secret',
                        'Suspense' => 'fa-eye',
                        'Fantasía' => 'fa-wand-magic-sparkles',
                        'Thriller' => 'fa-bolt',
                        'Misterio' => 'fa-search',
                        'Western' => 'fa-horse'
                    ];
                    $icon = $icons[$genre['name']] ?? 'fa-film';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="genre-name"><?php echo htmlspecialchars($genre['name']); ?></div>
                <div class="genre-count"><?php echo $genre['content_count']; ?> título(s)</div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
include __DIR__ . '/includes/footer.php';
?>

