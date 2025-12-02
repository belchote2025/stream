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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/admin.css">
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
                </ul>
            </nav>
            <div class="logout">
                <a href="<?php echo $baseUrl; ?>/api/auth/logout.php">
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
                                    $stmt = $db->query("SELECT SUM(view_count) as total FROM content");
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
                <div class="modal-body">
                    <form id="contentForm" enctype="multipart/form-data">
                        <input type="hidden" id="content-id" name="id" value="">
                        <input type="hidden" id="content-type" name="type" value="movie">
                        
                        <div class="form-group">
                            <label for="title">Título *</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="release_year">Año *</label>
                                <input type="number" id="release_year" name="release_year" min="1900" max="<?php echo date('Y') + 5; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Duración (min) *</label>
                                <input type="number" id="duration" name="duration" min="1" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Descripción *</label>
                            <textarea id="description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="poster_url">URL del Póster</label>
                                <input type="url" id="poster_url" name="poster_url" placeholder="https://...">
                            </div>
                            
                            <div class="form-group">
                                <label for="backdrop_url">URL del Backdrop</label>
                                <input type="url" id="backdrop_url" name="backdrop_url" placeholder="https://...">
                            </div>
                        </div>
                        
                        <!-- Video Principal -->
                        <div class="form-group">
                            <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                <i class="fas fa-video"></i> Video Principal
                            </label>
                            
                            <!-- Opción 1: URL -->
                            <div class="video-option-card" id="videoUrlOption" style="margin-bottom: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <input type="radio" id="video_source_url" name="video_source" value="url" style="margin-right: 0.5rem;">
                                    <label for="video_source_url" style="margin: 0; font-weight: 600; cursor: pointer; flex: 1;">
                                        <i class="fas fa-link"></i> Usar URL de Video
                                    </label>
                                </div>
                                <div id="videoUrlContainer" style="display: none; margin-top: 0.75rem;">
                                    <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                        <input type="url" id="video_url" name="video_url" placeholder="https://... o /uploads/videos/archivo.mp4" style="flex: 1; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;" disabled>
                                        <button type="button" id="previewVideoBtn" class="btn btn-secondary" style="white-space: nowrap; padding: 0.75rem 1rem;" title="Previsualizar video" disabled>
                                            <i class="fas fa-play"></i> Previsualizar
                                        </button>
                                    </div>
                                    <small class="form-text" style="display: block; margin-top: 0.5rem; color: #666;">
                                        Puedes usar una URL externa (https://...) o una ruta local (/uploads/videos/...)
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Opción 2: Archivo Local -->
                            <div class="video-option-card" id="videoFileOption" style="margin-bottom: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <input type="radio" id="video_source_file" name="video_source" value="file" style="margin-right: 0.5rem;">
                                    <label for="video_source_file" style="margin: 0; font-weight: 600; cursor: pointer; flex: 1;">
                                        <i class="fas fa-upload"></i> Subir Archivo Local
                                    </label>
                                </div>
                                <div id="videoFileContainer" style="display: none; margin-top: 0.75rem;">
                                    <input type="file" id="video_file" name="video_file" accept="video/*" data-max-size="2147483648" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; background: white;" disabled>
                                    <small class="form-text" style="display: block; margin-top: 0.5rem; color: #666;">
                                        Formatos: MP4, WebM, AVI, MKV (máx. 2GB)
                                    </small>
                                    <div id="video_file_info" class="file-info" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: rgba(0,0,0,0.05); border-radius: 4px; border-left: 3px solid #28a745;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                            <div style="flex: 1;">
                                                <div class="file-name" style="font-weight: 600;"></div>
                                                <div class="file-size" style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;"></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="clearVideoFile()" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contenedor de previsualización de video -->
                        <div id="videoPreviewContainer" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(0,0,0,0.1); border-radius: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0;">Previsualización del Video</h4>
                                <button type="button" id="closePreviewBtn" class="btn btn-sm btn-secondary" style="padding: 0.25rem 0.5rem;">
                                    <i class="fas fa-times"></i> Cerrar
                                </button>
                            </div>
                            <div id="videoPreviewPlayer" style="position: relative; width: 100%; padding-bottom: 56.25%; background: #000; border-radius: 4px; overflow: hidden;">
                                <!-- El reproductor se insertará aquí -->
                            </div>
                        </div>
                        
                        <!-- Tráiler (Opcional) -->
                        <div class="form-group">
                            <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">
                                <i class="fas fa-film"></i> Tráiler (Opcional)
                            </label>
                            
                            <!-- Opción 1: URL -->
                            <div class="trailer-option-card" id="trailerUrlOption" style="margin-bottom: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <input type="radio" id="trailer_source_url" name="trailer_source" value="url" style="margin-right: 0.5rem;">
                                    <label for="trailer_source_url" style="margin: 0; font-weight: 600; cursor: pointer; flex: 1;">
                                        <i class="fas fa-link"></i> Usar URL de Tráiler
                                    </label>
                                </div>
                                <div id="trailerUrlContainer" style="display: none; margin-top: 0.75rem;">
                                    <input type="url" id="trailer_url" name="trailer_url" placeholder="https://... o /uploads/videos/trailer.mp4" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px;" disabled>
                                    <small class="form-text" style="display: block; margin-top: 0.5rem; color: #666;">
                                        Puedes usar una URL externa (https://...) o una ruta local (/uploads/videos/...)
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Opción 2: Archivo Local -->
                            <div class="trailer-option-card" id="trailerFileOption" style="margin-bottom: 1rem; padding: 1rem; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <input type="radio" id="trailer_source_file" name="trailer_source" value="file" style="margin-right: 0.5rem;">
                                    <label for="trailer_source_file" style="margin: 0; font-weight: 600; cursor: pointer; flex: 1;">
                                        <i class="fas fa-upload"></i> Subir Tráiler Local
                                    </label>
                                </div>
                                <div id="trailerFileContainer" style="display: none; margin-top: 0.75rem;">
                                    <input type="file" id="trailer_file" name="trailer_file" accept="video/*" data-max-size="524288000" style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; background: white;" disabled>
                                    <small class="form-text" style="display: block; margin-top: 0.5rem; color: #666;">
                                        Formatos: MP4, WebM, AVI, MKV (máx. 500MB)
                                    </small>
                                    <div id="trailer_file_info" class="file-info" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: rgba(0,0,0,0.05); border-radius: 4px; border-left: 3px solid #28a745;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                            <div style="flex: 1;">
                                                <div class="file-name" style="font-weight: 600;"></div>
                                                <div class="file-size" style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;"></div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="clearTrailerFile()" style="padding: 0.25rem 0.5rem;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Opción 3: Ninguno -->
                            <div style="margin-bottom: 0.5rem;">
                                <input type="radio" id="trailer_source_none" name="trailer_source" value="none" checked style="margin-right: 0.5rem;">
                                <label for="trailer_source_none" style="margin: 0; cursor: pointer; color: #666;">
                                    No usar tráiler
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="torrent_magnet">Enlace Magnet (Torrent)</label>
                            <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                <input type="text" id="torrent_magnet" name="torrent_magnet" placeholder="magnet:?xt=urn:btih:..." style="flex: 1;">
                                <div style="display: flex; gap: 0.35rem;">
                                    <button type="button" id="searchTorrentBtn" class="btn btn-secondary" style="white-space: nowrap; padding: 0.75rem 1rem;">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <button type="button" id="retryTorrentBtn" class="btn btn-outline-warning" style="white-space: nowrap; padding: 0.75rem 1rem;" title="Si el enlace seleccionado no funciona, volver a buscar en otras fuentes">
                                        <i class="fas fa-redo"></i> Reintentar
                                    </button>
                                </div>
                            </div>
                            <small class="form-text">Busca automáticamente enlaces (YTS, EZTV, TPB, Torrentio). Usa "Reintentar" si el enlace no funciona.</small>
                            <div id="torrent-results" style="display: none; margin-top: 1rem; padding: 1rem; background: rgba(0,0,0,0.05); border-radius: 4px; max-height: 300px; overflow-y: auto;">
                                <div id="torrent-results-content"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="age_rating">Clasificación</label>
                            <select id="age_rating" name="age_rating">
                                <option value="">Selecciona...</option>
                                <option value="G">G - General</option>
                                <option value="PG">PG - Guía Parental</option>
                                <option value="PG-13">PG-13 - Mayores de 13</option>
                                <option value="R">R - Restringido</option>
                                <option value="NC-17">NC-17 - Solo adultos</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-container">
                                <input type="checkbox" id="is_featured" name="is_featured" value="1">
                                <span class="checkmark"></span>
                                Contenido Destacado
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-container">
                                <input type="checkbox" id="is_trending" name="is_trending" value="1">
                                <span class="checkmark"></span>
                                En Tendencia
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-container">
                                <input type="checkbox" id="is_premium" name="is_premium" value="1">
                                <span class="checkmark"></span>
                                Contenido Premium
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary close-modal-btn">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
    <script src="<?php echo $baseUrl; ?>/js/utils.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin-charts.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin-enhanced.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/admin.js"></script>
    <script src="<?php echo $baseUrl; ?>/js/notifications.js"></script>
</body>
</html>

