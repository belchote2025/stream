<?php
/**
 * CLI: Ingresa/actualiza novedades (películas y series) con prioridad torrents.
 * Usa múltiples fuentes: Trakt.tv, TMDB, TVMaze (todas gratuitas).
 *
 * Uso:
 *   php scripts/fetch-new-content.php --type=movie --limit=30 --since-days=7 --min-seeds=10
 *   php scripts/fetch-new-content.php --type=tv --limit=30 --since-days=7 --min-seeds=10
 *
 * Fuentes (en orden de prioridad):
 *   1. Trakt.tv (gratuita, requiere TRAKT_CLIENT_ID) - excelente para trending/popular
 *   2. TVMaze (gratuita, sin API key) - buena para series, limitada para películas
 *   3. TMDB (gratuita, requiere TMDB_API_KEY) - EXCELENTE para películas y series
 *   4. OMDb (opcional, requiere OMDB_API_KEY) - para completar metadatos e IMDb ID
 *
 * Para obtener API Keys (todas gratuitas):
 *   - TRAKT_CLIENT_ID: https://trakt.tv/oauth/applications
 *   - TMDB_API_KEY: https://www.themoviedb.org/settings/api (recomendado para películas)
 *   - OMDB_API_KEY: http://www.omdbapi.com/apikey.aspx
 *
 * Configuración:
 *   Agrega las API keys como variables de entorno o en includes/config.php:
 *   - TRAKT_CLIENT_ID=tu_client_id
 *   - TMDB_API_KEY=tu_api_key
 *   - OMDB_API_KEY=tu_api_key
 *
 * Efectos:
 *   - Inserta/actualiza filas en content (movie/series)
 *   - Inserta/actualiza episodios para series (episodes)
 *   - Asocia géneros (content_genres)
 *   - Busca enlaces de streaming (vidsrc/filemoon/streamtape) usando IMDb ID
 *   - Guarda magnet en content.torrent_magnet o episodes.video_url como fallback
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/imdb-helper.php';
require_once __DIR__ . '/../includes/addons/AddonManager.php';

// -------------------- Detectar contexto (CLI o Web) --------------------
$isCli = php_sapi_name() === 'cli';

// Función de salida que funciona tanto en CLI como en Web
function scriptOutput(string $message, bool $isError = false): void {
    global $isCli;
    // Forzar flush para asegurar que la salida se envíe inmediatamente
    if ($isCli) {
        $stream = $isError ? STDERR : STDOUT;
        fwrite($stream, $message . "\n");
        fflush($stream); // Forzar envío inmediato
    } else {
        // En web, la salida se captura con ob_start()
        // Asegurar que siempre se muestre la salida
        // Usar print en lugar de echo para mejor compatibilidad
        print $message . "\n";
        // Forzar flush múltiple para asegurar que se capture
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
        // También escribir a error_log para debugging si es necesario
        if ($isError) {
            @error_log("Script Error: " . trim($message));
        }
    }
}

// Mensaje de inicio inmediato para verificar que el script se ejecuta
scriptOutput("✓ Script iniciado correctamente\n");
scriptOutput("✓ PHP SAPI: " . php_sapi_name() . "\n");
scriptOutput("✓ Modo: " . ($isCli ? 'CLI' : 'Web') . "\n");

// -------------------- Configuración CLI/Web --------------------
if ($isCli) {
    // Desde CLI: usar getopt
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
} else {
    // Desde Web: leer de $_SERVER['argv'] simulado o variables globales
    $type = 'movie';
    $limit = 30;
    $sinceDays = 7;
    $minSeeds = 10;
    $dryRun = false;
    
    if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
        foreach ($_SERVER['argv'] as $arg) {
            if (strpos($arg, '--type=') === 0) {
                $type = substr($arg, 7);
                $type = $type === 'series' ? 'tv' : $type;
            } elseif (strpos($arg, '--limit=') === 0) {
                $limit = max(1, (int)substr($arg, 8));
            } elseif (strpos($arg, '--since-days=') === 0) {
                $sinceDays = max(0, (int)substr($arg, 13));
            } elseif (strpos($arg, '--min-seeds=') === 0) {
                $minSeeds = max(0, (int)substr($arg, 12));
            } elseif ($arg === '--dry-run') {
                $dryRun = true;
            }
        }
    }
}

$omdbKey = getenv('OMDB_API_KEY') ?: (defined('OMDB_API_KEY') ? OMDB_API_KEY : '');
$torrentioBase = getenv('TORRENTIO_BASE_URL') ?: 'https://torrentio.strem.fun';
$traktClientId = getenv('TRAKT_CLIENT_ID') ?: (defined('TRAKT_CLIENT_ID') ? TRAKT_CLIENT_ID : '');
$tmdbApiKey = getenv('TMDB_API_KEY') ?: (defined('TMDB_API_KEY') ? TMDB_API_KEY : '');

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
            scriptOutput("Error cURL: {$error}\n");
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
    scriptOutput("Buscando en TVMaze: {$query}...\n");
    
    $data = httpJson($url, 15);
    if (!$data || !is_array($data)) {
        scriptOutput("No se recibieron datos de TVMaze para: {$query}\n");
        return $results;
    }
    
    scriptOutput("TVMaze devolvió " . count($data) . " resultados para: {$query}\n");
    
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
    
    scriptOutput("Filtrados " . count($results) . " resultados válidos para: {$query}\n");
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

// -------------------- TMDB (The Movie Database) - API gratuita --------------------
function tmdbHttpJson(string $url, string $apiKey, int $timeout = 8): ?array
{
    if (empty($apiKey)) {
        return null;
    }
    
    $fullUrl = strpos($url, '?') !== false ? $url . '&api_key=' . $apiKey : $url . '?api_key=' . $apiKey;
    
    if (function_exists('curl_init')) {
        $ch = curl_init($fullUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200 && $resp) {
            $data = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
    } else {
        $resp = @file_get_contents($fullUrl, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0\r\n",
                'timeout' => $timeout,
                'ignore_errors' => true
            ]
        ]));
        if ($resp) {
            $data = json_decode($resp, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                return $data;
            }
        }
    }
    return null;
}

function tmdbGetTrending(string $type, string $apiKey, int $limit = 30): array
{
    if (empty($apiKey)) {
        return [];
    }
    
    $results = [];
    $mediaType = $type === 'tv' ? 'tv' : 'movie';
    $url = "https://api.themoviedb.org/3/trending/{$mediaType}/day";
    
    scriptOutput("Buscando trending en TMDB ({$type})...\n");
    $data = tmdbHttpJson($url, $apiKey, 8); // Timeout reducido
    
    if (!empty($data['results']) && is_array($data['results'])) {
        scriptOutput("TMDB devolvió " . count($data['results']) . " resultados\n");
        $count = 0;
        foreach ($data['results'] as $item) {
            if ($count >= $limit) break;
            
            $releaseDate = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $releaseYear = $releaseDate ? (int)substr($releaseDate, 0, 4) : null;
            
            $posterPath = $item['poster_path'] ?? '';
            $backdropPath = $item['backdrop_path'] ?? '';
            $posterUrl = $posterPath ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '';
            $backdropUrl = $backdropPath ? 'https://image.tmdb.org/t/p/w1280' . $backdropPath : '';
            
            $results[] = [
                'id' => $item['id'] ?? null,
                'name' => $item['title'] ?? $item['name'] ?? '',
                'premiered' => $releaseDate,
                'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                'summary' => $item['overview'] ?? '',
                'rating' => ['average' => $item['vote_average'] ?? 0],
                'genres' => [], // TMDB usa IDs, los convertiremos después
                'image' => [
                    'medium' => $posterUrl,
                    'original' => $posterUrl
                ],
                'backdrop' => $backdropUrl,
                'tmdb_id' => $item['id'] ?? null,
                'tmdb_data' => $item // Guardar datos completos de TMDB
            ];
            $count++;
        }
    }
    
    return $results;
}

function tmdbGetPopular(string $type, string $apiKey, int $limit = 30): array
{
    if (empty($apiKey)) {
        return [];
    }
    
    $results = [];
    $mediaType = $type === 'tv' ? 'tv' : 'movie';
    $url = "https://api.themoviedb.org/3/{$mediaType}/popular";
    
    scriptOutput("Buscando populares en TMDB ({$type})...\n");
    $data = tmdbHttpJson($url, $apiKey, 8); // Timeout reducido
    
    if (!empty($data['results']) && is_array($data['results'])) {
        scriptOutput("TMDB devolvió " . count($data['results']) . " resultados populares\n");
        $count = 0;
        foreach ($data['results'] as $item) {
            if ($count >= $limit) break;
            
            $releaseDate = $item['release_date'] ?? $item['first_air_date'] ?? '';
            $releaseYear = $releaseDate ? (int)substr($releaseDate, 0, 4) : null;
            
            $posterPath = $item['poster_path'] ?? '';
            $backdropPath = $item['backdrop_path'] ?? '';
            $posterUrl = $posterPath ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '';
            $backdropUrl = $backdropPath ? 'https://image.tmdb.org/t/p/w1280' . $backdropPath : '';
            
            $results[] = [
                'id' => $item['id'] ?? null,
                'name' => $item['title'] ?? $item['name'] ?? '',
                'premiered' => $releaseDate,
                'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                'summary' => $item['overview'] ?? '',
                'rating' => ['average' => $item['vote_average'] ?? 0],
                'genres' => [],
                'image' => [
                    'medium' => $posterUrl,
                    'original' => $posterUrl
                ],
                'backdrop' => $backdropUrl,
                'tmdb_id' => $item['id'] ?? null,
                'tmdb_data' => $item
            ];
            $count++;
        }
    }
    
    return $results;
}

function tmdbGetNowPlaying(string $type, string $apiKey, int $limit = 30): array
{
    if (empty($apiKey)) {
        return [];
    }
    
    $results = [];
    if ($type === 'movie') {
        $url = "https://api.themoviedb.org/3/movie/now_playing";
    } else {
        $url = "https://api.themoviedb.org/3/tv/on_the_air";
    }
    
    scriptOutput("Buscando estrenos recientes en TMDB ({$type})...\n");
    $data = tmdbHttpJson($url, $apiKey, 8); // Timeout reducido
    
    if (!empty($data['results']) && is_array($data['results'])) {
        scriptOutput("TMDB devolvió " . count($data['results']) . " resultados de estrenos\n");
        $count = 0;
        foreach ($data['results'] as $item) {
            if ($count >= $limit) break;
            
            $releaseDate = $item['release_date'] ?? $item['first_air_date'] ?? '';
            
            $posterPath = $item['poster_path'] ?? '';
            $backdropPath = $item['backdrop_path'] ?? '';
            $posterUrl = $posterPath ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '';
            $backdropUrl = $backdropPath ? 'https://image.tmdb.org/t/p/w1280' . $backdropPath : '';
            
            $results[] = [
                'id' => $item['id'] ?? null,
                'name' => $item['title'] ?? $item['name'] ?? '',
                'premiered' => $releaseDate,
                'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                'summary' => $item['overview'] ?? '',
                'rating' => ['average' => $item['vote_average'] ?? 0],
                'genres' => [],
                'image' => [
                    'medium' => $posterUrl,
                    'original' => $posterUrl
                ],
                'backdrop' => $backdropUrl,
                'tmdb_id' => $item['id'] ?? null,
                'tmdb_data' => $item
            ];
            $count++;
        }
    }
    
    return $results;
}

// -------------------- Trakt.tv (gratuita, requiere client_id) --------------------
function traktHttpJson(string $url, string $clientId, int $timeout = 10): ?array
{
    if (empty($clientId)) {
        scriptOutput("ERROR: Client ID vacío para Trakt.tv\n");
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
                scriptOutput("ERROR Trakt.tv: JSON inválido - " . json_last_error_msg() . "\n");
            }
        } else {
            $errorMsg = $error ?: "HTTP $code";
            if ($code === 401) {
                scriptOutput("ERROR Trakt.tv: No autorizado (401). Verifica que el Client ID sea correcto.\n");
            } elseif ($code === 404) {
                scriptOutput("ERROR Trakt.tv: Endpoint no encontrado (404). URL: $url\n");
            } else {
                scriptOutput("ERROR Trakt.tv: $errorMsg. Respuesta: " . substr($resp, 0, 200) . "\n");
            }
        }
    } else {
        scriptOutput("ERROR: cURL no disponible\n");
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
    
    scriptOutput("Buscando trending en Trakt.tv ({$type})...\n");
    $data = traktHttpJson($url, $clientId, 15);
    
    if (!empty($data) && is_array($data)) {
        scriptOutput("Trakt.tv devolvió " . count($data) . " resultados\n");
        foreach ($data as $item) {
            $show = $item[$endpoint === 'shows' ? 'show' : 'movie'] ?? null;
            if ($show) {
                // Trakt.tv no devuelve URLs de imágenes directamente, solo IDs
                // Necesitamos obtener las imágenes de TMDB usando el ID de TMDB
                $tmdbId = $show['ids']['tmdb'] ?? null;
                $posterUrl = '';
                $backdropUrl = '';
                
                // Si tenemos ID de TMDB, construir URLs (aunque Trakt no devuelve poster_path directamente)
                // Usaremos el título para buscar en IMDb como fallback
                
                $results[] = [
                    'id' => $show['ids']['trakt'] ?? $show['ids']['tmdb'] ?? null,
                    'name' => $show['title'] ?? '',
                    'premiered' => $show['released'] ?? $show['first_aired'] ?? '',
                    'type' => $type === 'tv' ? 'Scripted' : 'Movie',
                    'summary' => $show['overview'] ?? '',
                    'rating' => ['average' => $show['rating'] ?? 0],
                    'genres' => $show['genres'] ?? [],
                    'image' => [
                        'medium' => $posterUrl,
                        'original' => $posterUrl
                    ],
                    'backdrop' => $backdropUrl,
                    'tmdb_id' => $tmdbId, // Guardar ID de TMDB para uso posterior
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
    
    scriptOutput("Buscando populares en Trakt.tv ({$type})...\n");
    $data = traktHttpJson($url, $clientId, 15);
    
    if (!empty($data) && is_array($data)) {
        scriptOutput("Trakt.tv devolvió " . count($data) . " resultados populares\n");
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
    // Si no hay resultados con filtro de fecha, ignorar el filtro completamente
    $ignoreDateFilter = (count($results) === 0 && $sinceDays > 0);
    
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
                    $shouldAdd = false;
                    
                    if ($type === 'tv' && ($showType === 'Scripted' || $showType === 'Reality' || $showType === 'Documentary' || $showType === '')) {
                        $shouldAdd = true;
                    } elseif ($type === 'movie' && $showType === 'Movie') {
                        $shouldAdd = true;
                    }
                    
                    if ($shouldAdd) {
                        $seenIds[$id] = true;
                        // Si ignoramos el filtro de fecha o no hay filtro, añadir directamente
                        if ($ignoreDateFilter || $sinceDays === 0) {
                            $results[] = $show;
                        } else {
                            // Aplicar filtro de fecha solo si no estamos ignorándolo
                            $premiered = $show['premiered'] ?? '';
                            if ($premiered) {
                                $premieredTs = strtotime($premiered);
                                if ($premieredTs >= $cutoff) {
                                    $results[] = $show;
                                }
                            } else {
                                // Si no tiene fecha, añadir de todas formas
                                $results[] = $show;
                            }
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

// -------------------- Búsqueda de Video URL Directo --------------------
/**
 * Obtiene imdbId usando OMDB (si es posible) o los datos ya obtenidos.
 */
function resolveImdbId(string $title, ?int $year, string $type, ?array $omdb): string
{
    if (!empty($omdb['imdbID'])) {
        return $omdb['imdbID'];
    }

    $omdbKey = getenv('OMDB_API_KEY') ?: (defined('OMDB_API_KEY') ? OMDB_API_KEY : '');
    if (empty($omdbKey) || $omdbKey === 'demo') {
        // Fallback: intentar scraping directo a IMDb (sin API)
        return scrapeImdbIdFromWeb($title, $year, $type);
    }

    $fetched = omdbFetch($title, $year ? (string)$year : null, $type, $omdbKey);
    if (!empty($fetched['imdbID'])) {
        return $fetched['imdbID'];
    }

    // Fallback: scraping si OMDB no devolvió ID
    return scrapeImdbIdFromWeb($title, $year, $type);
}

/**
 * Construye lista de URLs de embed (prioriza mirrors tipo filemoon/streamtape via proveedores).
 */
function buildEmbedProviders(string $imdbId, string $type, ?int $season = null, ?int $episode = null): array
{
    // vidsrc soporta ruta /tv/{imdb}/{season}-{episode} para episodios concretos
    if ($type === 'tv' && $season !== null && $episode !== null) {
        return [
            "https://vidsrc.to/embed/tv/{$imdbId}/{$season}-{$episode}",
            "https://vidsrc.cc/v2/embed/tv/{$imdbId}/{$season}-{$episode}",
            "https://embed.smashystream.com/play/{$imdbId}/{$season}-{$episode}",
        ];
    }

    // Genérico por serie/película (el proveedor resuelve capítulos internamente)
    if ($type === 'tv') {
        return [
            "https://vidsrc.to/embed/tv/{$imdbId}",
            "https://vidsrc.cc/v2/embed/tv/{$imdbId}",
            "https://embed.smashystream.com/play/{$imdbId}",
        ];
    }

    return [
        "https://vidsrc.to/embed/movie/{$imdbId}",
        "https://vidsrc.cc/v2/embed/movie/{$imdbId}",
        "https://embed.smashystream.com/play/{$imdbId}",
    ];
}

/**
 * Scraping sencillo a IMDb para obtener un imdbId sin API.
 * Usa búsqueda web y toma el primer tt\d+ encontrado.
 */
function scrapeImdbIdFromWeb(string $title, ?int $year, string $type): string
{
    $query = urlencode(trim($title) . ' ' . ($year ?: ''));
    // imdb tt search; ttype ft(movie)/tv
    $ttype = ($type === 'tv') ? 'tv' : 'ft';
    $url = "https://www.imdb.com/find?q={$query}&s=tt&ttype={$ttype}";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
            'timeout' => 4,
            'ignore_errors' => true,
        ]
    ]);

    try {
        $html = @file_get_contents($url, false, $context);
        if (!$html) {
            return '';
        }
        if (preg_match('/\/title\/(tt\d+)/', $html, $m)) {
            return $m[1];
        }
    } catch (Exception $e) {
        // Silenciar errores; retornará vacío
    }
    return '';
}

/**
 * Busca enlace de video directo (streaming) para el contenido.
 * Busca en múltiples fuentes: vidsrc, upstream, powvideo, filemoon, streamtape, streamwish
 * Devuelve el primer enlace disponible.
 */
function searchVideoUrl(string $title, string $type, ?int $year, ?array $omdb, ?string $imdbId = null): ?string
{
    // Resolver imdbId si no viene
    $imdbId = $imdbId ?: resolveImdbId($title, $year, $type, $omdb);
    
    // Buscar en múltiples fuentes de streaming
    $sources = [];
    
    // 1. Vidsrc (ya implementado)
    if (!empty($imdbId)) {
        $vidsrcProviders = buildEmbedProviders($imdbId, $type);
        $sources = array_merge($sources, $vidsrcProviders);
    }
    
    // 2. Buscar en otras fuentes de streaming
    $streamingSources = searchStreamingSources($title, $type, $year, $imdbId);
    $sources = array_merge($sources, $streamingSources);
    
    // Devolver el primer enlace disponible
    return $sources[0] ?? null;
}

/**
 * Busca enlaces de streaming en múltiples fuentes: upstream, powvideo, filemoon, streamtape, streamwish
 */
function searchStreamingSources(string $title, string $type, ?int $year, ?string $imdbId = null): array
{
    $results = [];
    $query = urlencode($title . ($year ? ' ' . $year : ''));
    
    // 1. Upstream
    try {
        $upstreamUrl = searchUpstream($title, $type, $year, $imdbId);
        if ($upstreamUrl) {
            $results[] = $upstreamUrl;
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
    
    // 2. PowVideo
    try {
        $powvideoUrl = searchPowVideo($title, $type, $year, $imdbId);
        if ($powvideoUrl) {
            $results[] = $powvideoUrl;
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
    
    // 3. Filemoon
    try {
        $filemoonUrl = searchFilemoon($title, $type, $year, $imdbId);
        if ($filemoonUrl) {
            $results[] = $filemoonUrl;
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
    
    // 4. Streamtape
    try {
        $streamtapeUrl = searchStreamtape($title, $type, $year, $imdbId);
        if ($streamtapeUrl) {
            $results[] = $streamtapeUrl;
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
    
    // 5. Streamwish
    try {
        $streamwishUrl = searchStreamwish($title, $type, $year, $imdbId);
        if ($streamwishUrl) {
            $results[] = $streamwishUrl;
        }
    } catch (Exception $e) {
        // Ignorar errores
    }
    
    return $results;
}

/**
 * Busca enlace en Upstream
 */
function searchUpstream(string $title, string $type, ?int $year, ?string $imdbId = null): ?string
{
    if (empty($imdbId)) {
        return null;
    }
    
    // Upstream usa estructura similar a vidsrc
    $url = $type === 'tv' 
        ? "https://upstream.to/embed/tv/{$imdbId}"
        : "https://upstream.to/embed/movie/{$imdbId}";
    
    // Verificar que el enlace sea accesible
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $headers = @get_headers($url, true, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $url;
    }
    
    return null;
}

/**
 * Busca enlace en PowVideo
 */
function searchPowVideo(string $title, string $type, ?int $year, ?string $imdbId = null): ?string
{
    if (empty($imdbId)) {
        return null;
    }
    
    $url = $type === 'tv'
        ? "https://powvideo.net/embed/tv/{$imdbId}"
        : "https://powvideo.net/embed/movie/{$imdbId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $headers = @get_headers($url, true, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $url;
    }
    
    return null;
}

/**
 * Busca enlace en Filemoon
 */
function searchFilemoon(string $title, string $type, ?int $year, ?string $imdbId = null): ?string
{
    if (empty($imdbId)) {
        return null;
    }
    
    $url = $type === 'tv'
        ? "https://filemoon.to/embed/tv/{$imdbId}"
        : "https://filemoon.to/embed/movie/{$imdbId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $headers = @get_headers($url, true, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $url;
    }
    
    return null;
}

/**
 * Busca enlace en Streamtape
 */
function searchStreamtape(string $title, string $type, ?int $year, ?string $imdbId = null): ?string
{
    if (empty($imdbId)) {
        return null;
    }
    
    $url = $type === 'tv'
        ? "https://streamtape.com/embed/tv/{$imdbId}"
        : "https://streamtape.com/embed/movie/{$imdbId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $headers = @get_headers($url, true, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $url;
    }
    
    return null;
}

/**
 * Busca enlace en Streamwish
 */
function searchStreamwish(string $title, string $type, ?int $year, ?string $imdbId = null): ?string
{
    if (empty($imdbId)) {
        return null;
    }
    
    $url = $type === 'tv'
        ? "https://streamwish.to/embed/tv/{$imdbId}"
        : "https://streamwish.to/embed/movie/{$imdbId}";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    
    $headers = @get_headers($url, true, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $url;
    }
    
    return null;
}

// -------------------- Búsqueda de Trailer en YouTube --------------------
/**
 * Busca el trailer oficial en YouTube usando búsqueda web.
 * Retorna la URL del video de YouTube o un ID de video.
 */
function searchYouTubeTrailer(string $title, string $type, ?int $year): string
{
    // Intentar primero con YouTube Data API v3 si hay API key
    $youtubeApiKey = getenv('YOUTUBE_API_KEY') ?: (defined('YOUTUBE_API_KEY') ? YOUTUBE_API_KEY : '');
    
    if (!empty($youtubeApiKey)) {
        $searchQuery = $title;
        if ($year) {
            $searchQuery .= " {$year}";
        }
        $searchQuery .= " official trailer";
        
        $query = urlencode($searchQuery);
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q={$query}&type=video&maxResults=1&key={$youtubeApiKey}";
        
        $data = httpJson($url, 8);
        if ($data && !empty($data['items'][0]['id']['videoId'])) {
            $videoId = $data['items'][0]['id']['videoId'];
            return "https://www.youtube.com/watch?v={$videoId}";
        }
    }
    
    // Fallback: búsqueda web (scraping)
    $searchQuery = $title;
    if ($year) {
        $searchQuery .= " {$year}";
    }
    $searchQuery .= " official trailer";
    
    $query = urlencode($searchQuery);
    $url = "https://www.youtube.com/results?search_query={$query}";
    
    try {
        // Usar cURL si está disponible (más confiable)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
            $html = curl_exec($ch);
            curl_close($ch);
        } else {
            $html = @file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]));
        }
        
        if ($html) {
            // YouTube ahora usa JSON embebido en script tags con ytInitialData
            // Buscar en el formato más común: "videoId":"..."
            if (preg_match('/"videoId":"([a-zA-Z0-9_-]{11})"/', $html, $matches)) {
                $videoId = $matches[1];
                return "https://www.youtube.com/watch?v={$videoId}";
            }
            // Alternativa: buscar en watch?v=...
            if (preg_match('/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $matches)) {
                $videoId = $matches[1];
                return "https://www.youtube.com/watch?v={$videoId}";
            }
            // Alternativa: buscar en /watch/...
            if (preg_match('/\/watch\/([a-zA-Z0-9_-]{11})/', $html, $matches)) {
                $videoId = $matches[1];
                return "https://www.youtube.com/watch?v={$videoId}";
            }
        }
    } catch (Exception $e) {
        // Silenciar errores, simplemente retornar vacío
    }
    
    return '';
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

/**
 * Buscar en 1337x usando scraping web (sin API)
 */
function search1337xWeb(string $title, string $type): array
{
    $results = [];
    $query = urlencode($title);
    $category = $type === 'tv' ? 'TV' : 'Movies';
    
    $urls = [
        "https://1337x.to/search/{$query}/1/",
        "https://www.1377x.to/search/{$query}/1/",
    ];
    
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
    
    foreach ($urls as $url) {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                    'timeout' => 8,
                    'ignore_errors' => true,
                ]
            ]);
            
            $html = @file_get_contents($url, false, $context);
            if (!$html) continue;
            
            // Extraer magnet links del HTML
            if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
                foreach ($matches[0] as $magnet) {
                    // Extraer título si es posible
                    preg_match('/dn=([^&]+)/', $magnet, $dnMatch);
                    $torrentTitle = $dnMatch[1] ? urldecode($dnMatch[1]) : $title;
                    
                    // Agregar trackers
                    foreach ($trackers as $tracker) {
                        if (strpos($magnet, urlencode($tracker)) === false) {
                            $magnet .= '&tr=' . urlencode($tracker);
                        }
                    }
                    
                    $results[] = [
                        'title' => $torrentTitle,
                        'quality' => extractQualityFromTitle($torrentTitle),
                        'seeds' => 0, // No disponible en scraping simple
                        'peers' => 0,
                        'magnet' => $magnet,
                        'source' => '1337x'
                    ];
                }
                if (count($results) > 0) break; // Si encontramos resultados, no intentar más URLs
            }
        } catch (Exception $e) {
            // Continuar con siguiente URL
        }
    }
    
    return $results;
}

/**
 * Buscar en RARBG usando scraping web (sin API)
 */
function searchRARBGWeb(string $title, ?string $year): array
{
    $results = [];
    $query = urlencode($title);
    
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
        $url = "https://rarbg.to/torrents.php?search={$query}";
        if ($year) {
            $url .= "&year={$year}";
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html) {
            // Extraer magnet links
            if (preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
                foreach ($matches[0] as $magnet) {
                    preg_match('/dn=([^&]+)/', $magnet, $dnMatch);
                    $torrentTitle = $dnMatch[1] ? urldecode($dnMatch[1]) : $title;
                    
                    foreach ($trackers as $tracker) {
                        if (strpos($magnet, urlencode($tracker)) === false) {
                            $magnet .= '&tr=' . urlencode($tracker);
                        }
                    }
                    
                    $results[] = [
                        'title' => $torrentTitle,
                        'quality' => extractQualityFromTitle($torrentTitle),
                        'seeds' => 0,
                        'peers' => 0,
                        'magnet' => $magnet,
                        'source' => 'RARBG'
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
 * Buscar en LimeTorrents usando scraping web
 */
function searchLimeTorrentsWeb(string $title, string $type): array
{
    $results = [];
    $query = urlencode($title);
    $category = $type === 'tv' ? 'TV-shows' : 'Movies';
    
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
        $url = "https://www.limetorrents.lol/search/{$category}/{$query}/seeds/1/";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html && preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
            foreach ($matches[0] as $magnet) {
                preg_match('/dn=([^&]+)/', $magnet, $dnMatch);
                $torrentTitle = $dnMatch[1] ? urldecode($dnMatch[1]) : $title;
                
                foreach ($trackers as $tracker) {
                    if (strpos($magnet, urlencode($tracker)) === false) {
                        $magnet .= '&tr=' . urlencode($tracker);
                    }
                }
                
                $results[] = [
                    'title' => $torrentTitle,
                    'quality' => extractQualityFromTitle($torrentTitle),
                    'seeds' => 0,
                    'peers' => 0,
                    'magnet' => $magnet,
                    'source' => 'LimeTorrents'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return $results;
}

/**
 * Buscar en Torlock usando scraping web
 */
function searchTorlockWeb(string $title, string $type): array
{
    $results = [];
    $query = urlencode($title);
    
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
        $url = "https://www.torlock.com/all/torrents/{$title}.html?sort=seeds";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html && preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
            foreach ($matches[0] as $magnet) {
                foreach ($trackers as $tracker) {
                    if (strpos($magnet, urlencode($tracker)) === false) {
                        $magnet .= '&tr=' . urlencode($tracker);
                    }
                }
                
                $results[] = [
                    'title' => $title,
                    'quality' => extractQualityFromTitle($title),
                    'seeds' => 0,
                    'peers' => 0,
                    'magnet' => $magnet,
                    'source' => 'Torlock'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return $results;
}

/**
 * Buscar en TorrentGalaxy usando scraping web
 */
function searchTorrentGalaxyWeb(string $title, string $type): array
{
    $results = [];
    $query = urlencode($title);
    $category = $type === 'tv' ? '41' : '4'; // 4 = Movies, 41 = TV
    
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
        $url = "https://torrentgalaxy.to/torrents.php?search={$query}&cat={$category}&sort=seeders&order=desc";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html && preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
            foreach ($matches[0] as $magnet) {
                foreach ($trackers as $tracker) {
                    if (strpos($magnet, urlencode($tracker)) === false) {
                        $magnet .= '&tr=' . urlencode($tracker);
                    }
                }
                
                $results[] = [
                    'title' => $title,
                    'quality' => extractQualityFromTitle($title),
                    'seeds' => 0,
                    'peers' => 0,
                    'magnet' => $magnet,
                    'source' => 'TorrentGalaxy'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return $results;
}

/**
 * Buscar en Zooqle usando scraping web
 */
function searchZooqleWeb(string $title, string $type): array
{
    $results = [];
    $query = urlencode($title);
    
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
        $url = "https://zooqle.com/search?q={$query}";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        if ($html && preg_match_all('/magnet:\?xt=urn:btih:([a-f0-9]{40})[^"]*/i', $html, $matches)) {
            foreach ($matches[0] as $magnet) {
                preg_match('/dn=([^&]+)/', $magnet, $dnMatch);
                $torrentTitle = $dnMatch[1] ? urldecode($dnMatch[1]) : $title;
                
                foreach ($trackers as $tracker) {
                    if (strpos($magnet, urlencode($tracker)) === false) {
                        $magnet .= '&tr=' . urlencode($tracker);
                    }
                }
                
                $results[] = [
                    'title' => $torrentTitle,
                    'quality' => extractQualityFromTitle($torrentTitle),
                    'seeds' => 0,
                    'peers' => 0,
                    'magnet' => $magnet,
                    'source' => 'Zooqle'
                ];
            }
        }
    } catch (Exception $e) {
        // Silenciar errores
    }
    
    return $results;
}

/**
 * Extraer calidad del título del torrent
 */
function extractQualityFromTitle(string $title): ?string
{
    $titleUpper = strtoupper($title);
    if (strpos($titleUpper, '4K') !== false || strpos($titleUpper, '2160P') !== false) {
        return '4K';
    } elseif (strpos($titleUpper, '1080P') !== false || strpos($titleUpper, 'FULLHD') !== false) {
        return '1080p';
    } elseif (strpos($titleUpper, '720P') !== false || strpos($titleUpper, 'HD') !== false) {
        return '720p';
    } elseif (strpos($titleUpper, '480P') !== false || strpos($titleUpper, 'SD') !== false) {
        return '480p';
    }
    return null;
}

function pickBestTorrent(array $results, int $minSeeds): ?array
{
    if (empty($results)) {
        return null;
    }
    
    // Filtrar solo torrents con al menos minSeeds
    $filtered = array_filter($results, function ($r) use ($minSeeds) {
        return ($r['seeds'] ?? 0) >= $minSeeds && !empty($r['magnet']);
    });
    
    // Si no hay torrents válidos, retornar null (no usar torrents con pocos seeds)
    if (empty($filtered)) {
        return null;
    }
    
    // Ordenar por seeds (mayor primero), luego por calidad
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
                poster_url = CASE 
                    WHEN poster_url IS NULL OR poster_url = '' OR poster_url LIKE '%default-poster%' OR poster_url LIKE '%default-movie%' OR poster_url LIKE '%default-tv%' 
                    THEN :poster_url 
                    ELSE poster_url 
                END,
                backdrop_url = CASE 
                    WHEN backdrop_url IS NULL OR backdrop_url = '' OR backdrop_url LIKE '%default-backdrop%' OR backdrop_url LIKE '%default-poster%' OR backdrop_url LIKE '%default-movie%' OR backdrop_url LIKE '%default-tv%'
                    THEN :backdrop_url 
                    ELSE backdrop_url 
                END,
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
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, related_content_id, created_at) VALUES (1, :title, :message, 'new_content', 0, :cid, NOW())");
        $stmt->execute([':title' => $title, ':message' => $message, ':cid' => $contentId]);
    } catch (PDOException $e) {
        // Si la tabla no existe (SQLSTATE 42S02), omitir silenciosamente
        if ($e->getCode() !== '42S02') {
            scriptOutput("Error al insertar notificación: " . $e->getMessage() . "\n", true);
        }
    } catch (Exception $e) {
        // Ignorar otros errores no críticos
    }
}

// -------------------- Proceso principal --------------------
scriptOutput("========================================\n");
scriptOutput("INICIANDO BÚSQUEDA DE NOVEDADES\n");
scriptOutput("========================================\n");
scriptOutput("Buscando novedades desde múltiples fuentes (TVMaze, Trakt.tv, TMDB, Addons)...\n");
scriptOutput("Parámetros:\n");
scriptOutput("  - Tipo: {$type}\n");
scriptOutput("  - Límite: {$limit}\n");
scriptOutput("  - Últimos días: {$sinceDays}\n");
scriptOutput("  - Mínimo de seeds: {$minSeeds}\n");
scriptOutput("  - Modo: " . ($isCli ? 'CLI' : 'Web') . "\n");
scriptOutput("========================================\n\n");

$items = [];
$seenIds = [];

// Fuente 1: Trakt.tv (si está configurado)
if (!empty($traktClientId)) {
    scriptOutput("\n=== Buscando en Trakt.tv ===\n");
    scriptOutput("Client ID configurado: " . substr($traktClientId, 0, 20) . "...\n");
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
        scriptOutput("Trakt.tv trending: " . count($traktTrending) . " resultados\n");
        
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
            scriptOutput("Trakt.tv popular: " . count($traktPopular) . " resultados\n");
        }
    } catch (Exception $e) {
        scriptOutput("Error en Trakt.tv: " . $e->getMessage() . "\n", true);
    }
} else {
    scriptOutput("Trakt.tv no configurado (TRAKT_CLIENT_ID). Obtén uno gratis en https://trakt.tv/oauth/applications\n");
}

// Fuente 2: TVMaze
scriptOutput("\n=== Buscando en TVMaze ===\n");
// Probar conexión a TVMaze primero
scriptOutput("Probando conexión con TVMaze...\n");
$testUrl = "https://api.tvmaze.com/shows/1";
$testData = httpJson($testUrl, 10);
if ($testData) {
    scriptOutput("✓ Conexión con TVMaze exitosa\n");
} else {
    scriptOutput("✗ No se pudo conectar con TVMaze. Verifica tu conexión a internet.\n", true);
}

try {
    $tvmazeItems = tvmazeGetRecentShows($limit, $sinceDays, $type);
    scriptOutput("Resultados de tvmazeGetRecentShows: " . count($tvmazeItems) . " items\n");
    
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
        scriptOutput("No se encontraron resultados desde TVMaze con filtro de fecha. Buscando contenido popular sin filtro...\n");
        
        // Estrategia alternativa: obtener shows populares sin filtros de fecha
        // Reducir páginas para evitar timeouts (priorizar TMDB si está disponible)
        $maxPages = $type === 'movie' ? 3 : 2; // Reducido para evitar timeouts
        $count = 0;
        $checkedShows = 0;
        $maxTime = 30; // Máximo 30 segundos para esta búsqueda
        $startTime = time();
        
        scriptOutput("Buscando en hasta {$maxPages} páginas de TVMaze (máx. {$maxTime}s)...\n");
        
        for ($page = 0; $page < $maxPages && count($items) < $limit; $page++) {
            // Verificar tiempo límite
            if ((time() - $startTime) > $maxTime) {
                scriptOutput("Tiempo límite alcanzado ({$maxTime}s). Continuando con otras fuentes...\n");
                break;
            }
            
            scriptOutput("Obteniendo shows de la página {$page}...\n");
            $url = "https://api.tvmaze.com/shows?page={$page}";
            $shows = httpJson($url, 8); // Reducir timeout
            
            if (!empty($shows) && is_array($shows)) {
                scriptOutput("Se obtuvieron " . count($shows) . " shows de la página {$page}\n");
                $checkedShows += count($shows);
                
                foreach ($shows as $show) {
                    if (count($items) >= $limit) {
                        break 2; // Salir de ambos loops
                    }
                    
                    $showType = $show['type'] ?? '';
                    $itemId = $show['id'] ?? null;
                    
                    // Evitar duplicados
                    if ($itemId && isset($seenIds['tvmaze_' . $itemId])) {
                        continue;
                    }
                    
                    // Para películas: buscar solo tipo Movie
                    if ($type === 'movie' && $showType === 'Movie') {
                        $seenIds['tvmaze_' . $itemId] = true;
                        $items[] = $show;
                        $count++;
                        scriptOutput("  ✓ Encontrada película: " . ($show['name'] ?? 'Sin título') . " (ID: {$itemId})\n");
                    }
                    // Para series: buscar Scripted, Reality, Documentary o vacío
                    elseif ($type === 'tv' && ($showType === 'Scripted' || $showType === 'Reality' || $showType === 'Documentary' || $showType === '')) {
                        $seenIds['tvmaze_' . $itemId] = true;
                        $items[] = $show;
                        $count++;
                        scriptOutput("  ✓ Encontrada serie: " . ($show['name'] ?? 'Sin título') . " (ID: {$itemId})\n");
                    }
                }
            } else {
                scriptOutput("No se pudieron obtener shows de la página {$page}\n");
                break;
            }
            
            // Pausa más corta entre páginas
            if ($page < $maxPages - 1) {
                usleep(100000); // 0.1 segundos (reducido)
            }
        }
        
        scriptOutput("Revisados {$checkedShows} shows, se añadieron {$count} items de contenido popular\n");
        
        // NO hacer búsqueda por términos si ya pasó mucho tiempo (evitar timeout)
        // TMDB será más rápido y eficiente
    }
} catch (Exception $e) {
    scriptOutput("Error al buscar en TVMaze: " . $e->getMessage() . "\n", true);
    scriptOutput("Trace: " . $e->getTraceAsString() . "\n", true);
}

// Fuente 3: TMDB (The Movie Database) - API gratuita (MÁS RÁPIDA)
// Priorizar TMDB sobre búsquedas extensas en TVMaze para evitar timeouts
if (empty($items) || count($items) < $limit) {
    scriptOutput("\n=== Buscando en TMDB (The Movie Database) ===\n");
    if (!empty($tmdbApiKey)) {
        scriptOutput("API Key configurada: " . substr($tmdbApiKey, 0, 20) . "...\n");
        try {
            // TMDB es más rápido, intentar primero trending (más rápido que now_playing)
            $tmdbTrending = tmdbGetTrending($type, $tmdbApiKey, $limit);
            foreach ($tmdbTrending as $item) {
                $itemId = $item['tmdb_id'] ?? $item['id'] ?? null;
                if ($itemId && !isset($seenIds['tmdb_' . $itemId])) {
                    $seenIds['tmdb_' . $itemId] = true;
                    $items[] = $item;
                    if (count($items) >= $limit) {
                        break;
                    }
                }
            }
            scriptOutput("TMDB trending: " . count($tmdbTrending) . " resultados\n");
            
            // Solo si aún necesitamos más y no hemos alcanzado el límite de tiempo
            if (count($items) < $limit && count($items) < $limit) {
                $tmdbPopular = tmdbGetPopular($type, $tmdbApiKey, $limit);
                foreach ($tmdbPopular as $item) {
                    $itemId = $item['tmdb_id'] ?? $item['id'] ?? null;
                    if ($itemId && !isset($seenIds['tmdb_' . $itemId])) {
                        $seenIds['tmdb_' . $itemId] = true;
                        $items[] = $item;
                        if (count($items) >= $limit) {
                            break;
                        }
                    }
                }
                scriptOutput("TMDB popular: " . count($tmdbPopular) . " resultados\n");
            }
        } catch (Exception $e) {
            scriptOutput("Error en TMDB: " . $e->getMessage() . "\n", true);
        }
    } else {
        scriptOutput("TMDB no configurado (TMDB_API_KEY). Obtén una API key gratis en https://www.themoviedb.org/settings/api\n");
        scriptOutput("TMDB es excelente para películas y series, especialmente películas que TVMaze no tiene.\n");
        scriptOutput("⚠️ Sin TMDB, la búsqueda puede ser más lenta y encontrar menos películas.\n");
    }
}

// Fuente 4: Addons (si están disponibles y habilitados)
// Los addons pueden buscar contenido nuevo o contenido que necesita actualización de streams
$enabledAddons = []; // Inicializar variable para uso posterior
scriptOutput("\n=== Buscando en Addons ===\n");
try {
    $addonManager = AddonManager::getInstance();
    $addonManager->loadAddons();
    $activeAddons = $addonManager->getAddons();
    $enabledAddons = array_filter($activeAddons, function($addon) {
        return $addon->isEnabled();
    });
    
    if (!empty($enabledAddons)) {
        scriptOutput("Addons activos encontrados: " . count($enabledAddons) . "\n");
        foreach ($enabledAddons as $addonId => $addon) {
            scriptOutput("  → Buscando en addon: {$addon->getName()} ({$addonId})...\n");
            try {
                // Buscar contenido nuevo usando el addon
                // Los addons pueden buscar por tipo y filtros
                $filters = [
                    'type' => $type,
                    'since_days' => $sinceDays,
                    'limit' => $limit - count($items)
                ];
                
                // Intentar usar hook especial para obtener contenido nuevo/trending
                $hookResults = $addonManager->executeHook('get_new_content', [
                    'type' => $type,
                    'limit' => $limit - count($items),
                    'since_days' => $sinceDays
                ]);
                
                $addonNewContent = $hookResults[$addonId] ?? null;
                
                // Si el hook no devolvió nada, intentar búsqueda con términos especiales
                if (!$addonNewContent && method_exists($addon, 'onSearch')) {
                    scriptOutput("    → Intentando búsqueda con términos especiales (trending/popular/new)...\n");
                    try {
                        // Intentar buscar contenido trending/popular usando onSearch
                        $searchResults = $addon->onSearch('trending', $filters);
                        if (is_array($searchResults) && !empty($searchResults)) {
                            $addonNewContent = $searchResults;
                        } else {
                            // Intentar con "popular"
                            $searchResults = $addon->onSearch('popular', $filters);
                            if (is_array($searchResults) && !empty($searchResults)) {
                                $addonNewContent = $searchResults;
                            } else {
                                // Intentar con "new"
                                $searchResults = $addon->onSearch('new', $filters);
                                if (is_array($searchResults) && !empty($searchResults)) {
                                    $addonNewContent = $searchResults;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        scriptOutput("    → Error en búsqueda especial: " . $e->getMessage() . "\n", true);
                    }
                }
                
                // Si aún no hay contenido, el addon se usará solo para streams
                if (!$addonNewContent) {
                    scriptOutput("    → Addon {$addonId} no tiene método para obtener contenido nuevo (se usará para actualizar streams)\n");
                    continue;
                }
                
                if (is_array($addonNewContent) && !empty($addonNewContent)) {
                    // Normalizar formato
                    $results = isset($addonNewContent['results']) ? $addonNewContent['results'] : $addonNewContent;
                    
                    if (!is_array($results)) {
                        $results = [$results];
                    }
                    
                    foreach ($results as $result) {
                        if (count($items) >= $limit) {
                            break 2; // Salir de ambos loops
                        }
                        
                        // Verificar que el resultado tenga el formato correcto
                        $itemTitle = $result['title'] ?? $result['name'] ?? '';
                        $itemYear = $result['year'] ?? $result['release_year'] ?? null;
                        
                        if (empty($itemTitle)) {
                            continue;
                        }
                        
                        // Evitar duplicados
                        $itemKey = strtolower(trim($itemTitle)) . '_' . ($itemYear ?? '');
                        if (isset($seenIds['addon_' . $itemKey])) {
                            continue;
                        }
                        
                        $seenIds['addon_' . $itemKey] = true;
                        
                        // Normalizar formato del resultado del addon
                        $normalizedItem = [
                            'id' => $result['id'] ?? null,
                            'name' => $itemTitle,
                            'title' => $itemTitle,
                            'premiered' => $result['premiered'] ?? ($itemYear ? $itemYear . '-01-01' : null),
                            'release_year' => $itemYear,
                            'year' => $itemYear,
                            'type' => $result['type'] ?? $type,
                            'image' => [
                                'original' => $result['poster_url'] ?? $result['poster'] ?? $result['image'] ?? '',
                                'medium' => $result['poster_url'] ?? $result['poster'] ?? $result['image'] ?? ''
                            ],
                            'poster' => $result['poster_url'] ?? $result['poster'] ?? $result['image'] ?? '',
                            'backdrop' => $result['backdrop_url'] ?? $result['backdrop'] ?? '',
                            'summary' => $result['description'] ?? $result['overview'] ?? $result['summary'] ?? '',
                            'description' => $result['description'] ?? $result['overview'] ?? $result['summary'] ?? '',
                            'rating' => [
                                'average' => $result['rating'] ?? null
                            ],
                            'genres' => $result['genres'] ?? [],
                            'runtime' => $result['duration'] ?? $result['runtime'] ?? ($type === 'tv' ? 45 : 100),
                            'addon_source' => $addonId,
                            'addon_data' => $result // Guardar datos originales del addon
                        ];
                        
                        $items[] = $normalizedItem;
                        scriptOutput("    ✓ Encontrado: {$itemTitle}" . ($itemYear ? " ({$itemYear})" : "") . "\n");
                    }
                    
                    scriptOutput("  → Addon {$addonId}: " . count($results) . " resultados\n");
                } else {
                    scriptOutput("  → Addon {$addonId}: Sin contenido nuevo disponible\n");
                }
            } catch (Exception $e) {
                scriptOutput("  ✗ Error en addon {$addonId}: " . $e->getMessage() . "\n", true);
            }
        }
        
        $addonItemsCount = count(array_filter($items, function($item) {
            return isset($item['addon_source']);
        }));
        scriptOutput("Total de items desde addons: {$addonItemsCount}\n");
    } else {
        scriptOutput("No hay addons activos configurados.\n");
        scriptOutput("Puedes instalar addons desde el panel de administración.\n");
        scriptOutput("Nota: Los addons se usarán principalmente para obtener streams de contenido existente.\n");
    }
} catch (Exception $e) {
    scriptOutput("Error cargando addons: " . $e->getMessage() . "\n", true);
}

// Fuente 5: OMDb como último recurso (solo si tenemos API key y aún no hay resultados)
if ((empty($items) || count($items) < $limit) && !empty($omdbKey) && $omdbKey !== 'demo') {
    scriptOutput("\n=== Buscando en OMDb (último recurso) ===\n");
    scriptOutput("OMDb tiene limitaciones pero puede ayudar a encontrar contenido adicional...\n");
    // OMDb no tiene endpoints de búsqueda masiva, así que solo lo usamos para completar datos
    // No agregamos búsqueda aquí ya que requiere título específico
}

if (empty($items)) {
    scriptOutput("\n========================================\n");
    scriptOutput("⚠️ NO SE ENCONTRARON RESULTADOS\n");
    scriptOutput("========================================\n");
    scriptOutput("No se encontraron resultados de ninguna fuente con los criterios especificados.\n\n");
    scriptOutput("Resumen de búsqueda:\n");
    scriptOutput("  - Trakt.tv: " . (!empty($traktClientId) ? "Configurado" : "No configurado") . "\n");
    scriptOutput("  - TVMaze: " . (function_exists('httpJson') ? "Disponible" : "No disponible") . "\n");
    scriptOutput("  - TMDB: " . (!empty($tmdbApiKey) ? "Configurado" : "No configurado") . "\n");
    scriptOutput("  - Addons: " . (count($enabledAddons ?? []) > 0 ? count($enabledAddons) . " activos" : "Ninguno activo") . "\n\n");
    scriptOutput("Sugerencias para encontrar más contenido:\n");
    scriptOutput("  1. Configura TMDB_API_KEY (gratis en https://www.themoviedb.org/settings/api)\n");
    scriptOutput("  2. Configura TRAKT_CLIENT_ID (gratis en https://trakt.tv/oauth/applications)\n");
    scriptOutput("  3. Aumenta el valor de 'Últimos días' (ej: 30 o 365)\n");
    scriptOutput("  4. Reduce el valor de 'Mínimo de seeds' (ej: 5 o 10)\n");
    scriptOutput("  5. Instala y activa addons desde el panel de administración\n");
    scriptOutput("  6. Verifica tu conexión a internet\n\n");
    scriptOutput("========================================\n");
    scriptOutput("RESULTADO FINAL\n");
    scriptOutput("========================================\n");
    scriptOutput("Creados: 0, actualizados: 0, episodios nuevos: 0\n");
    scriptOutput("========================================\n");
    if ($isCli) {
        exit(0);
    }
    // En web, continuar para que el API pueda parsear la salida
}

scriptOutput("\n========================================\n");
scriptOutput("CONTENIDO ENCONTRADO\n");
scriptOutput("========================================\n");
scriptOutput("Se encontraron " . count($items) . " items para procesar\n");
scriptOutput("Iniciando procesamiento...\n");
scriptOutput("========================================\n\n");

$created = 0;
$updated = 0;
$newEpisodes = 0;

foreach ($items as $show) {
    // Detectar si viene de Trakt.tv, TMDB, TVMaze o Addon
    $isTrakt = isset($show['trakt_data']);
    $isTMDB = isset($show['tmdb_data']);
    $isFromAddon = isset($show['addon_source']);
    
    if ($isFromAddon) {
        // Datos del addon (ya formateados)
        $title = $show['name'] ?? $show['title'] ?? 'Sin título';
        $premiered = $show['premiered'] ?? '';
        $releaseYear = $show['release_year'] ?? $show['year'] ?? null;
        $runtime = $show['runtime'] ?? ($type === 'tv' ? 45 : 100);
        $poster = $show['poster'] ?? $show['poster_url'] ?? '';
        $backdrop = $show['backdrop'] ?? $show['backdrop_url'] ?? '';
        $rating = $show['rating'] ?? null;
        $genres = $show['genres'] ?? [];
        $description = $show['description'] ?? $show['overview'] ?? $show['summary'] ?? '';
        $tvmazeId = null;
        
        // Si el contenido viene del addon y tiene ID, puede que ya exista en la BD
        // En ese caso, solo necesitamos actualizar los streams
        if (!empty($show['id']) && is_numeric($show['id'])) {
            // El contenido ya existe, solo actualizar streams después
            scriptOutput("  → Contenido del addon ya existe (ID: {$show['id']}), se actualizarán streams si es necesario\n");
        }
    } elseif ($isTrakt) {
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
    } elseif ($isTMDB) {
        // Datos de TMDB
        $tmdbData = $show['tmdb_data'];
        $title = $show['name'] ?? $tmdbData['title'] ?? $tmdbData['name'] ?? 'Sin título';
        $premiered = $show['premiered'] ?? '';
        $releaseYear = $premiered ? (int)substr($premiered, 0, 4) : null;
        $runtime = isset($tmdbData['runtime']) ? (int)$tmdbData['runtime'] : (isset($tmdbData['episode_run_time'][0]) ? (int)$tmdbData['episode_run_time'][0] : ($type === 'tv' ? 45 : 100));
        $poster = !empty($show['image']['original']) 
            ? $show['image']['original'] 
            : (!empty($show['image']['medium']) ? $show['image']['medium'] : '');
        if (empty($poster)) {
            $poster = getPosterImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        }
        $backdrop = !empty($show['backdrop']) 
            ? $show['backdrop'] 
            : getBackdropImage($title, $type === 'tv' ? 'series' : 'movie', $releaseYear);
        $rating = isset($show['rating']['average']) ? round((float)$show['rating']['average'], 1) : null;
        // TMDB devuelve IDs de géneros, necesitamos convertirlos a nombres
        $genres = [];
        if (!empty($tmdbData['genre_ids']) && is_array($tmdbData['genre_ids'])) {
            // Mapeo básico de IDs de TMDB a nombres (se puede mejorar obteniendo la lista completa)
            $genreMap = [
                28 => 'Action', 12 => 'Adventure', 16 => 'Animation', 35 => 'Comedy',
                80 => 'Crime', 99 => 'Documentary', 18 => 'Drama', 10751 => 'Family',
                14 => 'Fantasy', 36 => 'History', 27 => 'Horror', 10402 => 'Music',
                9648 => 'Mystery', 10749 => 'Romance', 878 => 'Science Fiction',
                10770 => 'TV Movie', 53 => 'Thriller', 10752 => 'War', 37 => 'Western'
            ];
            foreach ($tmdbData['genre_ids'] as $genreId) {
                if (isset($genreMap[$genreId])) {
                    $genres[] = $genreMap[$genreId];
                }
            }
        }
        $description = $show['summary'] ?? $tmdbData['overview'] ?? '';
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
            scriptOutput("Sin detalles TVMaze para ID {$tvmazeId}\n", true);
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
    
    // Inicializar OMDB (se usa en varios puntos)
    $omdb = null;

    // OMDB para completar (opcional) - solo si no tenemos descripción
    if (empty($description)) {
        $omdb = omdbFetch($title, $releaseYear ? (string)$releaseYear : null, $type, $omdbKey);
        if ($omdb) {
            $description = $omdb['Plot'] ?? '';
        }
    }
    // Resolver imdbId (para embeds/episodios)
    $imdbId = resolveImdbId($title, $releaseYear, $type, $omdb);
    // Limpiar HTML de la descripción
    $description = strip_tags($description);
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    
    if ($omdb && (empty($rating) || $rating === 0.0)) {
        $imdbRating = $omdb['imdbRating'] ?? '';
        if ($imdbRating !== 'N/A' && $imdbRating !== '') {
            $rating = (float)$imdbRating;
        }
    }
    
    $slug = slugify($title . '-' . ($releaseYear ?? 'na') . '-' . $type);
    $genreIds = !empty($genres) ? ensureGenres($db, $genres) : [];

    // ========== PRIORIDAD DE BÚSQUEDA ==========
    // 1. Poster (ya obtenido arriba)
    // 2. Video URL directo (streaming)
    // 3. Torrent (si no hay video URL)
    // 4. Trailer de YouTube (si no hay video URL ni torrent)
    
    scriptOutput("  Buscando enlaces de video para: {$title}...\n");
    
    // 2. Buscar video URL directo (streaming) - Filemoon, Streamtape, Upstream, etc.
    if (!empty($imdbId)) {
        scriptOutput("  → IMDb ID encontrado: {$imdbId}, buscando enlaces de streaming (vidsrc/filemoon/streamtape)...\n");
    } else {
        scriptOutput("  → Intentando obtener IMDb ID para buscar enlaces de streaming...\n");
    }
    $videoUrl = searchVideoUrl($title, $type, $releaseYear, $omdb, $imdbId);
    
    // 3. Buscar torrents (solo si no hay video URL)
    $torrents = [];
    $bestTorrent = null;
    $hasTorrent = false;
    if (empty($videoUrl)) {
        scriptOutput("  No se encontró video URL directo, buscando torrents en múltiples fuentes...\n");
        $torrents = [];
        
        // Fuentes con API (más rápidas)
        scriptOutput("  → Buscando en Torrentio...\n");
        $torrents = array_merge($torrents, searchTorrentio($title, $type, $releaseYear ? (string)$releaseYear : null, $torrentioBase));
        
        if ($type === 'movie') {
            scriptOutput("  → Buscando en YTS...\n");
            $torrents = array_merge($torrents, searchYTS($title, $releaseYear ? (string)$releaseYear : null));
        } else {
            scriptOutput("  → Buscando en EZTV...\n");
            $torrents = array_merge($torrents, searchEZTV($title));
        }
        
        scriptOutput("  → Buscando en The Pirate Bay...\n");
        $torrents = array_merge($torrents, searchTPB($title));
        
        // Fuentes con scraping web (sin API, más lentas pero más opciones)
        scriptOutput("  → Buscando en 1337x (web scraping)...\n");
        $torrents = array_merge($torrents, search1337xWeb($title, $type));
        
        scriptOutput("  → Buscando en RARBG (web scraping)...\n");
        $torrents = array_merge($torrents, searchRARBGWeb($title, $releaseYear ? (string)$releaseYear : null));
        
        scriptOutput("  → Buscando en LimeTorrents (web scraping)...\n");
        $torrents = array_merge($torrents, searchLimeTorrentsWeb($title, $type));
        
        scriptOutput("  → Buscando en Torlock (web scraping)...\n");
        $torrents = array_merge($torrents, searchTorlockWeb($title, $type));
        
        scriptOutput("  → Buscando en TorrentGalaxy (web scraping)...\n");
        $torrents = array_merge($torrents, searchTorrentGalaxyWeb($title, $type));
        
        scriptOutput("  → Buscando en Zooqle (web scraping)...\n");
        $torrents = array_merge($torrents, searchZooqleWeb($title, $type));
        
        scriptOutput("  → Total de torrents encontrados: " . count($torrents) . "\n");
        
        $bestTorrent = pickBestTorrent($torrents, $minSeeds);
        $hasTorrent = !empty($bestTorrent) && !empty($bestTorrent['magnet']);
        if ($hasTorrent) {
            scriptOutput("  ✓ Mejor torrent seleccionado: " . ($bestTorrent['title'] ?? 'N/A') . " (seeds: " . ($bestTorrent['seeds'] ?? 0) . ", fuente: " . ($bestTorrent['source'] ?? 'N/A') . ")\n");
        } else {
            scriptOutput("  ✗ No se encontraron torrents válidos (con al menos {$minSeeds} seeds)\n");
        }
        
        // Si aún no hay torrent, intentar obtener desde addons
        if (!$hasTorrent && empty($videoUrl)) {
            scriptOutput("  → Intentando obtener streams desde addons...\n");
            try {
                $addonManager = AddonManager::getInstance();
                $addonManager->loadAddons();
                
                // Buscar streams usando el título e IMDb ID
                $addonStreams = [];
                if (!empty($imdbId)) {
                    // Si tenemos IMDb ID, podemos buscar directamente en addons
                    // Nota: getStreams requiere contentId, pero podemos intentar buscar por título
                    scriptOutput("    → Buscando streams con IMDb ID: {$imdbId}\n");
                }
                
                // Buscar en addons activos
                $activeAddons = $addonManager->getAddons();
                foreach ($activeAddons as $addonId => $addon) {
                    if ($addon->isEnabled() && method_exists($addon, 'onGetStreams')) {
                        try {
                            // Nota: onGetStreams requiere contentId que aún no tenemos
                            // Pero algunos addons pueden tener métodos alternativos
                            // Por ahora, intentamos después de crear el contenido
                            scriptOutput("    → Addon {$addonId} disponible para streams (se usará después de crear el contenido)\n");
                        } catch (Exception $e) {
                            // Ignorar errores aquí
                        }
                    }
                }
            } catch (Exception $e) {
                scriptOutput("    ✗ Error obteniendo streams de addons: " . $e->getMessage() . "\n", true);
            }
        }
    } else {
        scriptOutput("  ✓ Video URL encontrado: {$videoUrl}\n");
    }
    
    // 4. Buscar trailer de YouTube (solo si no hay video URL ni torrent)
    $trailer = '';
    if (empty($videoUrl) && !$hasTorrent) {
        scriptOutput("  No hay video URL ni torrent, buscando trailer de YouTube...\n");
        $trailer = searchYouTubeTrailer($title, $type, $releaseYear);
        if (!empty($trailer)) {
            scriptOutput("  ✓ Trailer encontrado: {$trailer}\n");
        } else {
            scriptOutput("  ✗ No se encontró trailer de YouTube\n");
        }
    }

    // Asegurar que release_year nunca sea null (requerido por la base de datos)
    if (empty($releaseYear) || $releaseYear === null) {
        // Intentar extraer el año del título o usar el año actual
        if (preg_match('/\((\d{4})\)/', $title, $matches)) {
            $releaseYear = (int)$matches[1];
        } elseif (preg_match('/(\d{4})/', $title, $matches)) {
            $year = (int)$matches[1];
            if ($year >= 1900 && $year <= date('Y') + 1) {
                $releaseYear = $year;
            }
        }
        // Si aún no hay año, usar el año actual
        if (empty($releaseYear) || $releaseYear === null) {
            $releaseYear = (int)date('Y');
        }
        scriptOutput("  ⚠ Año no encontrado para '{$title}', usando {$releaseYear} como valor por defecto\n");
    }
    
    $contentData = [
        'title' => $title,
        'slug' => $slug,
        'type' => $type === 'tv' ? 'series' : 'movie',
        'description' => $description,
        'release_year' => (int)$releaseYear, // Asegurar que sea un entero
        'duration' => $runtime,
        'rating' => $rating,
        'age_rating' => null,
        'poster_url' => $poster,
        'backdrop_url' => $backdrop,
        'trailer_url' => $trailer,
        'video_url' => $videoUrl,
        'torrent_magnet' => $bestTorrent['magnet'] ?? null,
        'is_featured' => 0,
        'is_trending' => 1,
        'is_premium' => 0
    ];

    if ($dryRun) {
        scriptOutput("[DRY-RUN] {$title} ({$contentData['release_year']})\n");
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
    
    // Intentar obtener streams desde addons después de crear el contenido
    // Esto es útil si no encontramos video URL ni torrent en las fuentes tradicionales
    // O si el contenido viene de un addon y necesita streams
    $needsAddonStreams = $isFromAddon && isset($show['needs_streams']) && $show['needs_streams'];
    if ((empty($videoUrl) && !$hasTorrent && !empty($contentId)) || $needsAddonStreams) {
        scriptOutput("  → Intentando obtener streams desde addons para el contenido creado...\n");
        try {
            $addonManager = AddonManager::getInstance();
            $addonManager->loadAddons();
            
            $contentTypeForAddon = $type === 'tv' ? 'series' : 'movie';
            $allStreams = $addonManager->getStreams($contentId, $contentTypeForAddon, null);
            
            if (!empty($allStreams)) {
                $addonStreamFound = false;
                foreach ($allStreams as $addonId => $streams) {
                    if (is_array($streams) && !empty($streams)) {
                        foreach ($streams as $stream) {
                            if (!empty($stream['url'])) {
                                scriptOutput("    ✓ Stream encontrado desde addon {$addonId}: " . substr($stream['url'], 0, 80) . "...\n");
                                
                                // Actualizar el contenido con el stream del addon
                                if (!$addonStreamFound) {
                                    $streamUrl = $stream['url'];
                                    $streamType = $stream['type'] ?? 'direct';
                                    
                                    // Si es torrent, actualizar torrent_magnet
                                    if ($streamType === 'torrent' || strpos($streamUrl, 'magnet:') === 0) {
                                        $updateStmt = $db->prepare("UPDATE content SET torrent_magnet = ? WHERE id = ?");
                                        $updateStmt->execute([$streamUrl, $contentId]);
                                        scriptOutput("    → Torrent del addon guardado en la base de datos\n");
                                    } else {
                                        // Si es video directo, actualizar video_url
                                        $updateStmt = $db->prepare("UPDATE content SET video_url = ? WHERE id = ?");
                                        $updateStmt->execute([$streamUrl, $contentId]);
                                        scriptOutput("    → Video URL del addon guardado en la base de datos\n");
                                    }
                                    
                                    $addonStreamFound = true;
                                    break 2; // Salir de ambos loops
                                }
                            }
                        }
                    }
                }
                
                if (!$addonStreamFound) {
                    scriptOutput("    → No se encontraron streams válidos en los addons\n");
                }
            } else {
                scriptOutput("    → Los addons no devolvieron streams para este contenido\n");
            }
        } catch (Exception $e) {
            scriptOutput("    ✗ Error obteniendo streams de addons: " . $e->getMessage() . "\n", true);
        }
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
                    $epEmbed = null;

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

                    // Embed directo por episodio (si tenemos imdbId)
                    if (!empty($imdbId)) {
                        $episodeEmbeds = buildEmbedProviders($imdbId, 'tv', $seasonNum, $episodeNum);
                        $epEmbed = $episodeEmbeds[0] ?? null;
                    }

                    $epDesc = $ep['summary'] ?? '';
                    $epDesc = strip_tags($epDesc);
                    $epDesc = html_entity_decode($epDesc, ENT_QUOTES, 'UTF-8');

                    $epData = [
                        'title' => $ep['name'] ?? "{$title} S{$seasonNum}E{$episodeNum}",
                        'description' => $epDesc,
                        'duration' => $epDuration,
                        'video_url' => $epEmbed ?: $epMagnet,
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
    scriptOutput("Procesado: {$title}\n");
}

scriptOutput("\n========================================\n");
scriptOutput("PROCESAMIENTO COMPLETADO\n");
scriptOutput("========================================\n");
scriptOutput("✅ Listo. Creados: {$created}, actualizados: {$updated}, episodios nuevos: {$newEpisodes}\n");
scriptOutput("========================================\n");
