<?php
/**
 * API Endpoint: Contenido destacado
 * GET /api/content/featured
 */

// Establecer headers ANTES de cualquier output
ob_start(); // Iniciar buffer de salida para capturar cualquier output accidental

// Desactivar mostrar errores en pantalla para APIs
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Configuración de encabezados HTTP
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');

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
    error_log('Error loading includes in featured.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar dependencias',
        'message' => 'Error interno del servidor'
    ]);
    exit;
}

// Limpiar cualquier output accidental antes de continuar
ob_clean();

try {
    // Intentar conectar a la base de datos
    try {
        $db = getDbConnection();
    } catch (PDOException $dbError) {
        // Si falla la conexión, devolver JSON de error en lugar de die()
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        error_log('Error de conexión a BD en featured.php: ' . $dbError->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Error de conexión a la base de datos. Por favor, inténtelo más tarde.'
        ]);
        exit;
    }
    
    // Obtener parámetros
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $limit = max(1, min($limit, 20)); // Entre 1 y 20
    
    // Consulta para obtener contenido destacado
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
            GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
        FROM content c
        LEFT JOIN content_genres cg ON c.id = cg.content_id
        LEFT JOIN genres g ON cg.genre_id = g.id
        WHERE c.is_featured = 1
        GROUP BY c.id
        ORDER BY c.added_date DESC, c.views DESC
        LIMIT :limit
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados
    $formatted = [];
    foreach ($results as $row) {
        $formatted[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'type' => $row['type'],
            'description' => $row['description'],
            'release_year' => (int)$row['release_year'],
            'duration' => (int)$row['duration'],
            'rating' => $row['rating'] ? (float)$row['rating'] : null,
            'age_rating' => $row['age_rating'],
            'poster_url' => getImageUrl($row['poster_url'] ?? '', '/assets/img/default-poster.svg'),
            'backdrop_url' => getImageUrl($row['backdrop_url'] ?? '', '/assets/img/default-backdrop.svg'),
            'trailer_url' => $row['trailer_url'],
            'video_url' => $row['video_url'],
            'torrent_magnet' => $row['torrent_magnet'],
            'is_featured' => (bool)$row['is_featured'],
            'is_trending' => (bool)$row['is_trending'],
            'is_premium' => (bool)$row['is_premium'],
            'views' => (int)$row['views'],
            'genres' => $row['genres'] ? explode(', ', $row['genres']) : []
        ];
    }
    
    // Limpiar buffer antes de enviar respuesta
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted)
    ]);
    
} catch (PDOException $e) {
    // Limpiar buffer antes de enviar error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en featured.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido destacado',
        'message' => 'Error de base de datos'
    ]);
    exit;
} catch (Exception $e) {
    // Limpiar buffer antes de enviar error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en featured.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido',
        'message' => $e->getMessage()
    ]);
    exit;
}

