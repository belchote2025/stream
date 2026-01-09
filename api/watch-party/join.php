<?php
/**
 * API: Unirse a Watch Party
 * Permite a un usuario unirse a una sesión usando el código
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
    
    $partyCode = isset($input['party_code']) ? strtoupper(trim($input['party_code'])) : null;

    if (!$partyCode || strlen($partyCode) !== 8) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Código de party inválido'
        ]);
        exit;
    }

    // Buscar el watch party
    $stmt = $db->prepare("
        SELECT wp.*, c.title as content_title, c.poster_url, c.video_url, c.torrent_magnet,
               e.video_url as episode_video_url, e.title as episode_title
        FROM watch_parties wp
        LEFT JOIN content c ON wp.content_id = c.id
        LEFT JOIN episodes e ON wp.episode_id = e.id
        WHERE wp.party_code = ?
    ");
    $stmt->execute([$partyCode]);
    $party = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$party) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Watch Party no encontrado'
        ]);
        exit;
    }

    // Verificar si el party ha expirado
    if ($party['expires_at'] && strtotime($party['expires_at']) < time()) {
        http_response_code(410);
        echo json_encode([
            'success' => false,
            'error' => 'Este Watch Party ha expirado'
        ]);
        exit;
    }

    // Verificar si el usuario ya es participante
    $stmt = $db->prepare("
        SELECT * FROM watch_party_participants 
        WHERE party_id = ? AND user_id = ?
    ");
    $stmt->execute([$party['id'], $userId]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        // Añadir como participante
        $stmt = $db->prepare("
            INSERT INTO watch_party_participants (party_id, user_id, joined_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$party['id'], $userId]);
    } else {
        // Actualizar last_seen_at y reactivar si estaba inactivo
        $stmt = $db->prepare("
            UPDATE watch_party_participants 
            SET last_seen_at = NOW(), is_active = TRUE
            WHERE party_id = ? AND user_id = ?
        ");
        $stmt->execute([$party['id'], $userId]);
    }

    // Obtener lista de participantes
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.avatar_url, p.joined_at, p.is_active,
               (u.id = ?) as is_host
        FROM watch_party_participants p
        JOIN users u ON p.user_id = u.id
        WHERE p.party_id = ? AND p.is_active = TRUE
        ORDER BY p.joined_at ASC
    ");
    $stmt->execute([$party['host_id'], $party['id']]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            'video_url' => $party['episode_video_url'] ?? $party['video_url'],
            'torrent_magnet' => $party['torrent_magnet'],
            'poster_url' => $party['poster_url'],
            'participants' => $participants,
            'url' => rtrim(SITE_URL, '/') . '/watch-party.php?code=' . $partyCode
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/join.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al unirse al watch party'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/join.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al unirse al watch party'
    ]);
    exit;
}
