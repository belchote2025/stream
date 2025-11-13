<?php
require_once __DIR__ . '/../../includes/config.php';

// Obtener conexión a la base de datos
$db = getDbConnection();

try {
    // Iniciar transacción
    $db->beginTransaction();

    // 1. Insertar géneros
    $genres = [
        'Acción', 'Aventura', 'Comedia', 'Drama', 'Terror', 'Ciencia Ficción', 
        'Fantasía', 'Romance', 'Suspenso', 'Documental', 'Animación', 'Crimen'
    ];
    
    $genreMap = [];
    
    $stmt = $db->prepare("INSERT INTO genres (name, slug) VALUES (:name, :slug)");
    
    foreach ($genres as $genre) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $genre)));
        $stmt->execute([':name' => $genre, ':slug' => $slug]);
        $genreMap[$genre] = $db->lastInsertId();
        echo "Insertado género: $genre (slug: $slug)\n";
    }
    
    // 2. Insertar películas de ejemplo
    $movies = [
        [
            'title' => 'El Padrino',
            'description' => 'La historia de la familia Corleone, una de las dinastías criminales más poderosas de Estados Unidos.',
            'type' => 'movie',
            'release_year' => 1972,
            'duration' => 175,
            'rating' => 9.2,
            'age_rating' => 'R',
            'poster_url' => 'https://m.media-amazon.com/images/M/MV5BM2MyNjYxNmUtYTAwNi00MTYxLWJmNWYtYzZlODY3ZTk3OTFlXkEyXkFqcGdeQXVyNzkwMjQ5NzM@._V1_.jpg',
            'backdrop_url' => 'https://m.media-amazon.com/images/M/MV5BMTc5NTM5OTY0Nl5BMl5BanBnXkFtZTcwOTg4OTM0OQ@@._V1_.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=sY1S34973zA',
            'is_featured' => 1,
            'is_trending' => 1,
            'genres' => ['Drama', 'Crimen']
        ],
        // Agrega más películas según sea necesario
    ];
    
    $movieStmt = $db->prepare("\n        INSERT INTO content (\n            title, slug, description, type, release_year, duration, rating, \n            age_rating, poster_url, backdrop_url, trailer_url, \n            is_featured, is_trending, created_at, updated_at\n        ) VALUES (\n            :title, :slug, :description, :type, :release_year, :duration, :rating, \n            :age_rating, :poster_url, :backdrop_url, :trailer_url, \n            :is_featured, :is_trending, NOW(), NOW()\n        )\n    ");
    
    $genreStmt = $db->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (:content_id, :genre_id)");
    
    foreach ($movies as $movie) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $movie['title'])));
        $movieStmt->execute([
            ':title' => $movie['title'],
            ':slug' => $slug,
            ':description' => $movie['description'],
            ':type' => $movie['type'],
            ':release_year' => $movie['release_year'],
            ':duration' => $movie['duration'],
            ':rating' => $movie['rating'],
            ':age_rating' => $movie['age_rating'],
            ':poster_url' => $movie['poster_url'],
            ':backdrop_url' => $movie['backdrop_url'],
            ':trailer_url' => $movie['trailer_url'],
            ':is_featured' => $movie['is_featured'],
            ':is_trending' => $movie['is_trending'] ?? 0
        ]);
        
        $contentId = $db->lastInsertId();
        
        foreach ($movie['genres'] as $genreName) {
            if (isset($genreMap[$genreName])) {
                $genreStmt->execute([
                    ':content_id' => $contentId,
                    ':genre_id' => $genreMap[$genreName]
                ]);
            }
        }
    }
    
    // 3. Insertar series de ejemplo (similar a las películas)
    // ... (puedes agregar series de manera similar)
    
    // Confirmar transacción
    $db->commit();
    
    echo "¡Datos de ejemplo insertados correctamente!\n";
    
} catch (Exception $e) {
    // Revertir en caso de error
    $db->rollBack();
    echo "Error al insertar datos de ejemplo: " . $e->getMessage() . "\n";
    exit(1);
}

echo "¡La base de datos ha sido poblada con datos de ejemplo!\n";
