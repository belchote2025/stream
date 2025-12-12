<?php
/**
 * CLI: Ingresa/actualiza novedades (películas y series) con prioridad torrents.
 * Usa múltiples fuentes: Trakt.tv y TVMaze (ambas gratuitas).
 *
 * Uso:
 *   php scripts/fetch-new-content.php --type=movie --limit=30 --since-days=7 --min-seeds=10
 *   php scripts/fetch-new-content.php --type=tv --limit=30 --since-days=7 --min-seeds=10
 *
 * Fuentes:
 *   - Trakt.tv (gratuita, requiere TRAKT_CLIENT_ID) - fuente principal
 *   - TVMaze (gratuita, sin API key) - fuente secundaria
 *   - OMDB_API_KEY (opcional) para completar metadatos
 *   - TORRENTIO_BASE_URL (opcional)
 *
 * Para obtener TRAKT_CLIENT_ID:
 *   1. Ve a https://trakt.tv/oauth/applications
 *   2. Crea una nueva aplicación (gratis)
 *   3. Copia el Client ID
 *   4. Configúralo como variable de entorno o en includes/config.php
 *
 * Efectos:
 *   - Inserta/actualiza filas en content (movie/series)
 *   - Inserta/actualiza episodios para series (episodes)
 *   - Asocia géneros (content_genres)
 *   - Guarda magnet en content.torrent_magnet o episodes.video_url
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/imdb-helper.php';

// -------------------- Configuración CLI --------------------
$options = getopt('', [
    'type::',         // movie | tv
    'limit::',        // int
    'since-days::',   // int
    'min-seeds::',    // int
    'dry-run::'       // bool
]);

$type = ($options['type'] ?? 'movie') === 'series' ? 'tv' : ($options['type'] ?? 'movie');
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 30;
$sinceDays = isset($options['since-days']) ? max(0, (int)$options['since-days']) : 7;
$minSeeds = isset($options['min-seeds']) ? max(0, (int)$options['min-seeds']) : 10;
$dryRun = filter_var($options['dry-run'] ?? false, FILTER_VALIDATE_BOOLEAN);

$omdbKey = getenv('OMDB_API_KEY') ?: (defined('OMDB_API_KEY') ? OMDB_API_KEY : '');
$torrentioBase = getenv('TORRENTIO_BASE_URL') ?: 'https://torrentio.strem.fun';
$traktClientId = getenv('TRAKT_CLIENT_ID') ?: (defined('TRAKT_CLIENT_ID') ? TRAKT_CLIENT_ID : '');

$db = getDbConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// -------------------- Helper principal --------------------
function httpJson(string $url, int $timeout = 10): ?array
{
    // Intentar primero con cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en-US,en;q=0.9'
            ]
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        } elseif ($error) {
            fwrite(STDERR, "Error cURL: {$error}\n");
        }
    }
    
    // Fallback a file_get_contents si cURL falla
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept: application/json\r\n",
            'timeout' => $timeout,
            'ignore_errors' => true
        ]
    ]);
    
    $resp = @file_get_contents($url, false, $context);
    if ($resp !== false) {
        $data = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }
    }
    
    return null;
}

function slugify(string $text): string
{
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text ?: 'item';
}

// -------------------- TVMaze (gratuita, sin API key) --------------------
function tvmazeSearch(string $query, string $type): array
{
    $results = [];
    $url = "https://api.tvmaze.com/search/shows?q=" . urlencode($query);
    fwrite(STDOUT, "Buscando en TVMaze: {$query}...\n");
    
    $data = httpJson($url, 15);
    if (!$data || !is_array($data)) {
        fwrite(STDOUT, "No se recibieron datos de TVMaze para: {$query}\n");
        return $results;
    }
    
    fwrite(STDOUT, "TVMaze devolvió " . count($data) . " resultados para: {$query}\n");
    
    foreach ($data as $item) {
        if (!isset($item['show'])) {
            continue;
        }
        $show = $item['show'];
        $showType = $show['type'] ?? '';
        // Filtrar por tipo si es necesario
        if ($type === 'tv' && $showType !== 'Scripted' && $showType !== 'Reality' && $showType !== 'Documentary' && $showType !== '') {
            continue;
        }
        if ($type === 'movie' && $showType !== 'Movie') {
            continue;
        }
        $results[] = $show;
        if (count($results) >= 50) {
            break;
        }
    }
    
    fwrite(STDOUT, "Filtrados " . count($results) . " resultados válidos para: {$query}\n");
    return $results;
}

function tvmazeGetShow(int $id): ?array
{
    $url = "https://api.tvmaze.com/shows/{$id}";
    return httpJson($url, 12);
}

function tvmazeGetEpisodes(int $showId): array
{
    $url = "https://api.tvmaze.com/shows/{$showId}/episodes";
    $data = httpJson($url, 15);
    return is_array($data) ? $data : [];
}

function tvmazeGetSchedule(string $country = 'US', string $date = null): array
{
    if (!$date) {
        $date = date('Y-m-d');
    }
    $url = "https://api.tvmaze.com/schedule?country={$country}&date={$date}";
    $data = httpJson($url, 12);
    return is_array($data) ? $data : [];
}

function tvmazeGetUpdates(): array
{
    $url = "https://api.tvmaze.com/updates/shows";
    $data = httpJson($url, 12);
    return is_array($data) ? $data : [];
}

// -------------------- Trakt.tv (gratuita, requiere client_id) --------------------
function traktHttpJson(string $url, string $clientId, int $timeout = 10): ?array
{
    if (empty($clientId)) {
        fwrite(STDERR, "ERROR: Client ID vacío para Trakt.tv\n");
        return null;
    }
    
    $headers = [
        'Content-Type: application/json',
        'trakt-api-version: 2',
        'trakt-api-key: ' . $clientId
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            } else {
                fwrite(STDERR, "ERROR Trakt.tv: JSON inválido - " . json_last_error_msg() . "\n");
            }
        } else {
            $errorMsg = $error ?: "HTTP $code";
            if ($code === 401) {
                fwrite(STDERR, "ERROR Trakt.tv: No autorizado (401). Verifica que el Client ID sea correcto.\n");
            } elseif ($code === 404) {
                fwrite(STDERR, "ERROR Trakt.tv: Endpoint no encontrado (404). URL: $url\n");
            } else {
                fwrite(STDERR, "ERROR Trakt.tv: $errorMsg. Respuesta: " . substr($resp, 0, 200) . "\n");
            }
        }
    } else {
        fwrite(STDERR, "ERROR: cURL no disponible\n");
    }
    return null;
}

function traktGetTrending(string $type, string $clientId, int $limit = 30): array
{
    if (empty($clientId)) {
        return [];
    }
    
    $results = [];
    $endpoint = $type === 'tv' ? 'shows' : 'movies';
    $url = "https://api.trakt.tv/{$endpoint}/trending?limit={$limit}";
    
    fwrite(STDOUT, "Buscando trending en Trakt.tv ({$type})...\n");
    $data = traktHttpJson($url, $clientId, 15);
    
    if (!empty($data) && is_array($data)) {
        fwrite(STDOUT, "Trakt.tv devolvió " . count($data) . " resultados\n");
        foreach ($data as $item) {
            $show = $item[$endpoint === 'shows' ? 'show' : 'movie'] ?? null;
            if ($show) {
                $results[] = [
                    'id' => $show['ids']['trakt'] ?? $show['ids']['tmdb'] ?? null,
                    'name' => $show['title'] ?? '',
                    'premiered' => $show['released'] ?? $show['first_aired'] ?? '',
                    'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                    'summary' => $show['overview'] ?? '',
                    'rating' => ['average' => $show['rating'] ?? 0],
                    'genres' => $show['genres'] ?? [],
                    'image' => [
                        'medium' => $show['poster'] ?? '',
                        'original' => $show['poster'] ?? ''
                    ],
                    'trakt_data' => $show // Guardar datos completos de Trakt
                ];
            }
        }
    }
    
    return $results;
}

function traktGetPopular(string $type, string $clientId, int $limit = 30): array
{
    if (empty($clientId)) {
        return [];
    }
    
    $results = [];
    $endpoint = $type === 'tv' ? 'shows' : 'movies';
    $url = "https://api.trakt.tv/{$endpoint}/popular?limit={$limit}";
    
    fwrite(STDOUT, "Buscando populares en Trakt.tv ({$type})...\n");
    $data = traktHttpJson($url, $clientId, 15);
    
    if (!empty($data) && is_array($data)) {
        fwrite(STDOUT, "Trakt.tv devolvió " . count($data) . " resultados populares\n");
        foreach ($data as $item) {
            $show = $item[$endpoint === 'shows' ? 'show' : 'movie'] ?? null;
            if ($show) {
                $results[] = [
                    'id' => $show['ids']['trakt'] ?? $show['ids']['tmdb'] ?? null,
                    'name' => $show['title'] ?? '',
                    'premiered' => $show['released'] ?? $show['first_aired'] ?? '',
                    'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                    'summary' => $show['overview'] ?? '',
                    'rating' => ['average' => $show['rating'] ?? 0],
                    'genres' => $show['genres'] ?? [],
                    'image' => [
                        'medium' => $show['poster'] ?? '',
                        'original' => $show['poster'] ?? ''
                    ],
                    'trakt_data' => $show
                ];
            }
        }
    }
    
    return $results;
}

// Obtener shows populares/actualizados recientes
function tvmazeGetRecentShows(int $limit, int $sinceDays, string $type): array
{
    $results = [];
    $cutoff = $sinceDays > 0 ? strtotime("-{$sinceDays} days") : 0;
    $seenIds = [];
    
    // Estrategia 1: Obtener shows actualizados recientemente
    if ($type === 'tv') {
        $updates = tvmazeGetUpdates();
        if (!empty($updates) && is_array($updates)) {
            // Ordenar por timestamp (más recientes primero)
            arsort($updates);
            $updateIds = array_slice(array_keys($updates), 0, min($limit * 2, 50));
            
            foreach ($updateIds as $showId) {
                if (count($results) >= $limit) {
                    break;
                }
                $show = tvmazeGetShow((int)$showId);
                if ($show && !isset($seenIds[$showId])) {
                    $seenIds[$showId] = true;
                    $premiered = $show['premiered'] ?? '';
                    if ($premiered) {
                        $premieredTs = strtotime($premiered);
                        if ($premieredTs >= $cutoff || $sinceDays === 0) {
                            $results[] = $show;
                        }
                    } else {
                        $results[] = $show;
                    }
                }
                usleep(100000); // 0.1 segundos entre peticiones
            }
        }
    }
    
    // Estrategia 2: Usar schedule del día (solo para series)
    if (count($results) < $limit && $type === 'tv') {
        $schedule = tvmazeGetSchedule('US', date('Y-m-d'));
        foreach ($schedule as $item) {
            if (count($results) >= $limit) {
                break;
            }
            $show = $item['show'] ?? null;
            if ($show && !isset($seenIds[$show['id'] ?? 0])) {
                $id = $show['id'] ?? 0;
                $seenIds[$id] = true;
                $results[] = $show;
            }
        }
    }
    
    // Estrategia 3: Buscar por términos específicos si aún no hay suficientes
    if (count($results) < $limit) {
        $currentYear = date('Y');
        $searchTerms = $type === 'tv' 
            ? [(string)$currentYear, (string)($currentYear - 1), 'new', 'latest']
            : [(string)$currentYear, (string)($currentYear - 1), 'movie'];
        
        foreach ($searchTerms as $term) {
            if (count($results) >= $limit) {
                break;
            }
            $shows = tvmazeSearch($term, $type);
            foreach ($shows as $show) {
                if (count($results) >= $limit) {
                    break 2;
                }
                $id = $show['id'] ?? 0;
                if ($id && !isset($seenIds[$id])) {
                    $seenIds[$id] = true;
                    $premiered = $show['premiered'] ?? '';
                    if ($premiered) {
                        $premieredTs = strtotime($premiered);
                        if ($premieredTs >= $cutoff || $sinceDays === 0) {
                            $results[] = $show;
                        }
                    } else {
                        $results[] = $show;
                    }
                }
            }
            usleep(200000); // 0.2 segundos
        }
    }
    
    // Estrategia 4: Obtener shows populares directamente (paginación)
    if (count($results) < $limit) {
        $page = 0;
        while (count($results) < $limit && $page < 5) {
            $url = "https://api.tvmaze.com/shows?page={$page}";
            $shows = httpJson($url, 12);
            if (empty($shows) || !is_array($shows)) {
                break;
            }
            foreach ($shows as $show) {
                if (count($results) >= $limit) {
                    break 2;
                }
                $id = $show['id'] ?? 0;
                if ($id && !isset($seenIds[$id])) {
                    $showType = $show['type'] ?? '';
                    if ($type === 'tv' && ($showType === 'Scripted' || $showType === 'Reality' || $showType === 'Documentary')) {
                        $seenIds[$id] = true;
                        $premiered = $show['premiered'] ?? '';
                        if ($premiered) {
                            $premieredTs = strtotime($premiered);
                            if ($premieredTs >= $cutoff || $sinceDays === 0) {
                                $results[] = $show;
                            }
                        } else {
                            $results[] = $show;
                        }
                    } elseif ($type === 'movie' && $showType === 'Movie') {
                        $seenIds[$id] = true;
                        $premiered = $show['premiered'] ?? '';
                        if ($premiered) {
                            $premieredTs = strtotime($premiered);
                            if ($premieredTs >= $cutoff || $sinceDays === 0) {
                                $results[] = $show;
                            }
                        } else {
                            $results[] = $show;
                        }
                    }
                }
            }
            $page++;
            usleep(200000);
        }
    }
    
    return array_slice($results, 0, $limit);
}

// -------------------- OMDB (opcional) --------------------
function omdbFetch(string $title, ?string $year, string $type, string $apiKey): ?array
{
    if (empty($apiKey) || $apiKey === 'demo') {
        return null;
    }
    $query = urlencode($title);
    $url = "http://www.omdbapi.com/?apikey={$apiKey}&t={$query}&type=" . ($type === 'tv' ? 'series' : 'movie');
    if (!empty($year)) {
        $url .= "&y={$year}";
    }
    $data = httpJson($url, 8);
    if ($data && ($data['Response'] ?? '') === 'True') {
        return $data;
    }
    return null;
}

// -------------------- Torrents --------------------
function searchTorrentio(string $title, string $type, ?string $year, string $baseUrl): array
{
    $results = [];
    $queryTerm = trim($title . ' ' . $year);
    $query = urlencode($queryTerm);
    $catalog = $type === 'tv' ? 'series/all' : 'movie/all';
    $url = rtrim($baseUrl, '/') . "/{$catalog}/search={$query}.json?sort=seeds";
    $data = httpJson($url, 12);
    if (!$data || empty($data['streams'])) {
        return [];
    }
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
        $results[] = [
            'title' => $stream['title'] ?? $title,
            'quality' => $stream['quality'] ?? null,
            'seeds' => $stream['seeders'] ?? $stream['seeds'] ?? 0,
            'peers' => $stream['peers'] ?? 0,
            'magnet' => $magnet,
            'size' => $stream['size'] ?? $stream['fileSize'] ?? null,
            'source' => 'Torrentio',
            'season' => $stream['season'] ?? null,
            'episode' => $stream['episode'] ?? null
        ];
    }
    return $results;
}

function searchYTS(string $title, ?string $year): array
{
    $results = [];
    $query = urlencode(trim($title . ' ' . $year));
    $url = "https://yts.mx/api/v2/list_movies.json?query_term={$query}";
    $data = httpJson($url, 12);
    if (!$data || empty($data['data']['movies'])) {
        return [];
    }
    foreach ($data['data']['movies'] as $movie) {
        if (empty($movie['torrents']) || !is_array($movie['torrents'])) {
            continue;
        }
        foreach ($movie['torrents'] as $torrent) {
            $hash = $torrent['hash'] ?? '';
            if (!$hash) {
                continue;
            }
            $magnet = "magnet:?xt=urn:btih:{$hash}&dn=" . urlencode($movie['title']);
            $results[] = [
                'title' => $movie['title'] . ' (' . ($movie['year'] ?? '') . ')',
                'quality' => $torrent['quality'] ?? null,
                'size' => $torrent['size'] ?? null,
                'seeds' => $torrent['seeds'] ?? 0,
                'peers' => $torrent['peers'] ?? 0,
                'magnet' => $magnet,
                'source' => 'YTS'
            ];
        }
    }
    return $results;
}

function searchEZTV(string $title): array
{
    $results = [];
    $query = urlencode($title);
    $url = "https://eztv.re/api/get-torrents?imdb_id=&limit=20&page=1&keywords={$query}";
    $data = httpJson($url, 12);
    if (!$data || empty($data['torrents'])) {
        return [];
    }
    foreach ($data['torrents'] as $torrent) {
        $magnet = $torrent['magnet_url'] ?? '';
        if (!$magnet) {
            continue;
        }
        $results[] = [
            'title' => $torrent['title'] ?? $title,
            'quality' => 'HD',
            'size' => $torrent['size_bytes'] ?? null,
            'seeds' => $torrent['seeds'] ?? 0,
            'peers' => $torrent['peers'] ?? 0,
            'magnet' => $magnet,
            'source' => 'EZTV',
            'episode' => $torrent['episode'] ?? null,
            'season' => $torrent['season'] ?? null
        ];
    }
    return $results;
}

function searchTPB(string $title): array
{
    $results = [];
    $query = urlencode($title);
    $url = "https://apibay.org/q.php?q={$query}&cat=201";
    $data = httpJson($url, 12);
    if (!$data || !is_array($data)) {
        return [];
    }
    foreach ($data as $item) {
        if (empty($item['info_hash'])) {
            continue;
        }
        $magnet = "magnet:?xt=urn:btih:{$item['info_hash']}&dn=" . urlencode($item['name'] ?? $title);
        $results[] = [
            'title' => $item['name'] ?? $title,
            'quality' => null,
            'size' => $item['size'] ?? null,
            'seeds' => isset($item['seeders']) ? (int)$item['seeders'] : 0,
            'peers' => isset($item['leechers']) ? (int)$item['leechers'] : 0,
            'magnet' => $magnet,
            'source' => 'TPB'
        ];
    }
    return $results;
}

function pickBestTorrent(array $results, int $minSeeds): ?array
{
    $filtered = array_filter($results, function ($r) use ($minSeeds) {
        return ($r['seeds'] ?? 0) >= $minSeeds;
    });
    if (empty($filtered) && !empty($results)) {
        $filtered = $results;
    }
    usort($filtered, function ($a, $b) {
        $seedsA = $a['seeds'] ?? 0;
        $seedsB = $b['seeds'] ?? 0;
        if ($seedsA === $seedsB) {
            return strcasecmp((string)($b['quality'] ?? ''), (string)($a['quality'] ?? ''));
        }
        return $seedsB <=> $seedsA;
    });
    return $filtered[0] ?? null;
}

// -------------------- Géneros --------------------
function ensureGenres(PDO $db, array $names): array
{
    $map = [];
    foreach ($names as $name) {
        if (empty($name)) {
            continue;
        }
        $slug = slugify($name);
        $stmt = $db->prepare("SELECT id FROM genres WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            $ins = $db->prepare("INSERT INTO genres (name, slug, created_at, updated_at) VALUES (:name, :slug, NOW(), NOW())");
            $ins->execute([':name' => $name, ':slug' => $slug]);
            $id = $db->lastInsertId();
        }
        $map[$name] = (int)$id;
    }
    return $map;
}

function setContentGenres(PDO $db, int $contentId, array $genreIds): void
{
    $stmtDel = $db->prepare("DELETE FROM content_genres WHERE content_id = :id");
    $stmtDel->execute([':id' => $contentId]);
    if (empty($genreIds)) {
        return;
    }
    $stmtIns = $db->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (:cid, :gid)");
    foreach ($genreIds as $gid) {
        $stmtIns->execute([':cid' => $contentId, ':gid' => $gid]);
    }
}

// -------------------- Upsert contenido --------------------
function findContent(PDO $db, string $slug): ?array
{
    $stmt = $db->prepare("SELECT * FROM content WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function upsertContent(PDO $db, array $data): int
{
    $existing = findContent($db, $data['slug']);
    if ($existing) {
        $stmt = $db->prepare("
            UPDATE content SET 
                description = COALESCE(NULLIF(:description, ''), description),
                release_year = COALESCE(:release_year, release_year),
                duration = COALESCE(:duration, duration),
                rating = COALESCE(:rating, rating),
                age_rating = COALESCE(NULLIF(:age_rating, ''), age_rating),
                poster_url = CASE WHEN poster_url IS NULL OR poster_url = '' THEN :poster_url ELSE poster_url END,
                backdrop_url = CASE WHEN backdrop_url IS NULL OR backdrop_url = '' THEN :backdrop_url ELSE backdrop_url END,
                trailer_url = CASE WHEN trailer_url IS NULL OR trailer_url = '' THEN :trailer_url ELSE trailer_url END,
                video_url = CASE WHEN video_url IS NULL OR video_url = '' THEN :video_url ELSE video_url END,
                torrent_magnet = CASE WHEN torrent_magnet IS NULL OR torrent_magnet = '' THEN :torrent_magnet ELSE torrent_magnet END,
                is_featured = GREATEST(is_featured, :is_featured),
                is_trending = GREATEST(is_trending, :is_trending),
                is_premium = GREATEST(is_premium, :is_premium),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':description' => $data['description'],
            ':release_year' => $data['release_year'],
            ':duration' => $data['duration'],
            ':rating' => $data['rating'],
            ':age_rating' => $data['age_rating'],
            ':poster_url' => $data['poster_url'],
            ':backdrop_url' => $data['backdrop_url'],
            ':trailer_url' => $data['trailer_url'],
            ':video_url' => $data['video_url'],
            ':torrent_magnet' => $data['torrent_magnet'],
            ':is_featured' => $data['is_featured'],
            ':is_trending' => $data['is_trending'],
            ':is_premium' => $data['is_premium'],
            ':id' => $existing['id']
        ]);
        return (int)$existing['id'];
    }

    $stmt = $db->prepare("
        INSERT INTO content (
            title, slug, type, description, release_year, duration, rating, age_rating,
            poster_url, backdrop_url, trailer_url, video_url, torrent_magnet,
            is_featured, is_trending, is_premium, views, created_at, updated_at
        ) VALUES (
            :title, :slug, :type, :description, :release_year, :duration, :rating, :age_rating,
            :poster_url, :backdrop_url, :trailer_url, :video_url, :torrent_magnet,
            :is_featured, :is_trending, :is_premium, 0, NOW(), NOW()
        )
    ");
    $stmt->execute([
        ':title' => $data['title'],
        ':slug' => $data['slug'],
        ':type' => $data['type'],
        ':description' => $data['description'],
        ':release_year' => $data['release_year'],
        ':duration' => $data['duration'],
        ':rating' => $data['rating'],
        ':age_rating' => $data['age_rating'],
        ':poster_url' => $data['poster_url'],
        ':backdrop_url' => $data['backdrop_url'],
        ':trailer_url' => $data['trailer_url'],
        ':video_url' => $data['video_url'],
        ':torrent_magnet' => $data['torrent_magnet'],
        ':is_featured' => $data['is_featured'],
        ':is_trending' => $data['is_trending'],
        ':is_premium' => $data['is_premium']
    ]);
    return (int)$db->lastInsertId();
}

// -------------------- Episodios --------------------
function upsertEpisode(PDO $db, int $seriesId, int $seasonNumber, int $episodeNumber, array $data): int
{
    $stmt = $db->prepare("
        SELECT id, video_url FROM episodes 
        WHERE series_id = :sid AND season_number = :sn AND episode_number = :en
        LIMIT 1
    ");
    $stmt->execute([':sid' => $seriesId, ':sn' => $seasonNumber, ':en' => $episodeNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $stmtU = $db->prepare("
            UPDATE episodes SET
                title = COALESCE(NULLIF(:title, ''), title),
                description = COALESCE(NULLIF(:description, ''), description),
                duration = COALESCE(:duration, duration),
                video_url = CASE WHEN video_url IS NULL OR video_url = '' THEN :video_url ELSE video_url END,
                thumbnail_url = CASE WHEN thumbnail_url IS NULL OR thumbnail_url = '' THEN :thumbnail_url ELSE thumbnail_url END,
                release_date = COALESCE(:release_date, release_date),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmtU->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':duration' => $data['duration'],
            ':video_url' => $data['video_url'],
            ':thumbnail_url' => $data['thumbnail_url'],
            ':release_date' => $data['release_date'],
            ':id' => $row['id']
        ]);
        return (int)$row['id'];
    }

    $stmtI = $db->prepare("
        INSERT INTO episodes (
            series_id, season_number, episode_number, title, description,
            duration, video_url, thumbnail_url, release_date, views, created_at, updated_at
        ) VALUES (
            :sid, :sn, :en, :title, :description,
            :duration, :video_url, :thumbnail_url, :release_date, 0, NOW(), NOW()
        )
    ");
    $stmtI->execute([
        ':sid' => $seriesId,
        ':sn' => $seasonNumber,
        ':en' => $episodeNumber,
        ':title' => $data['title'],
        ':description' => $data['description'],
        ':duration' => $data['duration'],
        ':video_url' => $data['video_url'],
        ':thumbnail_url' => $data['thumbnail_url'],
        ':release_date' => $data['release_date']
    ]);
    return (int)$db->lastInsertId();
}

// -------------------- Notificación simple --------------------
function notifyAdmin(PDO $db, string $title, string $message, ?int $contentId = null): void
{
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, related_content_id, created_at) VALUES (1, :title, :message, 'new_content', 0, :cid, NOW())");
    try {
        $stmt->execute([':title' => $title, ':message' => $message, ':cid' => $contentId]);
    } catch (Exception $e) {
        // Ignorar si falla
    }
}

// -------------------- Proceso principal --------------------
fwrite(STDOUT, "Buscando novedades desde múltiples fuentes (TVMaze, Trakt.tv)...\n");
fwrite(STDOUT, "Parámetros: tipo={$type}, límite={$limit}, días={$sinceDays}, min_seeds={$minSeeds}\n");

$items = [];
$seenIds = [];

// Fuente 1: Trakt.tv (si está configurado)
if (!empty($traktClientId)) {
    fwrite(STDOUT, "\n=== Buscando en Trakt.tv ===\n");
    fwrite(STDOUT, "Client ID configurado: " . substr($traktClientId, 0, 20) . "...\n");
    try {
        $traktTrending = traktGetTrending($type, $traktClientId, $limit);
        foreach ($traktTrending as $item) {
            $itemId = $item['id'] ?? null;
            if ($itemId && !isset($seenIds['trakt_' . $itemId])) {
                $seenIds['trakt_' . $itemId] = true;
                $items[] = $item;
                if (count($items) >= $limit) {
                    break;
                }
            }
        }
        fwrite(STDOUT, "Trakt.tv trending: " . count($traktTrending) . " resultados\n");
        
        if (count($items) < $limit) {
            $traktPopular = traktGetPopular($type, $traktClientId, $limit);
            foreach ($traktPopular as $item) {
                $itemId = $item['id'] ?? null;
                if ($itemId && !isset($seenIds['trakt_' . $itemId])) {
                    $seenIds['trakt_' . $itemId] = true;
                    $items[] = $item;
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            }
            fwrite(STDOUT, "Trakt.tv popular: " . count($traktPopular) . " resultados\n");
        }
    } catch (Exception $e) {
        fwrite(STDERR, "Error en Trakt.tv: " . $e->getMessage() . "\n");
    }
} else {
    fwrite(STDOUT, "Trakt.tv no configurado (TRAKT_CLIENT_ID). Obtén uno gratis en https://trakt.tv/oauth/applications\n");
}

// Fuente 2: TVMaze
fwrite(STDOUT, "\n=== Buscando en TVMaze ===\n");
// Probar conexión a TVMaze primero
fwrite(STDOUT, "Probando conexión con TVMaze...\n");
$testUrl = "https://api.tvmaze.com/shows/1";
$testData = httpJson($testUrl, 10);
if ($testData) {
    fwrite(STDOUT, "✓ Conexión con TVMaze exitosa\n");
} else {
    fwrite(STDOUT, "✗ No se pudo conectar con TVMaze. Verifica tu conexión a internet.\n");
}

try {
    $tvmazeItems = tvmazeGetRecentShows($limit, $sinceDays, $type);
    fwrite(STDOUT, "Resultados de tvmazeGetRecentShows: " . count($tvmazeItems) . " items\n");
    
    foreach ($tvmazeItems as $item) {
        $itemId = $item['id'] ?? null;
        if ($itemId && !isset($seenIds['tvmaze_' . $itemId])) {
            $seenIds['tvmaze_' . $itemId] = true;
            $items[] = $item;
            if (count($items) >= $limit) {
                break;
            }
        }
    }
    
    if (empty($items)) {
        fwrite(STDOUT, "No se encontraron resultados desde TVMaze. Intentando con búsqueda alternativa...\n");
        
        // Estrategia alternativa: obtener shows directamente sin filtros de fecha
        fwrite(STDOUT, "Obteniendo shows directamente (sin filtro de fecha)...\n");
        $url = "https://api.tvmaze.com/shows?page=0";
        $shows = httpJson($url, 15);
        
        if (!empty($shows) && is_array($shows)) {
            fwrite(STDOUT, "Se obtuvieron " . count($shows) . " shows de la página 0\n");
            $count = 0;
            foreach ($shows as $show) {
                if ($count >= $limit) {
                    break;
                }
                $showType = $show['type'] ?? '';
                $premiered = $show['premiered'] ?? '';
                
                // Para películas
                if ($type === 'movie' && $showType === 'Movie') {
                    // Si hay filtro de días, verificar fecha
                    if ($sinceDays > 0 && $premiered) {
                        $premieredTs = strtotime($premiered);
                        $cutoff = strtotime("-{$sinceDays} days");
                        if ($premieredTs >= $cutoff) {
                            $items[] = $show;
                            $count++;
                        }
                    } else {
                        $items[] = $show;
                        $count++;
                    }
                }
                // Para series
                elseif ($type === 'tv' && ($showType === 'Scripted' || $showType === 'Reality' || $showType === 'Documentary' || $showType === '')) {
                    // Si hay filtro de días, verificar fecha
                    if ($sinceDays > 0 && $premiered) {
                        $premieredTs = strtotime($premiered);
                        $cutoff = strtotime("-{$sinceDays} days");
                        if ($premieredTs >= $cutoff) {
                            $items[] = $show;
                            $count++;
                        }
                    } else {
                        $items[] = $show;
                        $count++;
                    }
                }
            }
            fwrite(STDOUT, "Se añadieron {$count} items después del filtrado\n");
        } else {
            fwrite(STDOUT, "No se pudieron obtener shows de TVMaze\n");
        }
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error al buscar en TVMaze: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Trace: " . $e->getTraceAsString() . "\n");
    $items = [];
}

if (empty($items)) {
    fwrite(STDOUT, "No se encontraron resultados. El script se ejecutó correctamente pero no hay contenido nuevo disponible.\n");
    fwrite(STDOUT, "Sugerencias:\n");
    fwrite(STDOUT, "  - Aumenta el valor de 'Últimos días' (ej: 30 o 365)\n");
    fwrite(STDOUT, "  - Verifica tu conexión a internet\n");
    fwrite(STDOUT, "  - TVMaze puede estar temporalmente no disponible\n");
    fwrite(STDOUT, "Listo. Creados: 0, actualizados: 0, episodios nuevos: 0\n");
    exit(0);
}

fwrite(STDOUT, "Se encontraron " . count($items) . " items para procesar\n");

$created = 0;
$updated = 0;
$newEpisodes = 0;

foreach ($items as $show) {
    // Detectar si viene de Trakt.tv o TVMaze
    $isTrakt = isset($show['trakt_data']);
    
    if ($isTrakt) {
        // Datos de Trakt.tv
        $traktData = $show['trakt_data'];
        $title = $show['name'] ?? $traktData['title'] ?? 'Sin título';
        $premiered = $show['premiered'] ?? '';
        $releaseYear = $premiered ? (int)substr($premiered, 0, 4) : null;
        $runtime = isset($traktData['runtime']) ? (int)$traktData['runtime'] : ($type === 'tv' ? 45 : 100);
        $poster = !empty($show['image']['original']) 
            ? $show['image']['original'] 
            : (!empty($show['image']['medium']) ? $show['image']['medium'] : '');
        if (empty($poster)) {
            $poster = getPosterImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        }
        $backdrop = !empty($show['image']['original']) 
            ? $show['image']['original'] 
            : getBackdropImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        $rating = isset($show['rating']['average']) ? round((float)$show['rating']['average'], 1) : null;
        $genres = $show['genres'] ?? [];
        $description = $show['summary'] ?? $traktData['overview'] ?? '';
        $tvmazeId = null; // No hay ID de TVMaze
    } else {
        // Datos de TVMaze
        $tvmazeId = $show['id'] ?? 0;
        if (!$tvmazeId) {
            continue;
        }
        
        // Obtener detalles completos
        $details = tvmazeGetShow($tvmazeId);
        if (!$details) {
            fwrite(STDERR, "Sin detalles TVMaze para ID {$tvmazeId}\n");
            continue;
        }

        $title = $details['name'] ?? 'Sin título';
        $premiered = $details['premiered'] ?? '';
        $releaseYear = $premiered ? (int)substr($premiered, 0, 4) : null;
        $runtime = isset($details['runtime']) ? (int)$details['runtime'] : ($type === 'tv' ? 45 : 100);
        $poster = !empty($details['image']['original']) 
            ? $details['image']['original'] 
            : (!empty($details['image']['medium']) ? $details['image']['medium'] : '');
        if (empty($poster)) {
            $poster = getPosterImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        }
        $backdrop = !empty($details['image']['original']) 
            ? $details['image']['original'] 
            : getBackdropImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        $rating = isset($details['rating']['average']) ? round((float)$details['rating']['average'], 1) : null;
        $genres = $details['genres'] ?? [];
        $description = $details['summary'] ?? '';
    }
    
    // OMDB para completar (opcional) - solo si no tenemos descripción
    if (empty($description)) {
        $omdb = omdbFetch($title, $releaseYear ? (string)$releaseYear : null, $type, $omdbKey);
        if ($omdb) {
            $description = $omdb['Plot'] ?? '';
        }
    }
    // Limpiar HTML de la descripción
    $description = strip_tags($description);
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    
    if ($omdb && (empty($rating) || $rating === 0.0)) {
        $imdbRating = $omdb['imdbRating'] ?? '';
        if ($imdbRating !== 'N/A' && $imdbRating !== '') {
            $rating = (float)$imdbRating;
        }
    }
    
    // Trailer: TVMaze no tiene trailers, intentar buscar en OMDB o dejar vacío
    $trailer = '';
    if ($omdb && !empty($omdb['Poster'])) {
        // OMDB no tiene trailer directo, pero podemos intentar buscar en YouTube más adelante
    }

    $slug = slugify($title . '-' . ($releaseYear ?? 'na') . '-' . $type);
    $genreIds = !empty($genres) ? ensureGenres($db, $genres) : [];

    // Torrents (prioridad)
    $torrents = searchTorrentio($title, $type, $releaseYear ? (string)$releaseYear : null, $torrentioBase);
    if ($type === 'movie') {
        $torrents = array_merge($torrents, searchYTS($title, $releaseYear ? (string)$releaseYear : null));
    } else {
        $torrents = array_merge($torrents, searchEZTV($title));
    }
    $torrents = array_merge($torrents, searchTPB($title));
    $bestTorrent = pickBestTorrent($torrents, $minSeeds);

    $contentData = [
        'title' => $title,
        'slug' => $slug,
        'type' => $type === 'tv' ? 'series' : 'movie',
        'description' => $description,
        'release_year' => $releaseYear,
        'duration' => $runtime,
        'rating' => $rating,
        'age_rating' => null,
        'poster_url' => $poster,
        'backdrop_url' => $backdrop,
        'trailer_url' => $trailer,
        'video_url' => null,
        'torrent_magnet' => $bestTorrent['magnet'] ?? null,
        'is_featured' => 0,
        'is_trending' => 1,
        'is_premium' => 0
    ];

    if ($dryRun) {
        fwrite(STDOUT, "[DRY-RUN] {$title} ({$contentData['release_year']})\n");
        continue;
    }

    $already = findContent($db, $slug);
    $contentId = upsertContent($db, $contentData);
    if ($already) {
        $updated++;
    } else {
        $created++;
    }

    if (!empty($genreIds)) {
        setContentGenres($db, $contentId, array_values($genreIds));
    }

    // Series: crear/actualizar episodios (solo si tenemos ID de TVMaze)
    if ($type === 'tv' && $tvmazeId) {
        $episodes = tvmazeGetEpisodes($tvmazeId);
        if (!empty($episodes)) {
            // Agrupar por temporada y procesar solo las más recientes
            $seasons = [];
            foreach ($episodes as $ep) {
                $sn = (int)($ep['season'] ?? 1);
                if (!isset($seasons[$sn])) {
                    $seasons[$sn] = [];
                }
                $seasons[$sn][] = $ep;
            }
            krsort($seasons); // Más recientes primero
            $processedSeasons = 0;
            foreach ($seasons as $seasonNum => $seasonEps) {
                if ($processedSeasons >= 2) { // Solo últimas 2 temporadas
                    break;
                }
                foreach ($seasonEps as $ep) {
                    $episodeNum = (int)($ep['number'] ?? 1);
                    $epDuration = isset($ep['runtime']) ? (int)$ep['runtime'] : $runtime;
                    $epThumb = !empty($ep['image']['original']) 
                        ? $ep['image']['original'] 
                        : (!empty($ep['image']['medium']) ? $ep['image']['medium'] : $poster);
                    $epMagnet = null;

                    // Intentar mapear torrents por SxxEyy
                    foreach ($torrents as $tor) {
                        if (!empty($tor['season']) && !empty($tor['episode']) 
                            && (int)$tor['season'] === $seasonNum && (int)$tor['episode'] === $episodeNum) {
                            $epMagnet = $tor['magnet'];
                            break;
                        }
                        $titleTor = $tor['title'] ?? '';
                        if (preg_match('/S0*' . $seasonNum . 'E0*' . $episodeNum . '/i', $titleTor)) {
                            $epMagnet = $tor['magnet'];
                            break;
                        }
                    }

                    $epDesc = $ep['summary'] ?? '';
                    $epDesc = strip_tags($epDesc);
                    $epDesc = html_entity_decode($epDesc, ENT_QUOTES, 'UTF-8');

                    $epData = [
                        'title' => $ep['name'] ?? "{$title} S{$seasonNum}E{$episodeNum}",
                        'description' => $epDesc,
                        'duration' => $epDuration,
                        'video_url' => $epMagnet,
                        'thumbnail_url' => $epThumb,
                        'release_date' => $ep['airdate'] ?? null
                    ];
                    upsertEpisode($db, $contentId, $seasonNum, $episodeNum, $epData);
                    if ($ep['airdate'] ?? null) {
                        $airDate = $ep['airdate'];
                        if ($airDate >= date('Y-m-d', strtotime("-{$sinceDays} days"))) {
                            $newEpisodes++;
                        }
                    }
                }
                $processedSeasons++;
            }
        }
    }

    notifyAdmin($db, "Nuevo/actualizado: {$title}", "Se procesó contenido {$title}", $contentId);
    fwrite(STDOUT, "Procesado: {$title}\n");
}

fwrite(STDOUT, "\n✅ Listo. Creados: {$created}, actualizados: {$updated}, episodios nuevos: {$newEpisodes}\n");
