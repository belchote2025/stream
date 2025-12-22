<?php
/**
 * Script de verificación de configuración Google OAuth
 * Acceder a: /verificar-google-oauth.php
 */

// Establecer headers
ob_start();
header('Content-Type: text/html; charset=utf-8');

// Limpiar buffer
ob_clean();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación Google OAuth</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .check {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            border-left: 4px solid #28a745;
        }
        .error {
            border-left: 4px solid #dc3545;
        }
        .warning {
            border-left: 4px solid #ffc107;
        }
        h1 {
            color: #333;
        }
        h2 {
            color: #666;
            font-size: 1.2em;
            margin-top: 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .value {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            word-break: break-all;
        }
        .instructions {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Verificación de Configuración Google OAuth</h1>
    
    <?php
    // Cargar configuración
    require_once __DIR__ . '/includes/config.php';
    
    $checks = [];
    $allPassed = true;
    
    // 1. Verificar archivo .env
    $envPath = __DIR__ . '/.env';
    $envExists = file_exists($envPath);
    $checks['env_file'] = $envExists;
    if (!$envExists) {
        $allPassed = false;
    }
    
    // 2. Verificar variables de entorno
    $googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
    $googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
    
    $checks['client_id'] = !empty($googleClientId);
    $checks['client_secret'] = !empty($googleClientSecret);
    
    if (empty($googleClientId) || empty($googleClientSecret)) {
        $allPassed = false;
    }
    
    // 3. Verificar formato del Client ID
    $validClientIdFormat = false;
    if (!empty($googleClientId)) {
        $validClientIdFormat = preg_match('/^[\d]+-[a-zA-Z0-9_-]+\.apps\.googleusercontent\.com$/', $googleClientId);
    }
    $checks['client_id_format'] = $validClientIdFormat;
    
    // 4. Verificar formato del Client Secret
    $validClientSecretFormat = false;
    if (!empty($googleClientSecret)) {
        $validClientSecretFormat = preg_match('/^GOCSPX-[a-zA-Z0-9_-]+$/', $googleClientSecret);
    }
    $checks['client_secret_format'] = $validClientSecretFormat;
    
    // 5. Verificar SITE_URL
    $siteUrl = defined('SITE_URL') ? SITE_URL : '';
    $checks['site_url'] = !empty($siteUrl);
    
    // Mostrar resultados
    ?>
    
    <div class="check <?php echo $envExists ? 'success' : 'error'; ?>">
        <h2>1. Archivo .env</h2>
        <?php if ($envExists): ?>
            <p>✅ El archivo <code>.env</code> existe en: <code><?php echo htmlspecialchars($envPath); ?></code></p>
        <?php else: ?>
            <p>❌ El archivo <code>.env</code> NO existe en: <code><?php echo htmlspecialchars($envPath); ?></code></p>
            <div class="instructions">
                <strong>💡 Solución:</strong> Crea un archivo llamado <code>.env</code> en la raíz del proyecto con el siguiente contenido:
                <pre style="background: white; padding: 10px; border-radius: 4px; margin-top: 10px;">GOOGLE_CLIENT_ID=tu_client_id_aqui
GOOGLE_CLIENT_SECRET=tu_client_secret_aqui</pre>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="check <?php echo $checks['client_id'] ? 'success' : 'error'; ?>">
        <h2>2. GOOGLE_CLIENT_ID</h2>
        <?php if ($checks['client_id']): ?>
            <p>✅ Variable <code>GOOGLE_CLIENT_ID</code> está configurada</p>
            <div class="value">
                <strong>Valor:</strong> <?php echo htmlspecialchars(substr($googleClientId, 0, 50)) . (strlen($googleClientId) > 50 ? '...' : ''); ?>
            </div>
            <?php if (!$validClientIdFormat): ?>
                <p>⚠️ El formato del Client ID no parece correcto. Debería ser: <code>123456789-abcdefg.apps.googleusercontent.com</code></p>
            <?php endif; ?>
        <?php else: ?>
            <p>❌ Variable <code>GOOGLE_CLIENT_ID</code> NO está configurada</p>
            <div class="instructions">
                <strong>💡 Solución:</strong> Añade esta línea a tu archivo <code>.env</code>:
                <pre style="background: white; padding: 10px; border-radius: 4px; margin-top: 10px;">GOOGLE_CLIENT_ID=tu_client_id_de_google_cloud_console</pre>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="check <?php echo $checks['client_secret'] ? 'success' : 'error'; ?>">
        <h2>3. GOOGLE_CLIENT_SECRET</h2>
        <?php if ($checks['client_secret']): ?>
            <p>✅ Variable <code>GOOGLE_CLIENT_SECRET</code> está configurada</p>
            <div class="value">
                <strong>Valor:</strong> <?php echo htmlspecialchars(substr($googleClientSecret, 0, 20)) . '...' . (strlen($googleClientSecret) > 20 ? ' (oculto por seguridad)' : ''); ?>
            </div>
            <?php if (!$validClientSecretFormat): ?>
                <p>⚠️ El formato del Client Secret no parece correcto. Debería empezar con: <code>GOCSPX-</code></p>
            <?php endif; ?>
        <?php else: ?>
            <p>❌ Variable <code>GOOGLE_CLIENT_SECRET</code> NO está configurada</p>
            <div class="instructions">
                <strong>💡 Solución:</strong> Añade esta línea a tu archivo <code>.env</code>:
                <pre style="background: white; padding: 10px; border-radius: 4px; margin-top: 10px;">GOOGLE_CLIENT_SECRET=tu_client_secret_de_google_cloud_console</pre>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="check <?php echo $checks['site_url'] ? 'success' : 'warning'; ?>">
        <h2>4. SITE_URL</h2>
        <?php if ($checks['site_url']): ?>
            <p>✅ Variable <code>SITE_URL</code> está configurada</p>
            <div class="value">
                <strong>Valor:</strong> <?php echo htmlspecialchars($siteUrl); ?>
            </div>
            <p><strong>⚠️ IMPORTANTE:</strong> Asegúrate de que esta URL esté configurada como "URI de redirección autorizados" en Google Cloud Console:</p>
            <div class="value">
                <?php echo htmlspecialchars($siteUrl); ?>/api/auth/social/google.php
            </div>
        <?php else: ?>
            <p>⚠️ Variable <code>SITE_URL</code> no está configurada</p>
        <?php endif; ?>
    </div>
    
    <div class="check <?php echo $allPassed ? 'success' : 'error'; ?>">
        <h2>📊 Resumen</h2>
        <?php if ($allPassed): ?>
            <p>✅ <strong>¡Todo está configurado correctamente!</strong></p>
            <p>Los botones de Google deberían funcionar ahora. Prueba haciendo clic en el botón de Google en la página de login.</p>
        <?php else: ?>
            <p>❌ <strong>Hay problemas en la configuración</strong></p>
            <p>Por favor, corrige los errores indicados arriba y recarga esta página para verificar nuevamente.</p>
        <?php endif; ?>
    </div>
    
    <div class="instructions">
        <h2>📚 Guía Completa</h2>
        <p>Para una guía detallada sobre cómo obtener las credenciales de Google OAuth, consulta el archivo:</p>
        <p><code>CONFIGURAR_GOOGLE_OAUTH.md</code></p>
        <p>O sigue estos pasos rápidos:</p>
        <ol>
            <li>Ve a <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Crea un proyecto o selecciona uno existente</li>
            <li>Habilita la API de Google+ o Google Identity</li>
            <li>Ve a "Credenciales" → "Crear credenciales" → "ID de cliente OAuth 2.0"</li>
            <li>Configura la pantalla de consentimiento (si es la primera vez)</li>
            <li>Crea un ID de cliente tipo "Aplicación web"</li>
            <li>Añade la URI de redirección: <code><?php echo htmlspecialchars($siteUrl); ?>/api/auth/social/google.php</code></li>
            <li>Copia el Client ID y Client Secret</li>
            <li>Añádelos a tu archivo <code>.env</code></li>
        </ol>
    </div>
    
    <p style="text-align: center; margin-top: 30px; color: #666;">
        <a href="login.php">← Volver al Login</a>
    </p>
</body>
</html>

