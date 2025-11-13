<?php
/**
 * API: Guardar progreso de reproducción
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $contentId = $data['content_id'] ?? null;
    $episodeId = $data['episode_id'] ?? null;
    $progress = $data['progress'] ?? 0; // en segundos
    $duration = $data['duration'] ?? 0; // en segundos
    
    if (!$contentId || !$duration) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Datos incompletos'
        ]);
        exit;
    }
    
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    
    // Determinar si está completado (más del 90% visto)
    $completed = ($progress / $duration) >= 0.9 ? 1 : 0;
    
    // Verificar si ya existe un registro
    $checkQuery = "
        SELECT id FROM playback_history 
        WHERE user_id = ? AND content_id = ? 
        " . ($episodeId ? "AND episode_id = ?" : "AND episode_id IS NULL");
    
    $checkStmt = $db->prepare($checkQuery);
    if ($episodeId) {
        $checkStmt->execute([$userId, $contentId, $episodeId]);
    } else {
        $checkStmt->execute([$userId, $contentId]);
    }
    
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        // Actualizar registro existente
        $updateQuery = "
            UPDATE playback_history 
            SET progress = ?, duration = ?, completed = ?, updated_at = NOW()
            WHERE id = ?
        ";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([$progress, $duration, $completed, $existing['id']]);
    } else {
        // Crear nuevo registro
        $insertQuery = "
            INSERT INTO playback_history (user_id, content_id, episode_id, progress, duration, completed)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([$userId, $contentId, $episodeId, $progress, $duration, $completed]);
    }
    
    // Actualizar contador de vistas del contenido
    $viewsStmt = $db->prepare("
        INSERT INTO views (content_id, user_id, viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $viewsStmt->execute([$contentId, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Progreso guardado',
        'completed' => (bool)$completed
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

