<?php
/**
 * API Endpoint para obtener fuentes de streaming en el addon Balandro
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
$season = !empty($_GET['season']) ? intval($_GET['season']) : null;
$episode = !empty($_GET['episode']) ? intval($_GET['episode']) : null;

// Validar parámetros
if (empty($contentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'El ID del contenido es requerido']);
    exit;
}

// Validar parámetros para series (opcional, puede obtener streams generales)
// if ($type === 'tv' && ($season === null || $episode === null)) {
//     http_response_code(400);
//     echo json_encode(['error' => 'Para series, se requieren los parámetros season y episode']);
//     exit;
// }

try {
    // Obtener instancia del gestor de addons
    $addonManager = AddonManager::getInstance();
    $balandroAddon = $addonManager->getAddon('balandro');
    
    if (!$balandroAddon) {
        throw new Exception('El addon Balandro no está instalado o activado');
    }
    
    // Obtener fuentes de streaming
    $streams = $balandroAddon->onGetStreams($contentId, $type, $season, $episode);
    
    if (empty($streams)) {
        http_response_code(404);
        echo json_encode(['error' => 'No se encontraron fuentes de streaming']);
        exit;
    }
    
    // Filtrar y ordenar streams por calidad
    usort($streams, function($a, $b) {
        $qualityOrder = ['4K' => 4, '1080p' => 3, '720p' => 2, '480p' => 1, '360p' => 0];
        $aQuality = $a['quality'] ?? '';
        $bQuality = $b['quality'] ?? '';
        
        $aOrder = $qualityOrder[$aQuality] ?? -1;
        $bOrder = $qualityOrder[$bQuality] ?? -1;
        
        return $bOrder - $aOrder;
    });
    
    // Formatear respuesta
    $response = [
        'status' => 'success',
        'data' => [
            'id' => $contentId,
            'type' => $type,
            'season' => $season,
            'episode' => $episode,
            'streams' => $streams
        ]
    ];
    
    // Establecer cabeceras y devolver respuesta
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener las fuentes de streaming: ' . $e->getMessage()
    ]);
}
