<?php
/**
 * API para crear nuevo contenido
 */
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validar datos requeridos
    $required = ['title', 'description', 'release_year', 'duration', 'type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    // Preparar datos
    $title = trim($data['title']);
    $description = trim($data['description']);
    $release_year = (int)$data['release_year'];
    $duration = (int)$data['duration'];
    $type = $data['type'] === 'series' ? 'series' : 'movie';
    $poster_url = !empty($data['poster_url']) ? trim($data['poster_url']) : null;
    $backdrop_url = !empty($data['backdrop_url']) ? trim($data['backdrop_url']) : null;
    $video_url = !empty($data['video_url']) ? trim($data['video_url']) : null;
    $trailer_url = !empty($data['trailer_url']) ? trim($data['trailer_url']) : null;
    $age_rating = !empty($data['age_rating']) ? trim($data['age_rating']) : null;
    $is_featured = isset($data['is_featured']) ? (int)$data['is_featured'] : 0;
    $is_trending = isset($data['is_trending']) ? (int)$data['is_trending'] : 0;
    $is_premium = isset($data['is_premium']) ? (int)$data['is_premium'] : 0;
    
    // Crear slug
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // Insertar en la base de datos
    $stmt = $db->prepare("
        INSERT INTO content (
            title, description, release_year, duration, type, 
            poster_url, backdrop_url, video_url, trailer_url, 
            age_rating, is_featured, is_trending, is_premium, slug, created_at, updated_at
        ) VALUES (
            :title, :description, :release_year, :duration, :type,
            :poster_url, :backdrop_url, :video_url, :trailer_url,
            :age_rating, :is_featured, :is_trending, :is_premium, :slug, NOW(), NOW()
        )
    ");
    
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':release_year' => $release_year,
        ':duration' => $duration,
        ':type' => $type,
        ':poster_url' => $poster_url,
        ':backdrop_url' => $backdrop_url,
        ':video_url' => $video_url,
        ':trailer_url' => $trailer_url,
        ':age_rating' => $age_rating,
        ':is_featured' => $is_featured,
        ':is_trending' => $is_trending,
        ':is_premium' => $is_premium,
        ':slug' => $slug
    ]);
    
    $contentId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Contenido creado correctamente',
        'data' => ['id' => $contentId]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

