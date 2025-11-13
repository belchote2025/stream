<?php
/**
 * Router para endpoints de contenido
 * Maneja las rutas: /api/content/featured, /api/content/popular, /api/content/recent
 */

// Obtener la ruta solicitada
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

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
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Endpoint no encontrado'
                ]);
            }
            break;
    }
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Ruta no válida'
    ]);
}

