<?php
/**
 * API para buscar enlaces de torrents de películas y series
 * Utiliza APIs públicas legítimas para obtener información de torrents
 */

// Prevenir cualquier output antes del JSON
ob_start();

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../scripts/fetch-new-content.php';

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
$quality = $_GET['quality'] ?? ''; // filtro de calidad: 720p, 1080p, etc.
$min_seeds = (int)($_GET['min_seeds'] ?? 0); // mínimo de seeds
$sources = $_GET['sources'] ?? ''; // fuentes específicas, separadas por coma

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
    
    // PRIMERO: Buscar enlaces de streaming directo (upstream, powvideo, filemoon, streamtape, streamwish)
    // Estos son más rápidos que los torrents y no requieren descarga
    $streamingResults = [];
    try {
        $imdbId = null;
        // Intentar obtener IMDb ID para búsquedas más precisas
        if (function_exists('resolveImdbId')) {
            $imdbId = resolveImdbId($titleClean, $year ? (int)$year : null, $type, null);
        }
        
        $streamingSources = searchStreamingSources($titleClean, $type, $year ? (int)$year : null, $imdbId);
        foreach ($streamingSources as $streamUrl) {
            if (!empty($streamUrl)) {
                $streamingResults[] = [
                    'title' => $titleClean,
                    'magnet' => null,
                    'url' => $streamUrl,
                    'source' => 'streaming',
                    'quality' => 'HD',
                    'seeds' => 999, // Prioridad alta para streaming
                    'size' => null,
                    'type' => 'streaming'
                ];
            }
        }
        $searchLog[] = "Streaming (upstream/powvideo/filemoon/streamtape/streamwish): " . count($streamingResults) . " resultados";
        if (!empty($streamingResults)) {
            $results = array_merge($results, $streamingResults);
        }
    } catch (Exception $e) {
        $searchLog[] = "Error en búsqueda de streaming: " . $e->getMessage();
    }
    
    // Búsqueda en paralelo de todas las fuentes para obtener más resultados
    // Orden optimizado: primero APIs rápidas, luego scraping web
    
    // Agregar filtros a debug
    $searchLog[] = "Filtros aplicados - Calidad: '{$quality}', Min Seeds: {$min_seeds}, Fuentes: '{$sources}'";
    
    // Opción 0: Jackett (si está configurado - agrega múltiples indexadores)
    try {
        $jackettUrl = getenv('JACKETT_URL') ?: (defined('JACKETT_URL') ? JACKETT_URL : '');
        $jackettApiKey = getenv('JACKETT_API_KEY') ?: (defined('JACKETT_API_KEY') ? JACKETT_API_KEY : '');
        
        if (!empty($jackettUrl) && !empty($jackettApiKey)) {
            $jackettResults = searchJackett($titleClean, $type, $year, $jackettUrl, $jackettApiKey);
            $searchLog[] = "Jackett: " . count($jackettResults) . " resultados";
            if (!empty($jackettResults)) {
                $results = array_merge($results, $jackettResults);
            }
        }
    } catch (Exception $e) {
        $searchLog[] = "Jackett error: " . $e->getMessage();
    }
    
    // Opción 1: Torrentio (más rápido y confiable)
    $torrentioResults = searchTorrentio($titleClean, $type, $year);
    $searchLog[] = "Torrentio con año: " . count($torrentioResults) . " resultados";
    if (!empty($torrentioResults)) {
        $results = array_merge($results, $torrentioResults);
    }
    $torrentioResultsNoYear = searchTorrentio($titleClean, $type, '');
    $searchLog[] = "Torrentio sin año: " . count($torrentioResultsNoYear) . " resultados";
    if (!empty($torrentioResultsNoYear)) {
        $results = array_merge($results, $torrentioResultsNoYear);
    }
    
    // Opción 2: YTS API (solo películas, API pública y legal)
    if ($type === 'movie') {
        if (!empty($year)) {
            $ytsResults = searchYTS($titleClean, $year);
            $searchLog[] = "YTS con año ({$year}): " . count($ytsResults) . " resultados";
            if (!empty($ytsResults)) {
                $results = array_merge($results, $ytsResults);
            }
        }
        $ytsResultsNoYear = searchYTS($titleClean, '');
        $searchLog[] = "YTS sin año: " . count($ytsResultsNoYear) . " resultados";
        if (!empty($ytsResultsNoYear)) {
            $results = array_merge($results, $ytsResultsNoYear);
        }
    }
    
    // Opción 3: EZTV API (solo series, API pública)
    if ($type === 'series') {
        $eztvResults = searchEZTV($titleClean);
        $searchLog[] = "EZTV: " . count($eztvResults) . " resultados";
        if (!empty($eztvResults)) {
            $results = array_merge($results, $eztvResults);
        }
    }
    
    // Opción 4: 1337x (API y scraping alternativo)
    foreach ($titleVariants as $idx => $variant) {
        $leetxResults = search1337x($variant, $type);
        $searchLog[] = "1337x variante #{$idx} ('{$variant}'): " . count($leetxResults) . " resultados";
        if (!empty($leetxResults)) {
            $results = array_merge($results, $leetxResults);
        }
    }
    
    // Opción 5: RARBG (solo películas, API)
    if ($type === 'movie') {
        $rarbgResults = searchRARBG($titleClean, $year);
        $searchLog[] = "RARBG: " . count($rarbgResults) . " resultados";
        if (!empty($rarbgResults)) {
            $results = array_merge($results, $rarbgResults);
        }
    }
    
    // Opción 6: The Pirate Bay API (búsqueda general) - Mover después de otras fuentes
    foreach ($titleVariants as $idx => $variant) {
        $tpbResults = searchTPB($variant, $type);
        $searchLog[] = "TPB variante #{$idx} ('{$variant}'): " . count($tpbResults) . " resultados";
        if (!empty($tpbResults)) {
            $results = array_merge($results, $tpbResults);
        }
    }
    
    // Opción 7: LimeTorrents (búsqueda general - nueva fuente)
    foreach ($titleVariants as $idx => $variant) {
        $limeResults = searchLimeTorrents($variant, $type);
        $searchLog[] = "LimeTorrents variante #{$idx} ('{$variant}'): " . count($limeResults) . " resultados";
        if (!empty($limeResults)) {
            $results = array_merge($results, $limeResults);
        }
    }
    
    // Opción 8: Torlock (búsqueda general - nueva fuente)
    foreach ($titleVariants as $idx => $variant) {
        $torlockResults = searchTorlock($variant, $type);
        $searchLog[] = "Torlock variante #{$idx} ('{$variant}'): " . count($torlockResults) . " resultados";
        if (!empty($torlockResults)) {
            $results = array_merge($results, $torlockResults);
        }
    }
    
    // Opción 9: TorrentGalaxy (búsqueda general - nueva fuente)
    foreach ($titleVariants as $idx => $variant) {
        $tgxResults = searchTorrentGalaxy($variant, $type);
        $searchLog[] = "TorrentGalaxy variante #{$idx} ('{$variant}'): " . count($tgxResults) . " resultados";
        if (!empty($tgxResults)) {
            $results = array_merge($results, $tgxResults);
        }
    }
    
    // Eliminar duplicados y ordenar por calidad y seeds
    $beforeDedupe = count($results);
    $results = removeDuplicates($results);
    $afterDedupe = count($results);
    
    // Aplicar filtros adicionales
    $results = applyFilters($results, $quality, $min_seeds, $sources);
    $afterFilters = count($results);
    
    $results = sortByQuality($results);
    
    // Log detallado para debugging
    $logMessage = "Búsqueda torrents - Título: '{$title}', Tipo: {$type}, Año: {$year}\n";
    $logMessage .= "Filtros aplicados - Calidad: '{$quality}', Min Seeds: {$min_seeds}, Fuentes: '{$sources}'\n";
    $logMessage .= "Detalles por fuente:\n" . implode("\n", $searchLog) . "\n";
    $logMessage .= "Total antes de eliminar duplicados: {$beforeDedupe}\n";
    $logMessage .= "Total después de eliminar duplicados: {$afterDedupe}\n";
    $logMessage .= "Total después de filtros: {$afterFilters}\n";
    $logMessage .= "Total después de filtros: {$afterFilters}\n";
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
 * Buscar en 1337x usando API y scraping alternativo
 */
function search1337x($title, $type = 'movie') {
    $results = [];
    
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
        
        // Intentar primero con API pública de 1337x
        $apiUrls = [
            "https://1337x.unblockit.how/api/search/{$query}/1/",
            "https://1337x.to/api/search/{$query}/1/",
        ];
        
        foreach ($apiUrls as $apiUrl) {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/json'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['torrents']) && is_array($data['torrents'])) {
                    foreach ($data['torrents'] as $torrent) {
                        if (isset($torrent['magnet']) && !empty($torrent['magnet'])) {
                            $magnet = $torrent['magnet'];
                            // Agregar trackers
                            foreach ($trackers as $tracker) {
                                if (strpos($magnet, urlencode($tracker)) === false && strpos($magnet, $tracker) === false) {
                                    $magnet .= '&tr=' . urlencode($tracker);
                                }
                            }
                            
                            $name = $torrent['name'] ?? 'Unknown';
                            $size = $torrent['size'] ?? 'Unknown';
                            
                            $results[] = [
                                'title' => $name,
                                'quality' => extractQuality($name),
                                'size' => $size,
                                'seeds' => (int)($torrent['seeders'] ?? 0),
                                'peers' => (int)($torrent['leechers'] ?? 0),
                                'magnet' => $magnet,
                                'source' => '1337x'
                            ];
                        }
                    }
                    
                    if (count($results) > 0) {
                        return $results; // Si encontramos resultados, retornar inmediatamente
                    }
                }
            }
        }
        
        // Si la API no funciona, intentar scraping web alternativo
        $mirrors = [
            "https://www.1377x.to/search/{$query}/1/",
            "https://1337x.st/search/{$query}/1/",
        ];
        
        foreach ($mirrors as $url) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            ]);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $html && strlen($html) > 1000) {
                // Extraer magnet links del HTML
                if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})([^"\'<>\s]*)/i', $html, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $magnet = $match[0];
                        // Extraer título del contexto
                        $contextStart = max(0, strpos($html, $magnet) - 400);
                        $context = substr($html, $contextStart, 800);
                        preg_match('/<a[^>]*>([^<]*' . preg_quote($title, '/') . '[^<]*)</i', $context, $titleMatch);
                        $torrentTitle = $titleMatch[1] ?? $title;
                        
                        // Agregar trackers
                        foreach ($trackers as $tracker) {
                            if (strpos($magnet, urlencode($tracker)) === false && strpos($magnet, $tracker) === false) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                        
                        $results[] = [
                            'title' => trim(html_entity_decode($torrentTitle, ENT_QUOTES, 'UTF-8')),
                            'quality' => extractQuality($torrentTitle),
                            'size' => 'Unknown',
                            'seeds' => 0,
                            'peers' => 0,
                            'magnet' => $magnet,
                            'source' => '1337x'
                        ];
                    }
                    if (count($results) > 0) break;
                }
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
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
 * Buscar en RARBG (solo películas) - Usando API pública
 */
function searchRARBG($title, $year = '') {
    $results = [];
    
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
        // RARBG API requiere obtener token primero, pero podemos usar scraping alternativo
        // Intentar primero con API si está disponible
        $query = urlencode($title);
        $url = "https://torrentapi.org/pubapi_v2.php?mode=search&search_string={$query}&format=json_extended&limit=50";
        
        if (!empty($year)) {
            $url .= "&search_imdb=" . urlencode($year);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['torrent_results']) && is_array($data['torrent_results'])) {
                foreach ($data['torrent_results'] as $torrent) {
                    $hash = $torrent['info_hash'] ?? '';
                    if (!empty($hash) && $hash !== '0000000000000000000000000000000000000000') {
                        $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($torrent['title'] ?? $title);
                        foreach ($trackers as $tracker) {
                            $magnet .= "&tr=" . urlencode($tracker);
                        }
                        
                        $results[] = [
                            'title' => $torrent['title'] ?? $title,
                            'quality' => extractQuality($torrent['title'] ?? ''),
                            'size' => formatBytes($torrent['size'] ?? 0),
                            'seeds' => (int)($torrent['seeders'] ?? 0),
                            'peers' => (int)($torrent['leechers'] ?? 0),
                            'magnet' => $magnet,
                            'source' => 'RARBG'
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silenciar errores pero loguear para debugging
        error_log('Error en RARBG: ' . $e->getMessage());
    }
    
    return $results;
}

/**
 * Buscar en LimeTorrents usando múltiples mirrors
 */
function searchLimeTorrents($title, $type = 'movie') {
    $results = [];
    
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
    
    $query = urlencode($title);
    $category = $type === 'movie' ? 'Movies' : 'TV';
    
    // Intentar múltiples mirrors de LimeTorrents
    $urls = [
        "https://www.limetorrents.lol/search/{$category}/{$query}/seeds/1/",
        "https://limetorrents.pro/search/{$category}/{$query}/seeds/1/",
    ];
    
    foreach ($urls as $url) {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Referer: https://www.limetorrents.lol/'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response && strlen($response) > 1000) {
                // Extraer magnet links del HTML usando regex mejorado
                if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})([^"\'<>\s]*)/i', $response, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $magnet = $match[0];
                        // Extraer título del contexto HTML
                        $contextStart = max(0, strpos($response, $magnet) - 500);
                        $context = substr($response, $contextStart, 1000);
                        preg_match('/<a[^>]*>([^<]*' . preg_quote($title, '/') . '[^<]*)</i', $context, $titleMatch);
                        $torrentTitle = $titleMatch[1] ?? $title;
                        
                        // Agregar trackers si no están
                        foreach ($trackers as $tracker) {
                            if (strpos($magnet, urlencode($tracker)) === false && strpos($magnet, $tracker) === false) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                        
                        $results[] = [
                            'title' => trim(html_entity_decode($torrentTitle, ENT_QUOTES, 'UTF-8')),
                            'quality' => extractQuality($torrentTitle),
                            'size' => 'Unknown',
                            'seeds' => 0,
                            'peers' => 0,
                            'magnet' => $magnet,
                            'source' => 'LimeTorrents'
                        ];
                    }
                    if (count($results) > 0) break; // Si encontramos resultados, no intentar más URLs
                }
            }
        } catch (Exception $e) {
            // Continuar con siguiente URL
            continue;
        }
    }
    
    return $results;
}

/**
 * Buscar en Torlock usando búsqueda mejorada
 */
function searchTorlock($title, $type = 'movie') {
    $results = [];
    
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
        $query = urlencode(strtolower($title));
        $category = $type === 'movie' ? 'movies' : 'tv';
        // Usar búsqueda por query en lugar de URL directa
        $url = "https://www.torlock.com/all/torrents/{$query}.html?sort=seeds";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response && strlen($response) > 1000) {
            // Extraer magnet links del HTML con mejor regex
            if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})([^"\'<>\s]*)/i', $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $magnet = $match[0];
                    // Extraer título del contexto
                    $contextStart = max(0, strpos($response, $magnet) - 300);
                    $context = substr($response, $contextStart, 600);
                    preg_match('/<a[^>]*>([^<]*' . preg_quote($title, '/') . '[^<]*)</i', $context, $titleMatch);
                    $torrentTitle = $titleMatch[1] ?? $title;
                    
                    // Agregar trackers
                    foreach ($trackers as $tracker) {
                        if (strpos($magnet, urlencode($tracker)) === false && strpos($magnet, $tracker) === false) {
                            $magnet .= '&tr=' . urlencode($tracker);
                        }
                    }
                    
                    $results[] = [
                        'title' => trim(html_entity_decode($torrentTitle, ENT_QUOTES, 'UTF-8')),
                        'quality' => extractQuality($torrentTitle),
                        'size' => 'Unknown',
                        'seeds' => 0,
                        'peers' => 0,
                        'magnet' => $magnet,
                        'source' => 'Torlock'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return $results;
}

/**
 * Buscar en TorrentGalaxy usando scraping mejorado
 */
function searchTorrentGalaxy($title, $type = 'movie') {
    $results = [];
    
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
        $category = $type === 'movie' ? '4' : '41'; // 4 = Movies, 41 = TV
        $url = "https://torrentgalaxy.to/torrents.php?search={$query}&cat={$category}&sort=seeders&order=desc";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Referer: https://torrentgalaxy.to/'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response && strlen($response) > 1000) {
            // Extraer magnet links con mejor regex
            if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})([^"\'<>\s]*)/i', $response, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $magnet = $match[0];
                    // Extraer título y seeds del contexto HTML
                    $contextStart = max(0, strpos($response, $magnet) - 500);
                    $context = substr($response, $contextStart, 1000);
                    
                    // Buscar título
                    preg_match('/<a[^>]*>([^<]*' . preg_quote($title, '/') . '[^<]*)</i', $context, $titleMatch);
                    $torrentTitle = $titleMatch[1] ?? $title;
                    
                    // Buscar seeds si están disponibles
                    preg_match('/<font[^>]*>(\d+)<\/font>/i', $context, $seedsMatch);
                    $seeds = isset($seedsMatch[1]) ? (int)$seedsMatch[1] : 0;
                    
                    // Agregar trackers
                    foreach ($trackers as $tracker) {
                        if (strpos($magnet, urlencode($tracker)) === false && strpos($magnet, $tracker) === false) {
                            $magnet .= '&tr=' . urlencode($tracker);
                        }
                    }
                    
                    $results[] = [
                        'title' => trim(html_entity_decode($torrentTitle, ENT_QUOTES, 'UTF-8')),
                        'quality' => extractQuality($torrentTitle),
                        'size' => 'Unknown',
                        'seeds' => $seeds,
                        'peers' => 0,
                        'magnet' => $magnet,
                        'source' => 'TorrentGalaxy'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
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

/**
 * Buscar torrents usando Jackett API
 * Jackett es un servidor proxy que agrega múltiples indexadores de torrents
 * 
 * @param string $title Título a buscar
 * @param string $type Tipo: 'movie' o 'series'
 * @param string $year Año (opcional)
 * @param string $jackettUrl URL base de Jackett (ej: http://localhost:9117)
 * @param string $apiKey API key de Jackett
 * @return array Array de resultados de torrents
 * 
 * Referencia: https://www.rapidseedbox.com/blog/guide-to-jackett
 */
function searchJackett($title, $type = 'movie', $year = '', $jackettUrl = '', $apiKey = '') {
    $results = [];
    
    if (empty($jackettUrl) || empty($apiKey)) {
        return $results;
    }
    
    try {
        // Normalizar URL de Jackett (eliminar trailing slash)
        $jackettUrl = rtrim($jackettUrl, '/');
        
        // Construir query para Jackett
        // Jackett usa formato: /api/v2.0/indexers/{indexer}/results?Query={query}
        // O podemos usar /api/v2.0/indexers/all/results para buscar en todos los indexadores
        $query = $title;
        if (!empty($year) && $type === 'movie') {
            $query .= ' ' . $year;
        }
        
        // Categorías de Jackett:
        // 2000 = Movies, 5000 = TV Shows
        $category = ($type === 'movie') ? '2000' : '5000';
        
        // URL de búsqueda en todos los indexadores
        $searchUrl = $jackettUrl . '/api/v2.0/indexers/all/results?apikey=' . urlencode($apiKey) . 
                     '&Query=' . urlencode($query) . 
                     '&Category[]=' . $category;
        
        // Configurar contexto HTTP
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                           "Accept: application/json\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ]);
        
        // Realizar búsqueda
        $response = @file_get_contents($searchUrl, false, $context);
        
        if ($response === false) {
            error_log("Jackett: No se pudo conectar a {$jackettUrl}");
            return $results;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['Results']) || !is_array($data['Results'])) {
            return $results;
        }
        
        // Procesar resultados
        foreach ($data['Results'] as $item) {
            // Jackett devuelve resultados con esta estructura:
            // {
            //   "FirstSeen": "2024-01-01T00:00:00Z",
            //   "Tracker": "ThePirateBay",
            //   "TrackerId": "thepiratebay",
            //   "CategoryDesc": "Movies",
            //   "BlackholeLink": null,
            //   "MagnetUri": "magnet:?xt=urn:btih:...",
            //   "Title": "Movie Title 2024 1080p",
            //   "Guid": "...",
            //   "Link": "https://...",
            //   "Details": "https://...",
            //   "PublishDate": "2024-01-01T00:00:00Z",
            //   "Category": [2000],
            //   "Size": 1234567890,
            //   "Files": null,
            //   "Grabs": 100,
            //   "Description": "...",
            //   "RageID": null,
            //   "TVDBId": null,
            //   "Imdb": null,
            //   "TMDb": null,
            //   "Seeders": 50,
            //   "Peers": 75,
            //   ...
            // }
            
            $magnet = $item['MagnetUri'] ?? $item['Link'] ?? null;
            
            // Compatibilidad con PHP < 8.0
            $isMagnet = !empty($magnet) && (function_exists('str_starts_with') ? str_starts_with($magnet, 'magnet:') : strpos($magnet, 'magnet:') === 0);
            
            if (empty($magnet) || !$isMagnet) {
                // Si no hay magnet, intentar construir uno desde el Link
                if (isset($item['Link']) && preg_match('/btih:([a-f0-9]{40})/i', $item['Link'], $matches)) {
                    $infoHash = $matches[1];
                    $titleEncoded = urlencode($item['Title'] ?? $title);
                    $magnet = "magnet:?xt=urn:btih:{$infoHash}&dn={$titleEncoded}";
                } else {
                    continue; // Saltar si no hay magnet válido
                }
            }
            
            // Extraer calidad del título
            $quality = extractQuality($item['Title'] ?? $title);
            
            // Tamaño en bytes
            $size = $item['Size'] ?? 0;
            
            // Seeds y peers
            $seeds = $item['Seeders'] ?? $item['Grabs'] ?? 0;
            $peers = $item['Peers'] ?? 0;
            
            // Tracker/source
            $tracker = $item['Tracker'] ?? 'Jackett';
            $trackerId = $item['TrackerId'] ?? 'unknown';
            
            $results[] = [
                'title' => $item['Title'] ?? $title,
                'magnet' => $magnet,
                'url' => $item['Link'] ?? null,
                'source' => $tracker,
                'tracker_id' => $trackerId,
                'quality' => $quality,
                'seeds' => (int)$seeds,
                'peers' => (int)$peers,
                'size' => $size > 0 ? formatBytes($size) : null,
                'size_bytes' => $size,
                'date' => $item['PublishDate'] ?? null,
                'category' => $item['CategoryDesc'] ?? ($type === 'movie' ? 'Movies' : 'TV Shows'),
                'details' => $item['Details'] ?? null,
                'imdb' => $item['Imdb'] ?? null,
                'tmdb' => $item['TMDb'] ?? null
            ];
        }
        
        // Ordenar por seeds (mayor a menor)
        usort($results, function($a, $b) {
            return ($b['seeds'] ?? 0) - ($a['seeds'] ?? 0);
        });
        
    } catch (Exception $e) {
        error_log("Error en búsqueda Jackett: " . $e->getMessage());
    }
    
    return $results;
}

/**
 * Aplicar filtros adicionales a los resultados
 */
function applyFilters($results, $quality, $min_seeds, $sources) {
    $filtered = [];
    $allowedSources = [];
    
    if (!empty($sources)) {
        $allowedSources = array_map('trim', explode(',', strtolower($sources)));
    }
    
    foreach ($results as $result) {
        // Filtro por calidad
        if (!empty($quality) && strtolower($result['quality']) !== strtolower($quality)) {
            continue;
        }
        
        // Filtro por mínimo seeds
        if ($min_seeds > 0 && ($result['seeds'] ?? 0) < $min_seeds) {
            continue;
        }
        
        // Filtro por fuentes
        if (!empty($allowedSources) && !in_array(strtolower($result['source']), $allowedSources)) {
            continue;
        }
        
        $filtered[] = $result;
    }
    
    return $filtered;
}
?>

