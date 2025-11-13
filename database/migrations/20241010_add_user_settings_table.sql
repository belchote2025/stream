-- Crear la tabla de configuraciones de usuario
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `receive_emails` tinyint(1) NOT NULL DEFAULT 1,
  `receive_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `push_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `privacy_profile` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `language` varchar(5) NOT NULL DEFAULT 'es',
  `video_quality` enum('auto','4k','full_hd','hd','sd') NOT NULL DEFAULT 'hd',
  `autoplay` tinyint(1) NOT NULL DEFAULT 1,
  `auto_play_next` tinyint(1) NOT NULL DEFAULT 1,
  `subtitle_language` varchar(5) NOT NULL DEFAULT 'es',
  `mature_content` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_auth` tinyint(1) NOT NULL DEFAULT 0,
  `theme` enum('light','dark','system') NOT NULL DEFAULT 'light',
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

-- Añadir columnas a la tabla users si no existen
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'subtitle_language';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (TABLE_SCHEMA = @dbname)
    AND (TABLE_NAME = @tablename)
    AND (COLUMN_NAME = @columnname)
  ) = 0,
  'ALTER TABLE users ADD COLUMN subtitle_language VARCHAR(5) DEFAULT "es" AFTER language',
  'SELECT 1;'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'theme';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE (TABLE_SCHEMA = @dbname)
    AND (TABLE_NAME = @tablename)
    AND (COLUMN_NAME = @columnname)
  ) = 0,
  'ALTER TABLE users ADD COLUMN theme VARCHAR(10) DEFAULT "light" AFTER subtitle_language',
  'SELECT 1;'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
