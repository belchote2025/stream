<?php
/**
 * API Endpoint: Contenido destacado
 * GET /api/content/featured
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

try {
    $db = getDbConnection();
    
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
            'poster_url' => !empty($row['poster_url']) ? $row['poster_url'] : '/streaming-platform/assets/img/default-poster.svg',
            'backdrop_url' => !empty($row['backdrop_url']) ? $row['backdrop_url'] : '/streaming-platform/assets/img/default-backdrop.svg',
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
    
    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido destacado: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

