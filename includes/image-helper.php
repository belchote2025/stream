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
function getImageUrl($url, $default = '/streaming-platform/assets/img/default-poster.svg') {
    if (empty($url) || $url === 'null' || $url === 'NULL') {
        return $default;
    }
    
    $url = trim($url);
    
    // Si es una URL externa que necesita proxy
    if (preg_match('/^https?:\/\/(image\.tmdb\.org|via\.placeholder\.com|images\.unsplash\.com)/', $url)) {
        return '/streaming-platform/api/image-proxy.php?url=' . urlencode($url);
    }
    
    // Si es una URL externa normal, usar directamente
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        return htmlspecialchars($url);
    }
    
    // Si es una ruta relativa, asegurar que empiece con /
    if (!empty($url) && !preg_match('/^\/|^https?:\/\//', $url)) {
        return '/' . ltrim($url, '/');
    }
    
    return !empty($url) ? htmlspecialchars($url) : $default;
}

