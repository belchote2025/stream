<?php
/**
 * API para incrementar el contador de vistas
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$contentId = $data['content_id'] ?? null;
$episodeId = $data['episode_id'] ?? null;

if (!$contentId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de contenido requerido']);
    exit;
}

try {
    $db = getDbConnection();
    
    // Incrementar vistas del contenido
    $stmt = $db->prepare("UPDATE content SET views = views + 1 WHERE id = ?");
    $stmt->execute([$contentId]);
    
    // Si es un episodio, también incrementar sus vistas
    if ($episodeId) {
        $stmt = $db->prepare("UPDATE episodes SET views = views + 1 WHERE id = ?");
        $stmt->execute([$episodeId]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al actualizar vistas: ' . $e->getMessage()]);
}

