<?php
/**
 * API: Continuar viendo
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/image-helper.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Debes iniciar sesión'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    // Obtener contenido que el usuario ha empezado a ver pero no ha completado
    $query = "
        SELECT 
            c.id,
            c.title,
            c.type,
            c.poster_url,
            c.backdrop_url,
            ph.progress,
            ph.duration,
            ph.episode_id,
            e.season_number,
            e.episode_number,
            e.title as episode_title
        FROM playback_history ph
        INNER JOIN content c ON ph.content_id = c.id
        LEFT JOIN episodes e ON ph.episode_id = e.id
        WHERE ph.user_id = :user_id
        AND ph.completed = 0
        AND ph.progress > 0
        ORDER BY ph.updated_at DESC
        LIMIT :limit
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $item) {
        $progressPercent = $item['duration'] > 0 
            ? round(($item['progress'] / $item['duration']) * 100) 
            : 0;
        
        $formatted[] = [
            'id' => (int)$item['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'poster_url' => getImageUrl($item['poster_url'] ?? '', '/assets/img/default-poster.svg'),
            'backdrop_url' => getImageUrl($item['backdrop_url'] ?? '', '/assets/img/default-backdrop.svg'),
            'progress' => $progressPercent,
            'progress_seconds' => (int)$item['progress'],
            'duration_seconds' => (int)$item['duration'],
            'episode_id' => $item['episode_id'] ? (int)$item['episode_id'] : null,
            'episode_info' => $item['episode_id'] 
                ? "T{$item['season_number']}E{$item['episode_number']}: {$item['episode_title']}"
                : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

