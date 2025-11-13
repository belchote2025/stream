<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Obtener la conexión a la base de datos
$db = getDbConnection();
$auth = new Auth($db);

// Obtener el token de autenticación
$token = $_COOKIE['auth_token'] ?? null;

if ($token) {
    // Invalidar el token en la base de datos
    $stmt = $db->prepare("
        UPDATE auth_tokens 
        SET is_revoked = TRUE 
        WHERE token = ? AND is_revoked = FALSE
    ");
    $stmt->execute([$token]);
    
    // Eliminar la cookie de autenticación
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Cerrar la sesión
$auth->logout();

echo json_encode(['message' => 'Sesión cerrada correctamente']);
