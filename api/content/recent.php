<?php
/**
 * API Endpoint: Contenido reciente
 * GET /api/content/recent?type=series&limit=10
 */

// Establecer headers ANTES de cualquier output
ob_start(); // Iniciar buffer de salida para capturar cualquier output accidental

// Configuración de encabezados HTTP
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Incluir dependencias con manejo de errores
try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/image-helper.php';
} catch (Throwable $e) {
    // Si hay error en los includes, devolver JSON de error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error loading includes in recent.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar dependencias',
        'message' => 'Error interno del servidor'
    ]);
    exit;
}

// Limpiar cualquier output accidental antes de continuar
ob_clean();

// Configuración de manejo de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api-errors.log');

// Función para enviar respuestas JSON estandarizadas
function sendJsonResponse($data = null, $statusCode = 200, $error = null) {
    // Limpiar cualquier output previo
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    
    $response = [
        'success' => $statusCode >= 200 && $statusCode < 300,
        'data' => $data,
    ];
    
    if ($error) {
        $response['error'] = is_object($error) && method_exists($error, 'getMessage') 
            ? $error->getMessage() 
            : (string)$error;
        
        // Solo mostrar detalles de depuración en entorno local
        if (defined('APP_ENV') && APP_ENV === 'local' && is_object($error)) {
            $response['debug'] = [
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine()
            ];
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    exit;
}

// Manejo de errores global
set_exception_handler(function($e) {
    // Asegurar que los headers JSON estén establecidos
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }
    error_log('Uncaught Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => defined('APP_ENV') && APP_ENV === 'local' ? $e->getMessage() : 'Error interno del servidor'
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Solo registrar errores críticos, no warnings
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR || $errno === E_COMPILE_ERROR) {
        error_log("Error [$errno] $errstr in $errfile on line $errline");
    }
    return false;
});

try {
    // Validar y obtener parámetros
    $type = isset($_GET['type']) && in_array($_GET['type'], ['movie', 'series']) ? $_GET['type'] : null;
    $source = isset($_GET['source']) && in_array($_GET['source'], ['local', 'tmdb']) ? $_GET['source'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min($limit, 50)); // Limitar entre 1 y 50

    // Intentar conectar a la base de datos
    try {
        $db = getDbConnection();
    } catch (PDOException $dbError) {
        // Si falla la conexión, devolver JSON de error en lugar de die()
        error_log('Error de conexión a BD en recent.php: ' . $dbError->getMessage());
        sendJsonResponse(null, 503, new Exception('Error de conexión a la base de datos. Por favor, inténtelo más tarde.'));
    }
    
    // Construir consulta base con parámetros preparados
    $query = "
        SELECT 
            c.id,
            c.title,
            c.slug,
            c.type,
            c.description,
            c.release_year,
            c.duration,
            c.rating,
            c.age_rating,
            c.poster_url,
            c.backdrop_url,
            c.trailer_url,
            c.video_url,
            c.torrent_magnet,
            c.is_featured,
            c.is_trending,
            c.is_premium,
            c.views,
            c.added_date,
            COALESCE(GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', '), '') as genres
        FROM content c
        LEFT JOIN content_genres cg ON c.id = cg.content_id
        LEFT JOIN genres g ON cg.genre_id = g.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtrar por tipo si se especifica
    if ($type) {
        $query .= " AND c.type = :type";
        $params[':type'] = $type;
    }
    
    // Filtrar por origen
    if ($source === 'local') {
        $query .= " AND c.video_url IS NOT NULL
                    AND c.video_url <> ''
                    AND (c.video_url LIKE :upload_path1 OR c.video_url LIKE :upload_path2)";
        $params[':upload_path1'] = '/uploads/%';
        $params[':upload_path2'] = '%/uploads/%';
    } elseif ($source === 'tmdb') {
        $query .= " AND c.source = 'tmdb'";
    }
    
    $query .= "
        GROUP BY c.id
        ORDER BY c.added_date DESC, c.created_at DESC
        LIMIT :limit
    ";
    
    // Preparar y ejecutar consulta
    $stmt = $db->prepare($query);
    
    // Vincular parámetros
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    
    // Vincular el límite como entero
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta SQL');
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'] ?? '',
            'slug' => $row['slug'] ?? '',
            'type' => $row['type'] ?? 'movie',
            'description' => $row['description'] ?? '',
            'release_year' => $row['release_year'] ? (int)$row['release_year'] : null,
            'duration' => $row['duration'] ? (int)$row['duration'] : null,
            'rating' => $row['rating'] ? (float)$row['rating'] : null,
            'age_rating' => $row['age_rating'] ?? null,
            'poster_url' => getImageUrl($row['poster_url'] ?? '', '/assets/img/default-poster.svg'),
            'backdrop_url' => getImageUrl($row['backdrop_url'] ?? '', '/assets/img/default-backdrop.svg'),
            'trailer_url' => $row['trailer_url'] ?? null,
            'video_url' => $row['video_url'] ?? null,
            'torrent_magnet' => $row['torrent_magnet'] ?? null,
            'is_featured' => !empty($row['is_featured']),
            'is_trending' => !empty($row['is_trending']),
            'is_premium' => !empty($row['is_premium']),
            'views' => isset($row['views']) ? (int)$row['views'] : 0,
            'added_date' => $row['added_date'] ?? date('Y-m-d H:i:s'),
            'genres' => !empty($row['genres']) ? explode(', ', $row['genres']) : []
        ];
    }
    
    // Enviar respuesta exitosa con formato consistente
    sendJsonResponse($formatted, 200, null);
    
} catch (PDOException $e) {
    // Asegurar headers JSON antes de enviar error
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log('Database Error in recent.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido de la base de datos',
        'message' => 'Error de base de datos'
    ]);
    exit;
} catch (Exception $e) {
    // Asegurar headers JSON antes de enviar error
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log('Error in recent.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido',
        'message' => $e->getMessage()
    ]);
    exit;
} catch (Throwable $e) {
    // Capturar cualquier otro tipo de error
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log('Fatal Error in recent.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => 'Error inesperado'
    ]);
    exit;
}

