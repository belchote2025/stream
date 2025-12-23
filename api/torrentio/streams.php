<?php
/**
 * Stremio streams endpoint para Torrentio proxy interno.
 * URL esperada por Stremio (manifest apunta aquí):
 *   /api/torrentio/streams.php?type=movie|series&id=tt1234567&title=Some+Title&year=2024
 *
 * Notas:
 * - Se requiere al menos 'title' o 'id' para buscar; si solo hay id y es tipo tt, se usa como término.
 * - No se exige autenticación (Stremio necesita acceso público).
 * - Usa Torrentio como fuente principal y YTS/EZTV/TPB como respaldo.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../../includes/config.php';

// --- Inputs ---
$type = $_GET['type'] ?? 'movie'; // movie | series
$id = $_GET['id'] ?? '';
$title = trim($_GET['title'] ?? '');
$year = trim($_GET['year'] ?? '');

// Normalizar tipo
$type = ($type === 'series' || $type === 'tv') ? 'series' : 'movie';

// Si no hay título, intentar derivar algo del id
if ($title === '' && $id !== '') {
    $title = $id; // fallback; idealmente pasar title desde el cliente
}

if ($title === '') {
    echo json_encode(['streams' => []]);
    exit;
}

// --- Funciones reutilizadas (simplificadas, sin requireAdmin) ---
function searchTorrentioLite($title, $type = 'movie', $year = '')
{
    $results = [];
    $baseUrl = getenv('TORRENTIO_BASE_URL') ?: 'https://torrentio.strem.fun';

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
                        if (!empty($stream['sources']) && is_array($stream['sources'])) {
                            foreach ($stream['sources'] as $tracker) {
                                $magnet .= '&tr=' . urlencode($tracker);
                            }
                        }
                    } elseif (!empty($stream['url']) && strpos($stream['url'], 'magnet:?') === 0) {
                        $magnet = $stream['url'];
                    }

                    if (empty($magnet)) {
                        continue;
                    }

                    $titleText = $stream['title'] ?? $stream['name'] ?? $title;
                    $results[] = [
                        'title' => $titleText,
                        'quality' => $stream['quality'] ?? extractQualityLite($titleText),
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

function searchYTSLite($title, $year = '')
{
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
                            $hash = $torrent['hash'] ?? '';
                            if (!empty($hash)) {
                                $magnet = "magnet:?xt=urn:btih:" . $hash . "&dn=" . urlencode($movie['title']);
                                $results[] = [
                                    'title' => $movie['title'] . ' (' . $movie['year'] . ')',
                                    'quality' => $torrent['quality'] ?? 'Unknown',
                                    'seeds' => $torrent['seeds'] ?? 0,
                                    'peers' => $torrent['peers'] ?? 0,
                                    'magnet' => $magnet,
                                    'source' => 'YTS'
                                ];
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en YTS: ' . $e->getMessage());
    }
    return $results;
}

function searchEZTVLite($title)
{
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
                    if (!$magnet) {
                        continue;
                    }
                    $results[] = [
                        'title' => $torrent['title'] ?? $title,
                        'quality' => 'HD',
                        'seeds' => $torrent['seeds'] ?? 0,
                        'peers' => $torrent['peers'] ?? 0,
                        'magnet' => $magnet,
                        'source' => 'EZTV'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en EZTV: ' . $e->getMessage());
    }
    return $results;
}

function searchTPBLite($title, $type = 'movie')
{
    $results = [];
    try {
        $query = urlencode($title);
        $url = "https://apibay.org/q.php?q={$query}&cat=201";
        $data = @file_get_contents($url);
        if ($data) {
            $items = json_decode($data, true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (empty($item['info_hash'])) {
                        continue;
                    }
                    $magnet = "magnet:?xt=urn:btih:{$item['info_hash']}&dn=" . urlencode($item['name'] ?? $title);
                    $results[] = [
                        'title' => $item['name'] ?? $title,
                        'quality' => null,
                        'seeds' => isset($item['seeders']) ? (int)$item['seeders'] : 0,
                        'peers' => isset($item['leechers']) ? (int)$item['leechers'] : 0,
                        'magnet' => $magnet,
                        'source' => 'TPB'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Error en TPB: ' . $e->getMessage());
    }
    return $results;
}

function extractQualityLite($text)
{
    if (preg_match('/\\b(2160p|1080p|720p|480p)\\b/i', $text, $m)) {
        return strtoupper($m[1]);
    }
    return 'HD';
}

function removeDuplicatesLite($results)
{
    $seen = [];
    $out = [];
    foreach ($results as $r) {
        $key = $r['magnet'] ?? '';
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $r;
    }
    return $out;
}

function sortByQualityLite($results)
{
    usort($results, function ($a, $b) {
        $qa = $a['seeds'] ?? 0;
        $qb = $b['seeds'] ?? 0;
        return $qb <=> $qa;
    });
    return $results;
}

// --- Búsqueda combinada ---
$results = [];
$results = array_merge(
    $results,
    searchTorrentioLite($title, $type, $year),
    $type === 'movie' ? searchYTSLite($title, $year) : searchEZTVLite($title),
    searchTPBLite($title, $type)
);

$results = removeDuplicatesLite($results);
$results = sortByQualityLite($results);
$results = array_slice($results, 0, 10);

// Formato Stremio
$streams = [];
foreach ($results as $r) {
    $streams[] = [
        'title' => ($r['source'] ?? 'Torrent') . ' | ' . ($r['quality'] ?? 'HD') . ' | Seeds: ' . ($r['seeds'] ?? 0),
        'url' => $r['magnet'],
        'availability' => 'online'
    ];
}

echo json_encode(['streams' => $streams], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);











