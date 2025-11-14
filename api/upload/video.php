<?php
/**
 * API para subir archivos de video
 */
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

// Configuración de subida
$uploadDir = __DIR__ . '/../../uploads/videos/';
$maxFileSize = 2 * 1024 * 1024 * 1024; // 2GB para videos
$maxTrailerSize = 500 * 1024 * 1024; // 500MB para trailers
$allowedTypes = ['video/mp4', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/x-matroska', 'video/quicktime'];
$allowedExtensions = ['mp4', 'webm', 'avi', 'mkv', 'mov'];

// Crear directorio si no existe
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Verificar que se haya subido un archivo
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'No se ha subido ningún archivo.';
        if (isset($_FILES['file']['error'])) {
            switch ($_FILES['file']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMsg = 'El archivo es demasiado grande.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMsg = 'El archivo se subió parcialmente.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMsg = 'No se seleccionó ningún archivo.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMsg = 'Falta la carpeta temporal.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMsg = 'Error al escribir el archivo en el disco.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMsg = 'Una extensión de PHP detuvo la subida del archivo.';
                    break;
            }
        }
        throw new Exception($errorMsg);
    }
    
    $file = $_FILES['file'];
    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileType = $file['type'];
    
    // Verificar tamaño del archivo
    $isTrailer = isset($_POST['is_trailer']) && $_POST['is_trailer'] === '1';
    $maxSize = $isTrailer ? $maxTrailerSize : $maxFileSize;
    
    if ($fileSize > $maxSize) {
        $maxSizeMB = round($maxSize / (1024 * 1024));
        throw new Exception("El archivo es demasiado grande. Tamaño máximo: {$maxSizeMB}MB");
    }
    
    // Verificar tipo de archivo
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Tipo de archivo no permitido. Formatos permitidos: " . implode(', ', $allowedExtensions));
    }
    
    // Verificar tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmpName);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Tipo de archivo no permitido. Tipo detectado: {$mimeType}");
    }
    
    // Generar nombre único para el archivo
    $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
    $fileBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileBaseName);
    $newFileName = $fileBaseName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
    $destination = $uploadDir . $newFileName;
    
    // Mover el archivo a la carpeta de destino
    if (!move_uploaded_file($fileTmpName, $destination)) {
        throw new Exception('Error al guardar el archivo en el servidor.');
    }
    
    // Generar URL relativa del archivo
    $fileUrl = '/streaming-platform/uploads/videos/' . $newFileName;
    
    // Obtener información del archivo
    $fileInfo = [
        'name' => $newFileName,
        'original_name' => $fileName,
        'url' => $fileUrl,
        'size' => $fileSize,
        'type' => $mimeType,
        'extension' => $fileExtension
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido correctamente',
        'data' => $fileInfo
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
