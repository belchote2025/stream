<?php
/**
 * API: Crear Watch Party
 * Permite crear una sesión de visualización en grupo
 */

ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';

ob_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Verificar autenticación
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Usuario no autenticado'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $db = getDbConnection();

    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $contentId = isset($input['content_id']) ? (int)$input['content_id'] : null;
    $contentType = isset($input['content_type']) ? $input['content_type'] : 'movie';
    $episodeId = isset($input['episode_id']) ? (int)$input['episode_id'] : null;
    $partyName = isset($input['party_name']) ? trim($input['party_name']) : 'Watch Party';

    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido requerido'
        ]);
        exit;
    }

    // Verificar que el contenido existe
    $stmt = $db->prepare("SELECT id, title FROM content WHERE id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch();

    if (!$content) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Contenido no encontrado'
        ]);
        exit;
    }

    // Generar código único para el party
    $partyCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

    // Crear watch party
    $stmt = $db->prepare("
        INSERT INTO watch_parties (
            party_code,
            host_id,
            content_id,
            content_type,
            episode_id,
            party_name,
            current_time,
            is_playing,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, NOW())
    ");

    $stmt->execute([
        $partyCode,
        $userId,
        $contentId,
        $contentType,
        $episodeId,
        $partyName
    ]);

    $partyId = $db->lastInsertId();

    // Añadir host como participante
    $stmt = $db->prepare("
        INSERT INTO watch_party_participants (party_id, user_id, joined_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$partyId, $userId]);

    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'party_id' => (int)$partyId,
            'party_code' => $partyCode,
            'party_name' => $partyName,
            'content_id' => $contentId,
            'content_title' => $content['title'],
            'host_id' => $userId,
            'url' => rtrim(SITE_URL, '/') . '/watch-party.php?code=' . $partyCode
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/create.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al crear watch party'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/create.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al crear watch party'
    ]);
    exit;
}






