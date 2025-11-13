<?php
/**
 * Script de instalación de la base de datos
 * Ejecuta este archivo desde el navegador para crear la base de datos
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'streaming_platform');

// Verificar si ya está instalado
$installed = false;
if (file_exists(__DIR__ . '/.installed')) {
    $installed = true;
}

// Procesar instalación
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // Conectar a MySQL (sin seleccionar base de datos)
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Leer el archivo SQL
        $sql = file_get_contents(__DIR__ . '/install.sql');
        
        if ($sql === false) {
            throw new Exception('No se pudo leer el archivo install.sql');
        }

        // Ejecutar el SQL
        $pdo->exec($sql);
        
        // Marcar como instalado
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        
        $success = true;
        $installed = true;
        
    } catch (PDOException $e) {
        $error = 'Error de base de datos: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Streaming Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #141414 0%, #1f1f1f 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(31, 31, 31, 0.95);
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        h1 {
            color: #e50914;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .subtitle {
            color: #999;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: rgba(229, 9, 20, 0.1);
            border-left: 4px solid #e50914;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box h3 {
            color: #e50914;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #e5e5e5;
        }
        
        .info-box li {
            margin: 5px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #e5e5e5;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            color: #fff;
            font-size: 1rem;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #e50914;
        }
        
        .btn {
            background: #e50914;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background: #f40612;
        }
        
        .btn:disabled {
            background: #666;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            color: #28a745;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border-left: 4px solid #dc3545;
            color: #dc3545;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
            color: #ffc107;
        }
        
        .success-icon {
            font-size: 3rem;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .next-steps {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .next-steps h3 {
            margin-bottom: 15px;
            color: #e50914;
        }
        
        .next-steps ol {
            margin-left: 20px;
            color: #e5e5e5;
        }
        
        .next-steps li {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎬 Instalación de Base de Datos</h1>
        <p class="subtitle">Streaming Platform - Estilo Netflix</p>
        
        <?php if ($success): ?>
            <div class="success-icon">✓</div>
            <div class="alert alert-success">
                <strong>¡Instalación completada exitosamente!</strong><br>
                La base de datos ha sido creada y configurada correctamente.
            </div>
            
            <div class="next-steps">
                <h3>Próximos pasos:</h3>
                <ol>
                    <li>Verifica la configuración en <code>includes/config.php</code></li>
                    <li>Cambia la contraseña del usuario admin (por defecto: <strong>admin123</strong>)</li>
                    <li>Accede a <a href="/streaming-platform/" style="color: #e50914;">la aplicación</a></li>
                    <li>Añade contenido desde el panel de administración</li>
                </ol>
            </div>
            
            <p style="margin-top: 20px; text-align: center;">
                <a href="/streaming-platform/" class="btn" style="text-decoration: none; display: inline-block; width: auto; padding: 12px 40px;">
                    Ir a la aplicación
                </a>
            </p>
            
        <?php elseif ($installed): ?>
            <div class="alert alert-warning">
                <strong>La base de datos ya está instalada.</strong><br>
                Si deseas reinstalarla, elimina el archivo <code>.installed</code> en la carpeta database.
            </div>
            
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3>⚠️ Antes de continuar:</h3>
                <ul>
                    <li>Asegúrate de que MySQL/MariaDB esté ejecutándose</li>
                    <li>Verifica las credenciales en <code>database/install.php</code></li>
                    <li>Este proceso creará la base de datos <strong>streaming_platform</strong></li>
                    <li>Se creará un usuario admin (email: admin@streamingplatform.com, password: admin123)</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Host de MySQL:</label>
                    <input type="text" value="<?php echo DB_HOST; ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label>Usuario:</label>
                    <input type="text" value="<?php echo DB_USER; ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label>Base de datos a crear:</label>
                    <input type="text" value="<?php echo DB_NAME; ?>" disabled>
                </div>
                
                <button type="submit" name="install" class="btn">
                    Instalar Base de Datos
                </button>
            </form>
            
            <p style="margin-top: 20px; text-align: center; color: #999; font-size: 0.9rem;">
                Si necesitas cambiar la configuración, edita las constantes al inicio de este archivo.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>

