<?php
/**
 * API: Estadísticas del dashboard de administración
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

$db = getDbConnection();

try {
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
    
    // Usuarios el mes pasado (para calcular el cambio porcentual)
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ");
    $lastMonthUsers = (int)$stmt->fetch()['total'];
    $usersChangePercent = $lastMonthUsers > 0 ? round((($newUsersThisMonth - $lastMonthUsers) / $lastMonthUsers) * 100) : 0;
    
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
    
    // Contenido nuevo este mes (total)
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM content 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $newContentThisMonth = (int)$stmt->fetch()['total'];
    
    // Vistas totales
    $stmt = $db->query("SELECT SUM(views) as total FROM content");
    $totalViews = (int)($stmt->fetch()['total'] ?? 0);
    
    // Vistas este mes (desde la tabla views si existe)
    $totalViewsThisMonth = 0;
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as total 
            FROM views 
            WHERE MONTH(viewed_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(viewed_at) = YEAR(CURRENT_DATE())
        ");
        $totalViewsThisMonth = (int)$stmt->fetch()['total'];
    } catch (PDOException $e) {
        // Si la tabla views no existe, usar 0
        $totalViewsThisMonth = 0;
    }
    
    // Ingresos mensuales (desde suscripciones si existe la tabla)
    $monthlyRevenue = 0;
    $revenueChangePercent = 0;
    try {
        // Intentar obtener ingresos de suscripciones activas
        $stmt = $db->query("
            SELECT SUM(price) as total 
            FROM subscriptions 
            WHERE status = 'active' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $monthlyRevenue = (float)($stmt->fetch()['total'] ?? 0);
        
        // Ingresos del mes pasado
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
        // Si la tabla no existe, usar 0
        $monthlyRevenue = 0;
    }
    
    // Usuarios activos (con login en los últimos 30 días)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT user_id) as total 
        FROM views 
        WHERE viewed_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $activeUsers = (int)($stmt->fetch()['total'] ?? 0);
    
    // Usuarios premium
    $stmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users 
        WHERE role = 'premium' OR role = 'admin'
    ");
    $premiumUsers = (int)$stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'totalUsers' => $totalUsers,
            'newUsersThisMonth' => $newUsersThisMonth,
            'usersChangePercent' => $usersChangePercent,
            'totalMovies' => $totalMovies,
            'newMoviesThisMonth' => $newMoviesThisMonth,
            'totalSeries' => $totalSeries,
            'newSeriesThisMonth' => $newSeriesThisMonth,
            'newContentThisMonth' => $newContentThisMonth,
            'totalViews' => $totalViews,
            'totalViewsThisMonth' => $totalViewsThisMonth,
            'monthlyRevenue' => $monthlyRevenue,
            'revenueChangePercent' => $revenueChangePercent,
            'activeUsers' => $activeUsers,
            'premiumUsers' => $premiumUsers
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
}

