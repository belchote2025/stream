#!/usr/bin/env php
<?php
/**
 * Script de Verificación de Correcciones
 * Verifica que todas las mejoras estén implementadas correctamente
 * 
 * Uso: php scripts/verify-fixes.php
 */

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  🔍 VERIFICACIÓN DE CORRECCIONES - UrresTV Platform\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$checks = [];
$errors = [];
$warnings = [];

// Función helper para verificaciones
function check($name, $condition, $successMsg, $errorMsg) {
    global $checks, $errors;
    
    if ($condition) {
        $checks[] = "✅ $name: $successMsg";
        return true;
    } else {
        $checks[] = "❌ $name: $errorMsg";
        $errors[] = $errorMsg;
        return false;
    }
}

function warn($msg) {
    global $warnings;
    $warnings[] = "⚠️  $msg";
}

echo "📋 Verificando archivos...\n\n";

// 1. Verificar archivos creados
check(
    "Logger JS",
    file_exists(__DIR__ . '/../js/logger.js'),
    "Archivo creado correctamente",
    "Archivo js/logger.js no encontrado"
);

check(
    "API Log Error",
    file_exists(__DIR__ . '/../api/log-error.php'),
    "Endpoint de logging creado",
    "Archivo api/log-error.php no encontrado"
);

check(
    "Security Utils",
    file_exists(__DIR__ . '/../includes/security-utils.php'),
    "Utilidades de seguridad disponibles",
    "Archivo includes/security-utils.php no encontrado"
);

check(
    "Clean Cache Script",
    file_exists(__DIR__ . '/../scripts/clean-cache.php'),
    "Script de mantenimiento creado",
    "Archivo scripts/clean-cache.php no encontrado"
);

check(
    "Documentación",
    file_exists(__DIR__ . '/../CORRECCIONES_IMPLEMENTADAS.md') && 
    file_exists(__DIR__ . '/../MEJORAS_RECOMENDADAS.md'),
    "Documentación completa generada",
    "Falta documentación"
);

echo "\n📁 Verificando configuración de archivos...\n\n";

// 2. Verificar .htaccess
$htaccess = file_get_contents(__DIR__ . '/../.htaccess');

check(
    "CORS Seguro",
    strpos($htaccess, 'SetEnvIf Origin') !== false && 
    strpos($htaccess, 'AccessControlAllowOrigin') !== false,
    "CORS configurado con SetEnvIf",
    "CORS no está configurado correctamente"
);

check(
    "Security Headers",
    strpos($htaccess, 'X-Content-Type-Options') !== false &&
    strpos($htaccess, 'X-Frame-Options') !== false &&
    strpos($htaccess, 'X-XSS-Protection') !== false,
    "Headers de seguridad presentes",
    "Faltan headers de seguridad"
);

if (strpos($htaccess, 'Access-Control-Allow-Origin "*"') !== false) {
    warn("CORS sigue usando comodín (*) - PELIGRO DE SEGURIDAD");
}

// 3. Verificar Service Worker
$sw = file_get_contents(__DIR__ . '/../sw.js');

check(
    "SW Version",
    strpos($sw, "CACHE_VERSION = '2.0.0'") !== false,
    "Service Worker actualizado a v2.0.0",
    "Service Worker no tiene la versión correcta"
);

check(
    "SW Debug Mode",
    strpos($sw, 'DEBUG_MODE') !== false,
    "Modo debug implementado",
    "Falta modo debug en Service Worker"
);

// 4. Verificar index.php
$index = file_get_contents(__DIR__ . '/../index.php');

check(
    "Logger incluido",
    strpos($index, 'logger.js') !== false,
    "Logger.js cargado en index.php",
    "Logger.js no está incluido en index.php"
);

check(
    "Defer en scripts",
    strpos($index, 'defer') !== false,
    "Scripts con atributo defer",
    "Scripts sin defer (afecta rendimiento)"
);

check(
    "Cache mejorado",
    strpos($index, 'LOCK_EX') !== false &&
    strpos($index, '!empty($content)') !== false,
    "Función de cache mejorada",
    "Cache no tiene las mejoras implementadas"
);

if (strpos($index, 'time()') !== false && strpos($index, 'filemtime') === false) {
    warn("Algunos scripts usan time() en lugar de filemtime para versionado");
}

echo "\n🔒 Verificando seguridad...\n\n";

// 5. Verificar config.php
if (file_exists(__DIR__ . '/../includes/config.php')) {
    $config = file_get_contents(__DIR__ . '/../includes/config.php');
    
    check(
        "PDO Error Handling",
        strpos($config, 'PDOException') !== false,
        "Manejo de errores PDO correcto",
        "Falta manejo de errores PDO"
    );
    
    if (strpos($config, 'die("Error de conexión') !== false) {
        warn("die() con mensaje de error podría exponer información sensible");
    }
}

echo "\n📂 Verificando directorios...\n\n";

// 6. Verificar directorios necesarios
$dirs = [
    'cache' => __DIR__ . '/../cache',
    'logs' => __DIR__ . '/../logs',
    'cache/rate-limit' => __DIR__ . '/../cache/rate-limit'
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    
    if ($exists && $writable) {
        $checks[] = "✅ Directorio $name: Existe y es escribible";
    } elseif ($exists && !$writable) {
        $checks[] = "⚠️  Directorio $name: Existe pero NO es escribible";
        warn("Directorio $name no es escribible");
    } else {
        $checks[] = "ℹ️  Directorio $name: No existe (se creará automáticamente)";
    }
}

echo "\n💾 Verificando cache...\n\n";

// 7. Verificar archivos de cache
$cacheFiles = glob(__DIR__ . '/../cache/*.cache');
if (count($cacheFiles) > 0) {
    echo "  Archivos en cache: " . count($cacheFiles) . "\n";
    
    $oldCache = 0;
    $now = time();
    foreach ($cacheFiles as $file) {
        if (($now - filemtime($file)) > 3600) {
            $oldCache++;
        }
    }
    
    if ($oldCache > 0) {
        warn("Hay $oldCache archivos de cache antiguos (>1 hora)");
        echo "  💡 Ejecuta: php scripts/clean-cache.php\n";
    }
}

// Mostrar resultados
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  📊 RESULTADOS DE VERIFICACIÓN\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach ($checks as $check) {
    echo "$check\n";
}

if (count($warnings) > 0) {
    echo "\n⚠️  ADVERTENCIAS:\n";
    foreach ($warnings as $warning) {
        echo "$warning\n";
    }
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";

if (count($errors) > 0) {
    echo "  ❌ RESULTADO: " . count($errors) . " problemas encontrados\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    echo "🔧 PROBLEMAS DETECTADOS:\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n";
    exit(1);
} else {
    $warningCount = count($warnings);
    echo "  ✅ RESULTADO: Todas las verificaciones pasaron";
    if ($warningCount > 0) {
        echo " ($warningCount advertencias)";
    }
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    echo "🎉 ¡Excelente! Todas las correcciones están implementadas.\n\n";
    
    if ($warningCount > 0) {
        echo "💡 Revisa las advertencias arriba para optimizaciones adicionales.\n\n";
    }
    
    echo "📖 Siguiente paso: Revisar CORRECCIONES_IMPLEMENTADAS.md\n\n";
    exit(0);
}
?>
