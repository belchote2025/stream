<?php
/**
 * API: Ejecutar script de actualización de novedades desde el panel admin
 * 
 * Método: POST
 * Parámetros (JSON o form-data):
 *   - type: 'movie' | 'tv' (opcional, default: 'movie')
 *   - limit: int (opcional, default: 30)
 *   - since_days: int (opcional, default: 7)
 *   - min_seeds: int (opcional, default: 10)
 *   - dry_run: bool (opcional, default: false)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';

// Verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado. Se requiere rol de administrador.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

try {
    // Obtener parámetros
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $type = $input['type'] ?? 'movie';
    $limit = isset($input['limit']) ? max(1, (int)$input['limit']) : 30;
    $sinceDays = isset($input['since_days']) ? max(0, (int)$input['since_days']) : 7;
    $minSeeds = isset($input['min_seeds']) ? max(0, (int)$input['min_seeds']) : 10;
    $dryRun = filter_var($input['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    // Validar tipo
    if (!in_array($type, ['movie', 'tv', 'series'])) {
        $type = 'movie';
    }
    if ($type === 'series') {
        $type = 'tv';
    }
    
    // Construir comando
    $scriptPath = __DIR__ . '/../../scripts/fetch-new-content.php';
    if (!file_exists($scriptPath)) {
        throw new Exception('Script no encontrado: ' . $scriptPath);
    }
    
    // Detectar ruta de PHP
    $phpPath = 'php'; // Por defecto
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: intentar encontrar PHP en ubicaciones comunes
        $possiblePaths = [
            'C:\\xampp\\php\\php.exe',
            'C:\\wamp\\bin\\php\\php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '\\php.exe',
            'C:\\php\\php.exe',
            getenv('PHP_BINARY') ?: null,
            PHP_BINARY
        ];
        
        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                $phpPath = $path;
                break;
            }
        }
        
        // Si no se encontró, intentar usar PHP_BINARY o la ruta actual
        if ($phpPath === 'php' && defined('PHP_BINARY')) {
            $phpPath = PHP_BINARY;
        }
    } else {
        // Linux/Mac: usar PHP_BINARY si está disponible
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $phpPath = PHP_BINARY;
        }
    }
    
    $cmd = sprintf(
        '"%s" "%s" --type=%s --limit=%d --since-days=%d --min-seeds=%d%s',
        $phpPath,
        escapeshellarg($scriptPath),
        escapeshellarg($type),
        $limit,
        $sinceDays,
        $minSeeds,
        $dryRun ? ' --dry-run' : ''
    );
    
    // Ejecutar script y capturar salida
    $output = [];
    $returnVar = 0;
    $startTime = microtime(true);
    
    exec($cmd . ' 2>&1', $output, $returnVar);
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    $outputText = implode("\n", $output);
    
    // Parsear resultados de la salida
    $created = 0;
    $updated = 0;
    $newEpisodes = 0;
    
    if (preg_match('/Creados:\s*(\d+)/', $outputText, $matches)) {
        $created = (int)$matches[1];
    }
    if (preg_match('/actualizados:\s*(\d+)/', $outputText, $matches)) {
        $updated = (int)$matches[1];
    }
    if (preg_match('/episodios nuevos:\s*(\d+)/', $outputText, $matches)) {
        $newEpisodes = (int)$matches[1];
    }
    
    // Determinar si fue exitoso
    $success = $returnVar === 0;
    
    // Determinar si fue exitoso basado en el código de retorno y la salida
    $success = $returnVar === 0;
    
    // Si hay errores críticos en la salida, marcar como fallido
    $hasCriticalError = false;
    if (stripos($outputText, 'error') !== false || stripos($outputText, 'fatal') !== false) {
        // Verificar si son errores críticos
        $criticalErrors = ['no se reconoce', 'command not found', 'fatal error', 'parse error', 'syntax error'];
        foreach ($criticalErrors as $criticalError) {
            if (stripos($outputText, $criticalError) !== false) {
                $hasCriticalError = true;
                $success = false;
                break;
            }
        }
    }
    
    // Si el script se ejecutó pero no encontró resultados, no es un error crítico
    if (!$hasCriticalError && stripos($outputText, 'No se encontraron resultados') !== false) {
        $success = true; // El script funcionó, solo no hay datos nuevos
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Actualización completada' : 'Actualización completada con advertencias',
        'data' => [
            'type' => $type,
            'limit' => $limit,
            'since_days' => $sinceDays,
            'min_seeds' => $minSeeds,
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'new_episodes' => $newEpisodes,
            'execution_time' => $executionTime . 's',
            'output' => $outputText,
            'return_code' => $returnVar,
            'php_path' => $phpPath
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

