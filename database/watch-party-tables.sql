-- Tablas para Watch Party (añadir a la base de datos existente)
-- NO rompe las tablas existentes, solo añade nuevas funcionalidades

-- Tabla de Watch Parties
CREATE TABLE IF NOT EXISTS watch_parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_code VARCHAR(8) UNIQUE NOT NULL,
    host_id INT NOT NULL,
    content_id INT NOT NULL,
    content_type ENUM('movie', 'series') NOT NULL DEFAULT 'movie',
    episode_id INT NULL,
    party_name VARCHAR(255) NOT NULL,
    current_time DECIMAL(10, 2) DEFAULT 0,
    is_playing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    INDEX idx_party_code (party_code),
    INDEX idx_host_id (host_id),
    INDEX idx_content_id (content_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de participantes de Watch Party
CREATE TABLE IF NOT EXISTS watch_party_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (party_id) REFERENCES watch_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (party_id, user_id),
    INDEX idx_party_id (party_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de mensajes de chat en Watch Party
CREATE TABLE IF NOT EXISTS watch_party_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (party_id) REFERENCES watch_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_party_id (party_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos de sincronización (play/pause/seek)
CREATE TABLE IF NOT EXISTS watch_party_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    party_id INT NOT NULL,
    user_id INT NOT NULL,
    event_type ENUM('play', 'pause', 'seek', 'buffering') NOT NULL,
    current_time DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (party_id) REFERENCES watch_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_party_id (party_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;






