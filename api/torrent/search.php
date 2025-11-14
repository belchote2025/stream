<?php
/**
 * API para buscar enlaces de torrents de películas y series
 * Utiliza APIs públicas legítimas para obtener información de torrents
 */
require_once __DIR__ . '/../../includes/config.php';
requireAdmin(); // Solo administradores pueden buscar torrents

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
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
    
    // Opción 1: YTS API (solo películas, API pública y legal)
    if ($type === 'movie') {
        $ytsResults = searchYTS($title, $year);
        if (!empty($ytsResults)) {
            $results = array_merge($results, $ytsResults);
        }
    }
    
    // Opción 2: EZTV API (solo series, API pública)
    if ($type === 'series') {
        $eztvResults = searchEZTV($title);
        if (!empty($eztvResults)) {
            $results = array_merge($results, $eztvResults);
        }
    }
    
    // Opción 3: The Pirate Bay API (búsqueda general)
    // Nota: TPB puede no estar siempre disponible, usar con precaución
    $tpbResults = searchTPB($title, $type);
    if (!empty($tpbResults)) {
        $results = array_merge($results, $tpbResults);
    }
    
    // Eliminar duplicados y ordenar por calidad
    $results = removeDuplicates($results);
    $results = sortByQuality($results);
    
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'results' => array_slice($results, 0, 10) // Limitar a 10 resultados
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar torrents: ' . $e->getMessage()
    ]);
}

/**
 * Buscar en YTS API (solo películas)
 */
function searchYTS($title, $year = '') {
    $results = [];
    
    try {
        $query = urlencode($title);
        $url = "https://yts.mx/api/v2/list_movies.json?query_term={$query}&sort_by=download_count&order_by=desc";
        
        if (!empty($year)) {
            $url .= "&year={$year}";
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['data']['movies']) && is_array($data['data']['movies'])) {
                foreach ($data['data']['movies'] as $movie) {
                    if (isset($movie['torrents']) && is_array($movie['torrents'])) {
                        foreach ($movie['torrents'] as $torrent) {
                            // Construir magnet link
                            $hash = $torrent['hash'] ?? '';
                            if (!empty($hash)) {
                                $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($movie['title']);
                                
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
    
    try {
        $query = urlencode($title);
        $url = "https://eztv.re/api/get-torrents?imdb_id=&limit=10&page=1&keywords={$query}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if (isset($data['torrents']) && is_array($data['torrents'])) {
                foreach ($data['torrents'] as $torrent) {
                    $magnet = $torrent['magnet_url'] ?? '';
                    if (!empty($magnet)) {
                        $results[] = [
                            'title' => $torrent['title'] ?? 'Unknown',
                            'quality' => 'HD', // EZTV generalmente es HD
                            'size' => $torrent['size_bytes'] ?? 'Unknown',
                            'seeds' => $torrent['seeds'] ?? 0,
                            'peers' => $torrent['peers'] ?? 0,
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
            // Agregar más proxies si es necesario
        ];
        
        foreach ($proxies as $proxyUrl) {
            $ch = curl_init($proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (is_array($data)) {
                    foreach ($data as $torrent) {
                        if (isset($torrent['info_hash'])) {
                            $hash = $torrent['info_hash'];
                            $name = $torrent['name'] ?? 'Unknown';
                            $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($name);
                            
                            $results[] = [
                                'title' => $name,
                                'quality' => extractQuality($name),
                                'size' => formatBytes($torrent['size'] ?? 0),
                                'seeds' => (int)($torrent['seeders'] ?? 0),
                                'peers' => (int)($torrent['leechers'] ?? 0),
                                'magnet' => $magnet,
                                'source' => 'TPB'
                            ];
                        }
                    }
                    break; // Si encontramos resultados, no intentar más proxies
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

