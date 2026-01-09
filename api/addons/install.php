<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/addons/AddonManager.php';
require_once __DIR__ . '/../../includes/auth/APIAuth.php';

header('Content-Type: application/json');

// Verificar autenticación
$auth = APIAuth::getInstance();
if (!$auth->isAuthenticated() || !$auth->hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Verificar si se envió un archivo
if (!isset($_FILES['addon_zip'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se ha subido ningún archivo']);
    exit;
}

$file = $_FILES['addon_zip'];
$tempFile = $file['tmp_name'];
$fileName = basename($file['name']);
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Verificar que sea un archivo ZIP
if ($fileExt !== 'zip') {
    echo json_encode(['status' => 'error', 'message' => 'El archivo debe ser un ZIP']);
    exit;
}

// Directorio temporal para extraer
$tempDir = sys_get_temp_dir() . '/addon_install_' . uniqid();
mkdir($tempDir, 0755, true);

// Extraer el ZIP
$zip = new ZipArchive();
if ($zip->open($tempFile) !== true) {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo abrir el archivo ZIP']);
    exit;
}

$zip->extractTo($tempDir);
$zip->close();

// Buscar el archivo addon.json
$addonJson = $tempDir . '/addon.json';
if (!file_exists($addonJson)) {
    // Buscar recursivamente en subdirectorios
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getFilename() === 'addon.json') {
            $addonJson = $file->getPathname();
            break;
        }
    }

    if (!file_exists($addonJson)) {
        echo json_encode(['status' => 'error', 'message' => 'No se encontró addon.json en el paquete']);
        exit;
    }
}

// Leer la configuración del addon
$config = json_decode(file_get_contents($addonJson), true);
if (!$config || !isset($config['id'], $config['name'], $config['version'])) {
    echo json_encode(['status' => 'error', 'message' => 'El archivo addon.json es inválido']);
    exit;
}

// Directorio de destino
$addonsDir = __DIR__ . '/../../../addons';
$addonDir = $addonsDir . '/' . $config['id'];

// Crear directorio de addons si no existe
if (!file_exists($addonsDir)) {
    mkdir($addonsDir, 0755, true);
}

// Si el addon ya existe, hacer copia de seguridad
$backupDir = null;
if (file_exists($addonDir)) {
    $backupDir = $addonsDir . '/.backup_' . $config['id'] . '_' . time();
    rename($addonDir, $backupDir);
}

// Mover los archivos al directorio de addons
$sourceDir = dirname($addonJson);
$destDir = $addonDir;

// Función para copiar directorios recursivamente
function copyDirectory($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

copyDirectory($sourceDir, $destDir);

// Limpiar archivos temporales
function removeDirectory($dir) {
    if (!file_exists($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

removeDirectory($tempDir);

// Cargar el addon
$addonManager = AddonManager::getInstance();
$addonManager->loadAddons();

// Verificar si el addon se cargó correctamente
$addon = $addonManager->getAddon($config['id']);
if (!$addon) {
    // Revertir la instalación si falla
    if ($backupDir) {
        removeDirectory($addonDir);
        rename($backupDir, $addonDir);
    } else {
        removeDirectory($addonDir);
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => 'El addon no se pudo cargar correctamente. Se ha restaurado la versión anterior si existía.'
    ]);
    exit;
}

// Limpiar copia de seguridad si todo fue bien
if ($backupDir && file_exists($backupDir)) {
    removeDirectory($backupDir);
}

echo json_encode([
    'status' => 'success',
    'message' => 'Addon instalado correctamente',
    'addon' => [
        'id' => $addon->getId(),
        'name' => $addon->getName(),
        'version' => $addon->getVersion(),
        'enabled' => $addon->isEnabled()
    ]
]);
?>
