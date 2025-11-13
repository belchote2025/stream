<?php
// Verificar si el usuario está autenticado
if (!$auth->isAuthenticated()) {
    redirect('/login.php');
}

// Obtener estadísticas del usuario
$userId = $user['id'];
$db = getDbConnection();

// Consulta para obtener el historial reciente
$recentlyWatched = [];
try {
    $stmt = $db->prepare("
        SELECT c.id, c.title, c.thumbnail_url, c.content_type, 
               h.progress, h.duration, h.last_watched, h.is_completed,
               e.id as episode_id, e.title as episode_title, e.season_id
        FROM watch_history h
        JOIN content c ON h.content_id = c.id
        LEFT JOIN episodes e ON h.episode_id = e.id
        WHERE h.user_id = ?
        ORDER BY h.last_watched DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentlyWatched = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener el historial reciente: " . $e->getMessage());
}

// Consulta para obtener recomendaciones
$recommendations = [];
try {
    // Esta es una consulta de ejemplo. En una aplicación real, usarías un algoritmo de recomendación más sofisticado
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM watch_history WHERE content_id = c.id) as watch_count,
               (SELECT AVG(rating) FROM ratings WHERE content_id = c.id) as avg_rating
        FROM content c
        WHERE c.id NOT IN (
            SELECT content_id FROM watch_history WHERE user_id = ?
        )
        ORDER BY c.release_year DESC, watch_count DESC, avg_rating DESC
        LIMIT 6
    ");
    $stmt->execute([$userId]);
    $recommendations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error al obtener recomendaciones: " . $e->getMessage());
}

// Obtener estadísticas de visualización
$stats = [
    'total_watched' => 0,
    'total_hours' => 0,
    'favorite_genre' => 'No hay datos',
    'completion_rate' => 0
];

try {
    // Total de contenido visto
    $stmt = $db->prepare("SELECT COUNT(DISTINCT content_id) as total FROM watch_history WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $stats['total_watched'] = $result ? $result['total'] : 0;
    
    // Horas totales vistas
    $stmt = $db->prepare("
        SELECT SUM(duration) as total_seconds 
        FROM (
            SELECT MAX(duration) as duration 
            FROM watch_history 
            WHERE user_id = ? 
            GROUP BY content_id, episode_id
        ) as unique_content
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $stats['total_hours'] = $result && $result['total_seconds'] ? round($result['total_seconds'] / 3600, 1) : 0;
    
    // Género favorito
    $stmt = $db->prepare("
        SELECT g.name, COUNT(*) as count 
        FROM watch_history h
        JOIN content c ON h.content_id = c.id
        JOIN content_categories cc ON c.id = cc.content_id
        JOIN categories g ON cc.category_id = g.id
        WHERE h.user_id = ?
        GROUP BY g.id
        ORDER BY count DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $stats['favorite_genre'] = $result ? $result['name'] : 'No hay datos';
    
    // Tasa de finalización (porcentaje de contenido visto hasta el final)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
        FROM watch_history 
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    $stats['completion_rate'] = $result && $result['total'] > 0 
        ? round(($result['completed'] / $result['total']) * 100) 
        : 0;
        
} catch (PDOException $e) {
    error_log("Error al obtener estadísticas: " . $e->getMessage());
}
?>

<div class="dashboard-overview">
    <!-- Encabezado de la página -->
    <div class="page-header">
        <h1 class="page-title">Bienvenido, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h1>
        <div class="page-actions">
            <a href="/browse" class="btn btn-primary">
                <i class="fas fa-play"></i> Ver catálogo
            </a>
        </div>
    </div>
    
    <!-- Tarjetas de estadísticas -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Contenido visto</h6>
                            <h3 class="mb-0"><?php echo $stats['total_watched']; ?></h3>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-film text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-success">
                            <i class="fas fa-arrow-up"></i> 12% este mes
                        </span>
                        <span class="text-muted ms-2">vs mes anterior</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Horas vistas</h6>
                            <h3 class="mb-0"><?php echo $stats['total_hours']; ?></h3>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-clock text-success" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-success">
                            <i class="fas fa-arrow-up"></i> 8% esta semana
                        </span>
                        <span class="text-muted ms-2">vs semana anterior</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Género favorito</h6>
                            <h3 class="mb-0"><?php echo htmlspecialchars($stats['favorite_genre']); ?></h3>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-heart text-warning" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-muted">Basado en tu actividad</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Tasa de finalización</h6>
                            <h3 class="mb-0"><?php echo $stats['completion_rate']; ?>%</h3>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-check-circle text-info" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" 
                                 style="width: <?php echo $stats['completion_rate']; ?>%" 
                                 aria-valuenow="<?php echo $stats['completion_rate']; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección de continuación viendo -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Continuar viendo</h5>
            <a href="?tab=history" class="btn btn-sm btn-outline-primary">Ver todo</a>
        </div>
        <div class="card-body">
            <?php if (!empty($recentlyWatched)): ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
                    <?php foreach ($recentlyWatched as $item): 
                        $progress = $item['duration'] > 0 ? ($item['progress'] / $item['duration']) * 100 : 0;
                        $progress = min(100, max(0, $progress));
                        $isEpisode = !empty($item['episode_id']);
                        $contentUrl = $isEpisode 
                            ? "/watch.php?content_id={$item['id']}&season_id={$item['season_id']}&episode_id={$item['episode_id']}"
                            : "/watch.php?content_id={$item['id']}";
                    ?>
                        <div class="col">
                            <div class="content-card">
                                <a href="<?php echo $contentUrl; ?>" class="content-thumbnail">
                                    <img src="<?php echo !empty($item['thumbnail_url']) ? htmlspecialchars($item['thumbnail_url']) : '/assets/images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                         class="img-fluid rounded">
                                    <div class="progress" style="height: 3px; margin: 0;">
                                        <div class="progress-bar bg-danger" role="progressbar" 
                                             style="width: <?php echo $progress; ?>%" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    <div class="play-icon">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <?php if ($isEpisode): ?>
                                        <div class="episode-badge">
                                            <i class="fas fa-tv"></i> Episodio
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <div class="content-details mt-2">
                                    <h6 class="content-title mb-0">
                                        <a href="<?php echo $contentUrl; ?>" class="text-dark text-decoration-none">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </h6>
                                    <?php if ($isEpisode && !empty($item['episode_title'])): ?>
                                        <small class="text-muted d-block">
                                            <?php echo htmlspecialchars($item['episode_title']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted">
                                            <?php echo $item['is_completed'] ? 'Completado' : round($progress) . '% visto'; ?>
                                        </small>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($item['last_watched'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-film fa-3x text-muted"></i>
                    </div>
                    <h5>No hay contenido reciente</h5>
                    <p class="text-muted mb-0">Empieza a ver contenido y aparecerá aquí</p>
                    <a href="/browse" class="btn btn-primary mt-3">Explorar catálogo</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sección de recomendaciones -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Recomendado para ti</h5>
            <button class="btn btn-sm btn-outline-primary" id="refresh-recommendations">
                <i class="fas fa-sync-alt me-1"></i> Actualizar
            </button>
        </div>
        <div class="card-body">
            <?php if (!empty($recommendations)): ?>
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3" id="recommendations-container">
                    <?php foreach ($recommendations as $item): 
                        $rating = isset($item['avg_rating']) ? round($item['avg_rating'], 1) : 'N/A';
                    ?>
                        <div class="col">
                            <div class="content-card">
                                <a href="/watch.php?content_id=<?php echo $item['id']; ?>" class="content-thumbnail">
                                    <img src="<?php echo !empty($item['thumbnail_url']) ? htmlspecialchars($item['thumbnail_url']) : '/assets/images/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                         class="img-fluid rounded">
                                    <div class="play-icon">
                                        <i class="fas fa-play"></i>
                                    </div>
                                    <?php if ($item['content_type'] === 'tv_show'): ?>
                                        <div class="content-type-badge">
                                            <i class="fas fa-tv"></i> Serie
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($rating !== 'N/A'): ?>
                                        <div class="rating-badge">
                                            <i class="fas fa-star text-warning"></i> <?php echo $rating; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <div class="content-details mt-2">
                                    <h6 class="content-title mb-0">
                                        <a href="/watch.php?content_id=<?php echo $item['id']; ?>" class="text-dark text-decoration-none">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo !empty($item['release_year']) ? $item['release_year'] : ''; ?>
                                        <?php if (!empty($item['age_rating'])): ?>
                                            <span class="ms-2"><?php echo htmlspecialchars($item['age_rating']); ?>+</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-magic fa-3x text-muted"></i>
                    </div>
                    <h5>No hay recomendaciones disponibles</h5>
                    <p class="text-muted mb-0">Sigue viendo contenido para recibir recomendaciones personalizadas</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Script para actualizar recomendaciones -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refresh-recommendations');
    const container = document.getElementById('recommendations-container');
    
    if (refreshBtn && container) {
        refreshBtn.addEventListener('click', function() {
            // Mostrar indicador de carga
            const originalHtml = container.innerHTML;
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Buscando nuevas recomendaciones...</p>
                </div>
            `;
            
            // Simular carga de nuevas recomendaciones (en un caso real, harías una petición AJAX)
            setTimeout(() => {
                // Aquí iría la lógica para cargar nuevas recomendaciones
                // Por ahora, solo recargamos la página
                window.location.reload();
            }, 1500);
        });
    }
    
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
