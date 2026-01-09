<?php
// Script de diagnóstico para el panel de administración
header('Content-Type: text/plain; charset=utf-8');
echo "=== DIAGNÓSTICO DEL SISTEMA ===\n\n";

// 1. Verificar versión de PHP
echo "1. Versión de PHP: " . phpversion() . "\n";

// 2. Verificar si el archivo addons.php existe
$addonsFile = __DIR__ . '/addons.php';
echo "2. Archivo addons.php: " . (file_exists($addonsFile) ? 'ENCONTRADO' : 'NO ENCONTRADO') . "\n";
if (file_exists($addonsFile)) {
    echo "   Tamaño: " . filesize($addonsFile) . " bytes\n";
    echo "   Última modificación: " . date('Y-m-d H:i:s', filemtime($addonsFile)) . "\n";
}

// 3. Verificar permisos del archivo
$perms = fileperms($addonsFile);
echo "3. Permisos de addons.php: " . substr(sprintf('%o', $perms), -4) . "\n";

// 4. Verificar si se puede leer el archivo
$canRead = is_readable($addonsFile);
echo "4. ¿Se puede leer addons.php? " . ($canRead ? 'SÍ' : 'NO') . "\n";

// 5. Verificar si el módulo mod_rewrite está habilitado
echo "5. Módulo mod_rewrite: " . (in_array('mod_rewrite', apache_get_modules()) ? 'HABILITADO' : 'NO HABILITADO') . "\n";

// 6. Verificar si hay un archivo .htaccess que pueda estar interfiriendo
$htaccess = __DIR__ . '/.htaccess';
echo "6. Archivo .htaccess: " . (file_exists($htaccess) ? 'ENCONTRADO' : 'NO ENCONTRADO') . "\n";
if (file_exists($htaccess)) {
    echo "   Contenido de .htaccess:\n";
    echo "   " . str_replace("\n", "\n   ", file_get_contents($htaccess)) . "\n";
}

// 7. Verificar errores de PHP
echo "7. Configuración de errores:\n";
echo "   display_errors: " . ini_get('display_errors') . "\n";
echo "   error_reporting: " . ini_get('error_reporting') . "\n";

// 8. Verificar si hay algún error al incluir el archivo
echo "8. Intentando incluir addons.php...\n";
ob_start();
try {
    include $addonsFile;
    $output = ob_get_clean();
    echo "   Inclusión exitosa.\n";
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "   ERROR al incluir addons.php: " . $e->getMessage() . "\n";
}

// 9. Verificar si hay redirecciones
function checkRedirect($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'redirect' => $redirectUrl ?: 'No'
    ];
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$testUrl = $baseUrl . '/streaming-platform/admin/addons.php';

echo "\n=== PRUEBA DE ACCESO A LA URL ===\n";
$result = checkRedirect($testUrl);
echo "URL: $testUrl\n";
echo "Código HTTP: " . $result['code'] . "\n";
echo "Redirección: " . $result['redirect'] . "\n";

// 10. Verificar si hay un archivo index.html que pueda estar interfiriendo
$indexHtml = __DIR__ . '/index.html';
echo "\n10. Archivo index.html en /admin: " . (file_exists($indexHtml) ? 'ENCONTRADO' : 'NO ENCONTRADO') . "\n";

// 11. Verificar si hay un archivo index.php que pueda estar interfiriendo
$indexPhp = __DIR__ . '/index.php';
echo "11. Archivo index.php en /admin: " . (file_exists($indexPhp) ? 'ENCONTRADO' : 'NO ENCONTRADO') . "\n";

// 12. Verificar configuración de Apache
$apacheConf = 'C:/xampp/apache/conf/httpd.conf';
echo "\n12. Verificando configuración de Apache...\n";
if (file_exists($apacheConf)) {
    $apacheConfig = file_get_contents($apacheConf);
    echo "   AllowOverride: " . (strpos($apacheConfig, 'AllowOverride All') !== false ? 'All' : 'None') . "\n";
    echo "   ModRewrite: " . (strpos($apacheConfig, 'LoadModule rewrite_module') !== false ? 'Cargado' : 'No cargado') . "\n";
} else {
    echo "   No se pudo encontrar el archivo de configuración de Apache.\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
?>
