<?php
/**
 * Router para endpoints de contenido
 * Maneja las rutas: /api/content/featured, /api/content/popular, /api/content/recent
 */

// Establecer headers ANTES de cualquier output
ob_start();

// Configuración de encabezados HTTP
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET');
header('X-Content-Type-Options: nosniff');

// Desactivar mostrar errores en pantalla
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Limpiar cualquier output accidental
ob_clean();

// Extraer la parte de la ruta después de /api/content/
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Buscar 'content' en la ruta
$contentIndex = array_search('content', $pathParts);

if ($contentIndex !== false && isset($pathParts[$contentIndex + 1])) {
    $endpoint = $pathParts[$contentIndex + 1];
    
    // Enrutar a los archivos correspondientes
    switch ($endpoint) {
        case 'featured':
            require __DIR__ . '/featured.php';
            break;
            
        case 'popular':
            require __DIR__ . '/popular.php';
            break;
            
        case 'recent':
            require __DIR__ . '/recent.php';
            break;
            
        default:
            // Si es un número, es un ID
            if (is_numeric($endpoint)) {
                $_GET['id'] = $endpoint;
                require __DIR__ . '/index.php';
            } else {
                if (ob_get_level() > 0) {
                    ob_clean();
                }
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Endpoint no encontrado'
                ]);
                exit;
            }
            break;
    }
} else {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Ruta no válida'
    ]);
    exit;
}

