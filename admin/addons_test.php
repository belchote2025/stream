<?php
// Archivo de prueba mínimo
header('Content-Type: text/plain; charset=utf-8');
echo "¡Funciona! El archivo se está cargando correctamente.\n\n";

// Mostrar información del servidor
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'No disponible') . "\n";

// Verificar si hay errores
echo "\nErrores:\n";
$addonsFile = __DIR__ . '/addons.php';
if (!file_exists($addonsFile)) {
    echo "- El archivo addons.php no existe en: " . $addonsFile . "\n";
} else {
    echo "- El archivo addons.php EXISTE en: " . $addonsFile . "\n";
    echo "- Tamaño: " . filesize($addonsFile) . " bytes\n";
    
    // Verificar permisos
    $perms = fileperms($addonsFile);
    echo "- Permisos: " . substr(sprintf('%o', $perms), -4) . "\n";
    
    // Verificar si es legible
    echo "- ¿Se puede leer? " . (is_readable($addonsFile) ? 'Sí' : 'No') . "\n";
    
    // Verificar errores de sintaxis
    $output = [];
    exec('php -l ' . escapeshellarg($addonsFile) . ' 2>&1', $output, $return_var);
    if ($return_var === 0) {
        echo "- La sintaxis de addons.php es correcta\n";
    } else {
        echo "- ERROR en la sintaxis de addons.php:\n";
        echo "  " . implode("\n  ", $output) . "\n";
    }
}

// Verificar si hay un .htaccess que pueda estar interfiriendo
$htaccess = __DIR__ . '/.htaccess';
if (file_exists($htaccess)) {
    echo "\nADVERTENCIA: Se encontró un archivo .htaccess que podría estar causando problemas.\n";
    echo "Contenido de .htaccess:\n";
    echo file_get_contents($htaccess) . "\n";
}

// Verificar si hay un archivo index.html/index.php que pueda estar interfiriendo
$indexFiles = ['index.html', 'index.php'];
foreach ($indexFiles as $file) {
    $indexFile = __DIR__ . '/' . $file;
    if (file_exists($indexFile)) {
        echo "\nADVERTENCIA: Se encontró $file que podría estar interfiriendo con las rutas.\n";
    }
}
?>
