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
    // En Windows/Apache, a veces es mejor ejecutar directamente
    $useExec = function_exists('exec') && function_exists('proc_open');
    
    // Forzar método directo si estamos en Windows (más confiable)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $useExec = false; // Usar método directo en Windows
    }
    
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
        } elseif (file_exists('C:\\xampp\\php\\php.exe')) {
            $phpPath = 'C:\\xampp\\php\\php.exe';
        }
        
        // Verificar que el script existe y es ejecutable
        if (!file_exists($scriptPath)) {
            throw new Exception("Script no encontrado: {$scriptPath}");
        }
        
        if (!is_readable($scriptPath)) {
            throw new Exception("Script no es legible: {$scriptPath}");
        }
        
        // Crear archivo temporal para capturar salida (método alternativo más confiable)
        $tempDir = sys_get_temp_dir();
        // Asegurar que el directorio temporal existe y es escribible
        if (!is_writable($tempDir)) {
            // Fallback al directorio del proyecto
            $tempDir = __DIR__ . '/../../tmp';
            if (!file_exists($tempDir)) {
                @mkdir($tempDir, 0755, true);
            }
        }
        $outputFile = $tempDir . DIRECTORY_SEPARATOR . 'fetch-content-output-' . uniqid() . '.txt';
        $errorFile = $tempDir . DIRECTORY_SEPARATOR . 'fetch-content-error-' . uniqid() . '.txt';
        
        // Asegurar que los archivos se pueden crear
        @touch($outputFile);
        @touch($errorFile);
        
        // Forzar salida sin buffering y capturar todo en archivos
        $cmd = sprintf(
            '%s -d output_buffering=0 -d implicit_flush=1 %s --type=%s --limit=%d --since-days=%d --min-seeds=%d%s > %s 2> %s',
            escapeshellcmd($phpPath),
            escapeshellarg($scriptPath),
            escapeshellarg($type),
            $limit,
            $sinceDays,
            $minSeeds,
            $dryRun ? ' --dry-run' : '',
            escapeshellarg($outputFile),
            escapeshellarg($errorFile)
        );
        
        $cmdWithOutput = $cmd;
        
        // Log del comando para debugging (sin exponer información sensible)
        error_log("Ejecutando comando: " . str_replace($scriptPath, basename($scriptPath), $cmd));
        error_log("Script path: {$scriptPath}");
        error_log("PHP path: {$phpPath}");
        error_log("Parámetros: type={$type}, limit={$limit}, sinceDays={$sinceDays}, minSeeds={$minSeeds}");
        error_log("Output file: {$outputFile}");
        error_log("Error file: {$errorFile}");
        
        $output = [];
        $returnVar = 0;
        
        // Ejecutar con timeout y capturar toda la salida
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open($cmdWithOutput, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            // Cerrar stdin y pipes (no los necesitamos porque la salida va a archivos)
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            // Esperar a que el proceso termine
            $timeout = 120; // 120 segundos máximo
            $startTime = time();
            
            while (true) {
                $status = proc_get_status($process);
                
                // Si el proceso terminó, salir
                if (!$status['running']) {
                    break;
                }
                
                // Verificar timeout
                if ((time() - $startTime) > $timeout) {
                    proc_terminate($process);
                    break;
                }
                
                // Pequeña pausa para no consumir CPU
                usleep(200000); // 0.2 segundos
            }
            
            // Obtener el código de retorno
            $returnVar = proc_close($process);
            
            // Inicializar stdout y stderr (se leerán de los archivos)
            $stdout = '';
            $stderr = '';
            
            // Esperar un momento para que los archivos se escriban completamente
            // Intentar leer los archivos varias veces ya que pueden tardar en escribirse
            $maxWait = 20; // Aumentar a 20 intentos (10 segundos)
            $waitCount = 0;
            $lastOutputSize = 0;
            $lastErrorSize = 0;
            
            // Esperar hasta que los archivos dejen de crecer o alcancemos el máximo
            while ($waitCount < $maxWait) {
                usleep(500000); // 0.5 segundos
                $waitCount++;
                
                $currentOutputSize = file_exists($outputFile) ? filesize($outputFile) : 0;
                $currentErrorSize = file_exists($errorFile) ? filesize($errorFile) : 0;
                
                // Si los archivos dejaron de crecer, asumir que terminaron de escribirse
                if ($currentOutputSize == $lastOutputSize && $currentErrorSize == $lastErrorSize && $waitCount > 3) {
                    break;
                }
                
                $lastOutputSize = $currentOutputSize;
                $lastErrorSize = $currentErrorSize;
            }
            
            // Leer salida de archivos temporales si existen
            if (file_exists($outputFile)) {
                $fileOutput = @file_get_contents($outputFile);
                if ($fileOutput !== false && !empty(trim($fileOutput))) {
                    $stdout = $fileOutput . (empty($stdout) ? '' : "\n" . $stdout); // Priorizar archivo
                    error_log("Output file read successfully: " . strlen($fileOutput) . " bytes");
                } else {
                    error_log("Output file exists but is empty or unreadable (size: " . filesize($outputFile) . ")");
                }
                @unlink($outputFile);
            } else {
                error_log("Output file does not exist: {$outputFile}");
            }
            
            if (file_exists($errorFile)) {
                $fileError = @file_get_contents($errorFile);
                if ($fileError !== false && !empty(trim($fileError))) {
                    // Filtrar errores de Apache antes de agregar
                    $errorLines = explode("\n", $fileError);
                    $filteredErrors = [];
                    foreach ($errorLines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        // Filtrar errores de Apache mpm_winnt
                        if (strpos($line, 'AH02965') === false && 
                            strpos($line, 'mpm_winnt') === false &&
                            strpos($line, 'Unable to retrieve my generation') === false &&
                            strpos($line, 'Ha terminado la canalización') === false) {
                            $filteredErrors[] = $line;
                        }
                    }
                    if (!empty($filteredErrors)) {
                        $stderr = implode("\n", $filteredErrors) . (empty($stderr) ? '' : "\n" . $stderr);
                    }
                    error_log("Error file read: " . strlen($fileError) . " bytes (filtered: " . count($filteredErrors) . " lines)");
                } else {
                    error_log("Error file exists but is empty (size: " . filesize($errorFile) . ")");
                }
                @unlink($errorFile);
            } else {
                error_log("Error file does not exist: {$errorFile}");
            }
            
            // Si no hay salida de pipes pero los archivos existen, leerlos de nuevo
            if (empty($stdout) && empty($stderr)) {
                // Esperar un poco más y verificar archivos de nuevo
                sleep(1);
                if (file_exists($outputFile)) {
                    $fileOutput = @file_get_contents($outputFile);
                    if ($fileOutput !== false && !empty(trim($fileOutput))) {
                        $stdout = $fileOutput;
                        error_log("Re-read output file: " . strlen($fileOutput) . " bytes");
                    }
                }
                if (file_exists($errorFile)) {
                    $fileError = @file_get_contents($errorFile);
                    if ($fileError !== false && !empty(trim($fileError))) {
                        // Filtrar errores de Apache
                        $errorLines = explode("\n", $fileError);
                        $filteredErrors = [];
                        foreach ($errorLines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;
                            if (strpos($line, 'AH02965') === false && 
                                strpos($line, 'mpm_winnt') === false &&
                                strpos($line, 'Unable to retrieve my generation') === false &&
                                strpos($line, 'Ha terminado la canalización') === false) {
                                $filteredErrors[] = $line;
                            }
                        }
                        if (!empty($filteredErrors)) {
                            $stderr = implode("\n", $filteredErrors);
                        }
                    }
                }
            }
            
            // Combinar stdout y stderr
            $allOutput = trim($stdout . "\n" . $stderr);
            if (!empty($allOutput)) {
                $output = explode("\n", $allOutput);
            }
        } else {
            // Fallback a exec() si proc_open no funciona
            exec($cmd . ' 2>&1', $output, $returnVar);
        }
        
        // Log para diagnóstico
        error_log("Script ejecutado - stdout length: " . strlen($stdout) . ", stderr length: " . strlen($stderr));
        error_log("Return code: {$returnVar}");
        if (!empty($stdout)) {
            error_log("Stdout preview (first 500 chars): " . substr($stdout, 0, 500));
        }
        if (!empty($stderr)) {
            error_log("Stderr preview (first 500 chars): " . substr($stderr, 0, 500));
        }
        
        // Si no hay salida, puede ser que el script se ejecutó muy rápido o hubo un error silencioso
        if (empty($output) && $returnVar !== 0) {
            $output[] = "El script se ejecutó pero no generó salida (código de retorno: {$returnVar})";
            $output[] = "Verifica que el script existe y tiene permisos de ejecución.";
        }
        
        // Si hay stdout o stderr pero output está vacío, usar esos directamente
        if (empty($output) && (!empty($stdout) || !empty($stderr))) {
            $combined = trim($stdout . "\n" . $stderr);
            if (!empty($combined)) {
                $output = explode("\n", $combined);
            }
        }
        
        // Guardar una copia del output sin filtrar para diagnóstico
        $rawOutput = $output;
        
        // Filtrar errores de Apache que no son relevantes para el usuario
        $filteredOutput = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Filtrar errores de Apache mpm_winnt (no son críticos)
            // Estos errores aparecen en diferentes formatos
            if (strpos($line, 'AH02965') !== false || 
                strpos($line, 'mpm_winnt:crit') !== false ||
                strpos($line, 'mpm_winnt') !== false ||
                strpos($line, 'Unable to retrieve my generation') !== false ||
                strpos($line, 'Ha terminado la canalización') !== false ||
                preg_match('/\[.*mpm_winnt.*\]/i', $line) ||
                preg_match('/\[.*AH02965.*\]/i', $line)) {
                // Ignorar estas líneas, son advertencias de Apache
                continue;
            }
            
            // Filtrar líneas que solo contienen timestamps de Apache
            if (preg_match('/^\[.*\]\s*$/', $line)) {
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
            $unfilteredOutput = implode("\n", $rawOutput ?? $output);
            
            // Filtrar solo errores de Apache del output sin filtrar para diagnóstico
            $diagnosticLines = [];
            foreach ($rawOutput ?? $output as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                
                // Solo filtrar errores de Apache, pero mostrar el resto
                if (strpos($line, 'AH02965') !== false || 
                    strpos($line, 'mpm_winnt:crit') !== false ||
                    strpos($line, 'mpm_winnt') !== false ||
                    strpos($line, 'Unable to retrieve my generation') !== false ||
                    strpos($line, 'Ha terminado la canalización') !== false ||
                    preg_match('/\[.*mpm_winnt.*\]/i', $line) ||
                    preg_match('/\[.*AH02965.*\]/i', $line)) {
                    continue;
                }
                
                $diagnosticLines[] = $line;
            }
            
            if (!empty($diagnosticLines)) {
                // Hay salida válida después de filtrar Apache
                $outputText = implode("\n", $diagnosticLines);
            } elseif (!empty($unfilteredOutput)) {
                // Solo había errores de Apache, no hay salida real
                // Pero intentar ejecutar el script directamente para ver si hay salida
                $outputText = "El script se ejecutó pero solo generó errores de Apache (no críticos).\n";
                $outputText .= "Esto puede indicar que el script se ejecutó muy rápido o no generó salida visible.\n\n";
                $outputText .= "Información de diagnóstico:\n";
                $outputText .= "- Stdout length: " . strlen($stdout) . " bytes\n";
                $outputText .= "- Stderr length: " . strlen($stderr) . " bytes\n";
                $outputText .= "- Return code: {$returnVar}\n";
                $outputText .= "- Script path: {$scriptPath}\n";
                if (!empty($stdout)) {
                    $outputText .= "\nPrimeras líneas de stdout:\n" . implode("\n", array_slice(explode("\n", $stdout), 0, 10)) . "\n";
                }
                if (!empty($stderr)) {
                    $outputText .= "\nPrimeras líneas de stderr:\n" . implode("\n", array_slice(explode("\n", $stderr), 0, 10)) . "\n";
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
                    $outputText .= "\nNota: El código 3 puede indicar que el script encontró contenido pero no pudo procesarlo,\n";
                    $outputText .= "o que todos los items ya existen en la base de datos.\n";
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
                $outputText .= "- Stdout length: " . strlen($stdout) . " bytes\n";
                $outputText .= "- Stderr length: " . strlen($stderr) . " bytes\n";
            }
        }
    } else {
        // Método 2: Ejecutar directamente sin exec() (para servidores restringidos)
        // Este método es más confiable en Windows/Apache
        $scriptPath = realpath(__DIR__ . '/../../scripts/fetch-new-content.php');
        if (!file_exists($scriptPath)) {
            throw new Exception('Script no encontrado: ' . $scriptPath);
        }
        
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
        
        // Capturar salida usando output buffering con múltiples niveles
        // Limpiar cualquier buffer existente de forma segura
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        // Iniciar nuevo buffer con callback para capturar todo
        // Usar un nivel de buffer más alto para asegurar captura
        ob_start(function($buffer) {
            return $buffer; // Devolver el buffer sin modificar
        }, 4096);
        
        // También iniciar un segundo nivel para asegurar captura completa
        ob_start();
        
        // Redirigir STDOUT y STDERR a nuestro buffer
        try {
            // Incluir el script - esto ejecutará todo el código
            include $scriptPath;
        } catch (Exception $e) {
            $outputText = ob_get_clean();
            error_log("Exception al ejecutar script: " . $e->getMessage());
            error_log("Salida capturada: " . substr($outputText, 0, 500));
            throw new Exception('Error al ejecutar script: ' . $e->getMessage() . "\nSalida: " . $outputText);
        } catch (Error $e) {
            $outputText = ob_get_clean();
            error_log("Error fatal al ejecutar script: " . $e->getMessage());
            error_log("Salida capturada: " . substr($outputText, 0, 500));
            throw new Exception('Error fatal al ejecutar script: ' . $e->getMessage() . "\nSalida: " . $outputText);
        }
        
        // Obtener toda la salida capturada (limpiar ambos buffers)
        $outputText = '';
        while (ob_get_level() > 0) {
            $buffer = ob_get_clean();
            if ($buffer !== false) {
                $outputText = $buffer . $outputText;
            }
        }
        $returnVar = 0; // Asumir éxito si no hay excepciones
        
        // Log para diagnóstico
        error_log("Script ejecutado (método directo) - Output length: " . strlen($outputText));
        if (!empty($outputText)) {
            error_log("Output preview (first 500 chars): " . substr($outputText, 0, 500));
        }
        
        // Si no hay salida, puede ser que el script no generó nada
        // Esto es normal si no encuentra contenido nuevo
        if (empty(trim($outputText))) {
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
        
        // Convertir salida a array de líneas
        if (!empty($outputText)) {
            $output = explode("\n", $outputText);
        } else {
            $output = [];
        }
        
        // Filtrar errores de Apache también en el método directo
        $lines = explode("\n", $outputText);
        $filteredLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Filtrar errores de Apache mpm_winnt (no son críticos)
            if (strpos($line, 'AH02965') !== false || 
                strpos($line, 'mpm_winnt:crit') !== false ||
                strpos($line, 'mpm_winnt') !== false ||
                strpos($line, 'Unable to retrieve my generation') !== false ||
                strpos($line, 'Ha terminado la canalización') !== false ||
                preg_match('/\[.*mpm_winnt.*\]/i', $line) ||
                preg_match('/\[.*AH02965.*\]/i', $line)) {
                // Ignorar estas líneas, son advertencias de Apache
                continue;
            }
            
            // Filtrar líneas que solo contienen timestamps de Apache
            if (preg_match('/^\[.*\]\s*$/', $line)) {
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
    // Formato 4: "Creados: 0, actualizados: 0, episodios nuevos: 0" (en una sola línea)
    
    // Buscar en toda la salida, no solo en una línea
    $allLines = explode("\n", $outputText);
    foreach ($allLines as $line) {
        // Buscar "Creados: X" o "creados: X" o "Creados X" etc.
        if (preg_match('/[Cc]reados[:\s]+(\d+)/i', $line, $matches)) {
            $created = max($created, (int)$matches[1]);
        }
        // Buscar "actualizados: X" o "Actualizados: X" etc.
        if (preg_match('/[Aa]ctualizados[:\s]+(\d+)/i', $line, $matches)) {
            $updated = max($updated, (int)$matches[1]);
        }
        // Buscar "episodios nuevos: X" o "Episodios nuevos: X" etc.
        if (preg_match('/[Ee]pisodios\s+nuevos[:\s]+(\d+)/i', $line, $matches)) {
            $newEpisodes = max($newEpisodes, (int)$matches[1]);
        }
    }
    
    // También buscar en formato más compacto: "Creados: 0, actualizados: 0, episodios nuevos: 0"
    if (preg_match('/Creados:\s*(\d+)[,\s]+actualizados:\s*(\d+)[,\s]+episodios\s+nuevos:\s*(\d+)/i', $outputText, $matches)) {
        $created = max($created, (int)$matches[1]);
        $updated = max($updated, (int)$matches[2]);
        $newEpisodes = max($newEpisodes, (int)$matches[3]);
    }
    
    // Si aún no se encontraron valores, buscar en formato alternativo
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
    // Pero después de filtrar, no deberían quedar estas advertencias
    $hasOnlyApacheWarnings = false; // Ya filtramos Apache, así que esto no debería ser necesario
    
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
    
    // Si no pudimos parsear los valores pero el script se ejecutó, intentar mostrar información útil
    if ($created === 0 && $updated === 0 && $newEpisodes === 0 && ($returnVar === 0 || $returnVar === 3) && !empty($outputText)) {
        // Verificar si hay indicios de que se procesó contenido
        $hasProcessedContent = stripos($outputText, 'Procesado:') !== false || 
                                stripos($outputText, 'items para procesar') !== false ||
                                stripos($outputText, 'Se encontraron') !== false;
        
        if ($hasProcessedContent && stripos($outputText, 'Creados') === false) {
            // El script procesó contenido pero no pudimos parsear los números
            // Esto puede indicar que todos los items ya existían y no se actualizaron
            $message = 'Actualización completada: Se procesaron items pero no se crearon ni actualizaron elementos nuevos';
            $outputText .= "\n\nNota: Si todos los items ya existen en la base de datos, no se contarán como 'actualizados' a menos que haya cambios reales en los datos.";
        }
    }
    if ($created === 0 && $updated === 0 && $newEpisodes === 0) {
        if (stripos($outputText, 'No se encontraron') !== false) {
            $message = 'Actualización completada: No se encontró contenido nuevo con los criterios especificados';
        } elseif (empty($message) || $message === 'Actualización completada') {
            $message = 'Actualización completada: No se crearon ni actualizaron elementos (puede que ya existan o no cumplan los criterios)';
        }
    } elseif ($created > 0 || $updated > 0 || $newEpisodes > 0) {
        $message = sprintf('Actualización completada: %d creados, %d actualizados, %d episodios nuevos', 
            $created, $updated, $newEpisodes);
    } elseif (!$success) {
        $message = 'Actualización completada con advertencias';
    }
    
    // Limpiar todos los buffers antes de enviar JSON
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Asegurar que no hay salida previa
    @ob_start();
    
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
    
    // Limpiar el buffer final
    @ob_end_flush();
    
} catch (Exception $e) {
    // Limpiar todos los buffers de forma segura
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

