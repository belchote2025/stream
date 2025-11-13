-- Create genres table
CREATE TABLE IF NOT EXISTS `genres` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `slug` varchar(50) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create content table
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
    `is_featured` tinyint(1) DEFAULT 0,
    `is_trending` tinyint(1) DEFAULT 0,
    `views` int(11) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `idx_type` (`type`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_trending` (`is_trending`),
    FULLTEXT KEY `idx_search` (`title`,`description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create content_genres junction table
CREATE TABLE IF NOT EXISTS `content_genres` (
    `content_id` int(11) NOT NULL,
    `genre_id` int(11) NOT NULL,
    PRIMARY KEY (`content_id`,`genre_id`),
    KEY `genre_id` (`genre_id`),
    CONSTRAINT `fk_cg_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cg_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create episodes table for series
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

-- Create playback history table
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

-- Create user playlists table
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

-- Create playlist_content junction table
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

-- Create user_favorites table
CREATE TABLE IF NOT EXISTS `user_favorites` (
    `user_id` int(11) NOT NULL,
    `content_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`user_id`,`content_id`),
    KEY `content_id` (`content_id`),
    CONSTRAINT `fk_uf_content` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
