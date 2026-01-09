<?php
/**
 * API: Enviar mensaje de chat en Watch Party
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
    $message = isset($input['message']) ? trim($input['message']) : '';

    if (!$partyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de party requerido'
        ]);
        exit;
    }

    if (empty($message)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'El mensaje no puede estar vacío'
        ]);
        exit;
    }

    if (strlen($message) > 500) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'El mensaje es demasiado largo (máximo 500 caracteres)'
        ]);
        exit;
    }

    // Verificar que el party existe
    $stmt = $db->prepare("SELECT id FROM watch_parties WHERE id = ?");
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

    // Sanitizar mensaje
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Insertar mensaje
    $stmt = $db->prepare("
        INSERT INTO watch_party_messages (party_id, user_id, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$partyId, $userId, $message]);

    $messageId = $db->lastInsertId();

    // Obtener información del usuario
    $stmt = $db->prepare("SELECT username, avatar_url FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'message_id' => (int)$messageId,
            'party_id' => $partyId,
            'user_id' => $userId,
            'username' => $user['username'],
            'avatar_url' => $user['avatar_url'],
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/chat.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar mensaje'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/chat.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar mensaje'
    ]);
    exit;
}
