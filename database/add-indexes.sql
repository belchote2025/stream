-- ============================================
-- OPTIMIZACIÓN DE BASE DE DATOS
-- Añadir índices para mejorar rendimiento
-- ============================================

USE streaming_platform;

-- ====== ÍNDICES PARA TABLA content ======

-- Índice para búsquedas por tipo (movies/series)
CREATE INDEX IF NOT EXISTS idx_content_type ON content(type);

-- Índice para ordenar por popularidad
CREATE INDEX IF NOT EXISTS idx_content_popularity ON content(popularity DESC);

-- Índice para búsquedas por año
CREATE INDEX IF NOT EXISTS idx_content_year ON content(release_year DESC);

-- Índice para contenido premium
CREATE INDEX IF NOT EXISTS idx_content_premium ON content(is_premium);

-- Índice para contenido destacado
CREATE INDEX IF NOT EXISTS idx_content_featured ON content(is_featured);

-- Índice para búsqueda por IMDb ID
CREATE INDEX IF NOT EXISTS idx_content_imdb ON content(imdb_id);

-- Índice para fecha de creación (contenido reciente)
CREATE INDEX IF NOT EXISTS idx_content_created ON content(created_at DESC);

-- Índice compuesto para búsquedas frecuentes
CREATE INDEX IF NOT EXISTS idx_content_type_year ON content(type, release_year DESC);
CREATE INDEX IF NOT EXISTS idx_content_type_popularity ON content(type, popularity DESC);

-- ====== ÍNDICES PARA TABLA episodes ======

-- Índice para búsquedas por serie
CREATE INDEX IF NOT EXISTS idx_episodes_series ON episodes(series_id);

-- Índice compuesto para temporada y episodio
CREATE INDEX IF NOT EXISTS idx_episodes_season_ep ON episodes(series_id, season_number, episode_number);

-- Índice para ordenar episodios
CREATE INDEX IF NOT EXISTS idx_episodes_order ON episodes(series_id, season_number ASC, episode_number ASC);

-- ====== ÍNDICES PARA TABLA content_genres ======

-- Índice para búsquedas por contenido
CREATE INDEX IF NOT EXISTS idx_content_genres_content ON content_genres(content_id);

-- Índice para búsquedas por género
CREATE INDEX IF NOT EXISTS idx_content_genres_genre ON content_genres(genre_id);

-- Índice compuesto para relación muchos a muchos
CREATE INDEX IF NOT EXISTS idx_content_genres_both ON content_genres(content_id, genre_id);

-- ====== ÍNDICES PARA TABLA genres ======

-- Índice para búsqueda por nombre
CREATE INDEX IF NOT EXISTS idx_genres_name ON genres(name);

-- ====== ÍNDICES PARA TABLA users ======

-- Índice para búsqueda por email (login)
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Índice para búsqueda por username
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- Índice para filtrar por rol
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Índice para filtrar por estado premium
CREATE INDEX IF NOT EXISTS idx_users_premium ON users(is_premium);

-- Índice para fecha de creación
CREATE INDEX IF NOT EXISTS idx_users_created ON users(created_at DESC);

-- ====== ÍNDICES PARA TABLA user_watchlist ======

-- Índice para búsquedas por usuario
CREATE INDEX IF NOT EXISTS idx_watchlist_user ON user_watchlist(user_id);

-- Índice para búsquedas por contenido
CREATE INDEX IF NOT EXISTS idx_watchlist_content ON user_watchlist(content_id);

-- Índice compuesto para relación única
CREATE INDEX IF NOT EXISTS idx_watchlist_user_content ON user_watchlist(user_id, content_id);

-- Índice para ordenar por fecha de adición
CREATE INDEX IF NOT EXISTS idx_watchlist_added ON user_watchlist(added_at DESC);

-- ====== ÍNDICES PARA TABLA viewing_progress ======

-- Índice para búsquedas por usuario
CREATE INDEX IF NOT EXISTS idx_progress_user ON viewing_progress(user_id);

-- Índice para búsquedas por contenido
CREATE INDEX IF NOT EXISTS idx_progress_content ON viewing_progress(content_id);

-- Índice compues

to para usuario + contenido (continuar viendo)
CREATE INDEX IF NOT EXISTS idx_progress_user_content ON viewing_progress(user_id, content_id);

-- Índice para última actualización
CREATE INDEX IF NOT EXISTS idx_progress_updated ON viewing_progress(updated_at DESC);

-- ====== ÍNDICES PARA TABLA ratings ======

-- Índice para búsquedas por contenido
CREATE INDEX IF NOT EXISTS idx_ratings_content ON ratings(content_id);

-- Índice para búsquedas por usuario
CREATE INDEX IF NOT EXISTS idx_ratings_user ON ratings(user_id);

-- Índice compuesto para rating único de usuario
CREATE INDEX IF NOT EXISTS idx_ratings_user_content ON ratings(user_id, content_id);

-- ====== VERIFICACIÓN DE ÍNDICES =======

-- Mostrar todos los índices de la tabla content
SHOW INDEX FROM content;

-- Mostrar todos los índices de la tabla episodes
SHOW INDEX FROM episodes;

-- Mostrar todos los índices de la tabla users
SHOW INDEX FROM users;

-- ====== OPTIMIZAR TABLAS =======

-- Optimizar para recuperar espacio y reorganizar datos
OPTIMIZE TABLE content;
OPTIMIZE TABLE episodes;
OPTIMIZE TABLE users;
OPTIMIZE TABLE content_genres;
OPTIMIZE TABLE genres;
OPTIMIZE TABLE user_watchlist;
OPTIMIZE TABLE viewing_progress;
OPTIMIZE TABLE ratings;

-- ====== ANÁLISIS DE TABLAS =======

-- Analizar estadísticas de las tablas para el optimizador de consultas
ANALYZE TABLE content;
ANALYZE TABLE episodes;
ANALYZE TABLE users;
ANALYZE TABLE content_genres;
ANALYZE TABLE genres;
ANALYZE TABLE user_watchlist;
ANALYZE TABLE viewing_progress;
ANALYZE TABLE ratings;

-- ====== FINALIZADO =======
SELECT 'Índices añadidos correctamente' AS status;
SELECT 'Tablas optimizadas' AS status;
SELECT 'Análisis completado' AS status;
