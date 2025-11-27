<?php
/**
 * Script interactivo/CLI para actualizar las credenciales de base de datos
 * en `includes/config.php`. Úsalo desde la raíz del proyecto:
 *
 * php scripts/update-db-config.php --host=localhost \
 *   --name=u6O0265163_HAggBlS0j_streamingplatf \
 *   --user=u6O0265163_HAggBlS0j_belchote \
 *   --pass='Belchote1@'
 *
 * Si omites algún parámetro, el script te lo pedirá.
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$configPath = $projectRoot . '/includes/config.php';
$backupPath = $configPath . '.' . date('Ymd_His') . '.bak';

if (!file_exists($configPath)) {
    fwrite(STDERR, "No se encontró includes/config.php\n");
    exit(1);
}

$options = getopt('', ['host::', 'name::', 'user::', 'pass::']);

$dbHost = $options['host'] ?? prompt('Host de la base de datos', 'localhost');
$dbName = $options['name'] ?? prompt('Nombre de la base de datos');
$dbUser = $options['user'] ?? prompt('Usuario de la base de datos');
$dbPass = $options['pass'] ?? prompt('Contraseña de la base de datos');

if ($dbName === '' || $dbUser === '' || $dbPass === '') {
    fwrite(STDERR, "Nombre, usuario y contraseña no pueden estar vacíos.\n");
    exit(1);
}

$original = file_get_contents($configPath);
if ($original === false) {
    fwrite(STDERR, "No se pudo leer includes/config.php\n");
    exit(1);
}

if (file_put_contents($backupPath, $original) === false) {
    fwrite(STDERR, "No se pudo crear el respaldo: {$backupPath}\n");
    exit(1);
}

$updated = $original;
$updated = replaceDefine($updated, 'DB_HOST', $dbHost);
$updated = replaceDefine($updated, 'DB_NAME', $dbName);
$updated = replaceDefine($updated, 'DB_USER', $dbUser);
$updated = replaceDefine($updated, 'DB_PASS', $dbPass);

if ($updated === $original) {
    fwrite(STDOUT, "No se detectaron cambios. Revisa los valores proporcionados.\n");
    exit(0);
}

if (file_put_contents($configPath, $updated) === false) {
    fwrite(STDERR, "No se pudo escribir en includes/config.php. Se mantiene el respaldo en {$backupPath}\n");
    exit(1);
}

fwrite(STDOUT, "Credenciales actualizadas correctamente.\n");
fwrite(STDOUT, "Respaldo creado en: {$backupPath}\n");

exit(0);

function replaceDefine(string $content, string $constant, string $value): string
{
    $pattern = "/define\\s*\\(\\s*'{$constant}'\\s*,\\s*'[^']*'\\s*\\);/";
    $replacement = "define('{$constant}', '" . addslashes($value) . "');";
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $replacement, $content, 1);
    }
    // Si no existe, lo añadimos al principio del archivo para evitar silencios.
    return "<?php\n{$replacement}\n" . ltrim($content, "<?php\n");
}

function prompt(string $label, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, "{$label}{$suffix}: ");
    $input = trim(fgets(STDIN));
    if ($input === '' && $default !== '') {
        return $default;
    }
    return $input;
}


