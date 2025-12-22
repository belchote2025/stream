<?php
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió el archivo de imagen');
    }

    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 6 * 1024 * 1024; // 6MB

    if ($file['size'] > $maxSize) {
        throw new Exception('La imagen supera el tamaño máximo de 6MB');
    }

    // Validar mime
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) {
        throw new Exception('Formato no permitido. Usa JPG, PNG o WEBP');
    }

    // Asegurar carpeta
    $uploadDir = __DIR__ . '/../../uploads/images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = uniqid('img_', true) . '.' . strtolower($ext);
    $destPath = $uploadDir . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('No se pudo guardar la imagen');
    }

    $baseUrl = rtrim(SITE_URL, '/');
    $url = $baseUrl . '/uploads/images/' . $safeName;

    echo json_encode([
        'success' => true,
        'data' => ['url' => $url]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}







