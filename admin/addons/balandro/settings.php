<?php
/**
 * Página de configuración del addon Balandro
 */

// Evitar cualquier salida antes del HTML y suprimir warnings
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);
ob_start();

// Incluir configuración y autenticación
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/addons/AddonManager.php';

// Funciones helper para mensajes flash
if (!function_exists('setFlashMessage')) {
    function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_message_type'] = $type;
    }
}

if (!function_exists('hasFlashMessage')) {
    function hasFlashMessage() {
        return isset($_SESSION['flash_message']);
    }
}

if (!function_exists('getFlashMessage')) {
    function getFlashMessage() {
        $message = $_SESSION['flash_message'] ?? '';
        unset($_SESSION['flash_message']);
        return $message;
    }
}

if (!function_exists('getFlashMessageType')) {
    function getFlashMessageType() {
        $type = $_SESSION['flash_message_type'] ?? 'info';
        unset($_SESSION['flash_message_type']);
        return $type;
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $decimals = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        if ($bytes == 0) return '0 B';
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), $decimals) . ' ' . $units[$i];
    }
}

// Verificar permisos de administrador
if (!isAdmin()) {
    header('Location: ' . SITE_URL . '/admin/login.php');
    exit;
}

// Obtener instancia del gestor de addons
try {
    $addonManager = AddonManager::getInstance();
    $addons = $addonManager->loadAddons();
    $balandroAddon = $addonManager->getAddon('balandro');
    
    // Verificar que el addon existe
    if (!$balandroAddon) {
        $error = 'Error: El addon Balandro no está instalado o no se pudo cargar.';
        $config = [];
    } else {
        // Obtener configuración actual
        $config = $balandroAddon->getConfig();
        
        // Procesar el formulario de configuración
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $newConfig = [
                    'api_url' => $_POST['api_url'] ?? 'https://repobal.github.io/base/',
                    'api_key' => $_POST['api_key'] ?? '',
                    'enable_caching' => isset($_POST['enable_caching']) ? true : false,
                    'cache_ttl' => intval($_POST['cache_ttl'] ?? 3600),
                    'max_results' => intval($_POST['max_results'] ?? 20),
                    'default_quality' => $_POST['default_quality'] ?? 'HD',
                    'timeout' => intval($_POST['timeout'] ?? 15),
                    'debug_mode' => isset($_POST['debug_mode']) ? true : false,
                    'enable_vidsrc' => isset($_POST['enable_vidsrc']) ? true : false,
                    'enable_upstream' => isset($_POST['enable_upstream']) ? true : false,
                    'enable_web_scraping' => isset($_POST['enable_web_scraping']) ? true : false,
                    'enable_torrents' => isset($_POST['enable_torrents']) ? true : false
                ];
                
                // Guardar configuración
                $balandroAddon->saveConfig($newConfig);
                $config = $balandroAddon->getConfig();
                
                // Limpiar caché si se solicitó
                if (isset($_POST['clear_cache']) && $_POST['clear_cache'] === '1') {
                    $balandroAddon->clearCache();
                }
                
                // Redirigir con mensaje de éxito
                setFlashMessage('Configuración guardada correctamente', 'success');
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                
            } catch (Exception $e) {
                $error = 'Error al guardar la configuración: ' . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error al cargar el addon: ' . $e->getMessage();
    $balandroAddon = null;
    $config = [];
}

// Limpiar cualquier salida accidental antes del HTML
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Incluir cabecera del panel de administración
$pageTitle = 'Configuración de Balandro';
include __DIR__ . '/../../includes/header.php';
?>

<script>
// Suprimir errores de extensiones del navegador (spoofer.js) - Debe estar al inicio
(function() {
    'use strict';
    if (typeof window.onerror === 'function') {
        var originalError = window.onerror;
    }
    window.onerror = function(msg, url, line, col, error) {
        if (msg && (
            msg.indexOf('spoofer.js') !== -1 ||
            msg.indexOf('An unexpected error occurred') !== -1 ||
            (url && url.indexOf('spoofer.js') !== -1)
        )) {
            return true; // Suprimir el error
        }
        if (originalError) {
            return originalError.apply(this, arguments);
        }
        return false;
    };
    
    window.addEventListener('error', function(e) {
        if (e.message && (
            e.message.indexOf('spoofer.js') !== -1 ||
            e.message.indexOf('An unexpected error occurred') !== -1 ||
            (e.filename && e.filename.indexOf('spoofer.js') !== -1)
        )) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            return false;
        }
    }, true);
    
    // También capturar errores no manejados
    window.addEventListener('unhandledrejection', function(e) {
        var reason = e.reason ? e.reason.toString() : '';
        if (reason.indexOf('spoofer.js') !== -1 || reason.indexOf('An unexpected error occurred') !== -1) {
            e.preventDefault();
            return false;
        }
    }, true);
})();
</script>

            <h1 class="page-header">
                <i class="fas fa-cog"></i> Configuración de Balandro
                <div class="pull-right">
                    <a href="<?php echo SITE_URL; ?>/admin/addons.php" class="btn btn-default">
                        <i class="fas fa-arrow-left"></i> Volver a Addons
                    </a>
                </div>
            </h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (hasFlashMessage()): ?>
                <div class="alert alert-<?php echo getFlashMessageType(); ?>">
                    <i class="fas fa-check-circle"></i> <?php echo getFlashMessage(); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$balandroAddon): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    El addon Balandro no está disponible. Por favor, verifica que el archivo <code>addons/balandro/balandro.php</code> existe y es válido.
                </div>
            <?php else: ?>
            
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-sliders-h"></i> Configuración General</h3>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">URL de la API</label>
                            <div class="col-sm-9">
                                <input type="text" name="api_url" class="form-control" value="<?php echo htmlspecialchars($config['api_url'] ?? 'https://repobal.github.io/base/'); ?>" placeholder="https://api.ejemplo.com/">
                                <p class="help-block">
                                    URL base de la API de Balandro. 
                                    <strong>Nota:</strong> La URL por defecto puede no ser correcta. 
                                    Verifica la documentación de la API para obtener la URL correcta.
                                </p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Clave de API</label>
                            <div class="col-sm-9">
                                <input type="password" name="api_key" class="form-control" value="<?php echo htmlspecialchars($config['api_key'] ?? ''); ?>">
                                <p class="help-block">Ingresa tu clave de API de Balandro (opcional, solo si la API requiere autenticación).</p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Habilitar caché</label>
                            <div class="col-sm-9">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_caching" value="1" <?php echo ($config['enable_caching'] ?? true) ? 'checked' : ''; ?>> Activar caché para mejorar el rendimiento
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Tiempo de caché (segundos)</label>
                            <div class="col-sm-3">
                                <input type="number" name="cache_ttl" class="form-control" value="<?php echo $config['cache_ttl'] ?? 3600; ?>">
                            </div>
                            <div class="col-sm-6">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="clear_cache" value="1"> Limpiar caché ahora
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Resultados por página</label>
                            <div class="col-sm-3">
                                <input type="number" name="max_results" class="form-control" value="<?php echo $config['max_results'] ?? 20; ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Calidad predeterminada</label>
                            <div class="col-sm-3">
                                <select name="default_quality" class="form-control">
                                    <option value="4K" <?php echo ($config['default_quality'] ?? 'HD') === '4K' ? 'selected' : ''; ?>>4K</option>
                                    <option value="1080p" <?php echo ($config['default_quality'] ?? 'HD') === '1080p' ? 'selected' : ''; ?>>1080p</option>
                                    <option value="720p" <?php echo ($config['default_quality'] ?? 'HD') === '720p' ? 'selected' : ''; ?>>720p</option>
                                    <option value="480p" <?php echo ($config['default_quality'] ?? 'HD') === '480p' ? 'selected' : ''; ?>>480p</option>
                                    <option value="360p" <?php echo ($config['default_quality'] ?? 'HD') === '360p' ? 'selected' : ''; ?>>360p</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Timeout (segundos)</label>
                            <div class="col-sm-3">
                                <input type="number" name="timeout" class="form-control" value="<?php echo $config['timeout'] ?? 15; ?>" min="5" max="60">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Modo debug</label>
                            <div class="col-sm-9">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="debug_mode" value="1" <?php echo ($config['debug_mode'] ?? false) ? 'checked' : ''; ?>> Activar modo debug para ver logs detallados
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Fuentes de Streaming</label>
                            <div class="col-sm-9">
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_vidsrc" value="1" <?php echo ($config['enable_vidsrc'] ?? true) ? 'checked' : ''; ?>> 
                                        Habilitar Vidsrc (fuente principal de streaming - como en Kodi)
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_upstream" value="1" <?php echo ($config['enable_upstream'] ?? true) ? 'checked' : ''; ?>> 
                                        Habilitar Upstream/PowVideo/Filemoon/Streamtape/Streamwish (fuentes alternativas)
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_web_scraping" value="1" <?php echo ($config['enable_web_scraping'] ?? true) ? 'checked' : ''; ?>> 
                                        Habilitar navegación web (extracción de enlaces desde páginas web - estilo Balandro)
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="enable_torrents" value="1" <?php echo ($config['enable_torrents'] ?? false) ? 'checked' : ''; ?>> 
                                        Habilitar búsqueda de torrents (solo si no hay streaming disponible)
                                    </label>
                                </div>
                                <p class="help-block">
                                    <strong>Nota:</strong> Este addon funciona como navegador de páginas web para extraer contenido, 
                                    similar al addon original de Kodi. Las fuentes web permiten buscar enlaces de streaming 
                                    desde múltiples proveedores.
                                </p>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-9">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar configuración
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-info-circle"></i> Información del Addon</h3>
                </div>
                <div class="panel-body">
                    <dl class="dl-horizontal">
                        <dt>Nombre:</dt>
                        <dd><?php echo htmlspecialchars($balandroAddon->getName()); ?></dd>
                        
                        <dt>Versión:</dt>
                        <dd><?php echo htmlspecialchars($balandroAddon->getVersion()); ?></dd>
                        
                        <dt>Autor:</dt>
                        <dd><?php echo htmlspecialchars($balandroAddon->getAuthor()); ?></dd>
                        
                        <dt>Descripción:</dt>
                        <dd><?php echo htmlspecialchars($balandroAddon->getDescription()); ?></dd>
                        
                        <dt>Estado:</dt>
                        <dd>
                            <span class="label label-<?php echo $balandroAddon->isEnabled() ? 'success' : 'default'; ?>">
                                <?php echo $balandroAddon->isEnabled() ? 'Habilitado' : 'Deshabilitado'; ?>
                            </span>
                        </dd>
                        
                        <dt>Directorio:</dt>
                        <dd><code><?php echo htmlspecialchars(__DIR__); ?></code></dd>
                        
                        <dt>Estado de la caché:</dt>
                        <dd>
                            <?php 
                            $cacheDir = __DIR__ . '/../../../cache/balandro/';
                            
                            // Intentar crear el directorio si no existe
                            if (!is_dir($cacheDir)) {
                                $cacheBaseDir = dirname($cacheDir);
                                if (!is_dir($cacheBaseDir)) {
                                    @mkdir($cacheBaseDir, 0755, true);
                                }
                                if (!@mkdir($cacheDir, 0755, true)) {
                                    echo '<span class="text-danger">No se pudo crear el directorio de caché. Verifica los permisos.</span>';
                                } else {
                                    echo '<span class="text-success">Directorio de caché creado correctamente</span> - 0 archivos en caché';
                                }
                            } else {
                                $files = glob($cacheDir . '*.cache');
                                $fileCount = count($files);
                                echo $fileCount . ' archivo' . ($fileCount != 1 ? 's' : '') . ' en caché';
                                
                                if ($fileCount > 0) {
                                    $totalSize = 0;
                                    foreach ($files as $file) {
                                        if (is_file($file)) {
                                            $totalSize += filesize($file);
                                        }
                                    }
                                    echo ' (' . formatBytes($totalSize) . ')';
                                }
                            }
                            ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="clear_cache" value="1">
                                <button type="submit" class="btn btn-xs btn-default" onclick="return confirm('¿Estás seguro de limpiar la caché?');">
                                    <i class="fas fa-trash-alt"></i> Limpiar caché
                                </button>
                            </form>
                        </dd>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

<?php
// Incluir pie de página del panel de administración
include __DIR__ . '/../../includes/footer.php';
?>

<script>
// Esperar a que jQuery esté disponible
(function() {
    function initSettings() {
        // Verificar que jQuery esté disponible
        if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
            // Reintentar después de 100ms
            setTimeout(initSettings, 100);
            return;
        }
        
        // Mostrar/ocultar campos de rate limit (si existen)
        var $rateLimitInput = $('input[name="rate_limit_enabled"]');
        if ($rateLimitInput.length > 0) {
            $rateLimitInput.on('change', function() {
                if ($(this).is(':checked')) {
                    $(this).closest('.form-group').next('.form-group').removeClass('hidden');
                } else {
                    $(this).closest('.form-group').next('.form-group').addClass('hidden');
                }
            });
        }
    }
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSettings);
    } else {
        // DOM ya está listo
        initSettings();
    }
})();
</script>
