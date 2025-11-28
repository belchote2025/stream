<?php
/**
 * API para buscar información de películas y series en IMDb
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

try {
    // Usar OMDB API (gratuita con límite, pero funciona bien)
    // Nota: Necesitarás una API key gratuita de OMDB: http://www.omdbapi.com/apikey.aspx
    $apiKey = getenv('OMDB_API_KEY') ?: 'demo'; // Usar 'demo' para pruebas, pero limitado
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        if (isset($data['Response']) && $data['Response'] === 'True') {
            // Formatear respuesta
            $result = [
                'success' => true,
                'data' => [
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
                    'metascore' => $data['Metascore'] ?? ''
                ]
            ];
            
            echo json_encode($result);
        } else {
            // Si no se encuentra en OMDB, devolver datos básicos
            echo json_encode([
                'success' => false,
                'error' => $data['Error'] ?? 'No se encontró información en IMDb',
                'data' => [
                    'title' => $title,
                    'year' => $year,
                    'poster' => ''
                ]
            ]);
        }
    } else {
        throw new Exception('Error al conectar con la API de IMDb');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al buscar información: ' . $e->getMessage()
    ]);
}
?>

