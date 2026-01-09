<?php
/**
 * API: Sincronizar estado del reproductor (Play/Pause/Seek)
 * Solo el host puede enviar eventos de sincronización
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
    
    $partyId = isset($input['party_id']) ? (int)$input['party_id'] : null;
    $eventType = isset($input['event_type']) ? $input['event_type'] : null;
    $currentTime = isset($input['current_time']) ? (float)$input['current_time'] : 0;
    $isPlaying = isset($input['is_playing']) ? (bool)$input['is_playing'] : false;

    if (!$partyId || !$eventType) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Datos incompletos'
        ]);
        exit;
    }

    // Verificar que el evento sea válido
    $validEvents = ['play', 'pause', 'seek', 'buffering'];
    if (!in_array($eventType, $validEvents)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Tipo de evento inválido'
        ]);
        exit;
    }

    // Verificar que el usuario es el host
    $stmt = $db->prepare("SELECT host_id FROM watch_parties WHERE id = ?");
    $stmt->execute([$partyId]);
    $party = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$party) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Watch Party no encontrado'
        ]);
        exit;
    }

    if ((int)$party['host_id'] !== $userId) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Solo el host puede sincronizar el reproductor'
        ]);
        exit;
    }

    // Actualizar estado del party
    $stmt = $db->prepare("
        UPDATE watch_parties 
        SET current_time = ?, is_playing = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$currentTime, $isPlaying ? 1 : 0, $partyId]);

    // Registrar evento
    $stmt = $db->prepare("
        INSERT INTO watch_party_events (party_id, user_id, event_type, current_time, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$partyId, $userId, $eventType, $currentTime]);

    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'party_id' => $partyId,
            'event_type' => $eventType,
            'current_time' => $currentTime,
            'is_playing' => $isPlaying,
            'timestamp' => time()
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/sync.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al sincronizar'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/sync.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al sincronizar'
    ]);
    exit;
}
