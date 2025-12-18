<?php
/**
 * Script de diagnóstico de conexión a base de datos
 * Acceder a: /api/content/test-db.php
 */

// Establecer headers ANTES de cualquier output
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Limpiar buffer
ob_clean();

$diagnostics = [
    'success' => false,
    'timestamp' => date('c'),
    'checks' => []
];

// 1. Verificar si existe el archivo .env
$envPath = __DIR__ . '/../../.env';
$diagnostics['checks']['env_file_exists'] = file_exists($envPath);
$diagnostics['checks']['env_file_path'] = $envPath;
$diagnostics['checks']['env_file_readable'] = file_exists($envPath) ? is_readable($envPath) : false;

// 2. Cargar configuración
require_once __DIR__ . '/../../includes/config.php';

// 3. Verificar valores de configuración (sin mostrar contraseña completa)
$diagnostics['checks']['db_config'] = [
    'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'NO DEFINIDO',
    'DB_USER' => defined('DB_USER') ? DB_USER : 'NO DEFINIDO',
    'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'NO DEFINIDO',
    'DB_PASS' => defined('DB_PASS') ? (DB_PASS !== '' ? '***CONFIGURADA***' : 'VACÍA') : 'NO DEFINIDO',
    'APP_ENV' => defined('APP_ENV') ? APP_ENV : 'NO DEFINIDO'
];

// 4. Verificar variables de entorno
$diagnostics['checks']['env_vars'] = [
    'DB_HOST' => getenv('DB_HOST') ?: 'NO CONFIGURADA',
    'DB_USER' => getenv('DB_USER') ?: 'NO CONFIGURADA',
    'DB_NAME' => getenv('DB_NAME') ?: 'NO CONFIGURADA',
    'DB_PASS' => getenv('DB_PASS') ? (getenv('DB_PASS') !== '' ? '***CONFIGURADA***' : 'VACÍA') : 'NO CONFIGURADA',
    'APP_ENV' => getenv('APP_ENV') ?: 'NO CONFIGURADA'
];

// 5. Intentar conectar a la base de datos
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ];
    
    $startTime = microtime(true);
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    $connectionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    $diagnostics['checks']['connection'] = [
        'status' => 'SUCCESS',
        'time_ms' => $connectionTime,
        'server_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION)
    ];
    
    // Probar una consulta simple
    $stmt = $conn->query('SELECT 1 as test');
    $result = $stmt->fetch();
    $diagnostics['checks']['query_test'] = $result ? 'SUCCESS' : 'FAILED';
    
    $diagnostics['success'] = true;
    $diagnostics['message'] = 'Conexión exitosa a la base de datos';
    
} catch (PDOException $e) {
    $diagnostics['checks']['connection'] = [
        'status' => 'FAILED',
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage()
    ];
    
    // Análisis del error
    $errorMsg = $e->getMessage();
    $diagnostics['checks']['error_analysis'] = [
        'is_host_error' => stripos($errorMsg, 'host') !== false || stripos($errorMsg, 'connection') !== false,
        'is_auth_error' => stripos($errorMsg, 'access denied') !== false || stripos($errorMsg, 'password') !== false,
        'is_db_error' => stripos($errorMsg, 'unknown database') !== false || stripos($errorMsg, 'database') !== false,
        'suggestions' => []
    ];
    
    // Sugerencias basadas en el error
    if (stripos($errorMsg, 'unknown database') !== false) {
        $diagnostics['checks']['error_analysis']['suggestions'][] = 'La base de datos no existe. Verifica DB_NAME en .env';
    }
    if (stripos($errorMsg, 'access denied') !== false) {
        $diagnostics['checks']['error_analysis']['suggestions'][] = 'Credenciales incorrectas. Verifica DB_USER y DB_PASS en .env';
    }
    if (stripos($errorMsg, 'host') !== false || stripos($errorMsg, 'connection') !== false) {
        $diagnostics['checks']['error_analysis']['suggestions'][] = 'No se puede conectar al servidor. En Hostinger, DB_HOST puede ser diferente a "localhost". Verifica en el panel.';
    }
    
    $diagnostics['message'] = 'Error de conexión: ' . $e->getMessage();
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;

