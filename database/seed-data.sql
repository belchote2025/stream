-- Datos de ejemplo para poblar la base de datos (Seed Data)
-- Incluye 5 películas populares recientes con datos completos y trailers

INSERT INTO content 
(title, slug, description, type, release_year, duration, rating, poster_url, backdrop_url, trailer_url, video_url, created_at, updated_at, is_featured, is_premium)
VALUES 
-- 1. Dune: Parte Dos
(
    'Dune: Parte Dos', 
    'dune-parte-dos', 
    'Paul Atreides se une a Chani y a los Fremen mientras busca venganza contra los conspiradores que destruyeron a su familia. Enfrentándose a una elección entre el amor de su vida y el destino del universo.', 
    'movie', 
    2024, 
    166, 
    8.9, 
    'https://image.tmdb.org/t/p/w500/1pdfLvkbY9ohJlCjQH2CZjjYVvJ.jpg', 
    'https://image.tmdb.org/t/p/original/xOMo8BRK7PfcJv9JCnx7s5hj0PX.jpg', 
    'https://www.youtube.com/watch?v=U2Qp5pL3ovA', 
    '', 
    NOW(), NOW(), 
    1, 0
),
-- 2. Oppenheimer
(
    'Oppenheimer', 
    'oppenheimer', 
    'La historia del físico estadounidense J. Robert Oppenheimer y su papel en el desarrollo de la bomba atómica durante la Segunda Guerra Mundial, explorando sus triunfos científicos y sus dilemas morales.', 
    'movie', 
    2023, 
    180, 
    8.5, 
    'https://image.tmdb.org/t/p/w500/ncKCQVXgk4bcqv6WbCJNgllfpCY.jpg', 
    'https://image.tmdb.org/t/p/original/fm6KqXpk3M2HVveHwvkHIeHYDI6.jpg', 
    'https://www.youtube.com/watch?v=yLyXEQPuLJo', 
    '', 
    NOW(), NOW(), 
    1, 1
),
-- 3. The Batman
(
    'The Batman', 
    'the-batman', 
    'Cuando un asesino sádico deja una serie de pistas crípticas, Batman se adentra en el submundo de Gotham City. A medida que la evidencia apunta a su propia familia, debe forjar nuevas alianzas para desenmascarar al culpable.', 
    'movie', 
    2022, 
    176, 
    8.3, 
    'https://image.tmdb.org/t/p/w500/cKX2Q7I1a6r5D6F5wG6a5y7J8x.jpg', 
    'https://image.tmdb.org/t/p/original/tRS6jvPM9qPrrnx2KRp3ew96Yot.jpg', 
    'https://www.youtube.com/watch?v=mqqft2x_Aa4', 
    '', 
    NOW(), NOW(), 
    1, 0
),
-- 4. Spider-Man: Across the Spider-Verse
(
    'Spider-Man: A través del Spider-Verso', 
    'spider-man-across-the-spider-verse', 
    'Miles Morales se catapulta a través del Multiverso, donde se encuentra con un equipo de Spider-People encargados de proteger su propia existencia. Cuando los héroes chocan sobre cómo manejar una nueva amenaza.', 
    'movie', 
    2023, 
    140, 
    8.7, 
    'https://image.tmdb.org/t/p/w500/8Vt6mWEReuy4Of61Lnj5Xj704m8.jpg', 
    'https://image.tmdb.org/t/p/original/4HodYYKEIsGOdinkGi2Ucz6X9i0.jpg', 
    'https://www.youtube.com/watch?v=cqGjhVJWtEg', 
    '', 
    NOW(), NOW(), 
    0, 0
),
-- 5. Interstellar
(
    'Interstellar', 
    'interstellar', 
    'Un equipo de exploradores viaja a través de un agujero de gusano en el espacio en un intento de asegurar la supervivencia de la humanidad, enfrentándose a desafíos que desafían el tiempo y el espacio.', 
    'movie', 
    2014, 
    169, 
    8.6, 
    'https://image.tmdb.org/t/p/w500/gEU2QniL6E8AHtMY4kOD08W47DR.jpg', 
    'https://image.tmdb.org/t/p/original/rAiYTfKGqDCRIIqo664sY9XZIvQ.jpg', 
    'https://www.youtube.com/watch?v=zSWdZVtXT7E', 
    '', 
    NOW(), NOW(), 
    0, 0
);

-- Asignar géneros (asegurando que existan primero)
INSERT IGNORE INTO genres (id, name, slug) VALUES 
(1, 'Action', 'action'),
(2, 'Sci-Fi', 'sci-fi'),
(3, 'Drama', 'drama'),
(4, 'Adventure', 'adventure'),
(5, 'Animation', 'animation');

-- Relacionar contenido con géneros
-- Dune: Action, Sci-Fi, Adventure
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 1 FROM content WHERE slug = 'dune-parte-dos';
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 2 FROM content WHERE slug = 'dune-parte-dos';
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 4 FROM content WHERE slug = 'dune-parte-dos';

-- Oppenheimer: Drama, History (usamos drama)
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 3 FROM content WHERE slug = 'oppenheimer';

-- The Batman: Action, Crime, Drama
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 1 FROM content WHERE slug = 'the-batman';
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 3 FROM content WHERE slug = 'the-batman';

-- Spiderman: Animation, Action, Adventure
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 5 FROM content WHERE slug = 'spider-man-across-the-spider-verse';
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 1 FROM content WHERE slug = 'spider-man-across-the-spider-verse';

-- Interstellar: Sci-Fi, Drama
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 2 FROM content WHERE slug = 'interstellar';
INSERT INTO content_genres (content_id, genre_id) 
SELECT id, 3 FROM content WHERE slug = 'interstellar';
