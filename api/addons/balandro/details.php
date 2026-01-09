<?php
/**
 * API Endpoint para obtener detalles de contenido en el addon Balandro
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
$contentId = $_GET['id'] ?? '';
$type = $_GET['type'] ?? 'movie'; // movie, tv

// Validar parámetros
if (empty($contentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'El ID del contenido es requerido']);
    exit;
}

try {
    // Obtener instancia del gestor de addons
    $addonManager = AddonManager::getInstance();
    $balandroAddon = $addonManager->getAddon('balandro');
    
    if (!$balandroAddon) {
        throw new Exception('El addon Balandro no está instalado o activado');
    }
    
    // Obtener detalles del contenido
    $details = $balandroAddon->onGetDetails($contentId, $type);
    
    if (!$details) {
        http_response_code(404);
        echo json_encode(['error' => 'Contenido no encontrado']);
        exit;
    }
    
    // Formatear respuesta
    $response = [
        'status' => 'success',
        'data' => $details
    ];
    
    // Establecer cabeceras y devolver respuesta
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener los detalles: ' . $e->getMessage()
    ]);
}
