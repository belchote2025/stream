<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Registro - ' . SITE_NAME;
$error = '';
$success = '';

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor, intenta de nuevo.';
    } else {
        try {
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            
            // Validar contraseñas coincidentes
            if ($password !== $confirmPassword) {
                throw new Exception('Las contraseñas no coinciden.');
            }
            
            // Registrar al usuario
            if ($auth->register($username, $email, $password, $fullName)) {
                $success = '¡Registro exitoso! Has iniciado sesión automáticamente.';
                
                // Redirigir después de un registro exitoso
                $redirectUrl = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : '/';
                unset($_SESSION['redirect_url']);
                
                header('Refresh: 2; URL=' . $redirectUrl);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
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
                <h1>Crear Cuenta</h1>
                <p>Únete a <?php echo htmlspecialchars(SITE_NAME); ?> y disfruta de todo nuestro contenido.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <div class="form-group">
                    <label for="full_name">Nombre completo</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" 
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                               placeholder="Tu nombre completo" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Nombre de usuario</label>
                    <div class="input-group">
                        <i class="fas fa-at"></i>
                        <input type="text" id="username" name="username" 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               placeholder="Elige un nombre de usuario" required minlength="3" maxlength="30"
                               pattern="[a-zA-Z0-9_]+" title="Solo letras, números y guiones bajos">
                    </div>
                    <small class="form-text">Solo letras, números y guiones bajos. Mínimo 3 caracteres.</small>
                </div>
                
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="tucorreo@ejemplo.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Crea una contraseña segura" required minlength="8">
                        <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter"></div>
                        <small class="strength-text">Seguridad de la contraseña: <span>Débil</span></small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmar contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Vuelve a escribir tu contraseña" required minlength="8">
                        <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-match">
                        <i class="fas fa-check-circle"></i>
                        <span>Las contraseñas coinciden</span>
                    </div>
                </div>
                
                <div class="form-group terms-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">
                        Acepto los <a href="#" target="_blank">Términos de Servicio</a> y la 
                        <a href="#" target="_blank">Política de Privacidad</a> de <?php echo htmlspecialchars(SITE_NAME); ?>.
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Crear cuenta
                </button>
                
                <div class="social-login">
                    <p class="divider">o regístrate con</p>
                    
                    <div class="social-buttons">
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/api/auth/social/google.php" class="btn btn-social btn-google" id="googleRegisterBtn">
                            <i class="fab fa-google"></i> Google
                        </a>
                        <a href="<?php echo rtrim(SITE_URL, '/'); ?>/api/auth/social/facebook.php" class="btn btn-social btn-facebook" id="facebookRegisterBtn">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                    </div>
                </div>
                
                <div class="auth-footer">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
                </div>
            </form>
        </div>
        
        <div class="auth-illustration">
            <div class="illustration-content">
                <h2>¡Bienvenido a <?php echo htmlspecialchars(SITE_NAME); ?>!</h2>
                <p>Disfruta de las mejores películas y series en un solo lugar.</p>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-film"></i>
                        <span>Contenido exclusivo</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-tv"></i>
                        <span>Disponible en todos tus dispositivos</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-star"></i>
                        <span>Recomendaciones personalizadas</span>
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
