<?php
/**
 * Script de prueba para verificar el funcionamiento del addon Balandro
 * Accede desde: /admin/addons/balandro/test.php
 */

// Evitar cualquier salida antes del HTML
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración y autenticación
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/addons/AddonManager.php';

// Verificar permisos de administrador
if (!isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/login.php');
    exit;
}

// Limpiar buffer
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

$pageTitle = 'Prueba del Addon Balandro';
include __DIR__ . '/../../includes/header.php';
?>

<h1 class="page-header">
                <i class="fas fa-vial"></i> Prueba del Addon Balandro
                <div class="pull-right">
                    <a href="<?php echo SITE_URL; ?>/admin/addons.php" class="btn btn-default">
                        <i class="fas fa-arrow-left"></i> Volver a Addons
                    </a>
                </div>
            </h1>
            
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-info-circle"></i> Información del Sistema</h3>
                </div>
                <div class="panel-body">
                    <?php
                    $tests = [];
                    $passed = 0;
                    $failed = 0;
                    
                    // Test 1: Verificar que PHP tiene cURL
                    $tests[] = [
                        'name' => 'cURL disponible',
                        'status' => function_exists('curl_init'),
                        'message' => function_exists('curl_init') ? 'cURL está disponible' : 'cURL NO está disponible (requerido)'
                    ];
                    
                    // Test 2: Verificar que el AddonManager funciona
                    try {
                        $addonManager = AddonManager::getInstance();
                        $tests[] = [
                            'name' => 'AddonManager cargado',
                            'status' => true,
                            'message' => 'AddonManager se cargó correctamente'
                        ];
                    } catch (Exception $e) {
                        $tests[] = [
                            'name' => 'AddonManager cargado',
                            'status' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ];
                    }
                    
                    // Test 3: Verificar que el addon se puede cargar
                    try {
                        $addonManager = AddonManager::getInstance();
                        $addons = $addonManager->loadAddons();
                        $balandroAddon = $addonManager->getAddon('balandro');
                        
                        if ($balandroAddon) {
                            $tests[] = [
                                'name' => 'Addon Balandro cargado',
                                'status' => true,
                                'message' => 'El addon se cargó correctamente'
                            ];
                        } else {
                            $tests[] = [
                                'name' => 'Addon Balandro cargado',
                                'status' => false,
                                'message' => 'El addon NO se pudo cargar'
                            ];
                        }
                    } catch (Exception $e) {
                        $tests[] = [
                            'name' => 'Addon Balandro cargado',
                            'status' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ];
                    }
                    
                    // Test 4: Verificar directorio de caché
                    if (isset($balandroAddon)) {
                        $cacheDir = __DIR__ . '/../../../cache/balandro/';
                        $cacheExists = is_dir($cacheDir);
                        $cacheWritable = $cacheExists && is_writable($cacheDir);
                        
                        $tests[] = [
                            'name' => 'Directorio de caché',
                            'status' => $cacheExists && $cacheWritable,
                            'message' => $cacheExists 
                                ? ($cacheWritable ? 'Directorio existe y es escribible' : 'Directorio existe pero NO es escribible')
                                : 'Directorio NO existe'
                        ];
                    }
                    
                    // Test 5: Verificar métodos del addon
                    if (isset($balandroAddon)) {
                        $methods = ['onSearch', 'onGetDetails', 'onGetStreams', 'clearCache', 'saveConfig'];
                        $missingMethods = [];
                        
                        foreach ($methods as $method) {
                            if (!method_exists($balandroAddon, $method)) {
                                $missingMethods[] = $method;
                            }
                        }
                        
                        $tests[] = [
                            'name' => 'Métodos del addon',
                            'status' => empty($missingMethods),
                            'message' => empty($missingMethods) 
                                ? 'Todos los métodos están disponibles'
                                : 'Faltan métodos: ' . implode(', ', $missingMethods)
                        ];
                    }
                    
                    // Test 6: Probar conexión a la API
                    if (isset($balandroAddon)) {
                        try {
                            // Intentar una búsqueda simple
                            $testQuery = 'test';
                            $result = $balandroAddon->onSearch($testQuery);
                            
                            $resultCount = isset($result['results']) ? count($result['results']) : 0;
                            $hasError = isset($result['error']);
                            
                            $tests[] = [
                                'name' => 'Conexión a API',
                                'status' => is_array($result) && !$hasError,
                                'message' => is_array($result) 
                                    ? ($hasError 
                                        ? 'Conexión establecida pero error: ' . $result['error']
                                        : 'Conexión exitosa (' . $resultCount . ' resultados). ' . 
                                          ($resultCount === 0 ? 'Nota: La API puede no tener contenido de prueba o requerir configuración adicional.' : ''))
                                    : 'Error en la conexión o respuesta inválida'
                            ];
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Conexión a API',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Test 7: Verificar configuración
                    if (isset($balandroAddon)) {
                        $config = $balandroAddon->getConfig();
                        $hasConfig = !empty($config);
                        
                        $tests[] = [
                            'name' => 'Configuración del addon',
                            'status' => $hasConfig,
                            'message' => $hasConfig 
                                ? 'Configuración cargada (' . count($config) . ' opciones)'
                                : 'No hay configuración disponible'
                        ];
                    }
                    
                    // Test 8: Probar guardado de configuración
                    if (isset($balandroAddon)) {
                        try {
                            $testConfig = ['test' => 'value_' . time()];
                            $saved = $balandroAddon->saveConfig($testConfig);
                            
                            $tests[] = [
                                'name' => 'Guardado de configuración',
                                'status' => $saved,
                                'message' => $saved 
                                    ? 'Configuración guardada correctamente'
                                    : 'Error al guardar configuración'
                            ];
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Guardado de configuración',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Test 9: Probar limpieza de caché
                    if (isset($balandroAddon)) {
                        try {
                            $cleared = $balandroAddon->clearCache();
                            
                            $tests[] = [
                                'name' => 'Limpieza de caché',
                                'status' => true,
                                'message' => 'Caché limpiado correctamente (' . $cleared . ' archivos eliminados)'
                            ];
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Limpieza de caché',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Test 10: Verificar APIs disponibles
                    $apiFiles = [
                        'search.php' => 'Búsqueda',
                        'details.php' => 'Detalles',
                        'streams.php' => 'Streaming'
                    ];
                    
                    foreach ($apiFiles as $file => $name) {
                        $apiPath = __DIR__ . '/../../../api/addons/balandro/' . $file;
                        $exists = file_exists($apiPath);
                        
                        $tests[] = [
                            'name' => "API: $name",
                            'status' => $exists,
                            'message' => $exists ? "Archivo $file existe" : "Archivo $file NO existe"
                        ];
                    }
                    
                    // Test 11: Verificar StreamExtractor
                    if (isset($balandroAddon)) {
                        $extractorPath = __DIR__ . '/../../../addons/balandro/StreamExtractor.php';
                        $extractorExists = file_exists($extractorPath);
                        
                        $tests[] = [
                            'name' => 'StreamExtractor',
                            'status' => $extractorExists,
                            'message' => $extractorExists 
                                ? 'Clase StreamExtractor disponible (extracción de enlaces estilo Kodi)'
                                : 'Clase StreamExtractor NO encontrada'
                        ];
                        
                        if ($extractorExists) {
                            try {
                                require_once $extractorPath;
                                if (class_exists('StreamExtractor')) {
                                    $tests[] = [
                                        'name' => 'StreamExtractor cargado',
                                        'status' => true,
                                        'message' => 'Clase StreamExtractor cargada correctamente'
                                    ];
                                } else {
                                    $tests[] = [
                                        'name' => 'StreamExtractor cargado',
                                        'status' => false,
                                        'message' => 'Clase StreamExtractor no se pudo cargar'
                                    ];
                                }
                            } catch (Exception $e) {
                                $tests[] = [
                                    'name' => 'StreamExtractor cargado',
                                    'status' => false,
                                    'message' => 'Error: ' . $e->getMessage()
                                ];
                            }
                        }
                    }
                    
                    // Test 12: Probar búsqueda con contenido real
                    if (isset($balandroAddon)) {
                        try {
                            require_once __DIR__ . '/../../../includes/config.php';
                            $db = getDbConnection();
                            
                            // Buscar una película en la base de datos
                            $stmt = $db->prepare("SELECT id, title, type FROM content WHERE type = 'movie' LIMIT 1");
                            $stmt->execute();
                            $testContent = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($testContent) {
                                $originalTitle = $testContent['title'];
                                
                                // Probar búsqueda con el título completo
                                $searchResult = $balandroAddon->onSearch($originalTitle);
                                
                                // Si no encuentra, probar con palabras clave del título
                                if (empty($searchResult['results'])) {
                                    $titleWords = explode(' ', $originalTitle);
                                    $keyWords = array_filter($titleWords, function($word) {
                                        $word = trim($word, '.,;:!?');
                                        return strlen($word) > 3 && !in_array(strtolower($word), ['the', 'and', 'or', 'a', 'an', 'for', 'with', 'from']);
                                    });
                                    if (!empty($keyWords)) {
                                        $searchQuery = implode(' ', array_slice($keyWords, 0, 3)); // Tomar las primeras 3 palabras clave
                                        $searchResult = $balandroAddon->onSearch($searchQuery);
                                    }
                                }
                                
                                // Si aún no encuentra, probar búsqueda directa en BD para verificar
                                $dbSearchStmt = $db->prepare("SELECT COUNT(*) as count FROM content WHERE title LIKE ? OR description LIKE ?");
                                $searchTerm = '%' . $originalTitle . '%';
                                $dbSearchStmt->execute([$searchTerm, $searchTerm]);
                                $dbResult = $dbSearchStmt->fetch(PDO::FETCH_ASSOC);
                                $dbCount = $dbResult['count'] ?? 0;
                                
                                $message = '';
                                if (!empty($searchResult['results'])) {
                                    $message = 'Búsqueda exitosa: ' . count($searchResult['results']) . ' resultados para "' . $originalTitle . '"';
                                } else {
                                    $message = 'No se encontraron resultados para "' . $originalTitle . '"';
                                    if ($dbCount > 0) {
                                        $message .= ' (pero hay ' . $dbCount . ' coincidencias en BD - posible problema en la búsqueda)';
                                    } else {
                                        // Verificar si hay contenido similar
                                        $similarStmt = $db->prepare("SELECT title FROM content WHERE type = 'movie' LIMIT 5");
                                        $similarStmt->execute();
                                        $similarTitles = $similarStmt->fetchAll(PDO::FETCH_COLUMN);
                                        if (!empty($similarTitles)) {
                                            $message .= ' (títulos en BD: ' . implode(', ', array_slice($similarTitles, 0, 3)) . '...)';
                                        } else {
                                            $message .= ' (no hay películas en la BD)';
                                        }
                                    }
                                }
                                
                                $tests[] = [
                                    'name' => 'Búsqueda con contenido real',
                                    'status' => !empty($searchResult['results']),
                                    'message' => $message
                                ];
                            } else {
                                $tests[] = [
                                    'name' => 'Búsqueda con contenido real',
                                    'status' => false,
                                    'message' => 'No hay contenido en la base de datos para probar'
                                ];
                            }
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Búsqueda con contenido real',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Test 13: Probar obtención de detalles
                    if (isset($balandroAddon)) {
                        try {
                            require_once __DIR__ . '/../../../includes/config.php';
                            $db = getDbConnection();
                            
                            $stmt = $db->prepare("SELECT id, type FROM content LIMIT 1");
                            $stmt->execute();
                            $testContent = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($testContent) {
                                $contentType = $testContent['type'] === 'series' ? 'tv' : 'movie';
                                $details = $balandroAddon->onGetDetails($testContent['id'], $contentType);
                                
                                $tests[] = [
                                    'name' => 'Obtención de detalles',
                                    'status' => !empty($details),
                                    'message' => !empty($details)
                                        ? 'Detalles obtenidos correctamente (ID: ' . $testContent['id'] . ')'
                                        : 'No se pudieron obtener los detalles'
                                ];
                            } else {
                                $tests[] = [
                                    'name' => 'Obtención de detalles',
                                    'status' => false,
                                    'message' => 'No hay contenido en la base de datos para probar'
                                ];
                            }
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Obtención de detalles',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Test 14: Probar obtención de streams
                    if (isset($balandroAddon)) {
                        try {
                            require_once __DIR__ . '/../../../includes/config.php';
                            $db = getDbConnection();
                            
                            // Verificar si existe la columna imdb_id
                            $stmt = $db->query("SHOW COLUMNS FROM content LIKE 'imdb_id'");
                            $hasImdbId = $stmt->rowCount() > 0;
                            
                            if ($hasImdbId) {
                                $stmt = $db->prepare("SELECT id, type, imdb_id FROM content WHERE imdb_id IS NOT NULL AND imdb_id != '' LIMIT 1");
                                $stmt->execute();
                                $testContent = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($testContent) {
                                    $contentType = $testContent['type'] === 'series' ? 'tv' : 'movie';
                                    $streams = $balandroAddon->onGetStreams($testContent['id'], $contentType);
                                    
                                    $tests[] = [
                                        'name' => 'Obtención de streams',
                                        'status' => !empty($streams),
                                        'message' => !empty($streams)
                                            ? 'Streams obtenidos: ' . count($streams) . ' fuentes encontradas (ID: ' . $testContent['id'] . ', IMDb: ' . $testContent['imdb_id'] . ')'
                                            : 'No se encontraron streams (puede ser normal si no hay fuentes disponibles)'
                                    ];
                                } else {
                                    $tests[] = [
                                        'name' => 'Obtención de streams',
                                        'status' => false,
                                        'message' => 'No hay contenido con IMDb ID en la base de datos para probar'
                                    ];
                                }
                            } else {
                                // Probar sin IMDb ID
                                $stmt = $db->prepare("SELECT id, type FROM content LIMIT 1");
                                $stmt->execute();
                                $testContent = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($testContent) {
                                    $contentType = $testContent['type'] === 'series' ? 'tv' : 'movie';
                                    $streams = $balandroAddon->onGetStreams($testContent['id'], $contentType);
                                    
                                    $tests[] = [
                                        'name' => 'Obtención de streams',
                                        'status' => true,
                                        'message' => 'Streams obtenidos: ' . count($streams) . ' fuentes (ID: ' . $testContent['id'] . ', sin IMDb ID - usando otras fuentes)'
                                    ];
                                } else {
                                    $tests[] = [
                                        'name' => 'Obtención de streams',
                                        'status' => false,
                                        'message' => 'No hay contenido en la base de datos para probar'
                                    ];
                                }
                            }
                        } catch (Exception $e) {
                            $tests[] = [
                                'name' => 'Obtención de streams',
                                'status' => false,
                                'message' => 'Error: ' . $e->getMessage()
                            ];
                        }
                    }
                    
                    // Contar resultados
                    foreach ($tests as $test) {
                        if ($test['status']) {
                            $passed++;
                        } else {
                            $failed++;
                        }
                    }
                    ?>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-<?php echo $failed === 0 ? 'success' : ($passed > $failed ? 'warning' : 'danger'); ?>">
                                <strong>Resultado:</strong> 
                                <?php echo $passed; ?> pruebas pasadas, 
                                <?php echo $failed; ?> pruebas fallidas
                            </div>
                        </div>
                    </div>
                    
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="30">#</th>
                                <th>Prueba</th>
                                <th width="100">Estado</th>
                                <th>Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tests as $index => $test): ?>
                            <tr class="<?php echo $test['status'] ? 'success' : 'danger'; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($test['name']); ?></strong></td>
                                <td>
                                    <?php if ($test['status']): ?>
                                        <span class="label label-success">
                                            <i class="fas fa-check"></i> OK
                                        </span>
                                    <?php else: ?>
                                        <span class="label label-danger">
                                            <i class="fas fa-times"></i> FALLO
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($test['message']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if (isset($balandroAddon)): ?>
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-cog"></i> Pruebas Funcionales</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Prueba de Búsqueda</h4>
                            <form id="testSearchForm" class="form-inline">
                                <div class="form-group">
                                    <input type="text" class="form-control" id="searchQuery" placeholder="Buscar..." value="batman">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </form>
                            <div id="searchResults" class="mt-3" style="display: none;">
                                <pre id="searchOutput" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h4>Prueba de Detalles</h4>
                            <form id="testDetailsForm" class="form-inline">
                                <div class="form-group">
                                    <input type="text" class="form-control" id="contentId" placeholder="ID del contenido" value="27205">
                                </div>
                                <select class="form-control" id="contentType">
                                    <option value="movie">Película</option>
                                    <option value="tv">Serie</option>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-info-circle"></i> Obtener
                                </button>
                            </form>
                            <div id="detailsResults" class="mt-3" style="display: none;">
                                <pre id="detailsOutput" class="bg-light p-3" style="max-height: 300px; overflow-y: auto;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-code"></i> Información Técnica</h3>
                </div>
                <div class="panel-body">
                    <dl class="dl-horizontal">
                        <dt>PHP Version:</dt>
                        <dd><?php echo PHP_VERSION; ?></dd>
                        
                        <dt>cURL Version:</dt>
                        <dd><?php echo function_exists('curl_version') ? curl_version()['version'] : 'No disponible'; ?></dd>
                        
                        <dt>Directorio del addon:</dt>
                        <dd><code><?php echo htmlspecialchars(__DIR__ . '/../../../addons/balandro/'); ?></code></dd>
                        
                        <dt>Directorio de caché:</dt>
                        <dd><code><?php echo htmlspecialchars(__DIR__ . '/../../../cache/balandro/'); ?></code></dd>
                        
                        <?php if (isset($balandroAddon)): ?>
                        <dt>Estado del addon:</dt>
                        <dd>
                            <span class="label label-<?php echo $balandroAddon->isEnabled() ? 'success' : 'default'; ?>">
                                <?php echo $balandroAddon->isEnabled() ? 'Habilitado' : 'Deshabilitado'; ?>
                            </span>
                        </dd>
                        
                        <dt>Versión del addon:</dt>
                        <dd><?php echo htmlspecialchars($balandroAddon->getVersion()); ?></dd>
                        
                        <dt>Configuración:</dt>
                        <dd>
                            <pre class="bg-light p-2" style="max-height: 200px; overflow-y: auto;"><?php 
                                echo htmlspecialchars(json_encode($balandroAddon->getConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                            ?></pre>
                        </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
</div>

<script>
$(document).ready(function() {
    // Prueba de búsqueda
    $('#testSearchForm').on('submit', function(e) {
        e.preventDefault();
        var query = $('#searchQuery').val();
        
        if (!query) {
            alert('Por favor ingresa un término de búsqueda');
            return;
        }
        
        $('#searchResults').show();
        $('#searchOutput').text('Buscando...');
        
        $.ajax({
            url: '<?php echo SITE_URL; ?>/api/addons/balandro/search.php',
            method: 'GET',
            data: { q: query },
            dataType: 'json',
            success: function(data) {
                $('#searchOutput').text(JSON.stringify(data, null, 2));
            },
            error: function(xhr, status, error) {
                $('#searchOutput').text('Error: ' + error + '\n' + xhr.responseText);
            }
        });
    });
    
    // Prueba de detalles
    $('#testDetailsForm').on('submit', function(e) {
        e.preventDefault();
        var contentId = $('#contentId').val();
        var type = $('#contentType').val();
        
        if (!contentId) {
            alert('Por favor ingresa un ID de contenido');
            return;
        }
        
        $('#detailsResults').show();
        $('#detailsOutput').text('Obteniendo detalles...');
        
        $.ajax({
            url: '<?php echo SITE_URL; ?>/api/addons/balandro/details.php',
            method: 'GET',
            data: { id: contentId, type: type },
            dataType: 'json',
            success: function(data) {
                $('#detailsOutput').text(JSON.stringify(data, null, 2));
            },
            error: function(xhr, status, error) {
                $('#detailsOutput').text('Error: ' + error + '\n' + xhr.responseText);
            }
        });
    });
});
</script>

<?php
include __DIR__ . '/../../includes/footer.php';
?>

