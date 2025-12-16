<?php
/**
 * Proxy para imágenes externas (TMDB, etc.)
 * Soluciona problemas de CORS
 */

// Permitir CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: public, max-age=31536000');

$url = $_GET['url'] ?? '';

if (empty($url)) {
    http_response_code(400);
    readfile(__DIR__ . '/../assets/img/default-poster.svg');
    exit;
}

// Validar que la URL sea de un dominio permitido
// Añadimos dominios de IMDb/Amazon, TVMaze e iTunes (mzstatic)
$allowedDomains = [
    'image.tmdb.org',
    'via.placeholder.com',
    'images.unsplash.com',
    'm.media-amazon.com',
    'ia.media-imdb.com',
    'static.tvmaze.com',
    'tvmaze.com',
    'mzstatic.com'
];

$parsedUrl = parse_url($url);
$domain = $parsedUrl['host'] ?? '';

// Permitir subdominios de tvmaze.com y mzstatic.com
$isAllowedSubdomain = false;
if ($domain) {
    foreach (['tvmaze.com', 'mzstatic.com'] as $allowedRoot) {
        if ($domain === $allowedRoot || str_ends_with($domain, '.' . $allowedRoot)) {
            $isAllowedSubdomain = true;
            break;
        }
    }
}

if (!in_array($domain, $allowedDomains) && !$isAllowedSubdomain) {
    http_response_code(403);
    header('Content-Type: image/svg+xml');
    readfile(__DIR__ . '/../assets/img/default-poster.svg');
    exit;
}

// Obtener la imagen
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$imageData = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $imageData) {
    // Detectar tipo de imagen
    $imageInfo = @getimagesizefromstring($imageData);
    if ($imageInfo) {
        $mimeType = $imageInfo['mime'];
        header('Content-Type: ' . $mimeType);
    }
    
    echo $imageData;
} else {
    // Fallback a imagen por defecto
    header('Content-Type: image/svg+xml');
    readfile(__DIR__ . '/../assets/img/default-poster.svg');
}

