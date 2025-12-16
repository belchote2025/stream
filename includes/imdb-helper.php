<?php
/**
 * Helper para obtener información de películas y series desde IMDB
 * Utiliza web scraping para obtener datos sin necesidad de API
 */

/**
 * Obtiene la URL de la imagen de una película o serie desde IMDB
 * 
 * @param string $title Título de la película o serie
 * @param string $type Tipo de contenido: 'movie' o 'series'
 * @param int $year Año de lanzamiento (opcional)
 * @return string URL de la imagen o cadena vacía si no se encuentra
 */
function getImdbImage($title, $type = 'movie', $year = null) {
    static $imdbRequests = 0;
    static $imdbCache = [];
    
    // Crear clave de caché
    $cacheKey = md5(strtolower(trim($title)) . '|' . $type . '|' . ($year ?? ''));
    
    // Si ya tenemos esta imagen en caché, devolverla
    if (isset($imdbCache[$cacheKey])) {
        return $imdbCache[$cacheKey];
    }
    
    $imdbRequests++;

    // Limitar el número de llamadas por petición para evitar timeouts masivos
    // Aumentado a 20 para permitir más imágenes en la actualización automática
    if ($imdbRequests > 20) {
        // Demasiadas peticiones en una sola carga de página: evitar bloquear la respuesta
        return '';
    }

    // Limpiar el título para la búsqueda
    $searchQuery = urlencode(trim($title) . ' ' . $year);
    $ttype = ($type == 'movie') ? 'ft' : 'tv';
    $searchUrl = "https://www.imdb.com/find?q={$searchQuery}&s=tt&ttype={$ttype}";
    
    // Usar file_get_contents con un contexto que incluye un user-agent y timeout corto
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            'timeout' => 3, // máximo 3s por petición
            'ignore_errors' => true,
        ]
    ]);
    
    try {
        // Obtener la página de resultados de búsqueda
        $html = @file_get_contents($searchUrl, false, $context);
        
        if ($html === false) {
            error_log("Error al acceder a IMDB para: {$title}");
            return '';
        }
        
        // Buscar el primer resultado
        if (preg_match('/<a href="(\/title\/tt\d+)\/\?ref_=fn_tt_tt_1".*?<img.*?src="([^"]+)"/s', $html, $matches)) {
            $imdbId = basename(dirname($matches[1]));
            $imageUrl = $matches[2];
            
            // Mejorar la calidad de la imagen (reemplazar V1_* con V1_.jpg para mejor resolución)
            $imageUrl = preg_replace('/V1_.*?\./', 'V1_.jpg', $imageUrl);
            
            // Devolver la URL de la imagen a través de nuestro proxy
            $result = "/api/image-proxy.php?url=" . urlencode($imageUrl);
            // Guardar en caché
            $imdbCache[$cacheKey] = $result;
            return $result;
        }
    } catch (Exception $e) {
        error_log("Error en getImdbImage para {$title}: " . $e->getMessage());
    }
    
    // Guardar resultado vacío en caché para no intentar de nuevo
    $imdbCache[$cacheKey] = '';
    return '';
}

/**
 * Obtiene la URL del póster de una película o serie
 * 
 * @param string $title Título de la película o serie
 * @param string $type Tipo de contenido: 'movie' o 'series'
 * @param int $year Año de lanzamiento (opcional)
 * @return string URL del póster o ruta por defecto si no se encuentra
 */
function getPosterImage($title, $type = 'movie', $year = null) {
    // Primero intentar obtener de IMDB
    $imdbImage = getImdbImage($title, $type, $year);
    
    if (!empty($imdbImage)) {
        return $imdbImage;
    }
    
    // Si no se encuentra en IMDB, usar una imagen por defecto según el tipo
    // Usar placeholders existentes en assets/img
    $defaultImage = '/assets/img/default-poster.svg';
    
    return $defaultImage;
}

/**
 * Obtiene la URL del fondo (backdrop) de una película o serie
 * 
 * @param string $title Título de la película o serie
 * @param string $type Tipo de contenido: 'movie' o 'series'
 * @param int $year Año de lanzamiento (opcional)
 * @return string URL del fondo o ruta por defecto si no se encuentra
 */
function getBackdropImage($title, $type = 'movie', $year = null) {
    // Primero intentar obtener de IMDB
    $imdbImage = getImdbImage($title, $type, $year);
    
    if (!empty($imdbImage)) {
        // Reemplazar para obtener una imagen de fondo en lugar de un póster
        $backdropUrl = str_replace('V1_.jpg', 'V1_.jpg', $imdbImage);
        return $backdropUrl;
    }
    
    // Si no se encuentra en IMDB, usar una imagen de fondo por defecto
    return '/assets/img/default-backdrop.svg';
}

/**
 * Procesa un array de contenido y añade imágenes de IMDB
 * 
 * @param array $content Array de contenido
 * @return array Contenido con imágenes actualizadas
 */
function addImdbImagesToContent($content) {
    if (!is_array($content)) {
        return [];
    }
    
    // Asegurar que getImageUrl esté disponible
    if (!function_exists('getImageUrl')) {
        require_once __DIR__ . '/image-helper.php';
    }
    
    foreach ($content as &$item) {
        // Procesar poster_url
        if (empty($item['poster_url']) || strpos($item['poster_url'], 'default-') !== false) {
            $item['poster_url'] = getPosterImage(
                $item['title'],
                $item['type'] ?? 'movie',
                $item['release_year'] ?? null
            );
        } else {
            // Si ya tiene URL, procesarla para usar proxy si es necesario
            $item['poster_url'] = getImageUrl($item['poster_url'], '/assets/img/default-poster.svg');
        }
        
        // Procesar backdrop_url
        if (empty($item['backdrop_url']) || strpos($item['backdrop_url'], 'default-') !== false) {
            $item['backdrop_url'] = getBackdropImage(
                $item['title'],
                $item['type'] ?? 'movie',
                $item['release_year'] ?? null
            );
        } else {
            // Si ya tiene URL, procesarla para usar proxy si es necesario
            $item['backdrop_url'] = getImageUrl($item['backdrop_url'], '/assets/img/default-backdrop.svg');
        }
    }
    
    return $content;
}
