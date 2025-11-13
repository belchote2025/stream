<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Obtener la conexión a la base de datos
$db = getDbConnection();

// Obtener el token de autenticación del encabezado o de la cookie
$token = null;
$headers = getallheaders();

// Buscar el token en el encabezado de autorización
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

// Si no se encontró en el encabezado, buscar en las cookies
if (!$token && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
}

// Si no hay token, devolver error de no autorizado
if (!$token) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado: Token de autenticación no proporcionado']);
    exit;
}

// Verificar el token en la base de datos
try {
    $stmt = $db->prepare("
        SELECT u.*, at.token, at.expires_at
        FROM auth_tokens at
        JOIN users u ON at.user_id = u.id
        WHERE at.token = ? 
        AND at.is_revoked = FALSE 
        AND at.expires_at > NOW()
    ");
    
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch();
    
    if (!$tokenData) {
        throw new Exception('Token inválido o expirado');
    }
    
    // Verificar si el token ha expirado
    $now = new DateTime();
    $expiresAt = new DateTime($tokenData['expires_at']);
    
    if ($now > $expiresAt) {
        throw new Exception('La sesión ha expirado');
    }
    
    // Actualizar la última vez que se usó el token
    $updateStmt = $db->prepare("
        UPDATE auth_tokens 
        SET last_used_at = NOW() 
        WHERE token = ?
    ");
    $updateStmt->execute([$token]);
    
    // Almacenar información del usuario en la variable global para su uso posterior
    $GLOBALS['current_user'] = $tokenData;
    
} catch (Exception $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado: ' . $e->getMessage()]);
    exit;
}

// Función para verificar si el usuario tiene un rol específico
function hasRole($role) {
    global $current_user;
    return isset($current_user['role']) && $current_user['role'] === $role;
}

// Función para verificar si el usuario tiene un permiso específico
function hasPermission($permission) {
    global $db, $current_user;
    
    if (!isset($current_user['id'])) {
        return false;
    }
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM role_permissions rp
        JOIN permissions p ON rp.permission_id = p.id
        JOIN roles r ON rp.role_id = r.id
        JOIN users u ON u.role = r.name
        WHERE u.id = ? AND p.name = ?
    ");
    
    $stmt->execute([$current_user['id'], $permission]);
    $result = $stmt->fetch();
    
    return $result && $result['count'] > 0;
}

// Función para requerir un rol específico
function requireRole($role) {
    if (!hasRole($role) && !hasRole('super_admin')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acceso denegado: Se requiere rol ' . $role]);
        exit;
    }
}

// Función para requerir un permiso específico
function requirePermission($permission) {
    if (!hasPermission($permission) && !hasRole('super_admin')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acceso denegado: Se requiere el permiso ' . $permission]);
        exit;
    }
}
