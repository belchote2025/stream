<?php
/**
 * API: Añadir valoración a contenido
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Debes iniciar sesión para valorar contenido'
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
    $rating = $data['rating'] ?? null;
    
    if (!$contentId || !$rating) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido y valoración requeridos'
        ]);
        exit;
    }
    
    // Validar valoración (1-5)
    $rating = (int)$rating;
    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'La valoración debe estar entre 1 y 5'
        ]);
        exit;
    }
    
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    
    // Verificar si ya existe una valoración
    $checkStmt = $db->prepare("SELECT id FROM ratings WHERE user_id = ? AND content_id = ?");
    $checkStmt->execute([$userId, $contentId]);
    
    if ($checkStmt->fetch()) {
        // Actualizar valoración existente
        $updateStmt = $db->prepare("UPDATE ratings SET rating = ?, updated_at = NOW() WHERE user_id = ? AND content_id = ?");
        $updateStmt->execute([$rating, $userId, $contentId]);
        $message = 'Valoración actualizada';
    } else {
        // Crear nueva valoración
        $insertStmt = $db->prepare("INSERT INTO ratings (user_id, content_id, rating) VALUES (?, ?, ?)");
        $insertStmt->execute([$userId, $contentId, $rating]);
        $message = 'Valoración añadida';
    }
    
    // Actualizar rating promedio del contenido
    $avgStmt = $db->prepare("
        UPDATE content c
        SET c.rating = (
            SELECT AVG(rating) 
            FROM ratings 
            WHERE content_id = c.id
        )
        WHERE c.id = ?
    ");
    $avgStmt->execute([$contentId]);
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

