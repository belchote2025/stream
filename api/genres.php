<?php
/**
 * API: Obtener géneros
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

try {
    $db = getDbConnection();
    
    $query = "SELECT id, name, slug FROM genres ORDER BY name";
    $stmt = $db->query($query);
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $genres,
        'count' => count($genres)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

