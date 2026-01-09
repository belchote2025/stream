<?php
/**
 * API para integrar con qBittorrent Web UI
 * Permite agregar torrents a qBittorrent y controlar descargas
 * 
 * Referencia: https://qbittorrent-api.readthedocs.io/en/v2021.4.20/apidoc/torrents.html
 */

require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Debes iniciar sesión.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Obtener configuración de qBittorrent
$qbUrl = getenv('QBITTORRENT_URL') ?: (defined('QBITTORRENT_URL') ? QBITTORRENT_URL : '');
$qbUsername = getenv('QBITTORRENT_USERNAME') ?: (defined('QBITTORRENT_USERNAME') ? QBITTORRENT_USERNAME : 'admin');
$qbPassword = getenv('QBITTORRENT_PASSWORD') ?: (defined('QBITTORRENT_PASSWORD') ? QBITTORRENT_PASSWORD : 'adminadmin');

if (empty($qbUrl)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'qBittorrent no está configurado. Añade QBITTORRENT_URL en .env'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Normalizar URL
$qbUrl = rtrim($qbUrl, '/');

/**
 * Autenticarse en qBittorrent
 */
function qbLogin($url, $username, $password) {
    $loginUrl = $url . '/api/v2/auth/login';
    
    $postData = http_build_query([
        'username' => $username,
        'password' => $password
    ]);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                       "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $response = @file_get_contents($loginUrl, false, $context);
    
    // qBittorrent devuelve "Ok." si el login es exitoso
    return $response === 'Ok.';
}

/**
 * Realizar petición autenticada a qBittorrent
 */
function qbRequest($url, $endpoint, $method = 'GET', $data = null, $username = '', $password = '') {
    // Primero autenticarse
    if (!qbLogin($url, $username, $password)) {
        return ['error' => 'Error de autenticación con qBittorrent'];
    }
    
    $requestUrl = $url . $endpoint;
    
    $headers = [
        "Content-Type: application/x-www-form-urlencoded",
    ];
    
    if ($method === 'POST' && $data) {
        $postData = is_array($data) ? http_build_query($data) : $data;
        $headers[] = "Content-Length: " . strlen($postData);
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => ($method === 'POST' && $data) ? (is_array($data) ? http_build_query($data) : $data) : null,
            'timeout' => 15,
            'ignore_errors' => true,
        ]
    ]);
    
    $response = @file_get_contents($requestUrl, false, $context);
    
    if ($response === false) {
        return ['error' => 'No se pudo conectar a qBittorrent'];
    }
    
    // Intentar parsear como JSON
    $json = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }
    
    // Si no es JSON, devolver la respuesta tal cual
    return ['response' => $response];
}

// Procesar acciones
try {
    switch ($action) {
        case 'add':
            // Agregar torrent a qBittorrent
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $magnet = $input['magnet'] ?? $_POST['magnet'] ?? '';
            $savePath = $input['save_path'] ?? $_POST['save_path'] ?? null;
            $category = $input['category'] ?? $_POST['category'] ?? null;
            $isPaused = isset($input['is_paused']) ? ($input['is_paused'] ? 'true' : 'false') : 'false';
            
            if (empty($magnet)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Enlace magnet requerido']);
                exit;
            }
            
            $addData = [
                'urls' => $magnet
            ];
            
            if ($savePath) {
                $addData['savepath'] = $savePath;
            }
            
            if ($category) {
                $addData['category'] = $category;
            }
            
            $addData['paused'] = $isPaused;
            
            $result = qbRequest($qbUrl, '/api/v2/torrents/add', 'POST', $addData, $qbUsername, $qbPassword);
            
            if (isset($result['error'])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // qBittorrent devuelve "Ok." si es exitoso
            $success = (isset($result['response']) && $result['response'] === 'Ok.') || 
                      (isset($result['response']) && strpos($result['response'], 'Ok.') !== false);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Torrent agregado correctamente a qBittorrent' : 'Error al agregar torrent',
                'response' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'list':
            // Listar torrents en qBittorrent
            $result = qbRequest($qbUrl, '/api/v2/torrents/info', 'GET', null, $qbUsername, $qbPassword);
            
            if (isset($result['error'])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'torrents' => is_array($result) ? $result : [],
                'count' => is_array($result) ? count($result) : 0
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'info':
            // Obtener información de un torrent específico
            $hash = $_GET['hash'] ?? '';
            
            if (empty($hash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Hash del torrent requerido']);
                exit;
            }
            
            $result = qbRequest($qbUrl, '/api/v2/torrents/properties?hash=' . urlencode($hash), 'GET', null, $qbUsername, $qbPassword);
            
            if (isset($result['error'])) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'torrent' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'pause':
            // Pausar torrent
            $hash = $_GET['hash'] ?? $_POST['hash'] ?? '';
            
            if (empty($hash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Hash del torrent requerido']);
                exit;
            }
            
            $result = qbRequest($qbUrl, '/api/v2/torrents/pause', 'POST', ['hashes' => $hash], $qbUsername, $qbPassword);
            
            echo json_encode([
                'success' => true,
                'message' => 'Torrent pausado',
                'response' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'resume':
            // Reanudar torrent
            $hash = $_GET['hash'] ?? $_POST['hash'] ?? '';
            
            if (empty($hash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Hash del torrent requerido']);
                exit;
            }
            
            $result = qbRequest($qbUrl, '/api/v2/torrents/resume', 'POST', ['hashes' => $hash], $qbUsername, $qbPassword);
            
            echo json_encode([
                'success' => true,
                'message' => 'Torrent reanudado',
                'response' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'delete':
            // Eliminar torrent
            $hash = $_GET['hash'] ?? $_POST['hash'] ?? '';
            $deleteFiles = isset($_GET['deleteFiles']) ? ($_GET['deleteFiles'] === 'true' || $_GET['deleteFiles'] === '1') : false;
            
            if (empty($hash)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Hash del torrent requerido']);
                exit;
            }
            
            $data = ['hashes' => $hash];
            if ($deleteFiles) {
                $data['deleteFiles'] = 'true';
            }
            
            $result = qbRequest($qbUrl, '/api/v2/torrents/delete', 'POST', $data, $qbUsername, $qbPassword);
            
            echo json_encode([
                'success' => true,
                'message' => 'Torrent eliminado',
                'response' => $result
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        case 'status':
            // Verificar estado de conexión con qBittorrent
            $loginSuccess = qbLogin($qbUrl, $qbUsername, $qbPassword);
            
            echo json_encode([
                'success' => $loginSuccess,
                'connected' => $loginSuccess,
                'url' => $qbUrl,
                'message' => $loginSuccess ? 'Conectado a qBittorrent' : 'No se pudo conectar a qBittorrent'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Acción no válida',
                'available_actions' => ['add', 'list', 'info', 'pause', 'resume', 'delete', 'status']
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



