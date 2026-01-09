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
        
        // Verificar que el script existe y es ejecutable
        if (!file_exists($scriptPath)) {
            throw new Exception("Script no encontrado: {$scriptPath}");
        }
        
        if (!is_readable($scriptPath)) {
            throw new Exception("Script no es legible: {$scriptPath}");
        }
        
        // Log del comando para debugging (sin exponer información sensible)
        error_log("Ejecutando comando: " . str_replace($scriptPath, basename($scriptPath), $cmd));
        error_log("Script path: {$scriptPath}");
        error_log("PHP path: {$phpPath}");
        error_log("Parámetros: type={$type}, limit={$limit}, sinceDays={$sinceDays}, minSeeds={$minSeeds}");
        
        $output = [];
        $returnVar = 0;
        
        // Ejecutar con timeout y capturar toda la salida
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            // Cerrar stdin
            fclose($pipes[0]);
            
            // Configurar los streams como no bloqueantes
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            
            // Leer stdout y stderr con timeout
            $stdout = '';
            $stderr = '';
            $timeout = 60; // 60 segundos máximo
            $startTime = time();
            
            // Leer mientras el proceso esté activo
            while (true) {
                $status = proc_get_status($process);
                
                // Leer de stdout
                $read = fread($pipes[1], 8192);
                if ($read !== false && $read !== '') {
                    $stdout .= $read;
                }
                
                // Leer de stderr
                $read = fread($pipes[2], 8192);
                if ($read !== false && $read !== '') {
                    $stderr .= $read;
                }
                
                // Si el proceso terminó, leer el resto
                if (!$status['running']) {
                    // Leer el resto de los datos
                    $remaining = stream_get_contents($pipes[1]);
                    if ($remaining !== false) {
                        $stdout .= $remaining;
                    }
                    $remaining = stream_get_contents($pipes[2]);
                    if ($remaining !== false) {
                        $stderr .= $remaining;
                    }
                    break;
                }
                
                // Verificar timeout
                if ((time() - $startTime) > $timeout) {
                    proc_terminate($process);
                    break;
                }
                
                // Pequeña pausa para no consumir CPU
                usleep(100000); // 0.1 segundos
            }
            
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            // Obtener el código de retorno
            $returnVar = proc_close($process);
            
            // Combinar stdout y stderr
            $allOutput = trim($stdout . "\n" . $stderr);
            if (!empty($allOutput)) {
                $output = explode("\n", $allOutput);
            }
        } else {
            // Fallback a exec() si proc_open no funciona
            exec($cmd . ' 2>&1', $output, $returnVar);
        }
        
        // Si no hay salida, puede ser que el script se ejecutó muy rápido o hubo un error silencioso
        if (empty($output) && $returnVar !== 0) {
            $output[] = "El script se ejecutó pero no generó salida (código de retorno: {$returnVar})";
            $output[] = "Verifica que el script existe y tiene permisos de ejecución.";
        }
        
        // Filtrar errores de Apache que no son relevantes para el usuario
        $filteredOutput = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Filtrar errores de Apache mpm_winnt (no son críticos)
            if (strpos($line, 'AH02965') !== false || 
                strpos($line, 'mpm_winnt:crit') !== false ||
                strpos($line, 'Unable to retrieve my generation') !== false) {
                // Ignorar estas líneas, son advertencias de Apache
                continue;
            }
            
            // Filtrar warnings de PHP que no son críticos
            if (preg_match('/^Warning:|^Notice:/i', $line)) {
                // Solo incluir si no es un warning común de PHP
                if (strpos($line, 'Undefined') === false && 
                    strpos($line, 'Array to string') === false) {
                    $filteredOutput[] = $line;
                }
                continue;
            }
            
            $filteredOutput[] = $line;
        }
        $outputText = implode("\n", $filteredOutput);
        
        // Si después de filtrar no hay salida pero el script se ejecutó, agregar mensaje informativo
        // PERO primero verificar si realmente no hay salida o si se filtró demasiado
        if (empty($outputText)) {
            // Si hay output sin filtrar, puede que se haya filtrado demasiado
            $unfilteredOutput = implode("\n", $output);
            if (!empty($unfilteredOutput)) {
                // Hay salida sin filtrar, pero se filtró toda
                // Mostrar al menos las primeras líneas para diagnóstico
                $lines = explode("\n", $unfilteredOutput);
                $firstLines = array_slice($lines, 0, 10);
                $outputText = "Salida del script (primeras líneas):\n" . implode("\n", $firstLines);
                if (count($lines) > 10) {
                    $outputText .= "\n... (" . (count($lines) - 10) . " líneas más)";
                }
            } else {
                // Realmente no hay salida
                if ($returnVar === 0) {
                    $outputText = "Script ejecutado correctamente pero no generó salida visible.\n";
                    $outputText .= "Esto puede indicar que:\n";
                    $outputText .= "- No se encontró contenido nuevo con los criterios especificados\n";
                    $outputText .= "- El script se ejecutó muy rápido y no tuvo tiempo de generar salida\n";
                } elseif ($returnVar === 3) {
                    $outputText = "Script ejecutado con código de retorno 3 (advertencia).\n";
                    $outputText .= "Puede que no haya encontrado contenido nuevo o haya advertencias menores.\n";
                } else {
                    $outputText = "Script ejecutado con código de retorno: {$returnVar}\n";
                    $outputText .= "No se generó salida visible. Verifica los logs del servidor para más detalles.\n";
                }
                
                // Agregar información de diagnóstico
                $outputText .= "\nInformación de diagnóstico:\n";
                $outputText .= "- Tipo: {$type}\n";
                $outputText .= "- Límite: {$limit}\n";
                $outputText .= "- Días: {$sinceDays}\n";
                $outputText .= "- Min seeds: {$minSeeds}\n";
                $outputText .= "- Método: exec()\n";
            }
        }
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
        
        // Filtrar errores de Apache también en el método directo
        $lines = explode("\n", $outputText);
        $filteredLines = [];
        foreach ($lines as $line) {
            // Filtrar errores de Apache mpm_winnt (no son críticos)
            if (strpos($line, 'AH02965') !== false || 
                strpos($line, 'mpm_winnt:crit') !== false ||
                strpos($line, 'Unable to retrieve my generation') !== false) {
                // Ignorar estas líneas, son advertencias de Apache
                continue;
            }
            $filteredLines[] = $line;
        }
        $outputText = implode("\n", $filteredLines);
    }
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Parsear resultados de la salida - múltiples formatos posibles
    // Formato 1: "✅ Listo. Creados: X, actualizados: Y, episodios nuevos: Z"
    // Formato 2: "Listo. Creados: X, actualizados: Y, episodios nuevos: Z"
    // Formato 3: "Creados: X, actualizados: Y, episodios nuevos: Z"
    
    if (preg_match('/Creados:\s*(\d+)/i', $outputText, $matches)) {
        $created = (int)$matches[1];
    }
    if (preg_match('/actualizados:\s*(\d+)/i', $outputText, $matches)) {
        $updated = (int)$matches[1];
    }
    if (preg_match('/episodios nuevos:\s*(\d+)/i', $outputText, $matches)) {
        $newEpisodes = (int)$matches[1];
    }
    
    // Si no se encontraron valores pero hay salida, intentar parsear de otra forma
    if ($created === 0 && $updated === 0 && $newEpisodes === 0 && !empty($outputText)) {
        // Buscar cualquier número después de "Creados", "actualizados", "episodios"
        if (preg_match('/(?:Creados|creados)[\s:]*(\d+)/i', $outputText, $matches)) {
            $created = (int)$matches[1];
        }
        if (preg_match('/(?:actualizados|Actualizados)[\s:]*(\d+)/i', $outputText, $matches)) {
            $updated = (int)$matches[1];
        }
        if (preg_match('/(?:episodios nuevos|Episodios nuevos)[\s:]*(\d+)/i', $outputText, $matches)) {
            $newEpisodes = (int)$matches[1];
        }
    }
    
    // Determinar si fue exitoso
    // Si solo hay advertencias de Apache (AH02965), considerar como éxito
    $hasOnlyApacheWarnings = !empty($outputText) && 
        preg_match('/AH02965|mpm_winnt:crit|Unable to retrieve my generation/', $outputText) &&
        !preg_match('/Error|Fatal|Exception/i', $outputText);
    
    // Return code 3 puede ser una advertencia, no necesariamente un error
    // Si el script se ejecutó y generó salida, considerar como éxito parcial
    $hasOutput = !empty($outputText) && (
        stripos($outputText, 'Listo') !== false || 
        stripos($outputText, 'Creados') !== false ||
        stripos($outputText, 'Procesado') !== false ||
        stripos($outputText, 'Se encontraron') !== false
    );
    
    $success = $returnVar === 0 || ($returnVar === 3 && ($hasOnlyApacheWarnings || $hasOutput));
    
    // Si hay errores críticos en la salida, marcar como fallido
    $hasCriticalError = false;
    if (stripos($outputText, 'error') !== false || stripos($outputText, 'fatal') !== false) {
        $criticalErrors = ['no se reconoce', 'command not found', 'fatal error', 'parse error', 'syntax error', 'file not found', 'cannot find'];
        foreach ($criticalErrors as $criticalError) {
            if (stripos($outputText, $criticalError) !== false) {
                $hasCriticalError = true;
                $success = false;
                break;
            }
        }
    }
    
    // Si el script se ejecutó pero no encontró resultados, no es un error crítico
    if (!$hasCriticalError && (
        stripos($outputText, 'No se encontraron resultados') !== false ||
        stripos($outputText, 'No se encontraron items') !== false ||
        stripos($outputText, 'No se encontraron') !== false
    )) {
        $success = true; // El script funcionó, solo no hay datos nuevos
    }
    
    // Si no hay salida pero el return code es 0 o 3, puede ser que el script se ejecutó correctamente
    // pero no generó salida (poco probable pero posible)
    if (empty($outputText) && ($returnVar === 0 || $returnVar === 3)) {
        // Si no hay salida, asumir que el script se ejecutó pero no encontró nada
        $success = true;
        // Agregar mensaje informativo si no hay salida
        if (empty($outputText)) {
            $outputText = "El script se ejecutó correctamente pero no generó salida visible.\n";
            $outputText .= "Esto puede indicar que:\n";
            $outputText .= "- No se encontró contenido nuevo con los criterios especificados\n";
            $outputText .= "- El contenido ya existe en la base de datos\n";
            $outputText .= "\nSugerencias para encontrar más contenido:\n";
            $outputText .= "- Aumenta el rango de días (actualmente: {$sinceDays} días)\n";
            $outputText .= "- Reduce el mínimo de seeds (actualmente: {$minSeeds})\n";
            $outputText .= "- Verifica que las API keys estén configuradas (TMDB, Trakt)\n";
            $outputText .= "- Instala y activa addons desde el panel de administración\n";
        }
    }
    
    // Generar mensaje más descriptivo
    $message = 'Actualización completada';
    if ($created === 0 && $updated === 0 && $newEpisodes === 0) {
        if (stripos($outputText, 'No se encontraron') !== false) {
            $message = 'Actualización completada: No se encontró contenido nuevo con los criterios especificados';
        } else {
            $message = 'Actualización completada: No se crearon ni actualizaron elementos (puede que ya existan o no cumplan los criterios)';
        }
    } elseif ($created > 0 || $updated > 0 || $newEpisodes > 0) {
        $message = sprintf('Actualización completada: %d creados, %d actualizados, %d episodios nuevos', 
            $created, $updated, $newEpisodes);
    } elseif (!$success) {
        $message = 'Actualización completada con advertencias';
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => $success,
        'message' => $message,
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

