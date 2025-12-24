<?php
// Incluir archivos necesarios
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';

// Obtener conexión a la base de datos
$db = getDbConnection();

// Configurar filtros
$filters = [
    'type' => $_GET['type'] ?? null,
    'genre' => $_GET['genre'] ?? null,
    'search' => $_GET['q'] ?? null,
    'order_by' => $_GET['order_by'] ?? 'c.release_date',
    'order_dir' => $_GET['order_dir'] ?? 'DESC',
    'page' => max(1, $_GET['page'] ?? 1),
    'limit' => 24
];

// Obtener contenido para la galería
$content = getGalleryContent($db, $filters);

// Obtener géneros para los filtros
$genres = getGenresForFilter($db);

// Configurar título de la página
$pageTitle = 'Catálogo';
if ($filters['type'] === 'movie') $pageTitle = 'Películas';
if ($filters['type'] === 'series') $pageTitle = 'Series';
if ($filters['search']) $pageTitle = 'Resultados de búsqueda: ' . htmlspecialchars($filters['search']);

// Incluir encabezado
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4"><?php echo $pageTitle; ?></h1>
            
            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="filterForm" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="q" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Título, género, actor...">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="type" class="form-label">Tipo</label>
                            <select class="form-select" id="type" name="type">
                                <option value="">Todos</option>
                                <option value="movie" <?php echo $filters['type'] === 'movie' ? 'selected' : ''; ?>>Películas</option>
                                <option value="series" <?php echo $filters['type'] === 'series' ? 'selected' : ''; ?>>Series</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="genre" class="form-label">Género</label>
                            <select class="form-select" id="genre" name="genre">
                                <option value="">Todos los géneros</option>
                                <?php foreach ($genres as $genre): ?>
                                    <option value="<?php echo $genre['id']; ?>" <?php echo $filters['genre'] == $genre['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($genre['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="order" class="form-label">Ordenar por</label>
                            <select class="form-select" id="order" name="order_by">
                                <option value="c.release_date" <?php echo $filters['order_by'] === 'c.release_date' ? 'selected' : ''; ?>>Fecha de lanzamiento</option>
                                <option value="c.title" <?php echo $filters['order_by'] === 'c.title' ? 'selected' : ''; ?>>Título</option>
                                <option value="c.rating" <?php echo $filters['order_by'] === 'c.rating' ? 'selected' : ''; ?>>Calificación</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-1"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Galería de contenido -->
            <?php 
            if (empty($content)) {
                echo '<div class="alert alert-info">No se encontró contenido con los filtros seleccionados.</div>';
            } else {
                echo renderGallery($content);
            }
            ?>
            
        </div>
    </div>
</div>

<!-- Modal de vista previa -->
<div class="modal fade" id="contentPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="previewTitle">Cargando...</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="previewContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir pie de página
include __DIR__ . '/includes/footer.php';
?>

<!-- Scripts específicos de la galería -->
<script>
// Esperar a que main.js se haya cargado completamente
(function() {
    function initContentCards() {
        // Inicializar tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Manejar clic en las tarjetas - Abrir modal de torrents
        // Usar delegación de eventos para capturar clics en fichas existentes y nuevas
        document.addEventListener('click', function(e) {
            const card = e.target.closest('.content-card');
            if (!card) return;
            
            // Evitar que se active el clic si se hace clic en un botón dentro de la tarjeta o en el contenedor del trailer
            if (e.target.closest('button') || e.target.closest('.content-trailer-container')) {
                return;
            }
            
            // Prevenir el comportamiento por defecto del onclick inline
            e.preventDefault();
            e.stopPropagation();
            
            const contentId = card.dataset.id;
            const contentType = card.dataset.type || 'movie';
            const title = card.dataset.title || '';
            const year = card.dataset.year || null;
            
            // Abrir modal de búsqueda de torrents
            if (typeof showTorrentModal === 'function') {
                showTorrentModal(contentId, title, year, contentType);
            } else if (typeof openTorrentModal === 'function') {
                openTorrentModal({
                    id: contentId,
                    title: title,
                    year: year,
                    type: contentType
                });
            } else {
                // Fallback: redirigir a detalles si no hay modal disponible
                const detailUrl = card.dataset.detailUrl || window.location.href;
                window.location.href = detailUrl;
            }
        }, true); // Usar capture phase para interceptar antes que otros handlers
    }
    
    // Intentar inicializar inmediatamente si el DOM ya está listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Esperar un poco más para asegurar que main.js se haya cargado
            setTimeout(initContentCards, 100);
        });
    } else {
        // El DOM ya está listo, esperar un poco para que main.js se cargue
        setTimeout(initContentCards, 100);
    }
})();
    
    // Función para cargar la vista previa del contenido
    function loadContentPreview(contentId, contentType) {
        const modal = new bootstrap.Modal(document.getElementById('contentPreviewModal'));
        const modalTitle = document.getElementById('previewTitle');
        const modalContent = document.getElementById('previewContent');
        
        // Mostrar spinner de carga
        modalContent.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            </div>
        `;
        
        // Mostrar el modal
        modal.show();
        
        // Simular carga de datos (en un caso real, harías una petición AJAX)
        setTimeout(() => {
            // Esto es un ejemplo, en un caso real cargarías los datos del servidor
            modalTitle.textContent = 'Título del contenido';
            
            modalContent.innerHTML = `
                <div class="row g-0">
                    <div class="col-md-4">
                        <img src="/assets/img/default-poster.jpg" class="img-fluid rounded-start" alt="Poster">
                    </div>
                    <div class="col-md-8 p-4">
                        <h2>Título del contenido</h2>
                        <div class="mb-3">
                            <span class="badge bg-primary me-2">${contentType === 'movie' ? 'Película' : 'Serie'}</span>
                            <span class="text-muted">2023</span>
                            <span class="mx-2">•</span>
                            <span class="text-muted">2h 15min</span>
                            <span class="mx-2">•</span>
                            <span class="text-warning">⭐ 8.5/10</span>
                        </div>
                        <p class="lead">Una descripción emocionante del contenido que se mostrará aquí.</p>
                        <div class="mb-3">
                            <span class="badge bg-secondary me-2">Acción</span>
                            <span class="badge bg-secondary me-2">Aventura</span>
                            <span class="badge bg-secondary">Ciencia Ficción</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-lg">
                                <i class="fas fa-play me-2"></i> Reproducir
                            </button>
                            <button class="btn btn-outline-light">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-outline-light">
                                <i class="far fa-thumbs-up"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }, 1000);
    }
    
    // Manejar envío del formulario de filtros
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Aquí podrías agregar validación adicional si es necesario
            // Por ahora, el formulario se envía normalmente
        });
    }
    
    // Actualizar la URL cuando cambian los filtros (sin recargar la página)
    const filterInputs = document.querySelectorAll('#filterForm select, #filterForm input[type="text"]');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // En un caso real, podrías usar AJAX para actualizar solo la galería
            // filterForm.submit();
        });
    });
});
</script>
