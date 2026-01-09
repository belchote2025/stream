<?php
/**
 * API: Obtener estado actual del Watch Party
 * Usado para polling y sincronización
 */

ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST');
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

    // Obtener party_id del GET o POST
    $partyId = isset($_GET['party_id']) ? (int)$_GET['party_id'] : 
               (isset($_POST['party_id']) ? (int)$_POST['party_id'] : null);
    
    if (!$partyId) {
        // Intentar obtener desde JSON POST
        $input = json_decode(file_get_contents('php://input'), true);
        $partyId = isset($input['party_id']) ? (int)$input['party_id'] : null;
    }

    if (!$partyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de party requerido'
        ]);
        exit;
    }

    // Obtener información del party
    $stmt = $db->prepare("
        SELECT wp.*, c.title as content_title, c.poster_url,
               e.title as episode_title
        FROM watch_parties wp
        LEFT JOIN content c ON wp.content_id = c.id
        LEFT JOIN episodes e ON wp.episode_id = e.id
        WHERE wp.id = ?
    ");
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

    // Verificar que el usuario es participante
    $stmt = $db->prepare("
        SELECT * FROM watch_party_participants 
        WHERE party_id = ? AND user_id = ? AND is_active = TRUE
    ");
    $stmt->execute([$partyId, $userId]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No eres participante de este Watch Party'
        ]);
        exit;
    }

    // Actualizar last_seen_at
    $stmt = $db->prepare("
        UPDATE watch_party_participants 
        SET last_seen_at = NOW()
        WHERE party_id = ? AND user_id = ?
    ");
    $stmt->execute([$partyId, $userId]);

    // Obtener lista de participantes activos
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.avatar_url, p.joined_at, p.last_seen_at,
               (u.id = ?) as is_host,
               TIMESTAMPDIFF(SECOND, p.last_seen_at, NOW()) as seconds_since_seen
        FROM watch_party_participants p
        JOIN users u ON p.user_id = u.id
        WHERE p.party_id = ? AND p.is_active = TRUE
        ORDER BY p.joined_at ASC
    ");
    $stmt->execute([$party['host_id'], $partyId]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimos eventos (últimos 10)
    $stmt = $db->prepare("
        SELECT e.*, u.username
        FROM watch_party_events e
        JOIN users u ON e.user_id = u.id
        WHERE e.party_id = ?
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$partyId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimos mensajes (últimos 20)
    $stmt = $db->prepare("
        SELECT m.*, u.username, u.avatar_url
        FROM watch_party_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.party_id = ?
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$partyId]);
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'party_id' => (int)$party['id'],
            'party_code' => $party['party_code'],
            'party_name' => $party['party_name'],
            'content_id' => (int)$party['content_id'],
            'content_title' => $party['content_title'],
            'content_type' => $party['content_type'],
            'episode_id' => $party['episode_id'] ? (int)$party['episode_id'] : null,
            'episode_title' => $party['episode_title'] ?? null,
            'host_id' => (int)$party['host_id'],
            'is_host' => ((int)$party['host_id'] === $userId),
            'current_time' => (float)$party['current_time'],
            'is_playing' => (bool)$party['is_playing'],
            'poster_url' => $party['poster_url'],
            'participants' => $participants,
            'events' => $events,
            'messages' => $messages,
            'updated_at' => $party['updated_at']
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estado'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/status.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener estado'
    ]);
    exit;
}
