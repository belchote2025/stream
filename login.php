<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Iniciar Sesión - ' . SITE_NAME;
$error = '';

// Procesar el formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor, intenta de nuevo.';
    } else {
        try {
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if ($auth->login($email, $password)) {
                // Si el usuario marcó "Recordarme", establecer cookie de sesión persistente
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 días
                    
                    // Guardar token en la base de datos
                    $stmt = $db->prepare("
                        INSERT INTO user_sessions (user_id, token, expires_at, user_agent, ip_address)
                        VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)
                        ON DUPLICATE KEY UPDATE 
                            token = VALUES(token),
                            expires_at = VALUES(expires_at),
                            updated_at = NOW()
                    ");
                    
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
                    
                    $stmt->execute([
                        $_SESSION['user_id'],
                        $token,
                        $expires,
                        $userAgent,
                        $ipAddress
                    ]);
                    
                    // Establecer cookie segura
                    setcookie(
                        'remember_token',
                        $token,
                        [
                            'expires' => $expires,
                            'path' => '/',
                            'domain' => '',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );
                }
                
                // Redirigir según el rol del usuario
                $redirectUrl = '/';
                
                // Si hay una URL de redirección guardada, usarla
                if (isset($_SESSION['redirect_url'])) {
                    $redirectUrl = $_SESSION['redirect_url'];
                    unset($_SESSION['redirect_url']);
                } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
                    // Si es admin, redirigir al panel de administración
                    $redirectUrl = '/admin/';
                }
                
                redirect($redirectUrl);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            
            // Registrar intento fallido (para prevención de ataques de fuerza bruta)
            error_log("Intento de inicio de sesión fallido para el correo: $email - " . $_SERVER['REMOTE_ADDR']);
        }
    }
}

// Si el usuario ya está autenticado, redirigir a la página principal
if ($auth->isAuthenticated()) {
    redirect('/');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Iniciar Sesión</h1>
                <p>Bienvenido de nuevo a <?php echo htmlspecialchars(SITE_NAME); ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'google_not_configured'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> La autenticación con Google no está configurada. Por favor, usa el formulario de inicio de sesión o contacta al administrador.
                    </div>
                <?php elseif ($_GET['error'] === 'facebook_not_configured'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i> La autenticación con Facebook no está configurada. Por favor, usa el formulario de inicio de sesión o contacta al administrador.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> ¡Registro exitoso! Por favor inicia sesión con tus credenciales.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Contraseña restablecida con éxito. Ahora puedes iniciar sesión con tu nueva contraseña.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="tucorreo@ejemplo.com" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="d-flex justify-content-between">
                        <label for="password">Contraseña</label>
                        <a href="forgot-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Ingresa tu contraseña" required>
                        <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Iniciar sesión
                    </button>
                </div>
                
                <div class="social-login">
                    <p class="divider">o inicia sesión con</p>
                    
                    <div class="social-buttons">
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/api/auth/social/google.php" class="btn btn-social btn-google" id="googleLoginBtn">
                            <i class="fab fa-google"></i> Google
                        </a>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/api/auth/social/facebook.php" class="btn btn-social btn-facebook" id="facebookLoginBtn">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                    </div>
                </div>
                
                <div class="auth-footer">
                    <p>¿No tienes una cuenta? <a href="register.php">Regístrate aquí</a></p>
                </div>
            </form>
        </div>
        
        <div class="auth-illustration">
            <div class="illustration-content">
                <h2>¡Bienvenido de nuevo!</h2>
                <p>Accede a tu cuenta para disfrutar de todo el contenido exclusivo.</p>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-play-circle"></i>
                        <span>Continúa viendo donde lo dejaste</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-heart"></i>
                        <span>Guarda tus favoritos</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-bell"></i>
                        <span>Recibe notificaciones de nuevos episodios</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/auth.js"></script>
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/social-auth.js"></script>
</body>
</html>
