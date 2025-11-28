<?php
/**
 * API para buscar información de películas y series
 * Usa múltiples fuentes con fallback: OMDB (IMDb), TMDB, y datos locales
 */
require_once __DIR__ . '/../../includes/config.php';

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

// Función para buscar en OMDB (IMDb)
function searchOMDB($title, $year, $type) {
    $apiKey = getenv('OMDB_API_KEY') ?: (defined('OMDB_API_KEY') ? OMDB_API_KEY : '');
    
    // Si no hay API key configurada, saltar OMDB
    if (empty($apiKey) || $apiKey === 'demo') {
        return null;
    }
    
    $query = urlencode($title);
    $url = "http://www.omdbapi.com/?apikey={$apiKey}&t={$query}";
    
    if (!empty($year)) {
        $url .= "&y={$year}";
    }
    
    if ($type === 'series') {
        $url .= "&type=series";
    } else {
        $url .= "&type=movie";
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Si hay error de autenticación (401) o límite alcanzado, retornar null
    if ($httpCode === 401 || $httpCode === 403) {
        return null;
    }
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['Response']) && $data['Response'] === 'True') {
            return [
                'title' => $data['Title'] ?? $title,
                'year' => $data['Year'] ?? $year,
                'rated' => $data['Rated'] ?? '',
                'released' => $data['Released'] ?? '',
                'runtime' => $data['Runtime'] ?? '',
                'genre' => $data['Genre'] ?? '',
                'director' => $data['Director'] ?? '',
                'writer' => $data['Writer'] ?? '',
                'actors' => $data['Actors'] ?? '',
                'plot' => $data['Plot'] ?? '',
                'language' => $data['Language'] ?? '',
                'country' => $data['Country'] ?? '',
                'awards' => $data['Awards'] ?? '',
                'poster' => $data['Poster'] ?? '',
                'imdb_rating' => $data['imdbRating'] ?? '',
                'imdb_votes' => $data['imdbVotes'] ?? '',
                'imdb_id' => $data['imdbID'] ?? '',
                'type' => $data['Type'] ?? $type,
                'metascore' => $data['Metascore'] ?? '',
                'source' => 'omdb'
            ];
        }
    }
    return null;
}

// Función para buscar en TMDB (The Movie Database) - alternativa gratuita
function searchTMDB($title, $year, $type) {
    $apiKey = getenv('TMDB_API_KEY') ?: (defined('TMDB_API_KEY') ? TMDB_API_KEY : '');
    if (empty($apiKey)) {
        return null; // TMDB requiere API key
    }
    
    $searchType = $type === 'series' ? 'tv' : 'movie';
    $url = "https://api.themoviedb.org/3/search/{$searchType}?api_key={$apiKey}&query=" . urlencode($title);
    
    if (!empty($year)) {
        $url .= "&year={$year}";
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (!empty($data['results']) && isset($data['results'][0])) {
            $result = $data['results'][0];
            return [
                'title' => $result['title'] ?? $result['name'] ?? $title,
                'year' => substr($result['release_date'] ?? $result['first_air_date'] ?? $year, 0, 4),
                'plot' => $result['overview'] ?? '',
                'poster' => $result['poster_path'] ? 'https://image.tmdb.org/t/p/w500' . $result['poster_path'] : '',
                'imdb_rating' => $result['vote_average'] ?? '',
                'imdb_votes' => $result['vote_count'] ?? '',
                'type' => $type,
                'source' => 'tmdb'
            ];
        }
    }
    return null;
}

// Función para buscar en la base de datos local
function searchLocal($title, $year, $type) {
    try {
        $db = getDbConnection();
        // Buscar coincidencia exacta primero, luego parcial
        $queries = [
            [
                'query' => "SELECT id, title, release_year, rating, poster_url, description, backdrop_url, genre FROM content WHERE title = :title AND type = :type",
                'params' => [':title' => $title, ':type' => $type]
            ],
            [
                'query' => "SELECT id, title, release_year, rating, poster_url, description, backdrop_url, genre FROM content WHERE title LIKE :title AND type = :type",
                'params' => [':title' => '%' . $title . '%', ':type' => $type]
            ]
        ];
        
        // Añadir filtro de año si está disponible
        foreach ($queries as &$q) {
            if (!empty($year)) {
                $q['query'] .= " AND release_year = :year";
                $q['params'][':year'] = $year;
            }
            $q['query'] .= " ORDER BY rating DESC, views DESC LIMIT 1";
        }
        
        foreach ($queries as $q) {
            $stmt = $db->prepare($q['query']);
            $stmt->execute($q['params']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Obtener géneros si están disponibles
                $genres = [];
                if (!empty($result['id'])) {
                    $genreStmt = $db->prepare("
                        SELECT g.name 
                        FROM genres g 
                        INNER JOIN content_genres cg ON g.id = cg.genre_id 
                        WHERE cg.content_id = :id
                    ");
                    $genreStmt->execute([':id' => $result['id']]);
                    $genres = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                return [
                    'title' => $result['title'] ?? $title,
                    'year' => $result['release_year'] ?? $year,
                    'plot' => $result['description'] ?? '',
                    'poster' => $result['poster_url'] ?? '',
                    'backdrop' => $result['backdrop_url'] ?? '',
                    'imdb_rating' => $result['rating'] ? (string)$result['rating'] : '',
                    'genre' => !empty($genres) ? implode(', ', $genres) : ($result['genre'] ?? ''),
                    'type' => $type,
                    'source' => 'local',
                    'content_id' => $result['id'] ?? null
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error en searchLocal: " . $e->getMessage());
    }
    return null;
}

try {
    $result = null;
    $errors = [];
    $sourcesTried = [];
    
    // Estrategia: Intentar BD local primero (más rápido y confiable)
    // Luego intentar APIs externas si no hay resultados locales
    
    // 1. Buscar en BD local primero
    try {
        $result = searchLocal($title, $year, $type);
        if ($result && !empty($result['imdb_rating'])) {
            $sourcesTried[] = 'local';
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Información obtenida de la base de datos local'
            ]);
            exit;
        }
    } catch (Exception $e) {
        $errors[] = 'Local: ' . $e->getMessage();
    }
    
    // 2. Si no hay rating local, intentar OMDB (IMDb)
    try {
        $result = searchOMDB($title, $year, $type);
        if ($result) {
            $sourcesTried[] = 'omdb';
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Información obtenida de OMDB (IMDb)'
            ]);
            exit;
        }
    } catch (Exception $e) {
        $errors[] = 'OMDB: ' . $e->getMessage();
    }
    
    // 3. Si OMDB falla, intentar TMDB
    try {
        $result = searchTMDB($title, $year, $type);
        if ($result) {
            $sourcesTried[] = 'tmdb';
            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Información obtenida de TMDB'
            ]);
            exit;
        }
    } catch (Exception $e) {
        $errors[] = 'TMDB: ' . $e->getMessage();
    }
    
    // 4. Si hay datos locales sin rating, usarlos
    if ($result && $result['source'] === 'local') {
        echo json_encode([
            'success' => true,
            'data' => $result,
            'message' => 'Información obtenida de la base de datos local (sin rating)'
        ]);
        exit;
    }
    
    // 5. Si todo falla, devolver datos básicos con información útil
    echo json_encode([
        'success' => false,
        'error' => 'No se encontró información completa en ninguna fuente disponible',
        'errors' => $errors,
        'sources_tried' => $sourcesTried,
        'data' => [
            'title' => $title,
            'year' => $year,
            'poster' => '',
            'imdb_rating' => '',
            'source' => 'none',
            'message' => 'Configura una API key de OMDB o TMDB para obtener más información'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar información: ' . $e->getMessage()
    ]);
}
?>

