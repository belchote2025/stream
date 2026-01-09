<?php
/**
 * Script de prueba para verificar la conexión con Jackett
 * Ejecutar desde: http://localhost/streaming-platform/test-jackett-connection.php
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Conexión con Jackett</title>
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
        input[type="text"] {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Prueba de Conexión con Jackett</h1>
        
        <?php
        // Obtener configuración
        $jackettUrl = getenv('JACKETT_URL') ?: (defined('JACKETT_URL') ? JACKETT_URL : '');
        $jackettApiKey = getenv('JACKETT_API_KEY') ?: (defined('JACKETT_API_KEY') ? JACKETT_API_KEY : '');
        
        // Permitir configuración manual desde formulario
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $jackettUrl = $_POST['jackett_url'] ?? '';
            $jackettApiKey = $_POST['jackett_api_key'] ?? '';
        }
        
        echo '<div class="section">';
        echo '<h2>📋 Configuración Actual</h2>';
        echo '<p><strong>JACKETT_URL:</strong> ' . ($jackettUrl ?: '<span class="warning">No configurado</span>') . '</p>';
        echo '<p><strong>JACKETT_API_KEY:</strong> ' . ($jackettApiKey ? '<span class="success">Configurado (' . substr($jackettApiKey, 0, 10) . '...)</span>' : '<span class="warning">No configurado</span>') . '</p>';
        echo '</div>';
        
        // Formulario para configurar manualmente
        echo '<div class="section">';
        echo '<h2>⚙️ Configurar Manualmente</h2>';
        echo '<form method="POST">';
        echo '<p><label>URL de Jackett:</label><br>';
        echo '<input type="text" name="jackett_url" value="' . htmlspecialchars($jackettUrl) . '" placeholder="http://localhost:9117"></p>';
        echo '<p><label>API Key:</label><br>';
        echo '<input type="text" name="jackett_api_key" value="' . htmlspecialchars($jackettApiKey) . '" placeholder="Tu API Key"></p>';
        echo '<button type="submit" class="btn">Probar Conexión</button>';
        echo '</form>';
        echo '</div>';
        
        if (empty($jackettUrl) || empty($jackettApiKey)) {
            echo '<div class="section">';
            echo '<h2 class="warning">⚠️ Configuración Requerida</h2>';
            echo '<p>Para usar Jackett, necesitas:</p>';
            echo '<ol>';
            echo '<li>Instalar Jackett (ver <a href="JACKETT_SETUP.md" style="color: #2196f3;">JACKETT_SETUP.md</a>)</li>';
            echo '<li>Añadir estas variables a tu archivo <code>.env</code>:</li>';
            echo '</ol>';
            echo '<pre>JACKETT_URL=http://localhost:9117
JACKETT_API_KEY=tu_api_key_aqui</pre>';
            echo '<p>O usa el formulario arriba para probar con valores temporales.</p>';
            echo '</div>';
        } else {
            echo '<div class="section">';
            echo '<h2>🔌 Prueba de Conexión</h2>';
            
            // Normalizar URL
            $jackettUrl = rtrim($jackettUrl, '/');
            
            // 1. Probar conexión básica
            echo '<h3>1. Verificar que Jackett esté corriendo</h3>';
            $healthUrl = $jackettUrl . '/api/v2.0/server/status';
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
                    'timeout' => 5,
                    'ignore_errors' => true,
                ]
            ]);
            
            $healthResponse = @file_get_contents($healthUrl, false, $context);
            if ($healthResponse !== false) {
                $healthData = json_decode($healthResponse, true);
                if ($healthData) {
                    echo '<p class="success">✅ Jackett está corriendo</p>';
                    echo '<pre>' . json_encode($healthData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                } else {
                    echo '<p class="error">❌ No se pudo parsear la respuesta de Jackett</p>';
                }
            } else {
                echo '<p class="error">❌ No se pudo conectar a Jackett en: ' . htmlspecialchars($jackettUrl) . '</p>';
                echo '<p class="warning">Verifica que:</p>';
                echo '<ul>';
                echo '<li>Jackett esté instalado y corriendo</li>';
                echo '<li>La URL sea correcta (por defecto: http://localhost:9117)</li>';
                echo '<li>El puerto 9117 no esté bloqueado por firewall</li>';
                echo '</ul>';
            }
            
            // 2. Probar API Key
            echo '<h3>2. Verificar API Key</h3>';
            $indexersUrl = $jackettUrl . '/api/v2.0/indexers/all/results?apikey=' . urlencode($jackettApiKey) . '&Query=test';
            $indexersContext = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true,
                ]
            ]);
            
            $indexersResponse = @file_get_contents($indexersUrl, false, $indexersContext);
            if ($indexersResponse !== false) {
                $indexersData = json_decode($indexersResponse, true);
                if (isset($indexersData['Results'])) {
                    echo '<p class="success">✅ API Key válida</p>';
                    echo '<p class="info">ℹ️ Se encontraron ' . count($indexersData['Results']) . ' resultados de prueba</p>';
                } else if (isset($indexersData['error'])) {
                    echo '<p class="error">❌ Error de API: ' . htmlspecialchars($indexersData['error']) . '</p>';
                } else {
                    echo '<p class="warning">⚠️ Respuesta inesperada de Jackett</p>';
                    echo '<pre>' . htmlspecialchars(substr($indexersResponse, 0, 500)) . '</pre>';
                }
            } else {
                echo '<p class="error">❌ No se pudo verificar la API Key</p>';
            }
            
            // 3. Listar indexadores configurados
            echo '<h3>3. Indexadores Configurados</h3>';
            $configUrl = $jackettUrl . '/api/v2.0/indexers/all/results?apikey=' . urlencode($jackettApiKey) . '&Query=test&Category[]=2000';
            $configContext = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true,
                ]
            ]);
            
            $configResponse = @file_get_contents($configUrl, false, $configContext);
            if ($configResponse !== false) {
                $configData = json_decode($configResponse, true);
                if (isset($configData['Results']) && count($configData['Results']) > 0) {
                    $trackers = [];
                    foreach ($configData['Results'] as $result) {
                        $tracker = $result['Tracker'] ?? 'Unknown';
                        if (!in_array($tracker, $trackers)) {
                            $trackers[] = $tracker;
                        }
                    }
                    echo '<p class="success">✅ Indexadores activos: ' . count($trackers) . '</p>';
                    echo '<ul>';
                    foreach ($trackers as $tracker) {
                        echo '<li>' . htmlspecialchars($tracker) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="warning">⚠️ No se encontraron indexadores configurados o activos</p>';
                    echo '<p>Ve a la interfaz de Jackett y configura algunos indexadores.</p>';
                }
            }
            
            // 4. Prueba de búsqueda real
            echo '<h3>4. Prueba de Búsqueda Real</h3>';
            echo '<form method="GET">';
            echo '<input type="hidden" name="test_search" value="1">';
            echo '<input type="hidden" name="jackett_url" value="' . htmlspecialchars($jackettUrl) . '">';
            echo '<input type="hidden" name="jackett_api_key" value="' . htmlspecialchars($jackettApiKey) . '">';
            echo '<p><label>Buscar:</label><br>';
            echo '<input type="text" name="search_query" value="' . htmlspecialchars($_GET['search_query'] ?? 'Inception') . '" placeholder="Título a buscar">';
            echo '<button type="submit" class="btn">Buscar</button></p>';
            echo '</form>';
            
            if (isset($_GET['test_search']) && !empty($_GET['search_query'])) {
                $searchQuery = $_GET['search_query'];
                $searchUrl = $jackettUrl . '/api/v2.0/indexers/all/results?apikey=' . urlencode($jackettApiKey) . 
                           '&Query=' . urlencode($searchQuery) . '&Category[]=2000';
                
                $searchResponse = @file_get_contents($searchUrl, false, $indexersContext);
                if ($searchResponse !== false) {
                    $searchData = json_decode($searchResponse, true);
                    if (isset($searchData['Results'])) {
                        echo '<p class="success">✅ Búsqueda exitosa: ' . count($searchData['Results']) . ' resultados</p>';
                        if (count($searchData['Results']) > 0) {
                            echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
                            echo '<tr style="background: #4a4a4a;"><th style="padding: 10px; text-align: left;">Título</th><th style="padding: 10px; text-align: left;">Tracker</th><th style="padding: 10px; text-align: left;">Seeds</th><th style="padding: 10px; text-align: left;">Tamaño</th></tr>';
                            $count = 0;
                            foreach (array_slice($searchData['Results'], 0, 10) as $result) {
                                $count++;
                                $bg = $count % 2 == 0 ? '#3a3a3a' : '#2a2a2a';
                                echo '<tr style="background: ' . $bg . ';">';
                                echo '<td style="padding: 8px;">' . htmlspecialchars($result['Title'] ?? 'N/A') . '</td>';
                                echo '<td style="padding: 8px;">' . htmlspecialchars($result['Tracker'] ?? 'N/A') . '</td>';
                                echo '<td style="padding: 8px;">' . ($result['Seeders'] ?? 0) . '</td>';
                                echo '<td style="padding: 8px;">' . (isset($result['Size']) ? number_format($result['Size'] / 1024 / 1024, 2) . ' MB' : 'N/A') . '</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                        }
                    } else {
                        echo '<p class="error">❌ No se encontraron resultados</p>';
                    }
                } else {
                    echo '<p class="error">❌ Error al realizar la búsqueda</p>';
                }
            }
            
            echo '</div>';
        }
        ?>
        
        <div class="section">
            <h2>📚 Documentación</h2>
            <p>Para más información sobre cómo instalar y configurar Jackett, consulta:</p>
            <ul>
                <li><a href="JACKETT_SETUP.md" style="color: #2196f3;">JACKETT_SETUP.md</a> - Guía de configuración</li>
                <li><a href="https://github.com/Jackett/Jackett" target="_blank" style="color: #2196f3;">Documentación oficial de Jackett</a></li>
            </ul>
        </div>
    </div>
</body>
</html>



