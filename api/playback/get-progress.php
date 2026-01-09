<?php
/**
 * API: Obtener progreso de reproducción guardado
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Debes iniciar sesión'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {
    $contentId = $_GET['content_id'] ?? null;
    $episodeId = $_GET['episode_id'] ?? null;
    
    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido requerido'
        ]);
        exit;
    }
    
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    
    $query = "
        SELECT progress, duration, completed 
        FROM playback_history 
        WHERE user_id = ? AND content_id = ? 
        " . ($episodeId ? "AND episode_id = ?" : "AND episode_id IS NULL") . "
        ORDER BY updated_at DESC 
        LIMIT 1";
    
    $stmt = $db->prepare($query);
    if ($episodeId) {
        $stmt->execute([$userId, $contentId, $episodeId]);
    } else {
        $stmt->execute([$userId, $contentId]);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'progress' => (float)$result['progress'],
            'duration' => (float)$result['duration'],
            'completed' => (bool)$result['completed']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'progress' => 0,
            'duration' => 0,
            'completed' => false
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}





















