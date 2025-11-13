<?php
/**
 * API Endpoint: Obtener contenido por ID
 * GET /api/content/{id}
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

try {
    $db = getDbConnection();
    
    // Obtener ID de la URL
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uriParts = explode('/', trim($uri, '/'));
    
    // Buscar el índice de 'content' y obtener el siguiente elemento
    $contentIndex = array_search('content', $uriParts);
    $contentId = null;
    
    if ($contentIndex !== false && isset($uriParts[$contentIndex + 1])) {
        $contentId = (int)$uriParts[$contentIndex + 1];
    }
    
    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido no proporcionado'
        ]);
        exit;
    }
    
    // Consulta para obtener contenido con géneros
    $query = "
        SELECT 
            c.*,
            GROUP_CONCAT(DISTINCT g.id ORDER BY g.name SEPARATOR ',') as genre_ids,
            GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
        FROM content c
        LEFT JOIN content_genres cg ON c.id = cg.content_id
        LEFT JOIN genres g ON cg.genre_id = g.id
        WHERE c.id = :id
        GROUP BY c.id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':id', $contentId, PDO::PARAM_INT);
    $stmt->execute();
    
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$content) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Contenido no encontrado'
        ]);
        exit;
    }
    
    // Formatear resultado
    $formatted = [
        'id' => (int)$content['id'],
        'title' => $content['title'],
        'slug' => $content['slug'],
        'type' => $content['type'],
        'description' => $content['description'],
        'release_year' => (int)$content['release_year'],
        'duration' => (int)$content['duration'],
        'rating' => $content['rating'] ? (float)$content['rating'] : null,
        'age_rating' => $content['age_rating'],
        'poster_url' => !empty($content['poster_url']) ? $content['poster_url'] : '/streaming-platform/assets/img/default-poster.svg',
        'backdrop_url' => !empty($content['backdrop_url']) ? $content['backdrop_url'] : '/streaming-platform/assets/img/default-backdrop.svg',
        'trailer_url' => $content['trailer_url'],
        'video_url' => $content['video_url'],
        'torrent_magnet' => $content['torrent_magnet'],
        'is_featured' => (bool)$content['is_featured'],
        'is_trending' => (bool)$content['is_trending'],
        'is_premium' => (bool)$content['is_premium'],
        'views' => (int)$content['views'],
        'genre_ids' => $content['genre_ids'] ? explode(',', $content['genre_ids']) : [],
        'genres' => $content['genres'] ? explode(', ', $content['genres']) : []
    ];
    
    // Si es una serie, obtener episodios
    if ($content['type'] === 'series') {
        $episodesQuery = "
            SELECT 
                id,
                season_number,
                episode_number,
                title,
                description,
                duration,
                video_url,
                thumbnail_url,
                release_date,
                views
            FROM episodes
            WHERE series_id = :series_id
            ORDER BY season_number ASC, episode_number ASC
        ";
        
        $episodesStmt = $db->prepare($episodesQuery);
        $episodesStmt->bindValue(':series_id', $contentId, PDO::PARAM_INT);
        $episodesStmt->execute();
        
        $episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted['episodes'] = array_map(function($ep) {
            return [
                'id' => (int)$ep['id'],
                'season_number' => (int)$ep['season_number'],
                'episode_number' => (int)$ep['episode_number'],
                'title' => $ep['title'],
                'description' => $ep['description'],
                'duration' => (int)$ep['duration'],
                'video_url' => $ep['video_url'],
                'thumbnail_url' => $ep['thumbnail_url'],
                'release_date' => $ep['release_date'],
                'views' => (int)$ep['views']
            ];
        }, $episodes);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

