-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS streaming_platform;
USE streaming_platform;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar_url VARCHAR(255),
    role ENUM('admin', 'premium', 'free') NOT NULL DEFAULT 'free',
    subscription_plan_id INT,
    subscription_start_date DATE,
    subscription_end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
);

-- Tabla de planes de suscripción
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') NOT NULL,
    max_simultaneous_streams INT DEFAULT 1,
    max_video_quality VARCHAR(20) DEFAULT 'HD',
    ad_free BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de contenido (películas y series)
CREATE TABLE IF NOT EXISTS content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    release_year INT,
    duration INT, -- en minutos
    content_type ENUM('movie', 'tv_show') NOT NULL,
    thumbnail_url VARCHAR(255),
    cover_image_url VARCHAR(255),
    trailer_url VARCHAR(255),
    age_rating VARCHAR(10),
    imdb_rating DECIMAL(3,1),
    is_featured BOOLEAN DEFAULT FALSE,
    is_premium BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de relación entre contenido y categorías
CREATE TABLE IF NOT EXISTS content_categories (
    content_id INT,
    category_id INT,
    PRIMARY KEY (content_id, category_id),
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Tabla de temporadas (para series)
CREATE TABLE IF NOT EXISTS seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    season_number INT NOT NULL,
    name VARCHAR(100),
    description TEXT,
    release_date DATE,
    poster_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- Tabla de episodios
CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    episode_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration INT, -- en minutos
    video_url VARCHAR(255) NOT NULL,
    thumbnail_url VARCHAR(255),
    release_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE
);

-- Tabla de listas de reproducción de usuarios
CREATE TABLE IF NOT EXISTS user_playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de relación entre listas de reproducción y contenido
CREATE TABLE IF NOT EXISTS playlist_content (
    playlist_id INT,
    content_id INT,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (playlist_id, content_id),
    FOREIGN KEY (playlist_id) REFERENCES user_playlists(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);

-- Tabla de historial de visualización
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT,
    episode_id INT,
    progress INT DEFAULT 0, -- en segundos
    duration INT, -- en segundos
    last_watched TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE SET NULL,
    FOREIGN KEY (episode_id) REFERENCES episodes(id) ON DELETE SET NULL
);

-- Tabla de valoraciones
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_rating (user_id, content_id)
);

-- Tabla de comentarios
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    parent_comment_id INT,
    content TEXT NOT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE SET NULL
);

-- Tabla de notificaciones
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error', 'new_content', 'subscription') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    related_content_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_content_id) REFERENCES content(id) ON DELETE SET NULL
);

-- Insertar planes de suscripción por defecto
INSERT INTO subscription_plans (name, description, price, billing_cycle, max_simultaneous_streams, max_video_quality, ad_free) VALUES
('Básico', 'Acceso a contenido estándar con anuncios', 7.99, 'monthly', 1, 'HD', FALSE),
('Estándar', 'Contenido en HD sin anuncios', 12.99, 'monthly', 2, 'HD', TRUE),
('Premium', 'Contenido en 4K HDR sin anuncios', 17.99, 'monthly', 4, '4K', TRUE);

-- Tabla de roles de usuario
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de permisos
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de relación entre roles y permisos
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Actualizar tabla users para usar roles
ALTER TABLE users 
ADD COLUMN role_id INT,
ADD FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- Tabla de tokens de autenticación
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_revoked BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de intentos de inicio de sesión
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insertar roles por defecto
INSERT INTO roles (name, description) VALUES
('super_admin', 'Administrador con todos los permisos'),
('admin', 'Administrador del sistema'),
('content_manager', 'Gestiona el contenido de la plataforma'),
('moderator', 'Modera el contenido y comentarios'),
('premium_user', 'Usuario con suscripción premium'),
('free_user', 'Usuario gratuito');

-- Insertar permisos por defecto
INSERT INTO permissions (name, description) VALUES
('manage_users', 'Gestionar usuarios'),
('manage_roles', 'Gestionar roles y permisos'),
('manage_content', 'Gestionar todo el contenido'),
('create_content', 'Crear nuevo contenido'),
('edit_content', 'Editar contenido existente'),
('delete_content', 'Eliminar contenido'),
('moderate_comments', 'Moderar comentarios'),
('view_reports', 'Ver reportes y estadísticas'),
('access_admin_panel', 'Acceder al panel de administración'),
('watch_premium', 'Ver contenido premium');

-- Asignar permisos al rol de super_admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p
WHERE r.name = 'super_admin';

-- Asignar permisos al rol de admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p
WHERE r.name = 'admin' 
AND p.name IN ('manage_users', 'manage_content', 'create_content', 'edit_content', 'delete_content', 'moderate_comments', 'view_reports', 'access_admin_panel', 'watch_premium');

-- Insertar categorías por defecto
INSERT INTO categories (name, description) VALUES
('Acción', 'Películas y series llenas de emoción y adrenalina'),
('Aventura', 'Historias épicas de exploración y descubrimiento'),
('Comedia', 'Contenido para reír y pasar un buen rato'),
('Drama', 'Historias conmovedoras y profundas'),
('Ciencia Ficción', 'Mundos futuristas y tecnología avanzada'),
('Terror', 'Para los amantes del miedo y el suspenso'),
('Romance', 'Historias de amor y relaciones'),
('Documental', 'Contenido educativo y de no ficción'),
('Animación', 'Contenido animado para todas las edades'),
('Crimen', 'Historias de misterio e investigación');
