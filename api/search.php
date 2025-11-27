<?php
/**
 * API: Búsqueda rápida
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/image-helper.php';

try {
    $db = getDbConnection();
    $query = $_GET['q'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'count' => 0
        ]);
        exit;
    }
    
    $searchQuery = "
        SELECT 
            c.id,
            c.title,
            c.type,
            c.release_year,
            c.poster_url,
            c.rating
        FROM content c
        WHERE c.title LIKE :query
        OR c.description LIKE :query
        ORDER BY c.rating DESC, c.views DESC
        LIMIT :limit
    ";
    
    $stmt = $db->prepare($searchQuery);
    $searchTerm = '%' . $query . '%';
    $stmt->bindValue(':query', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted = [];
    foreach ($results as $item) {
        $formatted[] = [
            'id' => (int)$item['id'],
            'title' => $item['title'],
            'type' => $item['type'],
            'release_year' => (int)$item['release_year'],
            'poster_url' => getImageUrl($item['poster_url'] ?? '', '/assets/img/default-poster.svg'),
            'rating' => $item['rating'] ? (float)$item['rating'] : null
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

