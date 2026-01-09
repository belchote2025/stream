<?php
// Mostrar información de la sesión
echo "<h1>Prueba de Sesión PHP</h1>";
echo "<h2>Configuración de PHP</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";

// Mostrar configuración de sesión
echo "\n--- Configuración de Sesión ---\n";
$sessionVars = [
    'session.save_path',
    'session.name',
    'session.cookie_lifetime',
    'session.cookie_path',
    'session.cookie_domain',
    'session.cookie_secure',
    'session.cookie_httponly',
    'session.use_strict_mode',
    'session.use_cookies',
    'session.use_only_cookies',
    'session.cache_limiter',
    'session.save_handler'
];

foreach ($sessionVars as $var) {
    echo str_pad($var . ":", 30) . (ini_get($var) ?: 'no definido') . "\n";
}

// Mostrar información de la sesión actual
echo "\n--- Sesión Actual ---\n";
if (session_status() === PHP_SESSION_NONE) {
    echo "Sesión no iniciada\n";
    session_start();
} else {
    echo "Sesión activa\n";
}

echo "ID de sesión: " . session_id() . "\n";
echo "Nombre de sesión: " . session_name() . "\n";

// Probar escritura de sesión
$_SESSION['test_time'] = date('Y-m-d H:i:s');
$sessionFile = session_save_path() . '/sess_' . session_id();

echo "\n--- Prueba de Escritura de Sesión ---\n";
if (is_writable(session_save_path())) {
    echo "El directorio de sesión es escribible\n";
    
    // Intentar crear un archivo de prueba
    $testFile = session_save_path() . '/test_' . uniqid();
    if (file_put_contents($testFile, 'test') !== false) {
        echo "Se pudo crear un archivo en el directorio de sesión\n";
        unlink($testFile);
    } else {
        echo "NO se pudo crear un archivo en el directorio de sesión\n";
    }
} else {
    echo "El directorio de sesión NO es escribible\n";
}

// Mostrar cookies
echo "\n--- Cookies ---\n";
if (empty($_COOKIE)) {
    echo "No hay cookies establecidas\n";
} else {
    foreach ($_COOKIE as $key => $value) {
        echo "$key: $value\n";
    }
}

// Mostrar encabezados
echo "\n--- Encabezados de Respuesta ---\n";
$headers = headers_list();
if (empty($headers)) {
    echo "No se han enviado encabezados aún\n";
} else {
    foreach ($headers as $header) {
        echo "$header\n";
    }
}

// Forzar el envío de la salida
ob_flush();
flush();
?>
