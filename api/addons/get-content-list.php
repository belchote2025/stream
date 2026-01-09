<?php
/**
 * API: Obtener lista de contenidos para el selector de búsqueda de enlaces
 */

// Iniciar buffer de salida para capturar errores
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Incluir configuración
require_once __DIR__ . '/../../includes/config.php';

// Limpiar buffer
ob_clean();

// Verificar autenticación de admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Verificar que la función existe
    if (!function_exists('getDbConnection')) {
        throw new Exception('Función getDbConnection no disponible');
    }
    
    $db = getDbConnection();
    
    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    $type = $_GET['type'] ?? 'all'; // 'movie', 'series', 'all'
    $limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100; // Limitar entre 1 y 500
    
    // Verificar qué columnas existen en la tabla content
    $checkColumns = $db->query("SHOW COLUMNS FROM content")->fetchAll(PDO::FETCH_COLUMN);
    $hasImdbId = in_array('imdb_id', $checkColumns);
    
    // Construir SELECT con columnas disponibles
    $columns = ['id', 'title', 'type', 'release_year'];
    if ($hasImdbId) {
        $columns[] = 'imdb_id';
    }
    
    $sql = "SELECT " . implode(', ', $columns) . " 
            FROM content 
            WHERE 1=1";
    
    $params = [];
    
    if ($type !== 'all') {
        $sql .= " AND type = :type";
        $params[':type'] = $type === 'movie' ? 'movie' : 'series';
    }
    
    $sql .= " ORDER BY title ASC LIMIT :limit";
    $params[':limit'] = $limit;
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear respuesta
    $formatted = [];
    foreach ($contents as $content) {
        $formatted[] = [
            'id' => (int)$content['id'],
            'title' => $content['title'] ?? 'Sin título',
            'type' => $content['type'] ?? 'movie',
            'year' => isset($content['release_year']) && $content['release_year'] ? (int)$content['release_year'] : null,
            'imdb_id' => $hasImdbId ? ($content['imdb_id'] ?? null) : null,
            'display' => ($content['title'] ?? 'Sin título') . 
                        (isset($content['release_year']) && $content['release_year'] ? ' (' . $content['release_year'] . ')' : '') . 
                        ' [' . ($content['type'] ?? 'movie') . ']'
        ];
    }
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'total' => count($formatted)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log('Error en get-content-list.php (PDO): ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage(),
        'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log('Error en get-content-list.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenidos: ' . $e->getMessage(),
        'trace' => ENVIRONMENT === 'development' ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE);
}
