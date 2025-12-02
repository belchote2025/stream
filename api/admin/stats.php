<?php
/**
 * API: Estadísticas avanzadas del dashboard de administración
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

$db = getDbConnection();

try {
    // ============================================
    // USUARIOS
    // ============================================
    
    // Usuarios totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = (int)$stmt->fetch()['total'];
    
    // Usuarios este mes
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $newUsersThisMonth = (int)$stmt->fetch()['total'];
    
    // Usuarios el mes pasado
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ");
    $lastMonthUsers = (int)$stmt->fetch()['total'];
    $usersChangePercent = $lastMonthUsers > 0 ? round((($newUsersThisMonth - $lastMonthUsers) / $lastMonthUsers) * 100) : 0;
    
    // Usuarios activos (últimos 7 días)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT user_id) as total 
        FROM playback_history 
        WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $activeUsersWeek = (int)($stmt->fetch()['total'] ?? 0);
    
    // Usuarios premium
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE role = 'premium' OR role = 'admin'
    ");
    $premiumUsers = (int)$stmt->fetch()['total'];
    
    // Distribución por rol
    $stmt = $db->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        GROUP BY role
    ");
    $usersByRole = [];
    while ($row = $stmt->fetch()) {
        $usersByRole[$row['role']] = (int)$row['count'];
    }
    
    // ============================================
    // CONTENIDO
    // ============================================
    
    // Películas totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'movie'");
    $totalMovies = (int)$stmt->fetch()['total'];
    
    // Películas este mes
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM content 
        WHERE type = 'movie' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $newMoviesThisMonth = (int)$stmt->fetch()['total'];
    
    // Series totales
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE type = 'series'");
    $totalSeries = (int)$stmt->fetch()['total'];
    
    // Series este mes
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM content 
        WHERE type = 'series' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $newSeriesThisMonth = (int)$stmt->fetch()['total'];
    
    // Contenido destacado
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE is_featured = 1");
    $featuredContent = (int)$stmt->fetch()['total'];
    
    // Contenido premium
    $stmt = $db->query("SELECT COUNT(*) as total FROM content WHERE is_premium = 1");
    $premiumContent = (int)$stmt->fetch()['total'];
    
    // ============================================
    // VISTAS Y ENGAGEMENT
    // ============================================
    
    // Vistas totales
    $stmt = $db->query("SELECT SUM(view_count) as total FROM content");
    $totalViews = (int)($stmt->fetch()['total'] ?? 0);
    
    // Vistas este mes
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM playback_history 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $viewsThisMonth = (int)($stmt->fetch()['total'] ?? 0);
    
    // Vistas hoy
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM playback_history 
        WHERE DATE(created_at) = CURDATE()
    ");
    $viewsToday = (int)($stmt->fetch()['total'] ?? 0);
    
    // Contenido más visto (últimos 30 días)
    $stmt = $db->query("
        SELECT c.id, c.title, c.type, COUNT(ph.id) as views
        FROM content c
        LEFT JOIN playback_history ph ON c.id = ph.content_id
        WHERE ph.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY c.id
        ORDER BY views DESC
        LIMIT 5
    ");
    $topContent = $stmt->fetchAll();
    
    // Tiempo promedio de visualización (en minutos)
    $stmt = $db->query("
        SELECT AVG(progress / 60) as avg_minutes
        FROM playback_history
        WHERE progress > 0
    ");
    $avgWatchTime = round((float)($stmt->fetch()['avg_minutes'] ?? 0), 1);
    
    // ============================================
    // INGRESOS (si existe tabla subscriptions)
    // ============================================
    
    $monthlyRevenue = 0;
    $revenueChangePercent = 0;
    $totalRevenue = 0;
    
    try {
        // Ingresos este mes
        $stmt = $db->query("
            SELECT SUM(price) as total 
            FROM subscriptions 
            WHERE status = 'active' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $monthlyRevenue = (float)($stmt->fetch()['total'] ?? 0);
        
        // Ingresos totales
        $stmt = $db->query("
            SELECT SUM(price) as total 
            FROM subscriptions 
            WHERE status = 'active'
        ");
        $totalRevenue = (float)($stmt->fetch()['total'] ?? 0);
        
        // Ingresos mes pasado
        $stmt = $db->query("
            SELECT SUM(price) as total 
            FROM subscriptions 
            WHERE status = 'active' 
            AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");
        $lastMonthRevenue = (float)($stmt->fetch()['total'] ?? 0);
        
        if ($lastMonthRevenue > 0) {
            $revenueChangePercent = round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100);
        }
    } catch (PDOException $e) {
        // Tabla no existe
    }
    
    // ============================================
    // TENDENCIAS (últimos 7 días)
    // ============================================
    
    $stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as views
        FROM playback_history
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $viewsTrend = $stmt->fetchAll();
    
    $stmt = $db->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as users
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $usersTrend = $stmt->fetchAll();
    
    // ============================================
    // RESPUESTA
    // ============================================
    
    echo json_encode([
        'success' => true,
        'data' => [
            // Usuarios
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'usersChangePercent' => $usersChangePercent,
            'activeUsersWeek' => $activeUsersWeek,
            'premiumUsers' => $premiumUsers,
            'usersByRole' => $usersByRole,
            
            // Contenido
            'totalMovies' => $totalMovies,
            'newMoviesThisMonth' => $newMoviesThisMonth,
            'totalSeries' => $totalSeries,
            'newSeriesThisMonth' => $newSeriesThisMonth,
            'newContentThisMonth' => $newMoviesThisMonth + $newSeriesThisMonth,
            'featuredContent' => $featuredContent,
            'premiumContent' => $premiumContent,
            
            // Vistas
            'totalViews' => $totalViews,
            'viewsThisMonth' => $viewsThisMonth,
            'viewsToday' => $viewsToday,
            'avgWatchTime' => $avgWatchTime,
            'topContent' => $topContent,
            
            // Ingresos
            'monthlyRevenue' => $monthlyRevenue,
            'totalRevenue' => $totalRevenue,
            'revenueChangePercent' => $revenueChangePercent,
            
            // Tendencias
            'viewsTrend' => $viewsTrend,
            'usersTrend' => $usersTrend
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}
