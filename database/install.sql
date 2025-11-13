-- ============================================
-- STREAMING PLATFORM - BASE DE DATOS COMPLETA
-- Estilo Netflix
-- ============================================

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS `streaming_platform` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `streaming_platform`;

-- ============================================
-- TABLA DE USUARIOS
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','premium') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(5) DEFAULT 'es',
  `subtitle_language` varchar(5) DEFAULT 'es',
  `theme` varchar(10) DEFAULT 'dark',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE CONFIGURACIONES DE USUARIO
-- ============================================
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `receive_emails` tinyint(1) NOT NULL DEFAULT 1,
  `receive_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `push_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `privacy_profile` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `video_quality` enum('auto','4k','full_hd','hd','sd') NOT NULL DEFAULT 'hd',
  `autoplay` tinyint(1) NOT NULL DEFAULT 1,
  `auto_play_next` tinyint(1) NOT NULL DEFAULT 1,
  `mature_content` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_auth` tinyint(1) NOT NULL DEFAULT 0,
  `data_saver` tinyint(1) NOT NULL DEFAULT 0,
  `show_activity` tinyint(1) NOT NULL DEFAULT 1,
  `newsletter` tinyint(1) NOT NULL DEFAULT 1,
  `notification_frequency` enum('instant','daily','weekly') NOT NULL DEFAULT 'instant',
  `auto_resume` tinyint(1) NOT NULL DEFAULT 1,
  `previews` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE GÉNEROS
-- ============================================
CREATE TABLE IF NOT EXISTS `genres` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `slug` varchar(50) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE CONTENIDO (PELÍCULAS Y SERIES)
-- ============================================
CREATE TABLE IF NOT EXISTS `content` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `slug` varchar(255) NOT NULL,
    `type` enum('movie','series') NOT NULL,
    `description` text DEFAULT NULL,
    `release_year` year(4) NOT NULL,
    `duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
    `rating` decimal(3,1) DEFAULT 0.0,
    `age_rating` varchar(10) DEFAULT NULL,
    `poster_url` varchar(255) DEFAULT NULL,
    `backdrop_url` varchar(255) DEFAULT NULL,
    `trailer_url` varchar(255) DEFAULT NULL,
    `video_url` varchar(255) DEFAULT NULL,
    `torrent_magnet` text DEFAULT NULL,
    `is_featured` tinyint(1) DEFAULT 0,
    `is_trending` tinyint(1) DEFAULT 0,
    `is_premium` tinyint(1) DEFAULT 0,
    `views` int(11) DEFAULT 0,
    `added_date` timestamp NULL DEFAULT current_timestamp(),
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `idx_type` (`type`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_trending` (`is_trending`),
    FULLTEXT KEY `idx_search` (`title`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE RELACIÓN CONTENIDO-GÉNEROS
-- ============================================
CREATE TABLE IF NOT EXISTS `content_genres` (
    `content_id` int(11) NOT NULL,
    `genre_id` int(11) NOT NULL,
    PRIMARY KEY (`content_id`,`genre_id`),
    KEY `genre_id` (`genre_id`),
    CONSTRAINT `fk_cg_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cg_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE EPISODIOS (PARA SERIES)
-- ============================================
CREATE TABLE IF NOT EXISTS `episodes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `series_id` int(11) NOT NULL,
    `season_number` int(11) NOT NULL,
    `episode_number` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `duration` int(11) DEFAULT NULL,
    `video_url` varchar(255) DEFAULT NULL,
    `thumbnail_url` varchar(255) DEFAULT NULL,
    `release_date` date DEFAULT NULL,
    `views` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `series_id` (`series_id`),
    KEY `idx_season_episode` (`series_id`,`season_number`,`episode_number`),
    CONSTRAINT `fk_episode_series` FOREIGN KEY (`series_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE HISTORIAL DE REPRODUCCIÓN
-- ============================================
CREATE TABLE IF NOT EXISTS `playback_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `content_id` int(11) DEFAULT NULL,
    `episode_id` int(11) DEFAULT NULL,
    `progress` int(11) NOT NULL DEFAULT 0 COMMENT 'Playback position in seconds',
    `duration` int(11) NOT NULL COMMENT 'Total duration in seconds',
    `completed` tinyint(1) NOT NULL DEFAULT 0,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_content_episode` (`user_id`,`content_id`,`episode_id`),
    KEY `content_id` (`content_id`),
    KEY `episode_id` (`episode_id`),
    CONSTRAINT `fk_ph_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ph_episode` FOREIGN KEY (`episode_id`) REFERENCES `episodes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ph_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE LISTAS DE REPRODUCCIÓN
-- ============================================
CREATE TABLE IF NOT EXISTS `user_playlists` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `is_public` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE CONTENIDO EN LISTAS
-- ============================================
CREATE TABLE IF NOT EXISTS `playlist_content` (
    `playlist_id` int(11) NOT NULL,
    `content_id` int(11) NOT NULL,
    `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `sort_order` int(11) DEFAULT 0,
    PRIMARY KEY (`playlist_id`,`content_id`),
    KEY `content_id` (`content_id`),
    CONSTRAINT `fk_pc_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pc_playlist` FOREIGN KEY (`playlist_id`) REFERENCES `user_playlists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE FAVORITOS
-- ============================================
CREATE TABLE IF NOT EXISTS `user_favorites` (
    `user_id` int(11) NOT NULL,
    `content_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`,`content_id`),
    KEY `content_id` (`content_id`),
    CONSTRAINT `fk_uf_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE VISTAS (PARA ESTADÍSTICAS)
-- ============================================
CREATE TABLE IF NOT EXISTS `views` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `content_id` int(11) NOT NULL,
    `user_id` int(11) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `viewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `content_id` (`content_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_views_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTAR DATOS INICIALES
-- ============================================

-- Insertar géneros
INSERT INTO `genres` (`name`, `slug`) VALUES
('Acción', 'accion'),
('Aventura', 'aventura'),
('Comedia', 'comedia'),
('Drama', 'drama'),
('Ciencia Ficción', 'ciencia-ficcion'),
('Terror', 'terror'),
('Romance', 'romance'),
('Documental', 'documental'),
('Animación', 'animacion'),
('Crimen', 'crimen'),
('Suspense', 'suspense'),
('Fantasía', 'fantasia'),
('Thriller', 'thriller'),
('Misterio', 'misterio'),
('Western', 'western')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insertar usuario administrador por defecto
-- Contraseña: admin123 (debes cambiarla después)
-- Hash generado con: password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`, `email_verified`) VALUES
('admin', 'admin@streamingplatform.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin', 'active', 1)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Insertar configuración para el admin
INSERT INTO `user_settings` (`user_id`, `video_quality`, `autoplay`, `auto_play_next`) 
SELECT `id`, '4k', 1, 1 FROM `users` WHERE `username` = 'admin'
ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`);

-- ============================================
-- FIN DE LA INSTALACIÓN
-- ============================================

