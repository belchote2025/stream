<?php
/**
 * API: Abandonar Watch Party
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

    if (!$partyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de party requerido'
        ]);
        exit;
    }

    // Verificar que el party existe
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

    // Si es el host, eliminar el party completo
    if ((int)$party['host_id'] === $userId) {
        $stmt = $db->prepare("DELETE FROM watch_parties WHERE id = ?");
        $stmt->execute([$partyId]);
    } else {
        // Si no es el host, solo marcar como inactivo
        $stmt = $db->prepare("
            UPDATE watch_party_participants 
            SET is_active = FALSE
            WHERE party_id = ? AND user_id = ?
        ");
        $stmt->execute([$partyId, $userId]);
    }

    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Has abandonado el Watch Party'
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/leave.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al abandonar el party'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en watch-party/leave.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al abandonar el party'
    ]);
    exit;
}
