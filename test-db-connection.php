<?php
/**
 * Script de prueba para verificar la conexión a la base de datos
 * Ejecutar desde: http://localhost/streaming-platform/test-db-connection.php
 */

require_once __DIR__ . '/includes/config.php';

echo "<h2>Prueba de Conexión a Base de Datos</h2>";
echo "<pre>";

$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalHost = in_array($httpHost, ['localhost', '127.0.0.1'], true) || strpos($httpHost, '.local') !== false;

echo "HTTP_HOST: " . $httpHost . "\n";
echo "Detectado como localhost: " . ($isLocalHost ? 'SÍ' : 'NO') . "\n";
echo "APP_ENV: " . APP_ENV . "\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_PASS: " . (empty(DB_PASS) ? '(vacío)' : '***') . "\n\n";

try {
    $conn = getDbConnection();
    echo "✅ Conexión exitosa a la base de datos!\n\n";
    
    // Probar una consulta simple
    $stmt = $conn->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch();
    echo "Base de datos actual: " . ($result['db_name'] ?? 'N/A') . "\n\n";
    
    // Verificar si existen tablas
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas encontradas: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo "Lista de tablas:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
    } else {
        echo "⚠️ No se encontraron tablas. La base de datos puede estar vacía.\n";
        echo "   Ejecuta el script de instalación: database/install.php\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error de conexión:\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
    echo "   Código: " . $e->getCode() . "\n\n";
    
    echo "Posibles soluciones:\n";
    echo "1. Verifica que XAMPP MySQL esté corriendo\n";
    echo "2. Verifica que la base de datos 'streaming_platform' exista\n";
    echo "3. Si la base de datos no existe, créala con:\n";
    echo "   CREATE DATABASE streaming_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    echo "4. Luego ejecuta: database/install.php\n";
}

echo "</pre>";
?>

