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

// Detectar si viene del panel de administración antes de cerrar sesión
$isFromAdmin = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false;
$isAdminPanel = isset($_GET['from']) && $_GET['from'] === 'admin';

// Cerrar la sesión
$auth->logout();

// Detectar si es una petición AJAX o un enlace directo
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    // Si es AJAX, devolver JSON
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Sesión cerrada correctamente',
        'redirect' => '/'
    ]);
} else {
    // Siempre redirigir a la página principal después del logout
    // Si viene del panel de administración, asegurar que vaya a index.php
    $redirectUrl = rtrim(SITE_URL, '/') . '/index.php';
    
    // Si viene del panel de admin, usar la URL completa para asegurar la redirección
    if ($isFromAdmin || $isAdminPanel) {
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        // Para otros casos, usar la función redirect
        redirect('/index.php');
    }
}
