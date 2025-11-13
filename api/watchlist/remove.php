<?php
/**
 * API: Remover contenido de Mi lista
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
    
    $stmt = $db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND content_id = ?");
    $stmt->execute([$userId, $contentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Removido de Mi lista correctamente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

