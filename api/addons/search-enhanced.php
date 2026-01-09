<?php
/**
 * API: Búsqueda mejorada que combina resultados de addons
 */

ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/addons/AddonManager.php';

ob_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Obtener parámetros
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $genre = isset($_GET['genre']) ? $_GET['genre'] : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, (int)$_GET['limit'])) : 20;
    
    if (empty($query)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Parámetro de búsqueda requerido'
        ]);
        exit;
    }
    
    // Preparar filtros
    $filters = [
        'type' => $type !== 'all' ? $type : null,
        'year' => $year,
        'genre' => $genre,
        'page' => $page,
        'limit' => $limit
    ];
    
    // Realizar búsqueda mejorada
    $addonManager = AddonManager::getInstance();
    $addonManager->loadAddons();
    $results = $addonManager->searchEnhanced($query, $filters);
    
    // Aplicar paginación
    $total = $results['total'] ?? 0;
    $allResults = $results['results'] ?? [];
    $offset = ($page - 1) * $limit;
    $paginatedResults = array_slice($allResults, $offset, $limit);
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'results' => $paginatedResults,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit),
            'sources' => array_keys($results['by_addon'] ?? []),
            'query' => $query,
            'filters' => $filters
        ]
    ]);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en api/addons/search-enhanced.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error en la búsqueda: ' . $e->getMessage()
    ]);
    exit;
}
