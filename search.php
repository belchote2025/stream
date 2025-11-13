<?php
/**
 * Página de búsqueda avanzada estilo Netflix
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

$pageTitle = 'Búsqueda - ' . SITE_NAME;
$searchQuery = $_GET['q'] ?? '';
$typeFilter = $_GET['type'] ?? 'all';
$genreFilter = $_GET['genre'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$ratingFilter = $_GET['rating'] ?? '';

// Obtener conexión a la base de datos
$db = getDbConnection();

// Obtener géneros para el filtro
$genres = getGenresForFilter($db);

// Construir filtros
$filters = [];
if (!empty($searchQuery)) {
    $filters['search'] = $searchQuery;
}
if ($typeFilter !== 'all') {
    $filters['type'] = $typeFilter;
}
if (!empty($genreFilter)) {
    $filters['genre'] = $genreFilter;
}
if (!empty($yearFilter)) {
    $filters['year'] = $yearFilter;
}
if (!empty($ratingFilter)) {
    $filters['min_rating'] = $ratingFilter;
}

// Obtener resultados
$results = [];
if (!empty($searchQuery) || $typeFilter !== 'all' || !empty($genreFilter) || !empty($yearFilter) || !empty($ratingFilter)) {
    $results = getGalleryContent($db, $filters);
}

// Incluir header
include __DIR__ . '/includes/header.php';
?>

<style>
.search-page {
    padding-top: 100px;
    min-height: 100vh;
    background: linear-gradient(180deg, #141414 0%, #1a1a1a 100%);
}

.search-header {
    padding: 2rem 4%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(10px);
    position: sticky;
    top: 70px;
    z-index: 100;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.search-form {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-input-group {
    flex: 1;
    min-width: 300px;
    position: relative;
}

.search-input-group input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    color: #fff;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.search-input-group input:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.15);
    border-color: #e50914;
    box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
}

.search-input-group i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.filters-section {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.85rem;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group select,
.filter-group input {
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    color: #fff;
    font-size: 0.9rem;
    min-width: 150px;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #e50914;
}

.search-results {
    padding: 2rem 4%;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.results-count {
    color: #999;
    font-size: 0.9rem;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.no-results {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.no-results i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-results h3 {
    margin-bottom: 0.5rem;
    color: #fff;
}

.clear-filters {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    color: #fff;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.clear-filters:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #e50914;
    color: #e50914;
}
</style>

<div class="search-page">
    <div class="search-header">
        <form method="GET" action="search.php" class="search-form">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input 
                    type="text" 
                    name="q" 
                    value="<?php echo htmlspecialchars($searchQuery); ?>" 
                    placeholder="Buscar películas, series, actores..."
                    autocomplete="off"
                >
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>
        
        <div class="filters-section">
            <div class="filter-group">
                <label>Tipo</label>
                <select name="type" onchange="updateFilter('type', this.value)">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>Todo</option>
                    <option value="movie" <?php echo $typeFilter === 'movie' ? 'selected' : ''; ?>>Películas</option>
                    <option value="series" <?php echo $typeFilter === 'series' ? 'selected' : ''; ?>>Series</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Género</label>
                <select name="genre" onchange="updateFilter('genre', this.value)">
                    <option value="">Todos los géneros</option>
                    <?php foreach ($genres as $genre): ?>
                        <option value="<?php echo $genre['id']; ?>" <?php echo $genreFilter == $genre['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genre['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Año</label>
                <input 
                    type="number" 
                    name="year" 
                    value="<?php echo htmlspecialchars($yearFilter); ?>" 
                    placeholder="Ej: 2020"
                    min="1900"
                    max="<?php echo date('Y') + 1; ?>"
                    onchange="updateFilter('year', this.value)"
                >
            </div>
            
            <div class="filter-group">
                <label>Calificación mínima</label>
                <select name="rating" onchange="updateFilter('rating', this.value)">
                    <option value="">Todas</option>
                    <option value="7" <?php echo $ratingFilter == '7' ? 'selected' : ''; ?>>7+</option>
                    <option value="8" <?php echo $ratingFilter == '8' ? 'selected' : ''; ?>>8+</option>
                    <option value="9" <?php echo $ratingFilter == '9' ? 'selected' : ''; ?>>9+</option>
                </select>
            </div>
            
            <?php if (!empty($searchQuery) || $typeFilter !== 'all' || !empty($genreFilter) || !empty($yearFilter) || !empty($ratingFilter)): ?>
                <div class="filter-group" style="align-self: flex-end;">
                    <a href="search.php" class="clear-filters">
                        <i class="fas fa-times"></i> Limpiar filtros
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="search-results">
        <?php if (!empty($results)): ?>
            <div class="results-header">
                <h2>Resultados de búsqueda</h2>
                <span class="results-count"><?php echo count($results); ?> resultado(s) encontrado(s)</span>
            </div>
            
            <div class="results-grid">
                <?php foreach ($results as $item): ?>
                    <?php echo createContentCard($item); ?>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($searchQuery) || $typeFilter !== 'all' || !empty($genreFilter) || !empty($yearFilter) || !empty($ratingFilter)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No se encontraron resultados</h3>
                <p>Intenta con otros términos de búsqueda o ajusta los filtros</p>
                <a href="search.php" class="btn btn-secondary mt-3">Limpiar búsqueda</a>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>Busca tu contenido favorito</h3>
                <p>Utiliza el formulario de arriba para buscar películas y series</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateFilter(name, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(name, value);
    } else {
        url.searchParams.delete(name);
    }
    window.location = url.toString();
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>

