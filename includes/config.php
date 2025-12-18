<?php
// Configuración de seguridad
// Configuración de reporte de errores inicial (se ajustará más abajo según entorno)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Crear directorio de logs si no existe
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Cargar variables de entorno
$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . '/.env';

if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, "'\" \t\n\r\0\x0B"); // Eliminar comillas y espacios
        
        // Validar valores sensibles
        if (in_array($key, ['DB_PASS', 'API_KEY', 'SECRET_KEY'])) {
            if (empty($value)) {
                error_log("Advertencia: Valor vacío para la variable sensible: $key");
            }
        }
        
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isCli = php_sapi_name() === 'cli';
$isLocalHost = in_array($httpHost, ['localhost', '127.0.0.1'], true) || strpos($httpHost, '.local') !== false || strpos($httpHost, 'ngrok') !== false;
$appEnv = getenv('APP_ENV') ?: (($isCli || $isLocalHost) ? 'local' : 'production');
$appEnv = strtolower($appEnv) === 'local' ? 'local' : 'production';
define('APP_ENV', $appEnv);

// Definir ENVIRONMENT basado en APP_ENV
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', APP_ENV === 'local' ? 'development' : 'production');
}

// Ajustar reporte de errores según entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuración de base de datos - Las credenciales reales deben estar en .env
$dbDefaults = [
    'local' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'name' => getenv('DB_NAME') ?: 'streaming_platform',
    ],
    'production' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
        'name' => getenv('DB_NAME') ?: '',
    ],
];
$currentDbDefaults = $dbDefaults[APP_ENV] ?? $dbDefaults['production'];

define('DB_HOST', getenv('DB_HOST') ?: $currentDbDefaults['host']);
define('DB_USER', getenv('DB_USER') ?: $currentDbDefaults['user']);
define('DB_PASS', getenv('DB_PASS') ?: $currentDbDefaults['pass']);
define('DB_NAME', getenv('DB_NAME') ?: $currentDbDefaults['name']);

// Configuración de la aplicación
if (!defined('SITE_URL')) {
    $envSiteUrl = getenv('SITE_URL');
    
    if ($envSiteUrl) {
        define('SITE_URL', rtrim($envSiteUrl, '/'));
    } else {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        
        if ($host) {
            $usesHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            );
            $scheme = $usesHttps ? 'https' : 'http';
            
            $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
            $docRoot = $docRoot ? rtrim(str_replace('\\', '/', realpath($docRoot)), '/') : '';
            $projectRootReal = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__))), '/');
            
            $basePath = '';
            if ($docRoot && $projectRootReal && strpos($projectRootReal, $docRoot) === 0) {
                $basePath = substr($projectRootReal, strlen($docRoot));
            }

            // En entornos locales (localhost/XAMPP) forzar la carpeta del proyecto
            // si Apache no reporta correctamente DOCUMENT_ROOT.
            if (($basePath === '' || $basePath === '/') && $isLocalHost) {
                $basePath = '/' . trim(basename($projectRootReal), '/');
            }
            
            if (!empty($basePath) && $basePath[0] !== '/') {
                $basePath = '/' . $basePath;
            }
            
            $siteUrl = rtrim($scheme . '://' . $host . $basePath, '/');
            define('SITE_URL', $siteUrl ?: $scheme . '://' . $host);
        } else {
            define('SITE_URL', 'http://localhost/streaming-platform');
        }
    }
}
define('SITE_NAME', 'UrresTv');

// Configuración de seguridad
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_OPTIONS', ['cost' => 12]);

// Configuración de sesión unificada
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// Usar HTTPS si estamos en producción o si el servidor lo reporta
$isHttps = (APP_ENV === 'production') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.cookie_secure', $isHttps ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Establece una conexión segura a la base de datos
 * @return mysqli
 * @throws Exception Si la conexión falla
 */
function getDbConnection() {
    static $conn = null;
    
    // Si ya hay una conexión activa, devolverla
    if ($conn !== null) {
        try {
            // Verificar si la conexión sigue activa
            $conn->query('SELECT 1');
            return $conn;
        } catch (PDOException $e) {
            // Si la conexión está inactiva, continuar para crear una nueva
            $conn = null;
        }
    }
    
    // Configuración de la conexión PDO
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    try {
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $conn;
    } catch (PDOException $e) {
        // En producción, registrar el error en un archivo de log
        error_log("Error de conexión: " . $e->getMessage());
        
        // Detectar si estamos en un contexto de API de forma más robusta
        $isApiContext = false;
        
        // Método 1: Verificar la URI de la petición (más confiable)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/api/') !== false) {
            $isApiContext = true;
        }
        
        // Método 2: Verificar el script que se está ejecutando
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptName, '/api/') !== false) {
            $isApiContext = true;
        }
        
        // Método 3: Verificar el archivo que está llamando usando debug_backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                $filePath = str_replace('\\', '/', $trace['file']);
                if (strpos($filePath, '/api/') !== false) {
                    $isApiContext = true;
                    break;
                }
            }
        }
        
        // Método 4: Verificar si Content-Type JSON ya fue establecido
        $headersList = headers_list();
        foreach ($headersList as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                $isApiContext = true;
                break;
            }
        }
        
        if ($isApiContext) {
            // Lanzar excepción para que los endpoints la capturen y devuelvan JSON
            // Esto permite que los endpoints manejen el error correctamente
            throw new PDOException("Error de conexión a la base de datos. Por favor, inténtelo más tarde.", 0, $e);
        } else {
            // Para páginas normales, usar die() como antes
            die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
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
