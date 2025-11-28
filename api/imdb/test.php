<?php
/**
 * Script de verificación para APIs de información de películas/series
 * Verifica que todas las fuentes funcionen correctamente
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

$testResults = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'summary' => [
        'total' => 0,
        'passed' => 0,
        'failed' => 0
    ]
];

// Test 1: OMDB API
function testOMDB() {
    global $testResults;
    $testName = 'OMDB API (IMDb)';
    $testResults['summary']['total']++;
    
    try {
        $apiKey = getenv('OMDB_API_KEY') ?: (defined('OMDB_API_KEY') ? OMDB_API_KEY : 'demo');
        $url = "http://www.omdbapi.com/?apikey={$apiKey}&t=The+Matrix&y=1999";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['Response']) && $data['Response'] === 'True') {
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => 'passed',
                    'message' => 'OMDB API funciona correctamente',
                    'data' => [
                        'title' => $data['Title'] ?? 'N/A',
                        'rating' => $data['imdbRating'] ?? 'N/A',
                        'api_key_status' => $apiKey === 'demo' || empty($apiKey) ? 'no configurada (usar demo)' : 'configurada'
                    ]
                ];
                $testResults['summary']['passed']++;
                return true;
            } else {
                $errorMsg = $data['Error'] ?? 'Respuesta inválida';
                $status = ($httpCode === 401 || $httpCode === 403) ? 'warning' : 'failed';
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => $status,
                    'message' => $errorMsg . ($httpCode === 401 ? ' (API key requerida o inválida)' : ''),
                    'data' => [
                        'api_key' => empty($apiKey) || $apiKey === 'demo' ? 'no configurada' : 'configurada',
                        'http_code' => $httpCode
                    ]
                ];
                if ($status === 'failed') {
                    $testResults['summary']['failed']++;
                } else {
                    $testResults['summary']['passed']++;
                }
                return false;
            }
        } else {
            $status = ($httpCode === 401 || $httpCode === 403) ? 'warning' : 'failed';
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => $status,
                'message' => "HTTP {$httpCode}: " . ($error ?: 'Sin respuesta') . ($httpCode === 401 ? ' (API key requerida)' : ''),
                'data' => ['http_code' => $httpCode]
            ];
            if ($status === 'failed') {
                $testResults['summary']['failed']++;
            } else {
                $testResults['summary']['passed']++;
            }
            return false;
        }
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => $testName,
            'status' => 'failed',
            'message' => $e->getMessage(),
            'data' => []
        ];
        $testResults['summary']['failed']++;
        return false;
    }
}

// Test 2: TMDB API
function testTMDB() {
    global $testResults;
    $testName = 'TMDB API';
    $testResults['summary']['total']++;
    
    try {
        $apiKey = getenv('TMDB_API_KEY') ?: (defined('TMDB_API_KEY') ? TMDB_API_KEY : '');
        
        if (empty($apiKey)) {
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => 'skipped',
                'message' => 'TMDB API key no configurada (opcional)',
                'data' => []
            ];
            return null;
        }
        
        $url = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query=The+Matrix";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['results'])) {
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => 'passed',
                    'message' => 'TMDB API funciona correctamente',
                    'data' => [
                        'results_count' => count($data['results']),
                        'first_result' => $data['results'][0]['title'] ?? 'N/A'
                    ]
                ];
                $testResults['summary']['passed']++;
                return true;
            } else {
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => 'failed',
                    'message' => 'No se encontraron resultados',
                    'data' => []
                ];
                $testResults['summary']['failed']++;
                return false;
            }
        } else {
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => 'failed',
                'message' => "HTTP {$httpCode}: " . ($error ?: 'Sin respuesta'),
                'data' => []
            ];
            $testResults['summary']['failed']++;
            return false;
        }
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => $testName,
            'status' => 'failed',
            'message' => $e->getMessage(),
            'data' => []
        ];
        $testResults['summary']['failed']++;
        return false;
    }
}

// Test 3: Base de datos local
function testLocalDB() {
    global $testResults;
    $testName = 'Base de Datos Local';
    $testResults['summary']['total']++;
    
    try {
        $db = getDbConnection();
        $query = "SELECT COUNT(*) as count FROM content WHERE rating IS NOT NULL AND rating > 0 LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['count'] > 0) {
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => 'passed',
                'message' => 'Base de datos local funciona correctamente',
                'data' => [
                    'content_with_rating' => (int)$result['count']
                ]
            ];
            $testResults['summary']['passed']++;
            return true;
        } else {
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => 'warning',
                'message' => 'Base de datos funciona pero no hay contenido con rating',
                'data' => []
            ];
            $testResults['summary']['passed']++;
            return true;
        }
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => $testName,
            'status' => 'failed',
            'message' => $e->getMessage(),
            'data' => []
        ];
        $testResults['summary']['failed']++;
        return false;
    }
}

// Test 4: Endpoint de búsqueda
function testSearchEndpoint() {
    global $testResults;
    $testName = 'Endpoint de Búsqueda IMDb';
    $testResults['summary']['total']++;
    
    try {
        $baseUrl = rtrim(SITE_URL, '/');
        $url = "{$baseUrl}/api/imdb/search.php?title=The+Matrix&year=1999&type=movie";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['success'])) {
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => $data['success'] ? 'passed' : 'warning',
                    'message' => $data['success'] ? 'Endpoint funciona correctamente' : ($data['error'] ?? 'Sin resultados'),
                    'data' => [
                        'source' => $data['data']['source'] ?? 'unknown',
                        'has_rating' => !empty($data['data']['imdb_rating'])
                    ]
                ];
                if ($data['success']) {
                    $testResults['summary']['passed']++;
                } else {
                    $testResults['summary']['failed']++;
                }
                return $data['success'];
            } else {
                $testResults['tests'][] = [
                    'name' => $testName,
                    'status' => 'failed',
                    'message' => 'Respuesta inválida del endpoint',
                    'data' => ['response' => substr($response, 0, 200)]
                ];
                $testResults['summary']['failed']++;
                return false;
            }
        } else {
            $testResults['tests'][] = [
                'name' => $testName,
                'status' => 'failed',
                'message' => "HTTP {$httpCode}: " . ($error ?: 'Sin respuesta'),
                'data' => []
            ];
            $testResults['summary']['failed']++;
            return false;
        }
    } catch (Exception $e) {
        $testResults['tests'][] = [
            'name' => $testName,
            'status' => 'failed',
            'message' => $e->getMessage(),
            'data' => []
        ];
        $testResults['summary']['failed']++;
        return false;
    }
}

// Ejecutar todos los tests
testOMDB();
testTMDB();
testLocalDB();
testSearchEndpoint();

// Añadir recomendaciones
$testResults['recommendations'] = [];

if ($testResults['summary']['failed'] > 0) {
    $testResults['recommendations'][] = 'Algunas APIs no están funcionando. Revisa las configuraciones.';
}

$omdbTest = array_filter($testResults['tests'], fn($t) => $t['name'] === 'OMDB API (IMDb)');
if (!empty($omdbTest)) {
    $omdb = reset($omdbTest);
    if ($omdb['status'] === 'failed' || (isset($omdb['data']['api_key_status']) && strpos($omdb['data']['api_key_status'], 'demo') !== false)) {
        $testResults['recommendations'][] = 'Configura una API key de OMDB en las variables de entorno (OMDB_API_KEY) para mejor funcionalidad. Obtén una gratis en: http://www.omdbapi.com/apikey.aspx';
    }
}

$tmdbTest = array_filter($testResults['tests'], fn($t) => $t['name'] === 'TMDB API');
if (!empty($tmdbTest)) {
    $tmdb = reset($tmdbTest);
    if ($tmdb['status'] === 'skipped') {
        $testResults['recommendations'][] = 'Configura una API key de TMDB (opcional) como alternativa a OMDB. Obtén una gratis en: https://www.themoviedb.org/settings/api';
    }
}

echo json_encode($testResults, JSON_PRETTY_PRINT);

