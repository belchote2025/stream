<?php
/**
 * Script de prueba para verificar la conexión con qBittorrent
 * Ejecutar desde: http://localhost/streaming-platform/test-qbittorrent-connection.php
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Conexión con qBittorrent</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #1a1a1a;
            color: #e0e0e0;
        }
        .container {
            background: #2a2a2a;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #e50914;
            border-bottom: 2px solid #e50914;
            padding-bottom: 10px;
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            background: #3a3a3a;
            border-radius: 5px;
        }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        .info { color: #2196f3; }
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #444;
            max-height: 400px;
            overflow-y: auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #e50914;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #ff0000;
        }
        input[type="text"], input[type="password"] {
            padding: 8px;
            width: 300px;
            border: 1px solid #555;
            border-radius: 5px;
            background: #4a4a4a;
            color: #e0e0e0;
        }
        form {
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        th {
            background: #4a4a4a;
        }
        tr:hover {
            background: #3a3a3a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Prueba de Conexión con qBittorrent</h1>
        
        <?php
        // Obtener configuración
        $qbUrl = getenv('QBITTORRENT_URL') ?: (defined('QBITTORRENT_URL') ? QBITTORRENT_URL : '');
        $qbUsername = getenv('QBITTORRENT_USERNAME') ?: (defined('QBITTORRENT_USERNAME') ? QBITTORRENT_USERNAME : 'admin');
        $qbPassword = getenv('QBITTORRENT_PASSWORD') ?: (defined('QBITTORRENT_PASSWORD') ? QBITTORRENT_PASSWORD : 'adminadmin');
        
        // Permitir configuración manual desde formulario
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
            $qbUrl = $_POST['qb_url'] ?? '';
            $qbUsername = $_POST['qb_username'] ?? '';
            $qbPassword = $_POST['qb_password'] ?? '';
        }
        
        echo '<div class="section">';
        echo '<h2>📋 Configuración Actual</h2>';
        echo '<p><strong>QBITTORRENT_URL:</strong> ' . ($qbUrl ?: '<span class="warning">No configurado</span>') . '</p>';
        echo '<p><strong>QBITTORRENT_USERNAME:</strong> ' . htmlspecialchars($qbUsername) . '</p>';
        echo '<p><strong>QBITTORRENT_PASSWORD:</strong> ' . ($qbPassword ? '<span class="success">Configurado</span>' : '<span class="warning">No configurado</span>') . '</p>';
        echo '</div>';
        
        // Formulario para configurar manualmente
        echo '<div class="section">';
        echo '<h2>⚙️ Configurar Manualmente</h2>';
        echo '<form method="POST">';
        echo '<p><label>URL de qBittorrent:</label><br>';
        echo '<input type="text" name="qb_url" value="' . htmlspecialchars($qbUrl) . '" placeholder="http://localhost:8080"></p>';
        echo '<p><label>Usuario:</label><br>';
        echo '<input type="text" name="qb_username" value="' . htmlspecialchars($qbUsername) . '" placeholder="admin"></p>';
        echo '<p><label>Contraseña:</label><br>';
        echo '<input type="password" name="qb_password" value="' . htmlspecialchars($qbPassword) . '" placeholder="adminadmin"></p>';
        echo '<button type="submit" name="test_connection" class="btn">Probar Conexión</button>';
        echo '</form>';
        echo '</div>';
        
        if (empty($qbUrl)) {
            echo '<div class="section">';
            echo '<h2 class="warning">⚠️ Configuración Requerida</h2>';
            echo '<p>Para usar qBittorrent, necesitas:</p>';
            echo '<ol>';
            echo '<li>Instalar qBittorrent con Web UI habilitado</li>';
            echo '<li>Habilitar la Web UI en qBittorrent: Tools → Options → Web UI</li>';
            echo '<li>Añadir estas variables a tu archivo <code>.env</code>:</li>';
            echo '</ol>';
            echo '<pre>QBITTORRENT_URL=http://localhost:8080
QBITTORRENT_USERNAME=admin
QBITTORRENT_PASSWORD=adminadmin</pre>';
            echo '<p>O usa el formulario arriba para probar con valores temporales.</p>';
            echo '</div>';
        } else {
            echo '<div class="section">';
            echo '<h2>🔌 Prueba de Conexión</h2>';
            
            // Normalizar URL
            $qbUrl = rtrim($qbUrl, '/');
            
            // Función para autenticarse
            function qbLogin($url, $username, $password) {
                $loginUrl = $url . '/api/v2/auth/login';
                $postData = http_build_query(['username' => $username, 'password' => $password]);
                
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                                   "Content-Length: " . strlen($postData) . "\r\n",
                        'content' => $postData,
                        'timeout' => 5,
                        'ignore_errors' => true,
                    ]
                ]);
                
                $response = @file_get_contents($loginUrl, false, $context);
                return $response === 'Ok.';
            }
            
            // Función para hacer petición autenticada
            function qbRequest($url, $endpoint, $method = 'GET', $data = null, $username = '', $password = '') {
                if (!qbLogin($url, $username, $password)) {
                    return ['error' => 'Error de autenticación'];
                }
                
                $requestUrl = $url . $endpoint;
                $headers = ["Content-Type: application/x-www-form-urlencoded"];
                
                if ($method === 'POST' && $data) {
                    $postData = is_array($data) ? http_build_query($data) : $data;
                    $headers[] = "Content-Length: " . strlen($postData);
                }
                
                $context = stream_context_create([
                    'http' => [
                        'method' => $method,
                        'header' => implode("\r\n", $headers),
                        'content' => ($method === 'POST' && $data) ? (is_array($data) ? http_build_query($data) : $data) : null,
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ]);
                
                $response = @file_get_contents($requestUrl, false, $context);
                if ($response === false) {
                    return ['error' => 'No se pudo conectar'];
                }
                
                $json = json_decode($response, true);
                return json_last_error() === JSON_ERROR_NONE ? $json : ['response' => $response];
            }
            
            // 1. Probar autenticación
            echo '<h3>1. Verificar Autenticación</h3>';
            $loginSuccess = qbLogin($qbUrl, $qbUsername, $qbPassword);
            if ($loginSuccess) {
                echo '<p class="success">✅ Autenticación exitosa</p>';
            } else {
                echo '<p class="error">❌ Error de autenticación</p>';
                echo '<p class="warning">Verifica que:</p>';
                echo '<ul>';
                echo '<li>qBittorrent esté corriendo</li>';
                echo '<li>La Web UI esté habilitada (Tools → Options → Web UI)</li>';
                echo '<li>El usuario y contraseña sean correctos</li>';
                echo '<li>La URL sea correcta (por defecto: http://localhost:8080)</li>';
                echo '</ul>';
            }
            
            if ($loginSuccess) {
                // 2. Obtener información de la aplicación
                echo '<h3>2. Información de qBittorrent</h3>';
                $appInfo = qbRequest($qbUrl, '/api/v2/app/version', 'GET', null, $qbUsername, $qbPassword);
                if (isset($appInfo['response'])) {
                    echo '<p class="info">ℹ️ Versión: ' . htmlspecialchars($appInfo['response']) . '</p>';
                }
                
                // 3. Listar torrents
                echo '<h3>3. Torrents Activos</h3>';
                $torrents = qbRequest($qbUrl, '/api/v2/torrents/info', 'GET', null, $qbUsername, $qbPassword);
                if (isset($torrents['error'])) {
                    echo '<p class="error">❌ ' . htmlspecialchars($torrents['error']) . '</p>';
                } else if (is_array($torrents)) {
                    echo '<p class="success">✅ Torrents encontrados: ' . count($torrents) . '</p>';
                    if (count($torrents) > 0) {
                        echo '<table>';
                        echo '<tr><th>Nombre</th><th>Estado</th><th>Progreso</th><th>Seeds</th><th>Tamaño</th></tr>';
                        foreach (array_slice($torrents, 0, 10) as $torrent) {
                            $progress = isset($torrent['progress']) ? number_format($torrent['progress'] * 100, 1) . '%' : 'N/A';
                            $size = isset($torrent['size']) ? number_format($torrent['size'] / 1024 / 1024, 2) . ' MB' : 'N/A';
                            $state = $torrent['state'] ?? 'unknown';
                            $seeds = $torrent['num_seeds'] ?? 0;
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($torrent['name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($state) . '</td>';
                            echo '<td>' . $progress . '</td>';
                            echo '<td>' . $seeds . '</td>';
                            echo '<td>' . $size . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
                
                // 4. Prueba de agregar torrent
                echo '<h3>4. Prueba de Agregar Torrent</h3>';
                echo '<form method="POST">';
                echo '<input type="hidden" name="test_connection" value="1">';
                echo '<input type="hidden" name="qb_url" value="' . htmlspecialchars($qbUrl) . '">';
                echo '<input type="hidden" name="qb_username" value="' . htmlspecialchars($qbUsername) . '">';
                echo '<input type="hidden" name="qb_password" value="' . htmlspecialchars($qbPassword) . '">';
                echo '<p><label>Enlace Magnet de prueba:</label><br>';
                echo '<input type="text" name="test_magnet" value="' . htmlspecialchars($_POST['test_magnet'] ?? 'magnet:?xt=urn:btih:08ada5a7a6183aae1e09d831df6748d566095a10&dn=Big+Buck+Bunny') . '" style="width: 600px;"></p>';
                echo '<button type="submit" name="add_torrent" class="btn">Agregar Torrent de Prueba</button>';
                echo '</form>';
                
                if (isset($_POST['add_torrent']) && !empty($_POST['test_magnet'])) {
                    $magnet = $_POST['test_magnet'];
                    $addResult = qbRequest($qbUrl, '/api/v2/torrents/add', 'POST', [
                        'urls' => $magnet,
                        'paused' => 'true' // Agregar pausado para no descargar
                    ], $qbUsername, $qbPassword);
                    
                    if (isset($addResult['response']) && $addResult['response'] === 'Ok.') {
                        echo '<p class="success">✅ Torrent agregado correctamente</p>';
                    } else {
                        echo '<p class="error">❌ Error al agregar torrent</p>';
                        echo '<pre>' . htmlspecialchars(json_encode($addResult, JSON_PRETTY_PRINT)) . '</pre>';
                    }
                }
            }
            
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h2>📚 Documentación</h2>
            <p>Para más información sobre la API de qBittorrent:</p>
            <ul>
                <li><a href="https://qbittorrent-api.readthedocs.io/en/v2021.4.20/apidoc/torrents.html" target="_blank" style="color: #2196f3;">Documentación oficial de qBittorrent API</a></li>
                <li><a href="https://github.com/qbittorrent/qBittorrent/wiki/Web-UI-API-Documentation" target="_blank" style="color: #2196f3;">Web UI API Documentation</a></li>
            </ul>
        </div>
    </div>
</body>
</html>



