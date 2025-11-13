<?php
/**
 * API: Añadir contenido a Mi lista
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Debes iniciar sesión para añadir contenido a tu lista'
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
    
    // Verificar si ya está en la lista
    $checkStmt = $db->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND content_id = ?");
    $checkStmt->execute([$userId, $contentId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode([
            'success' => true,
            'message' => 'Ya está en tu lista',
            'already_added' => true
        ]);
        exit;
    }
    
    // Añadir a la lista
    $insertStmt = $db->prepare("INSERT INTO user_favorites (user_id, content_id) VALUES (?, ?)");
    $insertStmt->execute([$userId, $contentId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Añadido a Mi lista correctamente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al añadir a la lista: ' . $e->getMessage()
    ]);
}

