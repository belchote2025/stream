<?php
/**
 * Utilidades de Seguridad - Sanitización y Validación
 * 
 * Funciones helper para mejorar la seguridad de la aplicación
 */

/**
 * Sanitización avanzada según contexto
 * 
 * @param mixed $data Datos a sanitizar
 * @param string $context Contexto: 'html', 'url', 'js', 'css', 'sql', 'email'
 * @return mixed Datos sanitizados
 */
function sanitizeOutput($data, $context = 'html') {
    if (is_null($data)) {
        return null;
    }
    
    if (is_array($data)) {
        return array_map(function($item) use ($context) {
            return sanitizeOutput($item, $context);
        }, $data);
    }
    
    switch ($context) {
        case 'html':
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
            
        case 'url':
            return urlencode($data);
            
        case 'js':
            return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            
        case 'css':
            // Eliminar caracteres peligrosos en CSS
            return preg_replace('/[^a-zA-Z0-9\s\-_#%.]/', '', $data);
            
        case 'sql':
            // Nota: Siempre usar prepared statements, esto es solo un backup
            return addslashes(strip_tags($data));
            
        case 'email':
            return filter_var($data, FILTER_SANITIZE_EMAIL);
            
        case 'filename':
            // Sanitizar nombres de archivo
            $data = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $data);
            return substr($data, 0, 255); // Limitar longitud
            
        default:
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

/**
 * Validar y limpiar URL
 * 
 * @param string $url URL a validar
 * @param bool $allowRelative Permitir URLs relativas
 * @return string|false URL válida o false
 */
function validateUrl($url, $allowRelative = false) {
    if (empty($url)) {
        return false;
    }
    
    // Si es relativa y está permitido
    if ($allowRelative && (strpos($url, '/') === 0 || strpos($url, './') === 0)) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
    
    // Validar URL completa
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    
    // Solo permitir http/https
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    
    return filter_var($url, FILTER_SANITIZE_URL);
}

/**
 * Validar email
 * 
 * @param string $email Email a validar
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validar número de teléfono (formato internacional básico)
 * 
 * @param string $phone Teléfono a validar
 * @return bool
 */
function validatePhone($phone) {
    // Permitir + - () y números
    return preg_match('/^[+]?[\d\-\(\)\s]{7,}$/', $phone) === 1;
}

/**
 * Prevenir SQL Injection - Wrapper para sanitización
 * IMPORTANTE: Siempre usar prepared statements, esta es solo una capa adicional
 * 
 * @param string $value Valor a sanitizar
 * @return string
 */
function sqlSafe($value) {
    if (is_numeric($value)) {
        return $value;
    }
    return addslashes(strip_tags(trim($value)));
}

/**
 * Validar que un ID sea numérico y positivo
 * 
 * @param mixed $id ID a validar
 * @return int|false ID válido o false
 */
function validateId($id) {
    if (!is_numeric($id)) {
        return false;
    }
    
    $id = (int)$id;
    return $id > 0 ? $id : false;
}

/**
 * Generar token seguro
 * 
 * @param int $length Longitud del token
 * @return string Token generado
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash de password con bcrypt
 * 
 * @param string $password Password a hashear
 * @return string Hash del password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verificar password
 * 
 * @param string $password Password ingresado
 * @param string $hash Hash almacenado
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Limitar longitud de string de forma segura
 * 
 * @param string $str String a limitar
 * @param int $length Longitud máxima
 * @param string $append Texto a añadir al final (ej: '...')
 * @return string
 */
function limitString($str, $length = 100, $append = '...') {
    if (mb_strlen($str) <= $length) {
        return $str;
    }
    
    $str = mb_substr($str, 0, $length);
    return rtrim($str) . $append;
}

/**
 * Prevenir XSS en JSON
 * 
 * @param mixed $data Datos a codificar
 * @return string JSON seguro
 */
function jsonSafe($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

/**
 * Validar formato de fecha
 * 
 * @param string $date Fecha a validar
 * @param string $format Formato esperado (default: Y-m-d)
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Sanitizar array de parámetros GET/POST
 * 
 * @param array $params Parámetros a sanitizar
 * @param string $context Contexto de sanitización
 * @return array Parámetros sanitizados
 */
function sanitizeParams($params, $context = 'html') {
    $sanitized = [];
    
    foreach ($params as $key => $value) {
        $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $sanitized[$cleanKey] = is_array($value) 
            ? sanitizeParams($value, $context)
            : sanitizeOutput($value, $context);
    }
    
    return $sanitized;
}

/**
 * Verificar si una IP está en una lista blanca/negra
 * 
 * @param string $ip IP a verificar
 * @param array $list Lista de IPs o rangos CIDR
 * @return bool
 */
function ipInList($ip, $list) {
    foreach ($list as $range) {
        if (strpos($range, '/') === false) {
            // IP exacta
            if ($ip === $range) {
                return true;
            }
        } else {
            // Rango CIDR
            list($subnet, $mask) = explode('/', $range);
            if ((ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Rate limiting simple basado en archivos
 * 
 * @param string $identifier Identificador único (IP, user_id, etc.)
 * @param int $maxAttempts Intentos máximos
 * @param int $timeWindow Ventana de tiempo en segundos
 * @return bool true si está dentro del límite, false si excedió
 */
function checkRateLimit($identifier, $maxAttempts = 60, $timeWindow = 60) {
    $cacheDir = __DIR__ . '/../cache/rate-limit';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheKey = md5($identifier);
    $cacheFile = $cacheDir . '/' . $cacheKey;
    
    if (!file_exists($cacheFile)) {
        file_put_contents($cacheFile, json_encode([
            'count' => 1,
            'reset' => time() + $timeWindow
        ]), LOCK_EX);
        return true;
    }
    
    $data = json_decode(file_get_contents($cacheFile), true);
    
    // Reset si pasó el tiempo
    if (time() > $data['reset']) {
        file_put_contents($cacheFile, json_encode([
            'count' => 1,
            'reset' => time() + $timeWindow
        ]), LOCK_EX);
        return true;
    }
    
    // Verificar si excedió el límite
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    // Incrementar contador
    $data['count']++;
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    return true;
}

/**
 * Limpiar directorio de cachés antiguos
 * 
 * @param string $dir Directorio a limpiar
 * @param int $maxAge Edad máxima en segundos
 */
function cleanOldCache($dir, $maxAge = 86400) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = glob($dir . '/*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
            @unlink($file);
        }
    }
}
?>
