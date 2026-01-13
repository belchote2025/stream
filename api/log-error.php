<?php
/**
 * API Endpoint: Error Logging
 * Recibe y almacena errores del frontend
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Leer datos
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['errors']) || !is_array($data['errors'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data format']);
        exit;
    }
    
    // Crear directorio de logs si no existe
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/frontend-errors.log';
    
    // Preparar entrada de log
    foreach ($data['errors'] as $error) {
        $logEntry = [
            'timestamp' => $error['timestamp'] ?? date('Y-m-d H:i:s'),
            'level' => $error['level'] ?? 'error',
            'message' => $error['message'] ?? 'No message',
            'url' => $error['url'] ?? 'Unknown',
            'userAgent' => $error['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        ];
        
        if (isset($error['error'])) {
            $logEntry['errorDetails'] = $error['error'];
        }
        
        // Escribir al archivo de log
        $logLine = date('[Y-m-d H:i:s]') . ' ' . strtoupper($error['level'] ?? 'ERROR') . ': ' 
                 . json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        error_log($logLine, 3, $logFile);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Errors logged successfully',
        'count' => count($data['errors'])
    ]);
    
} catch (Exception $e) {
    error_log('Error logging frontend errors: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log errors']);
}
?>
