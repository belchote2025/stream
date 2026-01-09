-- Tabla para almacenar IDs de proveedores sociales
-- Ejecutar este script si las columnas no existen en la tabla users

-- Añadir columna google_id si no existe
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL UNIQUE AFTER email;

-- Añadir columna facebook_id si no existe
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS facebook_id VARCHAR(255) NULL UNIQUE AFTER google_id;

-- Crear índices para búsquedas rápidas
CREATE INDEX IF NOT EXISTS idx_google_id ON users(google_id);
CREATE INDEX IF NOT EXISTS idx_facebook_id ON users(facebook_id);

-- Tabla para tokens OAuth (opcional, para almacenar tokens de acceso)
CREATE TABLE IF NOT EXISTS oauth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider ENUM('google', 'facebook') NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_provider (user_id, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;









