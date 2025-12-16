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
        
        // Buscar el primer resultado - múltiples patrones para mayor compatibilidad
        $patterns = [
            '/<a href="(\/title\/tt\d+)\/\?ref_=fn_tt_tt_1".*?<img.*?src="([^"]+)"/s',
            '/<a[^>]*href="(\/title\/tt\d+)[^"]*"[^>]*>.*?<img[^>]*src="([^"]+)"/s',
            '/<img[^>]*src="([^"]*\/tt\d+[^"]*)"[^>]*>/i',
            '/<img[^>]*data-src="([^"]*\/tt\d+[^"]*)"[^>]*>/i'
        ];
        
        $imageUrl = null;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                // El último match es la URL de la imagen
                $imageUrl = end($matches);
                if (!empty($imageUrl) && strpos($imageUrl, 'http') !== false) {
                    break;
                }
            }
        }
        
        if ($imageUrl && strpos($imageUrl, 'http') !== false) {
            // Limpiar la URL
            $imageUrl = html_entity_decode($imageUrl, ENT_QUOTES, 'UTF-8');
            
            // Mejorar la calidad de la imagen sin duplicar la extensión (.jpg -> .jpgjpg)
            // Solo reemplazar si el patrón V1_ está presente
            if (preg_match('/V1_.*?\.jpg/i', $imageUrl)) {
                $imageUrl = preg_replace('/V1_.*?(\.jpg)/i', 'V1_$1', $imageUrl);
            }
            // Normalizar errores comunes de doble extensión
            $imageUrl = preg_replace('/\.jpgjpg$/i', '.jpg', $imageUrl);
            
            // Asegurar que sea una URL completa
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = 'https://' . ltrim($imageUrl, '/');
            }
            
            // Devolver la URL de la imagen a través de nuestro proxy
            $result = "/api/image-proxy.php?url=" . urlencode($imageUrl);
            // Guardar en caché
            $imdbCache[$cacheKey] = $result;
            return $result;
        }
        
        // Si no encontramos imagen en los resultados, intentar buscar en la página del título directamente
        if (preg_match('/<a href="(\/title\/tt\d+)/', $html, $idMatches)) {
            $imdbId = basename($idMatches[1]);
            // Construir URL directa de la página del título
            $titleUrl = "https://www.imdb.com/title/{$imdbId}/";
            
            // Intentar obtener la página del título (con timeout más corto)
            $titleContext = stream_context_create([
                'http' => [
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
                    'timeout' => 2,
                    'ignore_errors' => true,
                ]
            ]);
            
            $titleHtml = @file_get_contents($titleUrl, false, $titleContext);
            if ($titleHtml && preg_match('/<img[^>]*class="[^"]*ipc-image[^"]*"[^>]*src="([^"]+)"/i', $titleHtml, $imgMatches)) {
                $imageUrl = $imgMatches[1];
                if (preg_match('/V1_.*?\.jpg/i', $imageUrl)) {
                    $imageUrl = preg_replace('/V1_.*?(\.jpg)/i', 'V1_$1', $imageUrl);
                }
                $imageUrl = preg_replace('/\.jpgjpg$/i', '.jpg', $imageUrl);
                if (strpos($imageUrl, 'http') !== 0) {
                    $imageUrl = 'https://' . ltrim($imageUrl, '/');
                }
                $result = "/api/image-proxy.php?url=" . urlencode($imageUrl);
                $imdbCache[$cacheKey] = $result;
                return $result;
            }
        }
    } catch (Exception $e) {
        error_log("Error en getImdbImage para {$title}: " . $e->getMessage());
    }
    
    // Guardar resultado vacío en caché para no intentar de nuevo
    $imdbCache[$cacheKey] = '';
    return '';
}

/**
 * Fallback: obtiene imágenes desde TVMaze (series) o iTunes (películas)
 * 
 * @param string $title
 * @param string $type
 * @param int|null $year
 * @return array ['poster' => string, 'backdrop' => string]
 */
function fetchFallbackImages($title, $type = 'movie', $year = null) {
    $poster = '';
    $backdrop = '';

    // Series: intentar TVMaze
    if ($type === 'series') {
        $tvmazeUrl = 'https://api.tvmaze.com/singlesearch/shows?q=' . urlencode($title);
        $tvmazeResp = @file_get_contents($tvmazeUrl);
        if ($tvmazeResp) {
            $data = json_decode($tvmazeResp, true);
            if (is_array($data) && isset($data['image']['original'])) {
                $poster = $data['image']['original'];
                $backdrop = $data['image']['original'];
            }
        }
    }

    // Películas: intentar iTunes Search
    if ($type === 'movie' || empty($poster)) {
        $itunesUrl = 'https://itunes.apple.com/search?media=movie&limit=1&term=' . urlencode($title . ' ' . ($year ?? ''));
        $itunesResp = @file_get_contents($itunesUrl);
        if ($itunesResp) {
            $data = json_decode($itunesResp, true);
            if (is_array($data) && !empty($data['results'][0]['artworkUrl100'])) {
                $art = $data['results'][0]['artworkUrl100'];
                // Subir resolución
                $art = str_replace('100x100', '1000x1000', $art);
                $poster = $poster ?: $art;
                $backdrop = $backdrop ?: $art;
            }
        }
    }

    return [
        'poster' => $poster,
        'backdrop' => $backdrop
    ];
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

    // Fallback: TVMaze / iTunes
    $fallback = fetchFallbackImages($title, $type, $year);
    if (!empty($fallback['poster'])) {
        return $fallback['poster'];
    }
    
    // Imagen por defecto
    return '/assets/img/default-poster.svg';
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
        $backdropUrl = preg_replace('/\.jpgjpg$/i', '.jpg', $backdropUrl);
        return $backdropUrl;
    }

    // Fallback: TVMaze / iTunes
    $fallback = fetchFallbackImages($title, $type, $year);
    if (!empty($fallback['backdrop'])) {
        return $fallback['backdrop'];
    }

    // Si no se encuentra, usar imagen por defecto
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
