<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // En producción, usa una contraseña segura
define('DB_NAME', 'streaming_platform');

// Configuración de la aplicación
define('SITE_URL', 'http://localhost/streaming-platform');
define('SITE_NAME', 'UrresTv');

// Configuración de seguridad
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_OPTIONS', ['cost' => 12]);

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Manejo de errores
if (defined('ENVIRONMENT')) {
    if (ENVIRONMENT === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    } else {
        error_reporting(0);
        ini_set('display_errors', 0);
    }
} else {
    // Por defecto, modo desarrollo
    define('ENVIRONMENT', 'development');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Función para conectar a la base de datos
function getDbConnection() {
    static $conn;
    
    if (!isset($conn)) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            
            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción, registrar el error en un archivo de log
            error_log("Error de conexión: " . $e->getMessage());
            die("Error de conexión con la base de datos. Por favor, inténtalo de nuevo más tarde.");
        }
    }
    
    return $conn;
}

// Función para redireccionar
function redirect($path) {
    // Si el path ya es una URL completa, usarla directamente
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        header("Location: " . $path);
        exit();
    }
    
    // Si el path ya incluye SITE_URL, evitar duplicación
    if (strpos($path, SITE_URL) === 0) {
        header("Location: " . $path);
        exit();
    }
    
    // Si el path ya comienza con /streaming-platform, extraer solo la parte relativa
    if (strpos($path, '/streaming-platform') === 0) {
        $path = substr($path, strlen('/streaming-platform'));
    }
    
    // Asegurar que el path comience con /
    if (substr($path, 0, 1) !== '/') {
        $path = '/' . $path;
    }
    
    // Construir la URL final
    $finalUrl = rtrim(SITE_URL, '/') . $path;
    header("Location: " . $finalUrl);
    exit();
}

// Función para generar tokens CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar tokens CSRF
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Función para limpiar datos de entrada
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// Función para verificar si el usuario está autenticado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Función para verificar si el usuario tiene suscripción premium
function isPremium() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'premium' || $_SESSION['user_role'] === 'admin');
}

// Función para requerir autenticación
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        redirect('/login.php');
    }
}

// Función para requerir rol de administrador
function requireAdmin() {
    requireAuth();
    
    if (!isAdmin()) {
        $_SESSION['error'] = "No tienes permiso para acceder a esta página.";
        redirect('/');
    }
}

// Función para requerir suscripción premium
function requirePremium() {
    requireAuth();
    
    if (!isPremium()) {
        $_SESSION['error'] = "Esta función está disponible solo para usuarios premium.";
        redirect('/subscription.php');
    }
}
?>
