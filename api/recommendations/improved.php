<?php
/**
 * Sistema de Recomendaciones Mejorado con Algoritmo Inteligente
 * Analiza preferencias del usuario y genera recomendaciones más precisas
 */

// Establecer headers ANTES de cualquier output
ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/image-helper.php';

// Limpiar buffer
ob_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Verificar autenticación
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Usuario no autenticado'
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $db = getDbConnection();
    
    // Obtener parámetros
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = max(1, min($limit, 50));
    $type = isset($_GET['type']) ? $_GET['type'] : null;

    // Paso 1: Analizar historial de visualización del usuario
    $viewedContent = [];
    $stmt = $db->prepare("
        SELECT content_id, type, views, completed_at
        FROM playback_progress
        WHERE user_id = ? AND completed = 1
        ORDER BY completed_at DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $viewedContent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Paso 2: Analizar géneros favoritos del usuario
    $favoriteGenres = [];
    if (!empty($viewedContent)) {
        $contentIds = array_column($viewedContent, 'content_id');
        $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
        
        $stmt = $db->prepare("
            SELECT g.id, g.name, COUNT(*) as view_count
            FROM genres g
            INNER JOIN content_genres cg ON g.id = cg.genre_id
            WHERE cg.content_id IN ($placeholders)
            GROUP BY g.id, g.name
            ORDER BY view_count DESC
            LIMIT 5
        ");
        $stmt->execute($contentIds);
        $favoriteGenres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Paso 3: Analizar contenido en "Mi Lista"
    $watchlistContent = [];
    $stmt = $db->prepare("
        SELECT c.id, c.type, c.rating, c.views
        FROM watchlist w
        INNER JOIN content c ON w.content_id = c.id
        WHERE w.user_id = ?
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $watchlistContent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Paso 4: Generar recomendaciones basadas en múltiples factores
    $recommendations = [];
    
    // Factor 1: Contenido similar por géneros favoritos
    if (!empty($favoriteGenres)) {
        $genreIds = array_column($favoriteGenres, 'id');
        $genrePlaceholders = implode(',', array_fill(0, count($genreIds), '?'));
        
        // Excluir contenido ya visto
        $viewedIds = !empty($viewedContent) ? array_column($viewedContent, 'content_id') : [0];
        $viewedPlaceholders = implode(',', array_fill(0, count($viewedIds), '?'));
        
        $query = "
            SELECT DISTINCT
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
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres,
                COUNT(DISTINCT cg.genre_id) as genre_match_count,
                AVG(c.rating) as avg_rating
            FROM content c
            INNER JOIN content_genres cg ON c.id = cg.content_id
            INNER JOIN genres g ON cg.genre_id = g.id
            WHERE cg.genre_id IN ($genrePlaceholders)
            AND c.id NOT IN ($viewedPlaceholders)
        ";
        
        $params = array_merge($genreIds, $viewedIds);
        
        if ($type && in_array($type, ['movie', 'series'])) {
            $query .= " AND c.type = ?";
            $params[] = $type;
        }
        
        $query .= "
            GROUP BY c.id
            HAVING genre_match_count >= 1
            ORDER BY genre_match_count DESC, avg_rating DESC, c.views DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($query);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $genreBased = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $recommendations = array_merge($recommendations, $genreBased);
    }

    // Factor 2: Contenido popular con buen rating (fallback)
    if (count($recommendations) < $limit) {
        $needed = $limit - count($recommendations);
        $viewedIds = !empty($viewedContent) ? array_column($viewedContent, 'content_id') : [0];
        $viewedPlaceholders = implode(',', array_fill(0, count($viewedIds), '?'));
        
        $query = "
            SELECT DISTINCT
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
            WHERE c.id NOT IN ($viewedPlaceholders)
            AND c.rating >= 7.0
        ";
        
        $params = $viewedIds;
        
        if ($type && in_array($type, ['movie', 'series'])) {
            $query .= " AND c.type = ?";
            $params[] = $type;
        }
        
        $query .= "
            GROUP BY c.id
            ORDER BY c.rating DESC, c.views DESC, c.release_year DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($query);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $needed, PDO::PARAM_INT);
        $stmt->execute();
        
        $popularBased = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Evitar duplicados
        $existingIds = array_column($recommendations, 'id');
        foreach ($popularBased as $item) {
            if (!in_array($item['id'], $existingIds)) {
                $recommendations[] = $item;
            }
        }
    }

    // Formatear resultados
    $formatted = [];
    foreach ($recommendations as $row) {
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
            'genres' => !empty($row['genres']) ? explode(', ', $row['genres']) : [],
            'match_score' => isset($row['genre_match_count']) ? (int)$row['genre_match_count'] : 0
        ];
    }

    // Limitar resultados
    $formatted = array_slice($formatted, 0, $limit);

    // Limpiar buffer antes de enviar respuesta
    if (ob_get_level() > 0) {
        ob_clean();
    }

    echo json_encode([
        'success' => true,
        'data' => $formatted,
        'count' => count($formatted),
        'meta' => [
            'favorite_genres' => array_column($favoriteGenres, 'name'),
            'viewed_count' => count($viewedContent),
            'watchlist_count' => count($watchlistContent)
        ]
    ]);

} catch (PDOException $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en recommendations/improved.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener recomendaciones'
    ]);
    exit;
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en recommendations/improved.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener recomendaciones'
    ]);
    exit;
}






