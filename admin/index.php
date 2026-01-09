<?php
/**
 * Panel de Administración - Streaming Platform
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Requerir autenticación de administrador
requireAdmin();

// Obtener conexión a la base de datos
$db = getDbConnection();

// Obtener estadísticas reales
try {
    // Usuarios totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    // Usuarios este mes
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newUsersThisMonth = $stmt->fetch()['total'];
    
    // Películas totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'movie'");
    $totalMovies = $stmt->fetch()['total'];
    
    // Series totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'series'");
    $totalSeries = $stmt->fetch()['total'];
    
    // Contenido nuevo este mes
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $newContentThisMonth = $stmt->fetch()['total'];
    
    // Usuarios recientes (últimos 5)
    $stmt = $db->query("SELECT id, username, email, full_name, role, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll();
    
    // Actividades recientes (últimas 4)
    $stmt = $db->query("
        SELECT 'content' as type, title, created_at 
        FROM content 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $recentActivities = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Error al cargar estadísticas: ' . $e->getMessage());
    $totalUsers = 0;
    $newUsersThisMonth = 0;
    $totalMovies = 0;
    $totalSeries = 0;
    $newContentThisMonth = 0;
    $recentUsers = [];
    $recentActivities = [];
}

// Obtener información del usuario actual
$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? 'Admin',
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'admin'
];

$baseUrl = rtrim(SITE_URL, '/');
$pageTitle = 'Panel de Administración - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $baseUrl; ?>/favicon.ico" onerror="this.style.display='none'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="admin-container">
        <!-- Overlay para móviles -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Barra lateral -->
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <h2><?php echo SITE_NAME; ?><span>Admin</span></h2>
            </div>
            <nav class="admin-nav">
                <ul>
                    <li class="active">
                        <a href="#dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="#contenido">
                            <i class="fas fa-film"></i>
                            <span>Contenido</span>
                        </a>
                        <ul class="submenu">
                            <li><a href="#contenido/peliculas">Películas</a></li>
                            <li><a href="#contenido/series">Series</a></li>
                            <li><a href="#contenido/episodios">Episodios</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#usuarios">
                            <i class="fas fa-users"></i>
                            <span>Usuarios</span>
                        </a>
                    </li>
                    <li>
                        <a href="#suscripciones">
                            <i class="fas fa-credit-card"></i>
                            <span>Suscripciones</span>
                        </a>
                    </li>
                    <li>
                        <a href="#reportes">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                    <li>
                        <a href="#configuracion">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                    </li>
                    <li>
                        <a href="#addons">
                            <i class="fas fa-puzzle-piece"></i>
                            <span>Addons</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <div class="logout">
                <a href="<?php echo $baseUrl; ?>/api/auth/logout.php?from=admin">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Contenido principal -->
        <main class="main-content">
            <!-- Barra superior -->
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="search-bar">
                    <input type="text" id="admin-search" placeholder="Buscar en el panel...">
                    <button id="search-btn"><i class="fas fa-search"></i></button>
                </div>
                <div class="user-menu">
                    <div class="notifications" id="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">0</span>
                    </div>
                    <div class="user-info">
                        <img src="<?php echo $baseUrl; ?>/assets/img/default-poster.svg" alt="Admin" class="avatar" onerror="this.src='<?php echo $baseUrl; ?>/assets/img/default-poster.svg'">
                        <span><?php echo htmlspecialchars($currentUser['username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </header>

            <!-- Contenido del dashboard -->
            <div class="dashboard" id="dashboard-content">
                <h1>Panel de Control</h1>
                
                <!-- Resumen -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Usuarios Totales</h3>
                            <p class="stat-number"><?php echo number_format($totalUsers); ?></p>
                            <p class="stat-change positive">+<?php echo $newUsersThisMonth; ?> este mes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-film"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Películas</h3>
                            <p class="stat-number"><?php echo number_format($totalMovies); ?></p>
                            <p class="stat-change positive">+<?php echo floor($newContentThisMonth / 2); ?> este mes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tv"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Series</h3>
                            <p class="stat-number"><?php echo number_format($totalSeries); ?></p>
                            <p class="stat-change positive">+<?php echo ceil($newContentThisMonth / 2); ?> este mes</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Vistas Totales</h3>
                            <p class="stat-number"><?php 
                                try {
                                    $stmt = $db->query("SELECT SUM(views) as total FROM content");
                                    $views = $stmt->fetch()['total'] ?? 0;
                                    echo number_format($views);
                                } catch (PDOException $e) {
                                    echo '0';
                                }
                            ?></p>
                            <p class="stat-change positive">Activo</p>
                        </div>
                    </div>
                </div>

                <!-- Actualización Automática de Contenido - Estilo Netflix Mejorado -->
                <div class="netflix-refresh-section">
                    <div class="netflix-refresh-header">
                        <div class="netflix-refresh-icon-wrapper">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="netflix-refresh-title-group">
                            <h2 class="netflix-refresh-title">Actualización Automática de Contenido</h2>
                            <p class="netflix-refresh-subtitle">Sincroniza novedades desde múltiples fuentes</p>
                        </div>
                    </div>

                    <div class="netflix-refresh-info">
                        <div class="netflix-info-card">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong>Fuentes disponibles:</strong> Trakt.tv y TVMaze (ambas gratuitas)
                                <br>
                                <small>Incluye portadas, trailers y enlaces torrent con prioridad por seeds</small>
                            </div>
                        </div>
                        <div class="netflix-info-links">
                            <a href="https://trakt.tv/oauth/applications" target="_blank" class="netflix-link">
                                <i class="fas fa-external-link-alt"></i> Configurar Trakt (Opcional)
                            </a>
                            <span class="netflix-link-separator">•</span>
                            <span class="netflix-code-link" onclick="copyToClipboard('https://torrentio.strem.fun/lite/manifest.json')" title="Clic para copiar">
                                <i class="fas fa-copy"></i> Torrentio Stremio
                            </span>
                        </div>
                    </div>

                    <!-- Presets rápidos -->
                    <div class="netflix-presets">
                        <label class="netflix-presets-label">Presets rápidos:</label>
                        <div class="netflix-presets-buttons">
                            <button type="button" class="netflix-preset-btn" data-preset="quick" onclick="applyPreset('quick')">
                                <i class="fas fa-bolt"></i> Rápido (10 items, 3 días)
                            </button>
                            <button type="button" class="netflix-preset-btn active" data-preset="standard" onclick="applyPreset('standard')">
                                <i class="fas fa-star"></i> Estándar (30 items, 7 días)
                            </button>
                            <button type="button" class="netflix-preset-btn" data-preset="extensive" onclick="applyPreset('extensive')">
                                <i class="fas fa-fire"></i> Extensivo (50 items, 14 días)
                            </button>
                            <button type="button" class="netflix-preset-btn" data-preset="full" onclick="applyPreset('full')">
                                <i class="fas fa-infinity"></i> Completo (100 items, 30 días)
                            </button>
                        </div>
                    </div>

                    <!-- Controles principales -->
                    <div class="netflix-refresh-controls">
                        <div class="netflix-control-grid">
                            <div class="netflix-control-field">
                                <label class="netflix-control-label" for="refresh-type">
                                    <i class="fas fa-film"></i> Tipo de Contenido
                                </label>
                                <div class="netflix-select-wrapper">
                                    <select id="refresh-type" class="netflix-select-control">
                                        <option value="movie">🎬 Películas</option>
                                        <option value="tv">📺 Series</option>
                                    </select>
                                    <i class="fas fa-chevron-down netflix-select-arrow"></i>
                                </div>
                            </div>

                            <div class="netflix-control-field">
                                <label class="netflix-control-label" for="refresh-limit">
                                    <i class="fas fa-list-ol"></i> Límite de Items
                                </label>
                                <div class="netflix-input-wrapper">
                                    <input type="number" id="refresh-limit" class="netflix-input-control" value="30" min="1" max="100">
                                    <span class="netflix-input-hint">1-100</span>
                                </div>
                            </div>

                            <div class="netflix-control-field">
                                <label class="netflix-control-label" for="refresh-days">
                                    <i class="fas fa-calendar-alt"></i> Últimos Días
                                </label>
                                <div class="netflix-input-wrapper">
                                    <input type="number" id="refresh-days" class="netflix-input-control" value="7" min="0" max="365">
                                    <span class="netflix-input-hint">0-365</span>
                                </div>
                            </div>

                            <div class="netflix-control-field">
                                <label class="netflix-control-label" for="refresh-seeds">
                                    <i class="fas fa-seedling"></i> Mín. Seeds
                                </label>
                                <div class="netflix-input-wrapper">
                                    <input type="number" id="refresh-seeds" class="netflix-input-control" value="10" min="0">
                                    <span class="netflix-input-hint">Calidad</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="netflix-refresh-actions">
                        <div class="netflix-actions-left">
                            <button id="btn-refresh-content" class="netflix-btn-refresh">
                                <i class="fas fa-sync-alt"></i>
                                <span>Actualizar Novedades</span>
                            </button>
                            <label class="netflix-checkbox-label">
                                <input type="checkbox" id="refresh-dry-run" class="netflix-checkbox-input">
                                <span class="netflix-checkbox-custom"></span>
                                <span class="netflix-checkbox-text">Modo prueba (no guarda cambios)</span>
                            </label>
                        </div>
                        <div id="refresh-status" class="netflix-refresh-status"></div>
                    </div>

                    <!-- Estadísticas en tiempo real -->
                    <div id="refresh-stats" class="netflix-refresh-stats" style="display: none;">
                        <div class="netflix-stat-item">
                            <div class="netflix-stat-icon created">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="netflix-stat-content">
                                <div class="netflix-stat-value" id="stat-created">0</div>
                                <div class="netflix-stat-label">Creados</div>
                            </div>
                        </div>
                        <div class="netflix-stat-item">
                            <div class="netflix-stat-icon updated">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="netflix-stat-content">
                                <div class="netflix-stat-value" id="stat-updated">0</div>
                                <div class="netflix-stat-label">Actualizados</div>
                            </div>
                        </div>
                        <div class="netflix-stat-item">
                            <div class="netflix-stat-icon episodes">
                                <i class="fas fa-tv"></i>
                            </div>
                            <div class="netflix-stat-content">
                                <div class="netflix-stat-value" id="stat-episodes">0</div>
                                <div class="netflix-stat-label">Episodios</div>
                            </div>
                        </div>
                        <div class="netflix-stat-item">
                            <div class="netflix-stat-icon time">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="netflix-stat-content">
                                <div class="netflix-stat-value" id="stat-time">0s</div>
                                <div class="netflix-stat-label">Tiempo</div>
                            </div>
                        </div>
                    </div>

                    <!-- Output mejorado -->
                    <div id="refresh-output" class="netflix-refresh-output" style="display: none;">
                        <div class="netflix-output-header">
                            <span class="netflix-output-title">
                                <i class="fas fa-terminal"></i> Salida del Proceso
                            </span>
                            <button type="button" class="netflix-output-clear" onclick="clearRefreshOutput()" title="Limpiar">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="netflix-output-content" id="refresh-output-content"></div>
                    </div>

                    <!-- Barra de progreso -->
                    <div id="refresh-progress" class="netflix-refresh-progress" style="display: none;">
                        <div class="netflix-progress-bar">
                            <div class="netflix-progress-fill" id="refresh-progress-fill"></div>
                        </div>
                        <div class="netflix-progress-text" id="refresh-progress-text">Iniciando...</div>
                    </div>
                </div>

                <!-- Actividades recientes -->
                <div class="recent-activity">
                    <div class="section-header">
                        <h2>Actividad Reciente</h2>
                        <a href="#contenido" class="view-all">Ver todo</a>
                    </div>
                    
                    <div class="activity-list">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon success">
                                        <i class="fas fa-plus"></i>
                                    </div>
                                    <div class="activity-details">
                                        <p>Nuevo contenido añadido: <strong><?php echo htmlspecialchars($activity['title']); ?></strong></p>
                                        <span class="activity-time"><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay actividades recientes</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Últimos usuarios registrados -->
                <div class="recent-users">
                    <div class="section-header">
                        <h2>Últimos Usuarios</h2>
                        <a href="#usuarios" class="view-all">Ver todos</a>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Registro</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentUsers)): ?>
                                    <?php foreach ($recentUsers as $user): ?>
                                        <tr data-id="<?php echo $user['id']; ?>">
                                            <td>
                                                <div class="user-cell">
                                                <img src="<?php echo $baseUrl; ?>/assets/img/default-poster.svg" alt="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <span><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                            <td><span class="badge <?php echo ($user['role'] === 'premium' || $user['role'] === 'admin') ? 'premium' : 'free'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                            <td><span class="status active">Activo</span></td>
                                            <td class="actions">
                                                <button class="btn btn-sm btn-view" title="Ver" data-id="<?php echo $user['id']; ?>"><i class="fas fa-eye"></i></button>
                                                <button class="btn btn-sm btn-edit" title="Editar" data-id="<?php echo $user['id']; ?>"><i class="fas fa-edit"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay usuarios registrados</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal para agregar/editar contenido -->
        <div class="modal" id="contentModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title">Agregar Nueva Película</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body netflix-form-body">
                    <form id="contentForm" enctype="multipart/form-data" class="netflix-form">
                        <input type="hidden" id="content-id" name="id" value="">
                        <input type="hidden" id="content-type" name="type" value="movie">
                        
                        <!-- Información Básica - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-info-circle"></i></span>
                                <h3 class="netflix-section-title">Información Básica</h3>
                            </div>
                            
                            <div class="netflix-form-grid">
                                <div class="netflix-form-field netflix-field-large">
                                    <label class="netflix-label" for="title">
                                        <span class="netflix-label-text">Título</span>
                                        <span class="netflix-required">*</span>
                                    </label>
                                    <input type="text" id="title" name="title" class="netflix-input" required placeholder="Ej: El Padrino">
                                </div>
                                
                                <div class="netflix-form-field">
                                    <label class="netflix-label" for="release_year">
                                        <span class="netflix-label-text">Año</span>
                                    </label>
                                    <input type="number" id="release_year" name="release_year" class="netflix-input" min="1900" max="<?php echo date('Y') + 5; ?>" placeholder="2024">
                                </div>
                                
                                <div class="netflix-form-field">
                                    <label class="netflix-label" for="duration">
                                        <span class="netflix-label-text">Duración (min)</span>
                                    </label>
                                    <input type="number" id="duration" name="duration" class="netflix-input" min="1" placeholder="120">
                                </div>
                            </div>
                            
                            <div class="netflix-form-field">
                                <label class="netflix-label" for="description">
                                    <span class="netflix-label-text">Descripción</span>
                                    <span class="netflix-required">*</span>
                                </label>
                                <textarea id="description" name="description" class="netflix-textarea" rows="4" required placeholder="Describe el contenido..."></textarea>
                            </div>
                        </div>

                        <!-- Archivos de Imagen - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-images"></i></span>
                                <h3 class="netflix-section-title">Imágenes</h3>
                            </div>
                            
                            <div class="netflix-form-grid netflix-grid-2">
                                <div class="netflix-form-field">
                                    <label class="netflix-label" for="poster_file">
                                        <span class="netflix-label-text">Póster</span>
                                    </label>
                                    <div class="netflix-file-upload">
                                        <input type="file" id="poster_file" name="poster_file" accept="image/*" data-max-size="5242880" class="netflix-file-input">
                                        <label for="poster_file" class="netflix-file-label">
                                            <div class="netflix-file-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                            <div class="netflix-file-text">
                                                <span class="netflix-file-main-text">Arrastra o selecciona póster</span>
                                                <span class="netflix-file-sub-text">JPG, PNG, WEBP (máx. 5MB)</span>
                                            </div>
                                        </label>
                                        <div id="poster_file_info" class="netflix-file-info" style="display: none;">
                                            <div class="netflix-file-info-content">
                                                <i class="fas fa-check-circle netflix-file-check"></i>
                                                <div class="netflix-file-details">
                                                    <div class="netflix-file-name"></div>
                                                    <div class="netflix-file-size"></div>
                                                </div>
                                                <button type="button" class="netflix-file-remove" onclick="clearImageFile('poster_file')" title="Eliminar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="netflix-form-field">
                                    <label class="netflix-label" for="backdrop_file">
                                        <span class="netflix-label-text">Backdrop</span>
                                    </label>
                                    <div class="netflix-file-upload">
                                        <input type="file" id="backdrop_file" name="backdrop_file" accept="image/*" data-max-size="6291456" class="netflix-file-input">
                                        <label for="backdrop_file" class="netflix-file-label">
                                            <div class="netflix-file-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                            <div class="netflix-file-text">
                                                <span class="netflix-file-main-text">Arrastra o selecciona backdrop</span>
                                                <span class="netflix-file-sub-text">JPG, PNG, WEBP (máx. 6MB)</span>
                                            </div>
                                        </label>
                                        <div id="backdrop_file_info" class="netflix-file-info" style="display: none;">
                                            <div class="netflix-file-info-content">
                                                <i class="fas fa-check-circle netflix-file-check"></i>
                                                <div class="netflix-file-details">
                                                    <div class="netflix-file-name"></div>
                                                    <div class="netflix-file-size"></div>
                                                </div>
                                                <button type="button" class="netflix-file-remove" onclick="clearImageFile('backdrop_file')" title="Eliminar">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Video Principal - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-video"></i></span>
                                <h3 class="netflix-section-title">Video Principal</h3>
                            </div>
                            
                            <div class="netflix-form-field">
                                <div class="netflix-file-upload netflix-file-upload-large">
                                    <input type="file" id="video_file" name="video_file" accept="video/*" data-max-size="2147483648" class="netflix-file-input">
                                    <label for="video_file" class="netflix-file-label netflix-file-label-large">
                                        <div class="netflix-file-icon-large"><i class="fas fa-film"></i></div>
                                        <div class="netflix-file-text">
                                            <span class="netflix-file-main-text">Arrastra o selecciona archivo de video</span>
                                            <span class="netflix-file-sub-text">MP4, WebM, AVI, MKV (máx. 2GB)</span>
                                        </div>
                                    </label>
                                    <div id="video_file_info" class="netflix-file-info" style="display: none;">
                                        <div class="netflix-file-info-content">
                                            <i class="fas fa-check-circle netflix-file-check"></i>
                                            <div class="netflix-file-details">
                                                <div class="netflix-file-name"></div>
                                                <div class="netflix-file-size"></div>
                                            </div>
                                            <button type="button" class="netflix-file-remove" onclick="clearVideoFile()" title="Eliminar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tráiler (Opcional) - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-film"></i></span>
                                <h3 class="netflix-section-title">Tráiler</h3>
                                <span class="netflix-optional">(Opcional)</span>
                            </div>
                            
                            <div class="netflix-form-field">
                                <div class="netflix-file-upload netflix-file-upload-large">
                                    <input type="file" id="trailer_file" name="trailer_file" accept="video/*" data-max-size="524288000" class="netflix-file-input">
                                    <label for="trailer_file" class="netflix-file-label netflix-file-label-large">
                                        <div class="netflix-file-icon-large"><i class="fas fa-play-circle"></i></div>
                                        <div class="netflix-file-text">
                                            <span class="netflix-file-main-text">Arrastra o selecciona archivo de tráiler</span>
                                            <span class="netflix-file-sub-text">MP4, WebM, AVI, MKV (máx. 500MB)</span>
                                        </div>
                                    </label>
                                    <div id="trailer_file_info" class="netflix-file-info" style="display: none;">
                                        <div class="netflix-file-info-content">
                                            <i class="fas fa-check-circle netflix-file-check"></i>
                                            <div class="netflix-file-details">
                                                <div class="netflix-file-name"></div>
                                                <div class="netflix-file-size"></div>
                                            </div>
                                            <button type="button" class="netflix-file-remove" onclick="clearTrailerFile()" title="Eliminar">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Enlace Torrent - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-magnet"></i></span>
                                <h3 class="netflix-section-title">Enlace Magnet (Torrent)</h3>
                            </div>
                            
                            <div class="netflix-form-field">
                                <div class="netflix-torrent-wrapper">
                                    <input type="text" id="torrent_magnet" name="torrent_magnet" class="netflix-input netflix-torrent-input" placeholder="magnet:?xt=urn:btih:...">
                                    <div class="netflix-torrent-actions">
                                        <button type="button" id="searchTorrentBtn" class="netflix-btn netflix-btn-secondary netflix-btn-torrent">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                        <button type="button" id="retryTorrentBtn" class="netflix-btn netflix-btn-outline netflix-btn-torrent" title="Reintentar búsqueda">
                                            <i class="fas fa-redo"></i> Reintentar
                                        </button>
                                        <button type="button" id="toggleFiltersBtn" class="netflix-btn netflix-btn-outline netflix-btn-torrent" title="Filtros avanzados">
                                            <i class="fas fa-filter"></i> Filtros
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Filtros avanzados -->
                                <div id="torrent-filters" class="netflix-torrent-filters" style="display: none; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                        <div>
                                            <label for="torrent_quality" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Calidad</label>
                                            <select id="torrent_quality" class="netflix-select" style="width: 100%;">
                                                <option value="">Todas</option>
                                                <option value="4K">4K</option>
                                                <option value="1080p">1080p</option>
                                                <option value="720p">720p</option>
                                                <option value="480p">480p</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="torrent_min_seeds" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Mínimo Seeds</label>
                                            <input type="number" id="torrent_min_seeds" class="netflix-input" placeholder="0" min="0" style="width: 100%;">
                                        </div>
                                        <div>
                                            <label for="torrent_sources" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333;">Fuentes (separadas por coma)</label>
                                            <input type="text" id="torrent_sources" class="netflix-input" placeholder="YTS,TPB,Torrentio" style="width: 100%;">
                                        </div>
                                    </div>
                                </div>
                                
                                <p class="netflix-help-text">Busca automáticamente enlaces (YTS, EZTV, TPB, Torrentio, 1337x, RARBG, LimeTorrents, Torlock, TorrentGalaxy)</p>
                                <div id="torrent-results" class="netflix-torrent-results" style="display: none;">
                                    <div id="torrent-results-content"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Configuración Adicional - Estilo Netflix -->
                        <div class="netflix-form-section">
                            <div class="netflix-section-header">
                                <span class="netflix-section-icon"><i class="fas fa-cog"></i></span>
                                <h3 class="netflix-section-title">Configuración Adicional</h3>
                            </div>
                            
                            <div class="netflix-form-field">
                                <label class="netflix-label" for="age_rating">
                                    <span class="netflix-label-text">Clasificación</span>
                                </label>
                                <select id="age_rating" name="age_rating" class="netflix-select">
                                    <option value="">Selecciona una clasificación</option>
                                    <option value="G">G - General</option>
                                    <option value="PG">PG - Guía Parental</option>
                                    <option value="PG-13">PG-13 - Mayores de 13</option>
                                    <option value="R">R - Restringido</option>
                                    <option value="NC-17">NC-17 - Solo adultos</option>
                                </select>
                            </div>
                            
                            <div class="netflix-checkbox-group">
                                <label class="netflix-checkbox-label">
                                    <input type="checkbox" id="is_featured" name="is_featured" value="1" class="netflix-checkbox">
                                    <span class="netflix-checkbox-custom"></span>
                                    <span class="netflix-checkbox-text">Contenido Destacado</span>
                                </label>
                                
                                <label class="netflix-checkbox-label">
                                    <input type="checkbox" id="is_trending" name="is_trending" value="1" class="netflix-checkbox">
                                    <span class="netflix-checkbox-custom"></span>
                                    <span class="netflix-checkbox-text">En Tendencia</span>
                                </label>
                                
                                <label class="netflix-checkbox-label">
                                    <input type="checkbox" id="is_premium" name="is_premium" value="1" class="netflix-checkbox">
                                    <span class="netflix-checkbox-custom"></span>
                                    <span class="netflix-checkbox-text">Contenido Premium</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="netflix-form-actions">
                            <button type="button" class="netflix-btn netflix-btn-secondary close-modal-btn">Cancelar</button>
                            <button type="submit" class="netflix-btn netflix-btn-primary">
                                <i class="fas fa-save"></i> Guardar Contenido
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal para agregar/editar usuarios -->
        <div class="modal" id="userModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="user-modal-title">Agregar Nuevo Usuario</h2>
                    <button class="close-modal-user">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="userForm">
                        <input type="hidden" id="user-id" name="id" value="">
                        
                        <div class="form-group">
                            <label for="username">Nombre de Usuario *</label>
                            <input type="text" id="username" name="username" required minlength="3" maxlength="50">
                            <small class="form-text">Mínimo 3 caracteres, solo letras, números y guiones bajos</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Nombre Completo</label>
                            <input type="text" id="full_name" name="full_name" maxlength="100">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Contraseña <span id="password-required" style="display:none;">*</span></label>
                                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password">
                                <small class="form-text" id="password-help">Mínimo 8 caracteres (requerida solo para nuevos usuarios)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_confirm">Confirmar Contraseña</label>
                                <input type="password" id="password_confirm" name="password_confirm" minlength="8" autocomplete="new-password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">Rol *</label>
                                <select id="role" name="role" required>
                                    <option value="user">Usuario</option>
                                    <option value="premium">Premium</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Estado *</label>
                                <select id="status" name="status" required>
                                    <option value="active">Activo</option>
                                    <option value="inactive">Inactivo</option>
                                    <option value="suspended">Suspendido</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary close-modal-user-btn">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para mostrar resultados de torrents -->
    <div class="modal" id="torrentModal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2 id="torrentModalTitle">Buscar Enlaces Torrent</h2>
                <button class="close-modal" onclick="closeTorrentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="torrentSearchStatus" style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #e50914;"></i>
                    <p>Buscando enlaces torrent...</p>
                </div>
                <div id="torrentResultsContainer" style="display: none;">
                    <div id="torrentResultsList" style="max-height: 500px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para información de IMDb -->
    <div class="modal" id="imdbModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 id="imdbModalTitle">Información de IMDb</h2>
                <button class="close-modal" onclick="closeIMDbModal()">&times;</button>
            </div>
            <div class="modal-body" id="imdbModalBody">
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #e50914;"></i>
                    <p>Cargando información...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.__APP_BASE_URL = '<?php echo $baseUrl; ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/utils.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin-charts.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin-enhanced.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo $baseUrl; ?>/js/notifications.js?v=<?php echo time(); ?>"></script>
</body>
</html>

