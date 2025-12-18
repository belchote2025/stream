<?php
// db_check.php - Sube este archivo a tu servidor (public_html) para probar la conexión
// Accede a él desde: https://tu-dominio.com/db_check.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Conexión a Base de Datos (Hostinger)</h1>";

// Intenta leer el .env
echo "<h2>1. Verificando archivo .env</h2>";
$envPath = __DIR__ . '/.env';

if (!file_exists($envPath)) {
    die("<p style='color:red; font-weight:bold;'>ERROR CRÍTICO: No se encuentra el archivo .env en: " . $envPath . "</p><p>Asegúrate de haber subido el archivo env.production y haberlo renombrado a .env</p>");
} else {
    echo "<p style='color:green'>Archivo .env encontrado.</p>";
}

// Cargar variables manualmente para evitar dependencias
$vars = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $vars[$key] = $value;
        putenv("$key=$value");
    }
}

$host = $vars['DB_HOST'] ?? 'No definido';
$user = $vars['DB_USER'] ?? 'No definido';
$name = $vars['DB_NAME'] ?? 'No definido';
// No mostramos la contraseña por seguridad

echo "<h2>2. Configuración leída</h2>";
echo "<ul>";
echo "<li><strong>DB_HOST:</strong> " . htmlspecialchars($host) . "</li>";
echo "<li><strong>DB_USER:</strong> " . htmlspecialchars($user) . "</li>";
echo "<li><strong>DB_NAME:</strong> " . htmlspecialchars($name) . "</li>";
echo "</ul>";

echo "<h2>3. Intentando conectar...</h2>";
$start = microtime(true);

try {
    // Prueba con PDO que es lo que usa la app
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
    $conn = new PDO($dsn, $vars['DB_USER'], $vars['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5 // Timeout corto
    ]);
    
    $end = microtime(true);
    echo "<h3 style='color:green'>¡CONEXIÓN EXITOSA! ✅</h3>";
    echo "<p>La base de datos respondió en " . round(($end - $start) * 1000, 2) . " ms.</p>";
    echo "<p><strong>Estado de la conexión:</strong> Funciona correctamente.</p>";
    
    // Prueba de versiones
    echo "<h3>Información del Servidor:</h3>";
    echo "<ul>";
    echo "<li>PHP Version: " . phpversion() . "</li>";
    echo "<li>MySQL Version: " . $conn->getAttribute(PDO::ATTR_SERVER_VERSION) . "</li>";
    echo "</ul>";

} catch (PDOException $e) {
    $end = microtime(true);
    echo "<h3 style='color:red'>FALLÓ LA CONEXIÓN ❌</h3>";
    echo "<p>Tiempo transcurrido: " . round(($end - $start) * 1000, 2) . " ms.</p>";
    echo "<div style='background:#ffebee; padding:15px; border:1px solid #f44336; border-radius:5px;'>";
    echo "<strong>Error devuelto:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    
    echo "<h3>Posibles causas del error 504 / Fallo de conexión:</h3>";
    echo "<ol>";
    echo "<li><strong>Host Incorrecto:</strong> Si ves 'Connection refused' o timeout, verifica el DB_HOST. En Hostinger a veces no es 'localhost', revisa tu panel.</li>";
    echo "<li><strong>Contraseña Incorrecta:</strong> Si ves 'Access denied', la contraseña del usuario '$user' está mal.</li>";
    echo "<li><strong>Usuario Incorrecto:</strong> Verifica que el usuario '$user' exista y esté asignado a la base de datos '$name'.</li>";
    echo "</ol>";
}
?>
