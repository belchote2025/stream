<?php
/**
 * Script para añadir contenido de ejemplo
 * Ejecuta este archivo para añadir películas y series de muestra
 */

require_once __DIR__ . '/../includes/config.php';

$success = false;
$error = null;
$added = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    try {
        $db = getDbConnection();
        
        // Obtener géneros
        $genres = [];
        $stmt = $db->query("SELECT id, slug FROM genres");
        while ($row = $stmt->fetch()) {
            $genres[$row['slug']] = $row['id'];
        }
        
        // Contenido de ejemplo
        $sampleContent = [
            [
                'title' => 'Stranger Things',
                'slug' => 'stranger-things',
                'type' => 'series',
                'description' => 'Cuando un niño desaparece, sus amigos, familiares y la policía se ven envueltos en una serie de eventos misteriosos relacionados con experimentos secretos, fuerzas sobrenaturales y una niña muy especial.',
                'release_year' => 2016,
                'duration' => 50,
                'rating' => 8.7,
                'age_rating' => 'TV-14',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/49WJfeN0moxb9IPfGn8AIqMGskD.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/56v2KjBlU4XaOv9rVYEQypROD7p.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=b9EkMc79ZSU',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['ciencia-ficcion', 'drama', 'terror']
            ],
            [
                'title' => 'The Crown',
                'slug' => 'the-crown',
                'type' => 'series',
                'description' => 'Sigue la vida política rivalidad y el romance de la reina Isabel II y los acontecimientos que dieron forma a la segunda mitad del siglo XX.',
                'release_year' => 2016,
                'duration' => 60,
                'rating' => 8.6,
                'age_rating' => 'TV-MA',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/1M876KPjulVwppEpldhdc8V4o68.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/7WJ3Y8Q7nWQ3Xr5Q2Z5Q5Q5Q5Q5.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=JWtnJjn6ng0',
                'is_featured' => 1,
                'is_trending' => 0,
                'is_premium' => 1,
                'genres' => ['drama', 'historia']
            ],
            [
                'title' => 'Breaking Bad',
                'slug' => 'breaking-bad',
                'type' => 'series',
                'description' => 'Un profesor de química de secundaria con cáncer terminal se asocia con un exalumno para fabricar y vender metanfetamina cristalina.',
                'release_year' => 2008,
                'duration' => 49,
                'rating' => 9.5,
                'age_rating' => 'TV-MA',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/ggFHVNu6YYI5L9pCfOacjizRGt.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=HhesaQXLuRY',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['drama', 'crimen', 'thriller']
            ],
            [
                'title' => 'Inception',
                'slug' => 'inception',
                'type' => 'movie',
                'description' => 'Un ladrón que roba secretos a través de la tecnología de compartir sueños recibe la tarea inversa de plantar una idea en la mente de un CEO.',
                'release_year' => 2010,
                'duration' => 148,
                'rating' => 8.8,
                'age_rating' => 'PG-13',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/s3TBrRGB1iav7gFOCNx3H31MoES.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=YoHD9XEInc0',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['ciencia-ficcion', 'accion', 'thriller']
            ],
            [
                'title' => 'The Dark Knight',
                'slug' => 'the-dark-knight',
                'type' => 'movie',
                'description' => 'Batman acepta uno de los mayores desafíos psicológicos y físicos de su capacidad para luchar contra la injusticia.',
                'release_year' => 2008,
                'duration' => 152,
                'rating' => 9.0,
                'age_rating' => 'PG-13',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/qJ2tW6WMUDux911r6m7haRef0WH.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/hqkIcbrONLjbqKXbITW4qXSl4Fz.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=EXeTwQWrcwY',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['accion', 'crimen', 'drama']
            ],
            [
                'title' => 'Pulp Fiction',
                'slug' => 'pulp-fiction',
                'type' => 'movie',
                'description' => 'Las vidas de dos mafiosos, un boxeador, la esposa de un gángster y un par de bandidos se entrelazan en cuatro historias de violencia y redención.',
                'release_year' => 1994,
                'duration' => 154,
                'rating' => 8.9,
                'age_rating' => 'R',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/d5iIlFn5s0ImszYzBPb8JPIfbXD.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/suaEOtk1N1sgg2YmvlvJTeVQxK.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=s7EdQ4FqbhY',
                'is_featured' => 1,
                'is_trending' => 0,
                'is_premium' => 1,
                'genres' => ['crimen', 'drama']
            ],
            [
                'title' => 'The Matrix',
                'slug' => 'the-matrix',
                'type' => 'movie',
                'description' => 'Un programador informático es llevado a la lucha contra las máquinas que han tomado el control del mundo.',
                'release_year' => 1999,
                'duration' => 136,
                'rating' => 8.7,
                'age_rating' => 'R',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/7u3pxc0K1wx32IleAkLv78MKernw.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=vKQi3bBA1y8',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['ciencia-ficcion', 'accion']
            ],
            [
                'title' => 'Game of Thrones',
                'slug' => 'game-of-thrones',
                'type' => 'series',
                'description' => 'Nueve familias nobles luchan por el control de las tierras de Westeros, mientras un antiguo enemigo regresa después de estar dormido durante milenios.',
                'release_year' => 2011,
                'duration' => 57,
                'rating' => 9.3,
                'age_rating' => 'TV-MA',
                'poster_url' => 'https://image.tmdb.org/t/p/w500/u3bZgnGQ9T01sWNhyveQz0wH0Hl.jpg',
                'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/2OMB0ynKlyIenMJWI2Dy9gWT4Ex.jpg',
                'trailer_url' => 'https://www.youtube.com/watch?v=KPLWWIOCOOc',
                'is_featured' => 1,
                'is_trending' => 1,
                'is_premium' => 0,
                'genres' => ['drama', 'aventura', 'fantasia']
            ]
        ];
        
        foreach ($sampleContent as $content) {
            // Verificar si ya existe
            $stmt = $db->prepare("SELECT id FROM content WHERE slug = ?");
            $stmt->execute([$content['slug']]);
            if ($stmt->fetch()) {
                continue; // Ya existe, saltar
            }
            
            // Insertar contenido
            $stmt = $db->prepare("
                INSERT INTO content (title, slug, type, description, release_year, duration, rating, age_rating, 
                                   poster_url, backdrop_url, trailer_url, is_featured, is_trending, is_premium, added_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $content['title'],
                $content['slug'],
                $content['type'],
                $content['description'],
                $content['release_year'],
                $content['duration'],
                $content['rating'],
                $content['age_rating'],
                $content['poster_url'],
                $content['backdrop_url'],
                $content['trailer_url'],
                $content['is_featured'] ? 1 : 0,
                $content['is_trending'] ? 1 : 0,
                $content['is_premium'] ? 1 : 0
            ]);
            
            $contentId = $db->lastInsertId();
            
            // Añadir géneros
            foreach ($content['genres'] as $genreSlug) {
                if (isset($genres[$genreSlug])) {
                    $stmt = $db->prepare("INSERT IGNORE INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                    $stmt->execute([$contentId, $genres[$genreSlug]]);
                }
            }
            
            $added++;
        }
        
        $success = true;
        $message = "Se añadieron {$added} elementos de contenido de ejemplo.";
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Contenido de Ejemplo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #141414 0%, #1f1f1f 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(31, 31, 31, 0.95);
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        h1 { color: #e50914; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        .info-box {
            background: rgba(229, 9, 20, 0.1);
            border-left: 4px solid #e50914;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box ul { margin-left: 20px; color: #e5e5e5; margin-top: 10px; }
        .info-box li { margin: 5px 0; }
        .btn {
            background: #e50914;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        .btn:hover { background: #f40612; }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            color: #28a745;
        }
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border-left: 4px solid #dc3545;
            color: #dc3545;
        }
        .content-list {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .content-list h3 {
            color: #e50914;
            margin-bottom: 15px;
        }
        .content-list ul {
            list-style: none;
            color: #e5e5e5;
        }
        .content-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .content-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎬 Añadir Contenido de Ejemplo</h1>
        <p class="subtitle">Streaming Platform - Estilo Netflix</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>¡Éxito!</strong><br>
                <?php echo $message; ?>
            </div>
            
            <div class="content-list">
                <h3>Contenido añadido:</h3>
                <ul>
                    <li>✅ Stranger Things (Serie)</li>
                    <li>✅ The Crown (Serie Premium)</li>
                    <li>✅ Breaking Bad (Serie)</li>
                    <li>✅ Inception (Película)</li>
                    <li>✅ The Dark Knight (Película)</li>
                    <li>✅ Pulp Fiction (Película Premium)</li>
                    <li>✅ The Matrix (Película)</li>
                    <li>✅ Game of Thrones (Serie)</li>
                </ul>
            </div>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="/streaming-platform/" class="btn" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 40px;">
                    Ver la aplicación
                </a>
            </p>
            
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>📋 Contenido que se añadirá:</h3>
                <ul>
                    <li>Stranger Things (Serie destacada)</li>
                    <li>The Crown (Serie Premium)</li>
                    <li>Breaking Bad (Serie)</li>
                    <li>Inception (Película)</li>
                    <li>The Dark Knight (Película)</li>
                    <li>Pulp Fiction (Película Premium)</li>
                    <li>The Matrix (Película)</li>
                    <li>Game of Thrones (Serie)</li>
                </ul>
            </div>
            
            <form method="POST">
                <button type="submit" name="add_content" class="btn">
                    Añadir Contenido de Ejemplo
                </button>
            </form>
            
            <p style="margin-top: 20px; text-align: center; color: #999; font-size: 0.9rem;">
                Nota: Si el contenido ya existe, se omitirá automáticamente.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>

