<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Obtener la conexión a la base de datos
$db = getDbConnection();

// Primero verificar si hay una sesión PHP activa (para el panel de administración)
$authenticated = false;
$current_user = null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión PHP primero
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $authenticated = true;
            $current_user = $user;
            $GLOBALS['current_user'] = $user;
        }
    } catch (Exception $e) {
        // Si hay error, continuar con verificación de token
    }
}

// Si no hay sesión PHP, intentar con token
if (!$authenticated) {
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

    // Si hay token, verificar
    if ($token) {
        try {
            $stmt = $db->prepare("
                SELECT u.*, at.token, at.expires_at
                FROM auth_tokens at
                JOIN users u ON at.user_id = u.id
                WHERE at.token = ? 
                AND at.is_revoked = FALSE 
                AND at.expires_at > NOW()
                AND u.status = 'active'
            ");
            
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tokenData) {
                // Verificar si el token ha expirado
                $now = new DateTime();
                $expiresAt = new DateTime($tokenData['expires_at']);
                
                if ($now <= $expiresAt) {
                    $authenticated = true;
                    $current_user = $tokenData;
                    $GLOBALS['current_user'] = $tokenData;
                    
                    // Actualizar la última vez que se usó el token
                    $updateStmt = $db->prepare("
                        UPDATE auth_tokens 
                        SET last_used_at = NOW() 
                        WHERE token = ?
                    ");
                    $updateStmt->execute([$token]);
                }
            }
        } catch (Exception $e) {
            // Error al verificar token
        }
    }
}

// Si no hay autenticación, devolver error
if (!$authenticated) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado: Token de autenticación no proporcionado o sesión inválida']);
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
