<?php
/**
 * Script de Mantenimiento: Limpiar Cachés Antiguos
 * 
 * Ejecutar periódicamente (ej: cron job diario)
 * Uso: php scripts/clean-cache.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security-utils.php';

echo "=== Iniciando limpieza de cachés ===\n\n";

$cacheDirectories = [
    __DIR__ . '/../cache' => 3600,        // 1 hora para cache general
    __DIR__ . '/../cache/rate-limit' => 86400,  // 24 horas para rate limiting
];

$totalCleaned = 0;

foreach ($cacheDirectories as $dir => $maxAge) {
    if (!is_dir($dir)) {
        echo "Directorio no existe: $dir\n";
        continue;
    }
    
    echo "Limpiando: $dir (archivos > " . ($maxAge/3600) . " horas)\n";
    
    $files = glob($dir . '/*');
    $cleaned = 0;
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $age = $now - filemtime($file);
            if ($age > $maxAge) {
                if (@unlink($file)) {
                    $cleaned++;
                    $totalCleaned++;
                }
            }
        }
    }
    
    echo "  → Eliminados: $cleaned archivos\n\n";
}

echo "=== Limpieza completada ===\n";
echo "Total de archivos eliminados: $totalCleaned\n";

// Limpiar logs antiguos (opcional)
$logFile = __DIR__ . '/../logs/frontend-errors.log';
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $sizeMB = round($size / 1024 / 1024, 2);
    
    echo "\nTamaño del log de errores: {$sizeMB} MB\n";
    
    // Si el log es muy grande (>10MB), rotarlo
    if ($size > 10 * 1024 * 1024) {
        $backupFile = $logFile . '.' . date('Y-m-d-His') . '.bak';
        rename($logFile, $backupFile);
        touch($logFile);
        echo "Log rotado a: " . basename($backupFile) . "\n";
    }
}

echo "\n✓ Proceso completado\n";
?>
