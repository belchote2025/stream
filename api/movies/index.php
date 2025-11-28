<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

require_once __DIR__ . '/../middleware/auth.php'; // Middleware de autenticación

// Obtener conexión a la base de datos
$db = getDbConnection();
// Obtener el método de la petición
$method = $_SERVER['REQUEST_METHOD'];

// Obtener el ID de la película de la URL si existe
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);
$movieId = isset($uri[array_search('movies', $uri) + 1]) ? $uri[array_search('movies', $uri) + 1] : null;

// Manejar la solicitud según el método
switch ($method) {
    case 'GET':
        // Obtener una película específica o todas las películas
        requirePermission('view_content'); // Requiere permiso para ver
        if ($movieId) {
            getMovie($movieId);
        } else {
            getMovies();
        }
        break;
    
    case 'POST':
        // Crear una nueva película
        requireRole('admin'); // Solo administradores
        createMovie();
        break;
    
    case 'PUT':
        // Actualizar una película existente
        requireRole('admin'); // Solo administradores
        if ($movieId) {
            updateMovie($movieId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere un ID de película']);
        }
        break;
    
    case 'DELETE':
        // Eliminar una película
        requireRole('admin'); // Solo administradores
        if ($movieId) {
            deleteMovie($movieId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere un ID de película']);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}

// Obtener todas las películas
function getMovies() {
    global $db;
    
    try {
        $query = "SELECT * FROM content WHERE type = 'movie' ORDER BY created_at DESC";
        $stmt = $db->query($query);
        $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['data' => $movies]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener las películas: ' . $e->getMessage()]);
    }
}

// Obtener una película por ID
function getMovie($id) {
    global $db;
    
    try {
        $query = "SELECT c.*, GROUP_CONCAT(g.name) as genres 
                 FROM content c 
                 LEFT JOIN content_genres cg ON c.id = cg.content_id 
                 LEFT JOIN genres g ON cg.genre_id = g.id 
                 WHERE c.id = :id 
                 GROUP BY c.id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($movie) {
            // Convertir la cadena de géneros en un array
            if (isset($movie['genres'])) {
                $movie['genres'] = explode(',', $movie['genres']);
            }
            
            echo json_encode(['data' => $movie]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Película no encontrada']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener la película: ' . $e->getMessage()]);
    }
}

// Crear una nueva película
function createMovie() {
    global $db;
    
    try {
        // Obtener los datos del cuerpo de la petición
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar los datos de entrada
        if (!validateMovieData($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos de película no válidos']);
            return;
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Normalizar URLs de video (asegurar que sean rutas relativas o absolutas válidas)
        $videoUrl = !empty($data['video_url']) ? trim($data['video_url']) : null;
        $trailerUrl = !empty($data['trailer_url']) ? trim($data['trailer_url']) : null;
        
        // Si la URL de video es relativa sin / al inicio, añadirla
        if ($videoUrl && strpos($videoUrl, 'http://') !== 0 && strpos($videoUrl, 'https://') !== 0 && strpos($videoUrl, '/') !== 0) {
            $videoUrl = '/' . ltrim($videoUrl, '/');
        }
        if ($trailerUrl && strpos($trailerUrl, 'http://') !== 0 && strpos($trailerUrl, 'https://') !== 0 && strpos($trailerUrl, '/') !== 0) {
            $trailerUrl = '/' . ltrim($trailerUrl, '/');
        }
        
        // Insertar la película en la tabla content
        $query = "INSERT INTO content (
                    title, slug, description, type, release_year, duration, 
                    rating, age_rating, poster_url, backdrop_url, video_url, trailer_url, torrent_magnet,
                    is_featured, is_trending, is_premium, created_at, updated_at
                  ) VALUES (
                    :title, :slug, :description, 'movie', :release_year, :duration, 
                    :rating, :age_rating, :poster_url, :backdrop_url, :video_url, :trailer_url, :torrent_magnet,
                    :is_featured, :is_trending, :is_premium, NOW(), NOW()
                  )";
        
        $stmt = $db->prepare($query);
        $slug = createSlug($data['title']);
        
        $stmt->execute([
            ':title' => $data['title'],
            ':slug' => $slug,
            ':description' => $data['description'],
            ':release_year' => $data['release_year'],
            ':duration' => $data['duration'],
            ':rating' => $data['rating'] ?? null,
            ':age_rating' => $data['age_rating'] ?? null,
            ':poster_url' => !empty($data['poster_url']) ? trim($data['poster_url']) : null,
            ':backdrop_url' => !empty($data['backdrop_url']) ? trim($data['backdrop_url']) : null,
            ':video_url' => $videoUrl,
            ':trailer_url' => $trailerUrl,
            ':torrent_magnet' => $data['torrent_magnet'] ?? null,
            ':is_featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
            ':is_trending' => isset($data['is_trending']) ? (int)$data['is_trending'] : 0,
            ':is_premium' => isset($data['is_premium']) ? (int)$data['is_premium'] : 0
        ]);
        
        $movieId = $db->lastInsertId();
        
        // Asociar géneros si se proporcionan
        if (!empty($data['genres']) && is_array($data['genres'])) {
            foreach ($data['genres'] as $genreId) {
                $query = "INSERT INTO content_genres (content_id, genre_id) VALUES (:content_id, :genre_id)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':content_id' => $movieId,
                    ':genre_id' => $genreId
                ]);
            }
        }
        
        // Confirmar la transacción
        $db->commit();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Película creada correctamente',
            'id' => $movieId
        ]);
        
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $db->rollBack();
        
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear la película: ' . $e->getMessage()]);
    }
}

// Actualizar una película existente
function updateMovie($id) {
    global $db;
    
    try {
        // Verificar si la película existe
        $stmt = $db->prepare("SELECT id FROM content WHERE id = :id AND type = 'movie'");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Película no encontrada']);
            return;
        }
        
        // Obtener los datos del cuerpo de la petición
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Si no se puede decodificar JSON, intentar con POST
        if (!$data && $_SERVER['REQUEST_METHOD'] === 'PUT') {
            parse_str(file_get_contents('php://input'), $data);
        }
        
        // Validar los datos de entrada
        if (!validateMovieData($data, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos de película no válidos']);
            return;
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Normalizar URLs de video (asegurar que sean rutas relativas o absolutas válidas)
        $videoUrl = !empty($data['video_url']) ? trim($data['video_url']) : null;
        $trailerUrl = !empty($data['trailer_url']) ? trim($data['trailer_url']) : null;
        
        // Si la URL de video es relativa sin / al inicio, añadirla
        if ($videoUrl && strpos($videoUrl, 'http://') !== 0 && strpos($videoUrl, 'https://') !== 0 && strpos($videoUrl, '/') !== 0) {
            $videoUrl = '/' . ltrim($videoUrl, '/');
        }
        if ($trailerUrl && strpos($trailerUrl, 'http://') !== 0 && strpos($trailerUrl, 'https://') !== 0 && strpos($trailerUrl, '/') !== 0) {
            $trailerUrl = '/' . ltrim($trailerUrl, '/');
        }
        
        // Actualizar la película en la tabla content
        $query = "UPDATE content SET 
                    title = :title,
                    slug = :slug,
                    description = :description,
                    release_year = :release_year,
                    duration = :duration,
                    rating = :rating,
                    age_rating = :age_rating,
                    poster_url = :poster_url,
                    backdrop_url = :backdrop_url,
                    video_url = :video_url,
                    trailer_url = :trailer_url,
                    torrent_magnet = :torrent_magnet,
                    is_featured = :is_featured,
                    is_trending = :is_trending,
                    is_premium = :is_premium,
                    updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $slug = createSlug($data['title']);
        
        $stmt->execute([
            ':id' => $id,
            ':title' => $data['title'],
            ':slug' => $slug,
            ':description' => $data['description'],
            ':release_year' => $data['release_year'],
            ':duration' => $data['duration'],
            ':rating' => $data['rating'] ?? null,
            ':age_rating' => $data['age_rating'] ?? null,
            ':poster_url' => !empty($data['poster_url']) ? trim($data['poster_url']) : null,
            ':backdrop_url' => !empty($data['backdrop_url']) ? trim($data['backdrop_url']) : null,
            ':video_url' => $videoUrl,
            ':trailer_url' => $trailerUrl,
            ':torrent_magnet' => $data['torrent_magnet'] ?? null,
            ':is_featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : 0,
            ':is_trending' => isset($data['is_trending']) ? (int)$data['is_trending'] : 0,
            ':is_premium' => isset($data['is_premium']) ? (int)$data['is_premium'] : 0
        ]);
        
        // Actualizar géneros si se proporcionan
        if (isset($data['genres']) && is_array($data['genres'])) {
            // Eliminar géneros existentes
            $query = "DELETE FROM content_genres WHERE content_id = :content_id";
            $stmt = $db->prepare($query);
            $stmt->execute([':content_id' => $id]);
            
            // Insertar los nuevos géneros
            foreach ($data['genres'] as $genreId) {
                $query = "INSERT INTO content_genres (content_id, genre_id) VALUES (:content_id, :genre_id)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':content_id' => $id,
                    ':genre_id' => $genreId
                ]);
            }
        }
        
        // Confirmar la transacción
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Película actualizada correctamente'
        ]);
        
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $db->rollBack();
        
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar la película: ' . $e->getMessage()]);
    }
}

// Eliminar una película
function deleteMovie($id) {
    global $db;
    
    try {
        // Verificar si la película existe
        $stmt = $db->prepare("SELECT id, poster_url, backdrop_url FROM content WHERE id = :id AND type = 'movie'");
        $stmt->execute([':id' => $id]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$movie) {
            http_response_code(404);
            echo json_encode(['error' => 'Película no encontrada']);
            return;
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Eliminar relaciones con géneros
        $query = "DELETE FROM content_genres WHERE content_id = :content_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':content_id' => $id]);
        
        // Eliminar la película
        $query = "DELETE FROM content WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        
        // Aquí podrías agregar lógica para eliminar las imágenes del servidor
        // si es necesario
        
        // Confirmar la transacción
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Película eliminada correctamente'
        ]);
        
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $db->rollBack();
        
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar la película: ' . $e->getMessage()]);
    }
}

// Función para validar los datos de la película
function validateMovieData($data, $isUpdate = false) {
    // Campos realmente requeridos
    $requiredFields = ['title', 'description', 'release_year', 'duration'];
    
    // Verificar campos requeridos
    foreach ($requiredFields as $field) {
        if (!$isUpdate && (!isset($data[$field]) || empty($data[$field]))) {
            return false;
        }
    }
    
    // Validar año de lanzamiento
    if (isset($data['release_year']) && (!is_numeric($data['release_year']) || $data['release_year'] < 1888 || $data['release_year'] > (int)date('Y') + 5)) {
        return false;
    }
    
    // Validar duración (en minutos)
    if (isset($data['duration']) && (!is_numeric($data['duration']) || $data['duration'] <= 0)) {
        return false;
    }
    
    // Validar rating (opcional, debe estar entre 0 y 10)
    if (isset($data['rating']) && $data['rating'] !== null && $data['rating'] !== '' && (!is_numeric($data['rating']) || $data['rating'] < 0 || $data['rating'] > 10)) {
        return false;
    }
    
    // Validar URLs (opcionales, permitir URLs relativas y absolutas)
    $urlFields = ['poster_url', 'backdrop_url', 'trailer_url', 'video_url'];
    foreach ($urlFields as $field) {
        if (isset($data[$field]) && !empty($data[$field]) && trim($data[$field]) !== '') {
            $url = trim($data[$field]);
            // Permitir URLs absolutas (http/https) o relativas (que empiezan con /)
            $isAbsoluteUrl = filter_var($url, FILTER_VALIDATE_URL) !== false;
            $isRelativeUrl = strpos($url, '/') === 0;
            
            // Si no es URL válida ni relativa, es inválida
            if (!$isAbsoluteUrl && !$isRelativeUrl) {
                return false;
            }
        }
    }
    
    // Validar géneros si se proporcionan
    if (isset($data['genres'])) {
        if (!is_array($data['genres'])) {
            return false;
        }
        
        // Verificar que los IDs de género sean enteros positivos
        foreach ($data['genres'] as $genreId) {
            if (!is_numeric($genreId) || $genreId <= 0) {
                return false;
            }
        }
    }
    
    return true;
}

// Función para crear un slug a partir de un texto
function createSlug($text) {
    // Reemplazar caracteres especiales
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    
    // Transliterar caracteres especiales a ASCII
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    
    // Eliminar caracteres no deseados
    $text = preg_replace('~[^-\w]+~', '', $text);
    
    // Convertir a minúsculas
    $text = strtolower(trim($text, '-'));
    
    // Si el resultado está vacío, devolver un valor por defecto
    if (empty($text)) {
        return 'pelicula-' . time();
    }
    
    return $text;
}
?>
