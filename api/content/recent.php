<?php
/**
 * API Endpoint: Contenido reciente
 * GET /api/content/recent?type=series&limit=10
 */

// Permitir CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/image-helper.php';

// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $db = getDbConnection();
    
    // Obtener parámetros
    $type = isset($_GET['type']) ? $_GET['type'] : null; // 'movie' o 'series'
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min($limit, 50)); // Entre 1 y 50
    
    // Construir consulta
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
            GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
        FROM content c
        LEFT JOIN content_genres cg ON c.id = cg.content_id
        LEFT JOIN genres g ON cg.genre_id = g.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtrar por tipo si se especifica
    if ($type && in_array($type, ['movie', 'series'])) {
        $query .= " AND c.type = :type";
        $params[':type'] = $type;
    }
    
    $query .= "
        GROUP BY c.id
        ORDER BY c.added_date DESC, c.created_at DESC
        LIMIT :limit
    ";
    
    $stmt = $db->prepare($query);
    
    // Vincular parámetros
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
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
            'added_date' => $row['added_date'],
            'genres' => $row['genres'] ? explode(', ', $row['genres']) : []
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted),
        'type' => $type ?: 'all'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido reciente: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

