<?php
// Verificar si el usuario está autenticado
if (!$auth->isAuthenticated()) {
    redirect('/login.php');
}

$db = getDbConnection();
$userId = $user['id'];
$success = '';
$error = '';
$activeTab = $_GET['type'] ?? 'all';
$allowedTabs = ['all', 'movies', 'tv_shows', 'in_progress', 'completed'];

// Validar pestaña activa
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'all';
}

// Procesar eliminación de elementos de la lista
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_list'])) {
    try {
        $contentId = (int)($_POST['content_id'] ?? 0);
        $contentType = $_POST['content_type'] ?? '';
        
        if ($contentId <= 0 || empty($contentType)) {
            throw new Exception('Datos de contenido no válidos');
        }
        
        // Eliminar de la lista de reproducción del usuario
        $stmt = $db->prepare("
            DELETE FROM user_watchlist 
            WHERE user_id = ? AND content_id = ?
        
        ");
        
        if (!$stmt->execute([$userId, $contentId])) {
            throw new Exception('No se pudo eliminar el contenido de tu lista');
        }
        
        $success = 'Contenido eliminado de tu lista correctamente';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

/**
 * Builds the watchlist query based on the active tab
 */
function buildWatchlistQuery($activeTab, $userId) {
    $baseQuery = "
        SELECT 
            wl.*, 
            c.title, 
            c.thumbnail_url, 
            c.content_type, 
            c.release_year,
            c.age_rating,
            c.duration,
            (SELECT COUNT(*) FROM episodes e WHERE e.content_id = c.id) as total_episodes,
            (SELECT COUNT(*) FROM user_watch_progress wp 
              WHERE wp.user_id = ? AND wp.content_id = c.id AND wp.is_completed = 1) as completed_episodes,
            (SELECT MAX(last_watched) FROM user_watch_progress wp 
              WHERE wp.user_id = ? AND wp.content_id = c.id) as last_watched
        FROM user_watchlist wl
        JOIN content c ON wl.content_id = c.id
        WHERE wl.user_id = ?
    ";
    
    $params = [$userId, $userId, $userId];
    
    // Define filter conditions for each tab
    $filters = [
        'movies' => "AND c.content_type = 'movie'",
        'tv_shows' => "AND c.content_type = 'tv_show'",
        'in_progress' => "AND EXISTS (
            SELECT 1 FROM user_watch_progress wp
            WHERE wp.user_id = ? AND wp.content_id = c.id
            AND wp.is_completed = 0 AND wp.progress > 0
        )",
        'completed' => "AND EXISTS (
            SELECT 1 FROM user_watch_progress wp
            WHERE wp.user_id = ? AND wp.content_id = c.id
            AND wp.is_completed = 1
        )"
    ];
    
    // Apply filter if active tab is not 'all' and exists in filters
    if ($activeTab !== 'all' && isset($filters[$activeTab])) {
        $baseQuery .= " " . $filters[$activeTab];
        // Add extra user ID param for progress-based filters
        if (in_array($activeTab, ['in_progress', 'completed'])) {
            $params[] = $userId;
        }
    }
    
    $baseQuery .= " ORDER BY wl.added_at DESC";
    
    return [
        'query' => $baseQuery,
        'params' => $params
    ];
}

/**
 * Calculates watchlist item counters
 */
function calculateCounters($items) {
    $counters = [
        'all' => 0,
        'movies' => 0,
        'tv_shows' => 0,
        'in_progress' => 0,
        'completed' => 0
    ];
    
    foreach ($items as $item) {
        $counters['all']++;
        $contentType = $item['content_type'] === 'movie' ? 'movies' : 'tv_shows';
        $counters[$contentType]++;
        
        if ($item['completed_episodes'] > 0) {
            if ($item['content_type'] === 'movie' || 
                ($item['total_episodes'] > 0 && $item['completed_episodes'] >= $item['total_episodes'])) {
                $counters['completed']++;
            } else {
                $counters['in_progress']++;
            }
        } elseif (isset($item['last_watched'])) {
            $counters['in_progress']++;
        }
    }
    
    return $counters;
}

// Obtener el contenido de la lista de reproducción del usuario
try {
    // Build and execute the query
    $queryData = buildWatchlistQuery($activeTab, $userId);
    $stmt = $db->prepare($queryData['query']);
    $stmt->execute($queryData['params']);
    $watchlistItems = $stmt->fetchAll();
    
    // Calculate counters
    $counters = calculateCounters($watchlistItems);
    
} catch (Exception $e) {
    $error = 'Error al cargar tu lista de reproducción: ' . $e->getMessage();
    error_log($error);
    $watchlistItems = [];
    $counters = array_fill_keys(['all', 'movies', 'tv_shows', 'in_progress', 'completed'], 0);
}
?>

<div class="dashboard-watchlist">
    <!-- Encabezado de la página -->
    <div class="page-header">
        <h1 class="page-title">Mi Lista</h1>
        <div class="page-actions">
            <a href="/browse" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Agregar más
            </a>
        </div>
    </div>
    
    <!-- Mensajes de éxito/error -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Pestañas de navegación -->
    <ul class="nav nav-tabs mb-4" id="watchlistTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $activeTab === 'all' ? ' active' : ''; ?>" 
                    id="all-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#all" 
                    type="button" 
                    role="tab" 
                    aria-controls="all" 
                    aria-selected="<?php echo $activeTab === 'all' ? 'true' : 'false'; ?>">
                Todo <span class="badge bg-secondary ms-1"><?php echo $counters['all']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $activeTab === 'movies' ? ' active' : ''; ?>" 
                    id="movies-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#movies" 
                    type="button" 
                    role="tab" 
                    aria-controls="movies" 
                    aria-selected="<?php echo $activeTab === 'movies' ? 'true' : 'false'; ?>">
                <i class="fas fa-film me-1"></i> Películas <span class="badge bg-secondary ms-1"><?php echo $counters['movies']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $activeTab === 'tv_shows' ? ' active' : ''; ?>" 
                    id="shows-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#shows" 
                    type="button" 
                    role="tab" 
                    aria-controls="shows" 
                    aria-selected="<?php echo $activeTab === 'tv_shows' ? 'true' : 'false'; ?>">
                <i class="fas fa-tv me-1"></i> Series <span class="badge bg-secondary ms-1"><?php echo $counters['tv_shows']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $activeTab === 'in_progress' ? ' active' : ''; ?>" 
                    id="in-progress-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#in-progress" 
                    type="button" 
                    role="tab" 
                    aria-controls="in-progress" 
                    aria-selected="<?php echo $activeTab === 'in_progress' ? 'true' : 'false'; ?>">
                <i class="fas fa-spinner me-1"></i> En progreso <span class="badge bg-secondary ms-1"><?php echo $counters['in_progress']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $activeTab === 'completed' ? ' active' : ''; ?>" 
                    id="completed-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#completed" 
                    type="button" 
                    role="tab" 
                    aria-controls="completed" 
                    aria-selected="<?php echo $activeTab === 'completed' ? 'true' : 'false'; ?>">
                <i class="fas fa-check-circle me-1"></i> Completados <span class="badge bg-secondary ms-1"><?php echo $counters['completed']; ?></span>
            </button>
        </li>
    </ul>
    
    <!-- Contenido de las pestañas -->
    <div class="tab-content" id="watchlistTabsContent">
        <!-- Pestaña Todo -->
        <div class="tab-pane fade<?php echo $activeTab === 'all' ? ' show active' : ''; ?>" 
             id="all" 
             role="tabpanel" 
             aria-labelledby="all-tab">
            <?php if (!empty($watchlistItems)): ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4 watchlist-container">
                    <?php foreach ($watchlistItems as $item): ?>
                        <?php include __DIR__ . '/../includes/watchlist-item.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-bookmark fa-3x text-muted mb-3"></i>
                    </div>
                    <h4>Tu lista está vacía</h4>
                    <p class="text-muted mb-4">Guarda tus películas y series favoritas para verlas más tarde</p>
                    <a href="/browse" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Explorar catálogo
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestaña Películas -->
        <div class="tab-pane fade<?php echo $activeTab === 'movies' ? ' show active' : ''; ?>" 
             id="movies" 
             role="tabpanel" 
             aria-labelledby="movies-tab">
            <?php 
            $movies = array_filter($watchlistItems, function($item) {
                return $item['content_type'] === 'movie';
            });
            
            if (!empty($movies)): 
            ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4 watchlist-container">
                    <?php foreach ($movies as $item): ?>
                        <?php include __DIR__ . '/../includes/watchlist-item.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-film fa-3x text-muted mb-3"></i>
                    </div>
                    <h4>No hay películas en tu lista</h4>
                    <p class="text-muted mb-4">Agrega películas a tu lista para verlas más tarde</p>
                    <a href="/browse?type=movie" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Ver películas
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestaña Series -->
        <div class="tab-pane fade<?php echo $activeTab === 'tv_shows' ? ' show active' : ''; ?>" 
             id="shows" 
             role="tabpanel" 
             aria-labelledby="shows-tab">
            <?php 
            $tvShows = array_filter($watchlistItems, function($item) {
                return $item['content_type'] === 'tv_show';
            });
            
            if (!empty($tvShows)): 
            ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4 watchlist-container">
                    <?php foreach ($tvShows as $item): ?>
                        <?php include __DIR__ . '/../includes/watchlist-item.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-tv fa-3x text-muted mb-3"></i>
                    </div>
                    <h4>No hay series en tu lista</h4>
                    <p class="text-muted mb-4">Agrega series a tu lista para verlas más tarde</p>
                    <a href="/browse?type=tv_show" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Ver series
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestaña En progreso -->
        <div class="tab-pane fade<?php echo $activeTab === 'in_progress' ? ' show active' : ''; ?>" 
             id="in-progress" 
             role="tabpanel" 
             aria-labelledby="in-progress-tab">
            <?php 
            $inProgress = array_filter($watchlistItems, function($item) {
                if ($item['content_type'] === 'movie') {
                    return isset($item['last_watched']) && !$item['is_completed'];
                } else {
                    return isset($item['last_watched']) && 
                           $item['completed_episodes'] > 0 && 
                           $item['completed_episodes'] < $item['total_episodes'];
                }
            });
            
            if (!empty($inProgress)): 
            ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4 watchlist-container">
                    <?php foreach ($inProgress as $item): ?>
                        <?php include __DIR__ . '/../includes/watchlist-item.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-spinner fa-3x text-muted mb-3"></i>
                    </div>
                    <h4>No hay contenido en progreso</h4>
                    <p class="text-muted mb-4">Empieza a ver contenido para verlo aquí</p>
                    <a href="/browse" class="btn btn-primary">
                        <i class="fas fa-play me-1"></i> Ver catálogo
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestaña Completados -->
        <div class="tab-pane fade<?php echo $activeTab === 'completed' ? ' show active' : ''; ?>" 
             id="completed" 
             role="tabpanel" 
             aria-labelledby="completed-tab">
            <?php 
            $completed = array_filter($watchlistItems, function($item) {
                if ($item['content_type'] === 'movie') {
                    return $item['is_completed'];
                } else {
                    return $item['total_episodes'] > 0 && 
                           $item['completed_episodes'] >= $item['total_episodes'];
                }
            });
            
            if (!empty($completed)): 
            ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4">
                    <?php foreach ($completed as $item): ?>
                        <?php include __DIR__ . '/../includes/watchlist-item.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                    </div>
                    <h4>No hay contenido completado</h4>
                    <p class="text-muted mb-4">Termina de ver contenido para verlo aquí</p>
                    <a href="/browse" class="btn btn-primary">
                        <i class="fas fa-play me-1"></i> Ver catálogo
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de confirmación de eliminación -->
    <div class="modal fade" id="removeFromListModal" tabindex="-1" aria-labelledby="removeFromListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeFromListModalLabel">Eliminar de Mi Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que quieres eliminar este contenido de tu lista?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form id="removeFromListForm" method="POST" style="display: inline;">
                        <input type="hidden" name="content_id" id="removeContentId">
                        <input type="hidden" name="content_type" id="removeContentType">
                        <input type="hidden" name="remove_from_list" value="1">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script para el manejo de la lista de reproducción -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Manejar el modal de eliminación
    const removeFromListModal = document.getElementById('removeFromListModal');
    if (removeFromListModal) {
        removeFromListModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const contentId = button.getAttribute('data-content-id');
            const contentType = button.getAttribute('data-content-type');
            const contentTitle = button.getAttribute('data-content-title');
            
            const modalTitle = removeFromListModal.querySelector('.modal-title');
            const removeContentId = document.getElementById('removeContentId');
            const removeContentType = document.getElementById('removeContentType');
            
            modalTitle.textContent = `Eliminar "${contentTitle}" de Mi Lista`;
            removeContentId.value = contentId;
            removeContentType.value = contentType;
        });
    }
    
    // Manejar la búsqueda en la lista
    const searchInput = document.getElementById('watchlistSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const items = document.querySelectorAll('.watchlist-item');
            
            items.forEach(item => {
                const title = item.getAttribute('data-title').toLowerCase();
                if (title.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Manejar el filtro de ordenación
    const sortSelect = document.getElementById('watchlistSort');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const sortBy = this.value;
            const container = document.querySelector('.watchlist-container');
            const items = Array.from(document.querySelectorAll('.watchlist-item'));
            
            items.sort((a, b) => {
                let aValue, bValue;
                
                switch(sortBy) {
                    case 'title_asc':
                        aValue = a.getAttribute('data-title');
                        bValue = b.getAttribute('data-title');
                        return aValue.localeCompare(bValue);
                        
                    case 'title_desc':
                        aValue = a.getAttribute('data-title');
                        bValue = b.getAttribute('data-title');
                        return bValue.localeCompare(aValue);
                        
                    case 'date_added':
                        aValue = a.getAttribute('data-added');
                        bValue = b.getAttribute('data-added');
                        return new Date(bValue) - new Date(aValue);
                        
                    case 'release_date':
                        aValue = a.getAttribute('data-release-year') || '0';
                        bValue = b.getAttribute('data-release-year') || '0';
                        return parseInt(bValue) - parseInt(aValue);
                        
                    case 'progress':
                        aValue = parseFloat(a.getAttribute('data-progress') || '0');
                        bValue = parseFloat(b.getAttribute('data-progress') || '0');
                        return bValue - aValue;
                        
                    default:
                        return 0;
                }
            });
            
            // Reordenar los elementos en el DOM
            items.forEach(item => container.appendChild(item));
        });
    }
    
    // Actualizar automáticamente las pestañas basadas en los parámetros de la URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('type');
    
    if (tabParam && document.getElementById(`${tabParam}-tab`)) {
        const tab = new bootstrap.Tab(document.getElementById(`${tabParam}-tab`));
        tab.show();
    }
    
    // Manejar la navegación entre pestañas
    const tabLinks = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const tabId = this.getAttribute('data-bs-target').replace('#', '');
            const url = new URL(window.location);
            
            if (tabId === 'all') {
                url.searchParams.delete('type');
            } else if (tabId === 'movies') {
                url.searchParams.set('type', 'movies');
            } else if (tabId === 'shows') {
                url.searchParams.set('type', 'tv_shows');
            } else if (tabId === 'in-progress') {
                url.searchParams.set('type', 'in_progress');
            } else if (tabId === 'completed') {
                url.searchParams.set('type', 'completed');
            }
            
            window.history.pushState({}, '', url);
        });
    });
});

// Función para formatear fechas como "hace X tiempo"
function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval > 1) return `hace ${interval} años`;
    if (interval === 1) return 'hace un año';
    
    interval = Math.floor(seconds / 2592000);
    if (interval > 1) return `hace ${interval} meses`;
    if (interval === 1) return 'hace un mes';
    
    interval = Math.floor(seconds / 86400);
    if (interval > 1) return `hace ${interval} días`;
    if (interval === 1) return 'ayer';
    
    interval = Math.floor(seconds / 3600);
    if (interval > 1) return `hace ${interval} horas`;
    if (interval === 1) return 'hace una hora';
    
    interval = Math.floor(seconds / 60);
    if (interval > 1) return `hace ${interval} minutos`;
    if (interval === 1) return 'hace un minuto';
    
    return 'hace unos segundos';
}
</script>
