-- Crear la tabla de usuarios si no existe
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(5) DEFAULT 'es',
  `subtitle_language` varchar(5) DEFAULT 'es',
  `theme` varchar(10) DEFAULT 'light',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear la tabla de configuraciones de usuario
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
