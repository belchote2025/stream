<?php
/**
 * Script para corregir rutas de archivos CSS/JS en producción
 * Elimina el prefijo /streaming-platform/ de las rutas
 */

// Token de seguridad - CAMBIA ESTO
$accessToken = 'belchote';

// Verificar token
$token = $_POST['token'] ?? $_GET['token'] ?? '';

if ($token !== $accessToken) {
    die('Acceso denegado. Token incorrecto.');
}

// Archivos a corregir
$filesToFix = [
    'includes/header.php',
    'includes/footer.php',
];

$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fix') {
    foreach ($filesToFix as $file) {
        $filePath = __DIR__ . '/' . $file;
        
        if (!file_exists($filePath)) {
            $errors[] = "Archivo no encontrado: $file";
            continue;
        }
        
        // Crear respaldo
        $backupPath = __DIR__ . '/backups/' . basename($file) . '.' . date('Ymd_His') . '.bak';
        if (!is_dir(__DIR__ . '/backups')) {
            mkdir(__DIR__ . '/backups', 0755, true);
        }
        copy($filePath, $backupPath);
        
        // Leer contenido
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        // Reemplazar rutas /streaming-platform/ por rutas relativas
        // Patrones a buscar y reemplazar
        $replacements = [
            // CSS
            '/streaming-platform/css/' => '/css/',
            '/streaming-platform/assets/css/' => '/assets/css/',
            // JS
            '/streaming-platform/js/' => '/js/',
            '/streaming-platform/assets/js/' => '/assets/js/',
            // Imágenes
            '/streaming-platform/assets/img/' => '/assets/img/',
            '/streaming-platform/assets/images/' => '/assets/images/',
            // API
            '/streaming-platform/api/' => '/api/',
            // Páginas
            '/streaming-platform/' => '/',
            // image-proxy.php específico
            'href="/streaming-platform/api/image-proxy.php' => 'href="/api/image-proxy.php',
            'src="/streaming-platform/api/image-proxy.php' => 'src="/api/image-proxy.php',
        ];
        
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }
        
        // Guardar si hubo cambios
        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            $results[] = [
                'file' => $file,
                'status' => 'corregido',
                'backup' => $backupPath,
                'changes' => substr_count($originalContent, '/streaming-platform/') - substr_count($content, '/streaming-platform/')
            ];
        } else {
            $results[] = [
                'file' => $file,
                'status' => 'sin cambios',
                'backup' => null
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corrector de Rutas - Streaming Platform</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #1a1a1a;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #2a2a2a;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        h1 {
            color: #e50914;
            margin-bottom: 20px;
            border-bottom: 2px solid #e50914;
            padding-bottom: 10px;
        }
        .warning {
            background: #ff6b35;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info {
            background: #2196F3;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #4CAF50;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background: #f44336;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        form {
            margin-top: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #444;
            border-radius: 5px;
            background: #1a1a1a;
            color: #fff;
            font-size: 16px;
        }
        button {
            background: #e50914;
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }
        button:hover {
            background: #b8070f;
        }
        .results {
            margin-top: 30px;
        }
        .result-item {
            background: #1a1a1a;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .result-item.error {
            border-left-color: #f44336;
        }
        .result-item.warning {
            border-left-color: #ff6b35;
        }
        code {
            background: #1a1a1a;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Corrector de Rutas para Producción</h1>
        
        <div class="warning">
            <strong>⚠️ IMPORTANTE:</strong> Este script eliminará el prefijo <code>/streaming-platform/</code> de todas las rutas en los archivos PHP. 
            Asegúrate de haber cambiado el token de seguridad antes de usar este script.
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="success">
                <strong>✅ Corrección completada</strong>
            </div>
            
            <div class="results">
                <h2>Resultados:</h2>
                <?php foreach ($results as $result): ?>
                    <div class="result-item <?php echo $result['status'] === 'corregido' ? '' : 'warning'; ?>">
                        <strong><?php echo htmlspecialchars($result['file']); ?></strong><br>
                        Estado: <?php echo $result['status']; ?><br>
                        <?php if ($result['status'] === 'corregido'): ?>
                            Cambios realizados: <?php echo $result['changes']; ?><br>
                            Respaldo guardado en: <code><?php echo htmlspecialchars($result['backup']); ?></code>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <strong>Errores:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="info" style="margin-top: 20px;">
                <strong>📝 Próximos pasos:</strong>
                <ol>
                    <li>Verifica que tu sitio funcione correctamente</li>
                    <li>Si todo está bien, <strong>elimina este archivo</strong> (<code>fix-paths.php</code>) por seguridad</li>
                    <li>También elimina los archivos de respaldo en <code>backups/</code> si no los necesitas</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="info">
                <strong>📋 Este script corregirá las siguientes rutas:</strong>
                <ul>
                    <li><code>/streaming-platform/css/</code> → <code>/css/</code></li>
                    <li><code>/streaming-platform/js/</code> → <code>/js/</code></li>
                    <li><code>/streaming-platform/assets/</code> → <code>/assets/</code></li>
                    <li><code>/streaming-platform/api/</code> → <code>/api/</code></li>
                    <li><code>/streaming-platform/</code> → <code>/</code> (en enlaces)</li>
                </ul>
                <strong>Archivos que se modificarán:</strong>
                <ul>
                    <?php foreach ($filesToFix as $file): ?>
                        <li><code><?php echo htmlspecialchars($file); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="fix">
                <label>
                    <strong>Token de seguridad:</strong>
                    <input type="text" name="token" placeholder="Introduce el token" required>
                </label>
                <button type="submit">🔧 Corregir Rutas</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

