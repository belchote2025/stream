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
    $season = isset($_GET['season']) ? (int)$_GET['season'] : null;
    $episode = isset($_GET['episode']) ? (int)$_GET['episode'] : null;
    
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
    
    // Si tenemos season y episode, pasarlos directamente a los addons
    // Los addons pueden usar estos parámetros para buscar enlaces específicos
    if ($contentType === 'series' && $season !== null && $episode !== null) {
        // Para series, algunos addons necesitan season y episode directamente
        $streams = [];
        foreach ($addonManager->getAddons() as $addon) {
            if ($addon->isEnabled() && method_exists($addon, 'onGetStreams')) {
                try {
                    $addonStreams = $addon->onGetStreams($contentId, $contentType, $season, $episode);
                    if (is_array($addonStreams) && !empty($addonStreams)) {
                        $streams[$addon->getId()] = $addonStreams;
                    }
                } catch (Exception $e) {
                    error_log("Error obteniendo streams del addon {$addon->getId()}: " . $e->getMessage());
                }
            }
        }
    } else {
        // Para películas o si no hay season/episode, usar el método normal
        $streams = $addonManager->getStreams($contentId, $contentType, $episodeId);
    }
    
    // Formatear respuesta
    $formattedStreams = [];
    $addonDetails = [];
    
    foreach ($streams as $addonId => $addonStreams) {
        if (is_array($addonStreams) && !empty($addonStreams)) {
            $addonDetails[$addonId] = [
                'count' => count($addonStreams),
                'enabled' => true
            ];
            
            foreach ($addonStreams as $stream) {
                if (!empty($stream['url'])) {
                    $formattedStreams[] = [
                        'url' => $stream['url'],
                        'quality' => $stream['quality'] ?? 'HD',
                        'language' => $stream['language'] ?? 'es',
                        'source' => $stream['source'] ?? $addonId,
                        'provider' => $stream['provider'] ?? $addonId,
                        'addon' => $addonId,
                        'type' => $stream['type'] ?? 'direct',
                        'format' => $stream['format'] ?? 'mp4',
                        'name' => $stream['name'] ?? ucfirst($addonId),
                        'subtitles' => $stream['subtitles'] ?? []
                    ];
                }
            }
        } else {
            $addonDetails[$addonId] = [
                'count' => 0,
                'enabled' => true,
                'message' => 'No se encontraron streams'
            ];
        }
    }
    
    // Si no hay streams, verificar qué addons están activos
    if (empty($formattedStreams)) {
        $activeAddons = [];
        foreach ($addonManager->getAddons() as $addon) {
            if ($addon->isEnabled()) {
                $activeAddons[] = [
                    'id' => $addon->getId(),
                    'name' => $addon->getName(),
                    'hasGetStreams' => method_exists($addon, 'onGetStreams')
                ];
            }
        }
        $addonDetails['_info'] = [
            'active_addons' => $activeAddons,
            'total_active' => count($activeAddons)
        ];
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
            'season' => $season,
            'episode' => $episode,
            'streams' => $formattedStreams,
            'total' => count($formattedStreams),
            'sources' => array_keys($streams),
            'addon_details' => $addonDetails
        ]
    ], JSON_UNESCAPED_UNICODE);
    
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
