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
@ini_set('max_execution_time', '300');
@set_time_limit(300);
@ignore_user_abort(true);
// Evitar que warnings/HTML contaminen el JSON
ob_start();

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
    ob_end_clean();
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
    
    // Verificar si exec() está disponible
    $useExec = function_exists('exec');
    
    $startTime = microtime(true);
    $outputText = '';
    $created = 0;
    $updated = 0;
    $newEpisodes = 0;
    $returnVar = 0;
    
    if ($useExec) {
        // Método 1: Usar exec() si está disponible (más eficiente)
        $scriptPath = realpath(__DIR__ . '/../../scripts/fetch-new-content.php');
        if (!file_exists($scriptPath)) {
            throw new Exception('Script no encontrado: ' . $scriptPath);
        }
        
        // Detectar ruta de PHP
        $phpPath = 'php';
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $phpPath = PHP_BINARY;
        }
        
        $cmd = sprintf(
            '%s %s --type=%s --limit=%d --since-days=%d --min-seeds=%d%s',
            escapeshellcmd($phpPath),
            escapeshellarg($scriptPath),
            escapeshellarg($type),
            $limit,
            $sinceDays,
            $minSeeds,
            $dryRun ? ' --dry-run' : ''
        );
        
        $output = [];
        exec($cmd . ' 2>&1', $output, $returnVar);
        $outputText = implode("\n", $output);
    } else {
        // Método 2: Ejecutar directamente sin exec() (para servidores restringidos)
        // Simular parámetros CLI para el script
        $_SERVER['argv'] = [
            'fetch-new-content.php',
            '--type=' . $type,
            '--limit=' . $limit,
            '--since-days=' . $sinceDays,
            '--min-seeds=' . $minSeeds
        ];
        if ($dryRun) {
            $_SERVER['argv'][] = '--dry-run';
        }
        
        // Capturar salida usando output buffering
        ob_start();
        
        // Incluir el script directamente
        $scriptPath = realpath(__DIR__ . '/../../scripts/fetch-new-content.php');
        if (!file_exists($scriptPath)) {
            throw new Exception('Script no encontrado: ' . $scriptPath);
        }
        
        // Redirigir STDOUT y STDERR a nuestro buffer
        // Necesitamos modificar el script para que use una función de salida personalizada
        try {
            // Incluir el script - esto ejecutará todo el código
            include $scriptPath;
        } catch (Exception $e) {
            $outputText = ob_get_clean();
            throw new Exception('Error al ejecutar script: ' . $e->getMessage() . "\nSalida: " . $outputText);
        }
        
        $outputText = ob_get_clean();
        $returnVar = 0; // Asumir éxito si no hay excepciones
    }
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Parsear resultados de la salida
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
    
    // Si hay errores críticos en la salida, marcar como fallido
    $hasCriticalError = false;
    if (stripos($outputText, 'error') !== false || stripos($outputText, 'fatal') !== false) {
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
    
    ob_end_clean();
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
            'method' => $useExec ? 'exec' : 'direct'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

