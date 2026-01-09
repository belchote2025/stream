<?php
// Asegurar que siempre se redirige, nunca se muestra contenido HTML
// Esto previene el modo Quirks si se accede directamente
// Limpiar cualquier output buffer previo
if (ob_get_level()) {
    ob_clean();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Obtener la conexión a la base de datos
try {
    $db = getDbConnection();
    $auth = new Auth($db);
} catch (Exception $e) {
    // Si hay error de conexión, redirigir de todas formas
    error_log("Error en logout: " . $e->getMessage());
    header('Location: ' . rtrim(SITE_URL, '/') . '/index.php');
    exit;
}

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

// Siempre redirigir, nunca mostrar contenido HTML directamente
$redirectUrl = rtrim(SITE_URL, '/') . '/index.php';

if ($isAjax) {
    // Si es AJAX, devolver JSON con redirect
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Sesión cerrada correctamente',
        'redirect' => $redirectUrl
    ]);
    exit;
} else {
    // Siempre redirigir a la página principal después del logout
    // Usar header Location para asegurar redirección inmediata
    header('Location: ' . $redirectUrl);
    exit;
}
