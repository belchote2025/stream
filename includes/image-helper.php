<?php
/**
 * Helper para manejar URLs de imágenes
 * Proxifica imágenes externas para evitar problemas de CORS
 */

/**
 * Obtiene la URL de una imagen, usando proxy si es necesario
 * 
 * @param string $url URL original de la imagen
 * @param string $default URL por defecto si no hay imagen
 * @return string URL procesada
 */
function buildAbsoluteUrl($path) {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return htmlspecialchars($path);
    }
    $baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($baseUrl === '') {
        return '/' . ltrim($path, '/');
    }
    return $baseUrl . '/' . ltrim($path, '/');
}

function getImageUrl($url, $default = '/assets/img/default-poster.svg') {
    // Normalizar urls legacy inexistentes
    if (preg_match('/default-(movie|tv)-poster\.jpg$/i', trim((string)$url))) {
        $url = '/assets/img/default-poster.svg';
    }

    if (empty($url) || $url === 'null' || $url === 'NULL') {
        return buildAbsoluteUrl($default);
    }
    
    $url = trim($url);
    
    // Si ya es una URL del proxy, devolverla tal cual
    if (strpos($url, '/api/image-proxy.php') !== false) {
        return buildAbsoluteUrl($url);
    }
    
    // Si es una URL externa que necesita proxy (verificar antes de otras URLs externas)
    $needsProxy = preg_match('/https?:\/\/(image\.tmdb\.org|via\.placeholder\.com|images\.unsplash\.com|m\.media-amazon\.com|ia\.media-imdb\.com|imdb\.com)/i', $url);
    if ($needsProxy) {
        return buildAbsoluteUrl('/api/image-proxy.php?url=' . urlencode($url));
    }
    
    // Si es una URL externa normal, usar directamente
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($url);
    }
    
    // Si es una ruta relativa, asegurar que empiece con /
    if (!empty($url) && !preg_match('/^\/|^https?:\/\//', $url)) {
        return buildAbsoluteUrl('/' . ltrim($url, '/'));
    }
    
    return !empty($url) ? buildAbsoluteUrl($url) : buildAbsoluteUrl($default);
}

