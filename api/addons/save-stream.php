<?php
/**
 * API: Guardar un enlace de streaming en el contenido
 */

ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: POST');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/addons/AddonManager.php';

ob_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Verificar autenticación de admin
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'No autorizado'
        ]);
        exit;
    }
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $contentId = isset($input['content_id']) ? (int)$input['content_id'] : null;
    $contentType = isset($input['content_type']) ? $input['content_type'] : 'movie';
    $streamUrl = isset($input['stream_url']) ? trim($input['stream_url']) : null;
    $streamType = isset($input['stream_type']) ? $input['stream_type'] : 'direct'; // 'direct', 'embed', 'torrent'
    $season = isset($input['season']) ? (int)$input['season'] : null;
    $episode = isset($input['episode']) ? (int)$input['episode'] : null;
    $verifyUrl = isset($input['verify_url']) ? (bool)$input['verify_url'] : true;
    
    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido requerido'
        ]);
        exit;
    }
    
    if (!$streamUrl) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'URL del stream requerida'
        ]);
        exit;
    }
    
    if (!in_array($contentType, ['movie', 'series'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Tipo de contenido inválido'
        ]);
        exit;
    }
    
    // Verificar que el enlace funciona (opcional)
    $urlValid = true;
    $urlMessage = '';
    
    if ($verifyUrl && $streamType !== 'torrent') {
        // Para enlaces directos y embeds, verificar que la URL es válida
        if (!filter_var($streamUrl, FILTER_VALIDATE_URL)) {
            $urlValid = false;
            $urlMessage = 'URL no válida';
        } else {
            // Intentar verificar que el servidor responde (solo para enlaces directos)
            if ($streamType === 'direct') {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'timeout' => 5,
                        'ignore_errors' => true,
                        'follow_location' => true,
                        'max_redirects' => 3
                    ]
                ]);
                
                $headers = @get_headers($streamUrl, 1, $context);
                if ($headers === false || strpos($headers[0], '200') === false) {
                    // No es crítico, puede ser un enlace que requiere autenticación o tiene CORS
                    $urlMessage = 'No se pudo verificar la URL (puede requerir autenticación)';
                } else {
                    $urlValid = true;
                    $urlMessage = 'URL verificada correctamente';
                }
            } else {
                // Para embeds, solo verificar formato
                $urlValid = true;
                $urlMessage = 'URL de embed válida';
            }
        }
    } elseif ($streamType === 'torrent') {
        // Para torrents, verificar que es un magnet link válido
        if (strpos($streamUrl, 'magnet:') === 0) {
            $urlValid = true;
            $urlMessage = 'Enlace magnet válido';
        } else {
            $urlValid = false;
            $urlMessage = 'El enlace torrent debe ser un magnet link';
        }
    }
    
    if (!$urlValid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $urlMessage
        ]);
        exit;
    }
    
    // Conectar a la base de datos
    $db = getDbConnection();
    
    // Verificar que el contenido existe
    $stmt = $db->prepare("SELECT id, type FROM content WHERE id = ?");
    $stmt->execute([$contentId]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Contenido no encontrado'
        ]);
        exit;
    }
    
    // Actualizar el contenido según el tipo
    if ($contentType === 'series' && $season !== null && $episode !== null) {
        // Para series, actualizar el episodio específico
        $episodeStmt = $db->prepare("
            SELECT id FROM episodes 
            WHERE series_id = ? AND season_number = ? AND episode_number = ?
            LIMIT 1
        ");
        $episodeStmt->execute([$contentId, $season, $episode]);
        $episodeData = $episodeStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($episodeData) {
            // Actualizar episodio existente
            $updateStmt = $db->prepare("
                UPDATE episodes 
                SET video_url = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$streamUrl, $episodeData['id']]);
        } else {
            // Crear nuevo episodio
            $insertStmt = $db->prepare("
                INSERT INTO episodes (series_id, season_number, episode_number, video_url, title, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $contentId, 
                $season, 
                $episode, 
                $streamUrl,
                "Episodio {$episode}"
            ]);
        }
        
        $savedType = 'episode';
        $savedInfo = "Temporada {$season}, Episodio {$episode}";
    } else {
        // Para películas o series sin episodio específico, actualizar el contenido principal
        if ($streamType === 'torrent') {
            $updateStmt = $db->prepare("
                UPDATE content 
                SET torrent_magnet = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$streamUrl, $contentId]);
        } else {
            $updateStmt = $db->prepare("
                UPDATE content 
                SET video_url = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$streamUrl, $contentId]);
        }
        
        $savedType = $contentType;
        $savedInfo = $content['type'];
    }
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Enlace guardado correctamente',
        'data' => [
            'content_id' => $contentId,
            'content_type' => $contentType,
            'stream_url' => $streamUrl,
            'stream_type' => $streamType,
            'saved_type' => $savedType,
            'saved_info' => $savedInfo,
            'url_verified' => $urlValid,
            'url_message' => $urlMessage
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en api/addons/save-stream.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el enlace: ' . $e->getMessage()
    ]);
    exit;
}
