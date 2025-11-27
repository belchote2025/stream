<?php
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

// Detectar si es una petición AJAX o un enlace directo
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    // Si es AJAX, devolver JSON
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Sesión cerrada correctamente']);
} else {
    // Si es un enlace directo, redirigir a la página principal
    redirect('/');
}
