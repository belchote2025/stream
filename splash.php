<?php
/**
 * Página de inicio/splash con logo animado
 * Muestra el logo de UrresTV animado con botones de Login y Register
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Si el usuario ya está autenticado, redirigir a la página principal
if ($auth->isAuthenticated()) {
    redirect('/');
}

$pageTitle = 'Bienvenido - ' . SITE_NAME;
$baseUrl = rtrim(SITE_URL, '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Estilos -->
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/splash.css">
    
    <!-- PWA -->
    <link rel="manifest" href="<?php echo $baseUrl; ?>/manifest.json">
    <meta name="theme-color" content="#E50914">
</head>
<body>
    <div class="splash-container">
        <!-- Fondo con gradiente oscuro -->
        <div class="splash-background"></div>
        
        <!-- Logo animado -->
        <div class="logo-container">
            <div class="logo-circle">
                <div class="logo-play-button">
                    <i class="fas fa-play"></i>
                </div>
            </div>
            <h1 class="logo-text">UrresTV</h1>
        </div>
        
        <!-- Botones de acción -->
        <div class="splash-actions">
            <a href="<?php echo $baseUrl; ?>/login.php" class="btn-splash btn-login">
                <i class="fas fa-sign-in-alt"></i>
                <span>Iniciar Sesión</span>
            </a>
            <a href="<?php echo $baseUrl; ?>/register.php" class="btn-splash btn-register">
                <i class="fas fa-user-plus"></i>
                <span>Registrarse</span>
            </a>
        </div>
        
        <!-- Texto de bienvenida -->
        <p class="splash-subtitle">Tu plataforma de streaming favorita</p>
        
        <!-- Efecto de partículas opcional -->
        <div class="particles"></div>
    </div>
    
    <script>
        // Animación suave al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.splash-container');
            container.classList.add('loaded');
        });
        
        // Efecto de hover en los botones
        document.querySelectorAll('.btn-splash').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>








