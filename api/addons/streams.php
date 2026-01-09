<?php
/**
 * API: Obtener streams de contenido desde addons
 * Combina streams de múltiples addons activos
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
    // Verificar autenticación (opcional, puede ser público)
    $requireAuth = $_GET['auth'] ?? false;
    if ($requireAuth && !isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Usuario no autenticado'
        ]);
        exit;
    }
    
    // Obtener parámetros
    $contentId = isset($_GET['content_id']) ? (int)$_GET['content_id'] : null;
    $contentType = isset($_GET['content_type']) ? $_GET['content_type'] : 'movie';
    $episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : null;
    
    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de contenido requerido'
        ]);
        exit;
    }
    
    if (!in_array($contentType, ['movie', 'series'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Tipo de contenido inválido'
        ]);
        exit;
    }
    
    // Obtener streams desde addons
    $addonManager = AddonManager::getInstance();
    $addonManager->loadAddons();
    $streams = $addonManager->getStreams($contentId, $contentType, $episodeId);
    
    // Formatear respuesta
    $formattedStreams = [];
    foreach ($streams as $addonId => $addonStreams) {
        if (is_array($addonStreams)) {
            foreach ($addonStreams as $stream) {
                $formattedStreams[] = [
                    'url' => $stream['url'] ?? null,
                    'quality' => $stream['quality'] ?? 'HD',
                    'language' => $stream['language'] ?? 'es',
                    'source' => $stream['source'] ?? $addonId,
                    'addon' => $addonId,
                    'type' => $stream['type'] ?? 'direct',
                    'subtitles' => $stream['subtitles'] ?? []
                ];
            }
        }
    }
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'content_id' => $contentId,
            'content_type' => $contentType,
            'episode_id' => $episodeId,
            'streams' => $formattedStreams,
            'total' => count($formattedStreams),
            'sources' => array_keys($streams)
        ]
    ]);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en api/addons/streams.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener streams: ' . $e->getMessage()
    ]);
    exit;
}
