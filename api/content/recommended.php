<?php
/**
 * API Endpoint: Recomendaciones personalizadas
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/image-helper.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Autenticación requerida'
    ]);
    exit;
}

try {
    $db = getDbConnection();
    $userId = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $limit = max(1, min($limit, 30));

    // Recomendaciones basadas en favoritos del usuario
    $query = "
        WITH favorite_genres AS (
            SELECT cg.genre_id, COUNT(*) as cnt
            FROM user_favorites uf
            INNER JOIN content_genres cg ON uf.content_id = cg.content_id
            WHERE uf.user_id = :user_id
            GROUP BY cg.genre_id
            ORDER BY cnt DESC
            LIMIT 5
        )
        SELECT DISTINCT c.*
        FROM content c
        INNER JOIN content_genres cg ON c.id = cg.content_id
        INNER JOIN favorite_genres fg ON cg.genre_id = fg.genre_id
        WHERE c.id NOT IN (
            SELECT content_id FROM user_favorites WHERE user_id = :user_id_exclude
        )
        ORDER BY c.rating DESC, c.views DESC, COALESCE(c.added_date, c.created_at) DESC
        LIMIT :limit
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_exclude', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si no hay resultados, usar contenido trending
    if (empty($results)) {
        $fallbackQuery = "
            SELECT *
            FROM content
            WHERE is_trending = 1
            ORDER BY rating DESC, views DESC
            LIMIT :limit
        ";
        $fallbackStmt = $db->prepare($fallbackQuery);
        $fallbackStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $fallbackStmt->execute();
        $results = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $formatted = array_map(function ($row) {
        return [
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
            'views' => (int)$row['views']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener recomendaciones: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

