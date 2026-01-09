<?php
/**
 * API Endpoint para búsqueda de contenido en el addon Balandro
 */

// Incluir configuración y autenticación
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Obtener parámetros de la solicitud
$query = $_GET['q'] ?? '';
$page = intval($_GET['page'] ?? 1);
$type = $_GET['type'] ?? 'all'; // all, movie, tv
$year = !empty($_GET['year']) ? intval($_GET['year']) : null;
$genre = $_GET['genre'] ?? null;

// Validar parámetros
if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'El parámetro de búsqueda es requerido']);
    exit;
}

try {
    // Obtener instancia del gestor de addons
    $addonManager = AddonManager::getInstance();
    $balandroAddon = $addonManager->getAddon('balandro');
    
    if (!$balandroAddon) {
        throw new Exception('El addon Balandro no está instalado o activado');
    }
    
    // Preparar filtros
    $filters = [
        'page' => $page,
        'type' => $type !== 'all' ? $type : null,
        'year' => $year,
        'genre' => $genre
    ];
    
    // Realizar búsqueda
    $results = $balandroAddon->onSearch($query, $filters);
    
    // Formatear respuesta
    $response = [
        'status' => 'success',
        'data' => $results
    ];
    
    // Establecer cabeceras y devolver respuesta
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al realizar la búsqueda: ' . $e->getMessage()
    ]);
}
