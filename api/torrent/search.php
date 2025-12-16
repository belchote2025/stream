<?php
/**
 * API para buscar enlaces de torrents de películas y series
 * Utiliza APIs públicas legítimas para obtener información de torrents
 */

// Prevenir cualquier output antes del JSON
ob_start();

require_once __DIR__ . '/../../includes/config.php';

// Limpiar cualquier output que haya generado config.php
ob_clean();

// Establecer headers JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y rol de administrador sin hacer redirect
// config.php ya inicia la sesión, así que solo verificamos
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Debes iniciar sesión.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Verificar si es administrador
$db = getDbConnection();
$userId = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    ob_clean();
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Se requiere rol de administrador.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_clean();
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

$title = $_GET['title'] ?? '';
$year = $_GET['year'] ?? '';
$type = $_GET['type'] ?? 'movie'; // 'movie' o 'series'

if (empty($title)) {
    http_response_code(400);
    echo json_encode(['error' => 'El título es requerido']);
    exit;
}

try {
    $results = [];
    $searchLog = []; // Para debugging
    
    // Normalizar título para búsquedas
    $titleClean = trim($title);
    $titleVariants = [
        $titleClean, // Título original
        strtolower($titleClean), // Minúsculas
        ucwords(strtolower($titleClean)), // Capitalizado
    ];
    
    // Opción 1: YTS API (solo películas, API pública y legal)
    if ($type === 'movie') {
        // Buscar primero con año si está disponible
        if (!empty($year)) {
            $ytsResults = searchYTS($titleClean, $year);
            $searchLog[] = "YTS con año ({$year}): " . count($ytsResults) . " resultados";
            if (!empty($ytsResults)) {
                $results = array_merge($results, $ytsResults);
            }
        }
        // Buscar también sin año para encontrar todas las versiones
        $ytsResultsNoYear = searchYTS($titleClean, '');
        $searchLog[] = "YTS sin año: " . count($ytsResultsNoYear) . " resultados";
        if (!empty($ytsResultsNoYear)) {
            $results = array_merge($results, $ytsResultsNoYear);
        }
    }
    
    // Opción 2: EZTV API (solo series, API pública)
    if ($type === 'series') {
        $eztvResults = searchEZTV($titleClean);
        $searchLog[] = "EZTV: " . count($eztvResults) . " resultados";
        if (!empty($eztvResults)) {
            $results = array_merge($results, $eztvResults);
        }
    }
    
    // Opción 3: 1337x API (búsqueda general - nueva fuente)
    foreach ($titleVariants as $idx => $variant) {
        $leetxResults = search1337x($variant, $type);
        $searchLog[] = "1337x variante #{$idx} ('{$variant}'): " . count($leetxResults) . " resultados";
        if (!empty($leetxResults)) {
            $results = array_merge($results, $leetxResults);
        }
    }
    
    // Opción 4: Torrentio (addon de Stremio, búsqueda agregada)
    // Buscar siempre con y sin año para encontrar todas las versiones
    $torrentioResults = searchTorrentio($titleClean, $type, $year);
    $searchLog[] = "Torrentio con año: " . count($torrentioResults) . " resultados";
    if (!empty($torrentioResults)) {
        $results = array_merge($results, $torrentioResults);
    }
    // Siempre buscar sin año también
    $torrentioResultsNoYear = searchTorrentio($titleClean, $type, '');
    $searchLog[] = "Torrentio sin año: " . count($torrentioResultsNoYear) . " resultados";
    if (!empty($torrentioResultsNoYear)) {
        $results = array_merge($results, $torrentioResultsNoYear);
    }
    
    // Opción 5: The Pirate Bay API (búsqueda general)
    // Buscar con diferentes variantes del título
    foreach ($titleVariants as $idx => $variant) {
        $tpbResults = searchTPB($variant, $type);
        $searchLog[] = "TPB variante #{$idx} ('{$variant}'): " . count($tpbResults) . " resultados";
        if (!empty($tpbResults)) {
            $results = array_merge($results, $tpbResults);
        }
    }
    
    // Eliminar duplicados y ordenar por calidad y seeds
    $beforeDedupe = count($results);
    $results = removeDuplicates($results);
    $afterDedupe = count($results);
    $results = sortByQuality($results);
    
    // Log detallado para debugging
    $logMessage = "Búsqueda torrents - Título: '{$title}', Tipo: {$type}, Año: {$year}\n";
    $logMessage .= "Detalles por fuente:\n" . implode("\n", $searchLog) . "\n";
    $logMessage .= "Total antes de eliminar duplicados: {$beforeDedupe}\n";
    $logMessage .= "Total después de eliminar duplicados: {$afterDedupe}\n";
    $logMessage .= "Resultados finales: " . count($results);
    error_log($logMessage);
    
    ob_clean();
    $response = [
        'success' => true,
        'count' => count($results),
        'results' => array_slice($results, 0, 50), // Aumentado a 50 resultados
        'debug' => $searchLog // Incluir info de debug en respuesta
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar torrents: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Buscar en Torrentio (addon de Stremio)
 */
function searchTorrentio($title, $type = 'movie', $year = '') {
    $results = [];
    $baseUrl = getenv('TORRENTIO_BASE_URL') ?: 'https://torrentio.strem.fun';
    
    // Trackers populares para agregar a los magnet links
    $trackers = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.stealth.si:80/announce',
        'udp://tracker.torrent.eu.org:451/announce',
        'udp://tracker.bittor.pw:1337/announce',
        'udp://public.popcorn-tracker.org:6969/announce',
        'udp://tracker.dler.org:6969/announce',
        'udp://exodus.desync.com:6969',
        'udp://open.demonii.com:1337/announce'
    ];
    
    try {
        $queryTerm = trim($title . ' ' . $year);
        $query = urlencode($queryTerm);
        $catalog = $type === 'series' ? 'series/all' : 'movie/all';
        $url = rtrim($baseUrl, '/') . "/{$catalog}/search={$query}.json?sort=seeds";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['streams']) && is_array($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    $magnet = '';
                    if (!empty($stream['infoHash'])) {
                        $magnet = 'magnet:?xt=urn:btih:' . $stream['infoHash'];
                        if (!empty($stream['title'])) {
                            $magnet .= '&dn=' . urlencode($stream['title']);
                        }
                        // Agregar trackers de la fuente
                        if (!empty($stream['sources']) && is_array($stream['sources'])) {
                            foreach ($stream['sources'] as $tracker) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                        // Agregar trackers adicionales
                        foreach ($trackers as $tracker) {
                            $magnet .= '&tr=' . urlencode($tracker);
                        }
                    } elseif (!empty($stream['url']) && strpos($stream['url'], 'magnet:?') === 0) {
                        $magnet = $stream['url'];
                        // Agregar trackers adicionales si no están ya
                        foreach ($trackers as $tracker) {
                            if (strpos($magnet, urlencode($tracker)) === false) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                    }
                    
                    if (empty($magnet)) {
                        continue;
                    }
                    
                    $titleText = $stream['title'] ?? $stream['name'] ?? $title;
                    $size = $stream['size'] ?? $stream['fileSize'] ?? null;
                    $results[] = [
                        'title' => $titleText,
                        'quality' => $stream['quality'] ?? extractQuality($titleText),
                        'size' => $size ? (is_numeric($size) ? formatBytes((int)$size) : $size) : 'Unknown',
                        'seeds' => $stream['seeders'] ?? $stream['seeds'] ?? 0,
                        'peers' => $stream['peers'] ?? 0,
                        'magnet' => $magnet,
                        'source' => 'Torrentio'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en Torrentio: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Buscar en YTS API (solo películas)
 */
function searchYTS($title, $year = '') {
    $results = [];
    
    // Trackers populares para agregar a los magnet links
    $trackers = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.stealth.si:80/announce',
        'udp://tracker.torrent.eu.org:451/announce',
        'udp://tracker.bittor.pw:1337/announce',
        'udp://public.popcorn-tracker.org:6969/announce',
        'udp://tracker.dler.org:6969/announce',
        'udp://exodus.desync.com:6969',
        'udp://open.demonii.com:1337/announce'
    ];
    
    try {
        $query = urlencode($title);
        // Aumentar límite de resultados
        $url = "https://yts.mx/api/v2/list_movies.json?query_term={$query}&sort_by=download_count&order_by=desc&limit=50";
        
        if (!empty($year)) {
            $url .= "&year={$year}";
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['data']['movies']) && is_array($data['data']['movies'])) {
                // Incluir TODAS las películas devueltas por YTS (la API ya filtra por relevancia)
                foreach ($data['data']['movies'] as $movie) {
                    if (isset($movie['torrents']) && is_array($movie['torrents'])) {
                        foreach ($movie['torrents'] as $torrent) {
                            // Construir magnet link
                            $hash = $torrent['hash'] ?? '';
                            if (!empty($hash)) {
                                $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($movie['title']);
                                
                                // Agregar trackers
                                foreach ($trackers as $tracker) {
                                    $magnet .= "&tr=" . urlencode($tracker);
                                }
                                
                                $results[] = [
                                    'title' => $movie['title'] . ' (' . $movie['year'] . ')',
                                    'quality' => $torrent['quality'] ?? 'Unknown',
                                    'size' => $torrent['size'] ?? 'Unknown',
                                    'seeds' => $torrent['seeds'] ?? 0,
                                    'peers' => $torrent['peers'] ?? 0,
                                    'magnet' => $magnet,
                                    'source' => 'YTS',
                                    'year' => $movie['year'] ?? null,
                                    'rating' => $movie['rating'] ?? null
                                ];
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en YTS API: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Buscar en EZTV API (solo series)
 */
function searchEZTV($title) {
    $results = [];
    
    // Trackers populares para agregar a los magnet links
    $trackers = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.stealth.si:80/announce',
        'udp://tracker.torrent.eu.org:451/announce',
        'udp://tracker.bittor.pw:1337/announce',
        'udp://public.popcorn-tracker.org:6969/announce',
        'udp://tracker.dler.org:6969/announce',
        'udp://exodus.desync.com:6969',
        'udp://open.demonii.com:1337/announce'
    ];
    
    try {
        $query = urlencode($title);
        // Aumentar límite para obtener más resultados
        $url = "https://eztv.re/api/get-torrents?imdb_id=&limit=100&page=1&keywords={$query}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['torrents']) && is_array($data['torrents'])) {
                foreach ($data['torrents'] as $torrent) {
                    $magnet = $torrent['magnet_url'] ?? '';
                    if (!empty($magnet)) {
                        // Agregar trackers adicionales
                        foreach ($trackers as $tracker) {
                            if (strpos($magnet, urlencode($tracker)) === false) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                        
                        $size = $torrent['size_bytes'] ?? 0;
                        $results[] = [
                            'title' => $torrent['title'] ?? 'Unknown',
                            'quality' => extractQuality($torrent['title'] ?? '') ?: 'HD',
                            'size' => is_numeric($size) ? formatBytes((int)$size) : ($size ?: 'Unknown'),
                            'seeds' => (int)($torrent['seeds'] ?? 0),
                            'peers' => (int)($torrent['peers'] ?? 0),
                            'magnet' => $magnet,
                            'source' => 'EZTV',
                            'episode' => $torrent['episode'] ?? null,
                            'season' => $torrent['season'] ?? null
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en EZTV API: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Buscar en 1337x (búsqueda general)
 */
function search1337x($title, $type = 'movie') {
    $results = [];
    
    try {
        $query = urlencode($title);
        
        // Usar API pública de 1337x
        $apiUrls = [
            "https://1337x.unblockit.how/api/search/{$query}/1/",
            "https://1337x.to/api/search/{$query}/1/",
        ];
        
        foreach ($apiUrls as $apiUrl) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['torrents']) && is_array($data['torrents'])) {
                    foreach ($data['torrents'] as $torrent) {
                        if (isset($torrent['magnet']) && !empty($torrent['magnet'])) {
                            $name = $torrent['name'] ?? 'Unknown';
                            $size = $torrent['size'] ?? 'Unknown';
                            
                            $results[] = [
                                'title' => $name,
                                'quality' => extractQuality($name),
                                'size' => $size,
                                'seeds' => (int)($torrent['seeders'] ?? 0),
                                'peers' => (int)($torrent['leechers'] ?? 0),
                                'magnet' => $torrent['magnet'],
                                'source' => '1337x'
                            ];
                        }
                    }
                    
                    if (count($results) > 0) {
                        break; // Si encontramos resultados, no intentar más URLs
                    }
                }
            }
        }
        
        // Si la API no funciona, intentar scraping alternativo
        if (count($results) === 0) {
            // Usar un servicio de proxy/API alternativo
            $proxyUrl = "https://api.proxyscrape.com/v2/?request=get&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all";
            
            // Intentar búsqueda directa con diferentes mirrors
            $mirrors = [
                "https://www.1377x.to/search/{$query}/1/",
                "https://1337x.st/search/{$query}/1/",
            ];
            
            // Por ahora, si la API falla, simplemente retornamos vacío
            // En el futuro se puede implementar scraping web
        }
    } catch (Exception $e) {
        error_log('Error en 1337x API: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Buscar en The Pirate Bay (búsqueda general)
 * Nota: TPB puede no estar siempre disponible
 */
function searchTPB($title, $type = 'movie') {
    $results = [];
    
    try {
        // Usar un proxy público de TPB API
        $query = urlencode($title);
        $category = $type === 'movie' ? '201' : '205'; // 201 = Movies, 205 = TV Shows
        
        // Intentar múltiples proxies de TPB
        $proxies = [
            "https://apibay.org/q.php?q={$query}&cat={$category}",
            "https://apibay.org/q.php?q={$query}", // Sin categoría para búsqueda más amplia
        ];
        
        // Trackers populares para agregar a los magnet links
        $trackers = [
            'udp://tracker.opentrackr.org:1337/announce',
            'udp://open.stealth.si:80/announce',
            'udp://tracker.torrent.eu.org:451/announce',
            'udp://tracker.bittor.pw:1337/announce',
            'udp://public.popcorn-tracker.org:6969/announce',
            'udp://tracker.dler.org:6969/announce',
            'udp://exodus.desync.com:6969',
            'udp://open.demonii.com:1337/announce'
        ];
        
        foreach ($proxies as $proxyUrl) {
            $ch = curl_init($proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (is_array($data) && count($data) > 0) {
                    // Incluir TODOS los resultados (hasta 100 por proxy)
                    $count = 0;
                    foreach ($data as $torrent) {
                        if ($count >= 100) break; // Aumentado a 100 resultados
                        
                        // Filtrar resultados inválidos o de baja calidad
                        if (isset($torrent['info_hash']) && !empty($torrent['info_hash']) && 
                            $torrent['info_hash'] !== '0000000000000000000000000000000000000000') {
                            
                            $hash = $torrent['info_hash'];
                            $name = $torrent['name'] ?? 'Unknown';
                            
                            // Construir magnet link con trackers
                            $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($name);
                            foreach ($trackers as $tracker) {
                                $magnet .= "&tr=" . urlencode($tracker);
                            }
                            
                            $results[] = [
                                'title' => $name,
                                'quality' => extractQuality($name),
                                'size' => formatBytes($torrent['size'] ?? 0),
                                'seeds' => (int)($torrent['seeders'] ?? 0),
                                'peers' => (int)($torrent['leechers'] ?? 0),
                                'magnet' => $magnet,
                                'source' => 'TPB'
                            ];
                            $count++;
                        }
                    }
                    if (count($results) > 0) {
                        break; // Si encontramos resultados, no intentar más proxies
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en TPB API: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Extraer calidad del nombre del torrent
 */
function extractQuality($title) {
    $title = strtoupper($title);
    
    if (strpos($title, '4K') !== false || strpos($title, '2160P') !== false) {
        return '4K';
    } elseif (strpos($title, '1080P') !== false || strpos($title, 'FULLHD') !== false) {
        return '1080p';
    } elseif (strpos($title, '720P') !== false || strpos($title, 'HD') !== false) {
        return '720p';
    } elseif (strpos($title, '480P') !== false || strpos($title, 'SD') !== false) {
        return '480p';
    }
    
    return 'Unknown';
}

/**
 * Formatear bytes a formato legible
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base = log($bytes, 1024);
    $unit = $units[floor($base)];
    
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $unit;
}

/**
 * Eliminar duplicados basados en el hash del magnet
 */
function removeDuplicates($results) {
    $seen = [];
    $unique = [];
    
    foreach ($results as $result) {
        // Extraer hash del magnet link
        if (preg_match('/btih:([a-f0-9]{40})/i', $result['magnet'], $matches)) {
            $hash = strtolower($matches[1]);
            
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $unique[] = $result;
            }
        }
    }
    
    return $unique;
}

/**
 * Ordenar resultados por calidad y número de seeds
 */
function sortByQuality($results) {
    usort($results, function($a, $b) {
        // Priorizar por calidad
        $qualityOrder = ['4K' => 4, '1080p' => 3, '720p' => 2, '480p' => 1, 'Unknown' => 0];
        $qualityA = $qualityOrder[$a['quality']] ?? 0;
        $qualityB = $qualityOrder[$b['quality']] ?? 0;
        
        if ($qualityA !== $qualityB) {
            return $qualityB - $qualityA; // Mayor calidad primero
        }
        
        // Si misma calidad, ordenar por seeds
        return ($b['seeds'] ?? 0) - ($a['seeds'] ?? 0);
    });
    
    return $results;
}
?>

