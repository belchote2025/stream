<?php
/**
 * Archivo de prueba para verificar que los endpoints funcionan
 * Acceder a: /api/content/test.php
 */

// Establecer headers ANTES de cualquier output
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Limpiar buffer
ob_clean();

echo json_encode([
    'success' => true,
    'message' => 'API funcionando correctamente',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
]);

exit;








