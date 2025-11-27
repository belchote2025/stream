<?php
/**
 * Página de Mi Lista - Contenido guardado por el usuario
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('/login.php');
}

$pageTitle = 'Mi Lista - ' . SITE_NAME;
$db = getDbConnection();
$userId = $_SESSION['user_id'];

// Obtener contenido de la lista del usuario
$query = "
    SELECT 
        c.id,
        c.title,
        c.slug,
        c.type,
        c.description,
        c.release_year,
        c.duration,
        c.rating,
        c.poster_url,
        c.backdrop_url,
        c.trailer_url,
        c.is_premium,
        uf.created_at as added_date,
        GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
    FROM user_favorites uf
    INNER JOIN content c ON uf.content_id = c.id
    LEFT JOIN content_genres cg ON c.id = cg.content_id
    LEFT JOIN genres g ON cg.genre_id = g.id
    WHERE uf.user_id = :user_id
    GROUP BY c.id
    ORDER BY uf.created_at DESC
";

$stmt = $db->prepare($query);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$myList = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<style>
.my-list-page {
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

.page-subtitle {
    color: #999;
    font-size: 1.1rem;
}

.content-section {
    padding: 2rem 4%;
}

.list-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: #fff;
}

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 1rem;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    color: #999;
    font-size: 1rem;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.filter-tab:hover,
.filter-tab.active {
    color: #fff;
    border-bottom-color: #e50914;
}
</style>

<div class="my-list-page">
    <div class="page-header">
        <h1 class="page-title">Mi Lista</h1>
        <p class="page-subtitle">Tu colección personal de películas y series</p>
    </div>
    
    <div class="content-section">
        <?php if (!empty($myList)): ?>
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">Todo (<?php echo count($myList); ?>)</button>
                <button class="filter-tab" data-filter="movie">Películas</button>
                <button class="filter-tab" data-filter="series">Series</button>
            </div>
            
            <div class="list-grid" id="listGrid">
                <?php foreach ($myList as $item): ?>
                    <div class="content-card-wrapper" data-type="<?php echo $item['type']; ?>">
                        <?php 
                        // Formatear el item para createContentCard
                        $formattedItem = [
                            'id' => $item['id'],
                            'title' => $item['title'],
                            'type' => $item['type'],
                            'release_year' => $item['release_year'],
                            'duration' => $item['duration'],
                            'rating' => $item['rating'],
                            'poster_url' => $item['poster_url'],
                            'backdrop_url' => $item['backdrop_url'],
                            'is_premium' => $item['is_premium']
                        ];
                        echo createContentCard($formattedItem);
                        ?>
                        <button class="btn btn-sm btn-danger mt-2" onclick="removeFromList(<?php echo $item['id']; ?>)">
                            <i class="fas fa-trash"></i> Quitar
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bookmark"></i>
                <h3>Tu lista está vacía</h3>
                <p>Comienza a añadir películas y series que te gusten</p>
                <a href="<?php echo rtrim(SITE_URL, '/'); ?>/" class="btn btn-primary mt-3">
                    <i class="fas fa-search"></i> Explorar contenido
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const BASE_URL = '<?php echo rtrim(SITE_URL, '/'); ?>';
// Filtrado de contenido
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.dataset.filter;
        const cards = document.querySelectorAll('.content-card-wrapper');
        
        cards.forEach(card => {
            if (filter === 'all' || card.dataset.type === filter) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

function removeFromList(contentId) {
    if (!confirm('¿Quitar este contenido de tu lista?')) return;
    
    fetch(`${BASE_URL}/api/watchlist/remove.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ content_id: contentId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error al quitar de la lista');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al quitar de la lista');
    });
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

