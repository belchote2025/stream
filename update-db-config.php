<?php
/**
 * Herramienta web temporal para actualizar las credenciales de BD.
 * IMPORTANTE: elimina este archivo después de usarlo.
 */
declare(strict_types=1);

$projectRoot = __DIR__;
$configPath  = $projectRoot . '/includes/config.php';
$backupDir   = $projectRoot . '/backups';

// Cambia este token antes de subirlo a producción
$accessToken = 'CAMBIA_ESTE_TOKEN';

$status  = '';
$error   = '';

if (!file_exists($configPath)) {
    $error = 'No se encontró includes/config.php';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['auth_token'] ?? '');
    if ($token === '' || !hash_equals($accessToken, $token)) {
        $error = 'Token de seguridad incorrecto.';
    } else {
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = trim($_POST['db_pass'] ?? '');

        if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
            $error = 'Todos los campos son obligatorios.';
        } else {
            $original = file_get_contents($configPath);
            if ($original === false) {
                $error = 'No se pudo leer includes/config.php.';
            } else {
                if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
                    $error = 'No se pudo crear la carpeta de respaldos.';
                } else {
                    $backupPath = $backupDir . '/config.php.' . date('Ymd_His') . '.bak';
                    if (file_put_contents($backupPath, $original) === false) {
                        $error = 'No se pudo crear el respaldo.';
                    } else {
                        $updated = $original;
                        $updated = replaceDefine($updated, 'DB_HOST', $dbHost);
                        $updated = replaceDefine($updated, 'DB_NAME', $dbName);
                        $updated = replaceDefine($updated, 'DB_USER', $dbUser);
                        $updated = replaceDefine($updated, 'DB_PASS', $dbPass);

                        if (file_put_contents($configPath, $updated) === false) {
                            $error = 'No se pudo escribir en includes/config.php.';
                        } else {
                            $status = 'Credenciales actualizadas. Respaldo guardado en ' . htmlspecialchars($backupPath, ENT_QUOTES, 'UTF-8');
                        }
                    }
                }
            }
        }
    }
}

require_once $configPath;

function replaceDefine(string $content, string $constant, string $value): string
{
    $pattern = "/define\\s*\\(\\s*'{$constant}'\\s*,\\s*'[^']*'\\s*\\);/";
    $replacement = "define('{$constant}', '" . addslashes($value) . "');";
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, $replacement, $content, 1);
    }
    return "<?php\n{$replacement}\n" . ltrim($content, "<?php\n");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizar BD</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 640px; margin: 40px auto; }
        label { display: block; margin-top: 15px; }
        input { width: 100%; padding: 8px; margin-top: 5px; }
        .status { margin-top: 20px; padding: 10px; border-radius: 6px; }
        .ok { background: #e0f7e9; border: 1px solid #34a853; color: #1b5e20; }
        .error { background: #fdecea; border: 1px solid #ea4335; color: #7f1d1d; }
        button { margin-top: 20px; padding: 10px 20px; }
    </style>
</head>
<body>
    <h1>Actualizar credenciales de base de datos</h1>
    <p>Usa este formulario solo de forma temporal y elimínalo cuando termines.</p>

    <?php if ($status !== ''): ?>
        <div class="status ok"><?= $status ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="status error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <label>
            Token de seguridad
            <input type="password" name="auth_token" required>
        </label>
        <label>
            Host
            <input type="text" name="db_host" value="<?= htmlspecialchars(DB_HOST ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>
            Nombre de la base
            <input type="text" name="db_name" value="<?= htmlspecialchars(DB_NAME ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>
            Usuario
            <input type="text" name="db_user" value="<?= htmlspecialchars(DB_USER ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <label>
            Contraseña
            <input type="text" name="db_pass" value="<?= htmlspecialchars(DB_PASS ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </label>
        <button type="submit">Actualizar</button>
    </form>

    <p style="margin-top:30px;color:#555;">Después de actualizar, elimina este archivo (`update-db-config.php`) por seguridad.</p>
</body>
</html>

