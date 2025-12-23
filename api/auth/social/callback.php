<?php
/**
 * Callback para procesar respuestas OAuth de proveedores sociales
 */

ob_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

$db = getDbConnection();
$auth = new Auth($db);
$baseUrl = rtrim(SITE_URL, '/');
$provider = $_GET['provider'] ?? '';

if (empty($provider)) {
    header('Location: ' . $baseUrl . '/login.php?error=provider_required');
    exit;
}

// Por ahora, mostrar mensaje informativo
// En producción, aquí procesarías el código OAuth y crearías/iniciarías sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Autenticación Social - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
        }
        .message-box {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            backdrop-filter: blur(10px);
        }
        .message-box h2 {
            margin-top: 0;
            color: #e50914;
        }
        .message-box p {
            line-height: 1.6;
            color: #ccc;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #e50914;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #f40612;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h2>Autenticación con <?php echo ucfirst($provider); ?></h2>
        <p>
            La autenticación con <?php echo ucfirst($provider); ?> requiere configuración adicional.
            Por favor, contacta al administrador para configurar las credenciales OAuth necesarias.
        </p>
        <p>
            <strong>Nota:</strong> Para usar esta funcionalidad, necesitas:
        </p>
        <ul style="text-align: left; display: inline-block;">
            <li>Credenciales OAuth de <?php echo ucfirst($provider); ?></li>
            <li>Configurar las variables de entorno (GOOGLE_CLIENT_ID o FACEBOOK_APP_ID)</li>
            <li>Configurar las URLs de callback en el panel de desarrollador</li>
        </ul>
        <a href="<?php echo $baseUrl; ?>/login.php" class="btn">Volver al Login</a>
    </div>
</body>
</html>
<?php
ob_end_flush();
?>





