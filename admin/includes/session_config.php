<?php
// Configuración de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    // Configurar parámetros de la cookie de sesión
    $cookieParams = session_get_cookie_params();
    
    // Configurar la cookie de sesión para que sea segura y accesible solo por HTTP
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Iniciar la sesión
    session_start();
    
    // Regenerar el ID de sesión periódicamente para mayor seguridad
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) {
        // Regenerar cada 30 minutos
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Función para verificar si el usuario está autenticado
function is_authenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Función para requerir autenticación
function require_auth() {
    if (!is_authenticated()) {
        // Guardar la URL actual para redirigir después del login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}
?>
