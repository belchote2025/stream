<?php
/**
 * API: Recomendaciones personalizadas
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

try {
    $db = getDbConnection();
    $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $recommendations = [];
    
    if ($userId) {
        // Obtener géneros favoritos del usuario basados en su historial
        $favGenresQuery = "
            SELECT g.id, g.name, COUNT(*) as count
            FROM genres g
            INNER JOIN content_genres cg ON g.id = cg.genre_id
            INNER JOIN user_favorites uf ON cg.content_id = uf.content_id
            WHERE uf.user_id = :user_id
            GROUP BY g.id
            ORDER BY count DESC
            LIMIT 3
        ";
        
        $favGenresStmt = $db->prepare($favGenresQuery);
        $favGenresStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $favGenresStmt->execute();
        $favGenres = $favGenresStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($favGenres)) {
            $genreIds = array_column($favGenres, 'id');
            $placeholders = implode(',', array_fill(0, count($genreIds), '?'));
            
            // Obtener contenido de géneros favoritos que el usuario no ha visto
            $recQuery = "
                SELECT DISTINCT c.*
                FROM content c
                INNER JOIN content_genres cg ON c.id = cg.content_id
                WHERE cg.genre_id IN ($placeholders)
                AND c.id NOT IN (
                    SELECT content_id FROM user_favorites WHERE user_id = ?
                )
                GROUP BY c.id
                ORDER BY c.rating DESC, c.views DESC
                LIMIT ?
            ";
            
            $recStmt = $db->prepare($recQuery);
            $params = array_merge($genreIds, [$userId, $limit]);
            $recStmt->execute($params);
            $recommendations = $recStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Si no hay recomendaciones personalizadas, mostrar contenido popular
    if (empty($recommendations)) {
        $popularQuery = "
            SELECT c.*
            FROM content c
            WHERE c.is_trending = 1
            ORDER BY c.rating DESC, c.views DESC
            LIMIT ?
        ";
        
        $popularStmt = $db->prepare($popularQuery);
        $popularStmt->bindValue(1, $limit, PDO::PARAM_INT);
        $popularStmt->execute();
        $recommendations = $popularStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Formatear resultados
    $formatted = [];
    foreach ($recommendations as $item) {
        $formatted[] = [
            'id' => (int)$item['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'poster_url' => $item['poster_url'] ?: '/streaming-platform/assets/img/default-poster.svg',
            'backdrop_url' => $item['backdrop_url'] ?: '/streaming-platform/assets/img/default-backdrop.svg',
            'rating' => $item['rating'] ? (float)$item['rating'] : null,
            'release_year' => (int)$item['release_year']
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
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

