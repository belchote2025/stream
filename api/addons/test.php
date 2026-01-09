<?php
/**
 * API: Probar un addon específico
 * Endpoint genérico para probar cualquier addon
 */

ob_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/addons/AddonManager.php';
require_once __DIR__ . '/../../includes/auth/APIAuth.php';

ob_clean();

ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Verificar autenticación
    $auth = APIAuth::getInstance();
    if (!$auth->isAuthenticated() || !$auth->hasRole('admin')) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'No tienes permisos para probar addons'
        ]);
        exit;
    }
    
    // Obtener parámetros
    $addonId = isset($_GET['addon_id']) ? $_GET['addon_id'] : null;
    
    if (!$addonId) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID del addon requerido'
        ]);
        exit;
    }
    
    // Cargar addons
    $addonManager = AddonManager::getInstance();
    $addonManager->loadAddons();
    $addon = $addonManager->getAddon($addonId);
    
    if (!$addon) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Addon '{$addonId}' no encontrado"
        ]);
        exit;
    }
    
    // Ejecutar pruebas
    $tests = [];
    
    // Prueba 1: Verificar que el addon está cargado
    $tests[] = [
        'name' => 'Addon cargado',
        'passed' => $addon !== null,
        'message' => $addon ? "Addon '{$addonId}' cargado correctamente" : "No se pudo cargar el addon '{$addonId}'"
    ];
    
    // Prueba 2: Verificar que el addon está habilitado
    $tests[] = [
        'name' => 'Addon habilitado',
        'passed' => $addon->isEnabled(),
        'message' => $addon->isEnabled() ? 'El addon está habilitado' : 'El addon está deshabilitado'
    ];
    
    // Prueba 3: Verificar métodos disponibles
    $hasSearch = method_exists($addon, 'onSearch');
    $hasGetDetails = method_exists($addon, 'onGetDetails');
    $hasGetStreams = method_exists($addon, 'onGetStreams');
    
    $tests[] = [
        'name' => 'Métodos disponibles',
        'passed' => $hasSearch || $hasGetDetails || $hasGetStreams,
        'message' => sprintf(
            'Métodos: %s%s%s',
            $hasSearch ? 'onSearch ' : '',
            $hasGetDetails ? 'onGetDetails ' : '',
            $hasGetStreams ? 'onGetStreams ' : ''
        )
    ];
    
    // Prueba 4: Probar búsqueda (si está disponible)
    if ($hasSearch) {
        try {
            $searchResult = $addon->onSearch('test', ['type' => 'movie', 'limit' => 1]);
            $tests[] = [
                'name' => 'Búsqueda funcional',
                'passed' => is_array($searchResult),
                'message' => is_array($searchResult) 
                    ? 'La búsqueda funciona correctamente' 
                    : 'Error en la búsqueda: ' . (is_string($searchResult) ? $searchResult : 'Resultado inválido')
            ];
        } catch (Exception $e) {
            $tests[] = [
                'name' => 'Búsqueda funcional',
                'passed' => false,
                'message' => 'Error en búsqueda: ' . $e->getMessage()
            ];
        }
    }
    
    // Prueba 5: Verificar información del addon
    $tests[] = [
        'name' => 'Información del addon',
        'passed' => true,
        'message' => sprintf(
            'ID: %s, Nombre: %s, Versión: %s, Autor: %s',
            $addon->getId(),
            $addon->getName(),
            $addon->getVersion(),
            $addon->getAuthor()
        )
    ];
    
    // Contar pruebas pasadas
    $passedCount = count(array_filter($tests, function($test) {
        return $test['passed'];
    }));
    $totalCount = count($tests);
    
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "Pruebas completadas: {$passedCount}/{$totalCount} pasaron",
        'data' => [
            'addon_id' => $addonId,
            'addon_name' => $addon->getName(),
            'addon_version' => $addon->getVersion(),
            'tests' => $tests,
            'summary' => [
                'total' => $totalCount,
                'passed' => $passedCount,
                'failed' => $totalCount - $passedCount
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    error_log('Error en api/addons/test.php: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al probar el addon: ' . $e->getMessage()
    ]);
    exit;
}
