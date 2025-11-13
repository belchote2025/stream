<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Restablecer Contraseña - ' . SITE_NAME;
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$validToken = false;

// Verificar si el token es válido
if (!empty($token)) {
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? 
        AND reset_token_expires > NOW() 
        AND status = 'active'
    
    ");
    
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
        $userId = $user['id'];
        
        // Procesar el formulario de restablecimiento de contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verificar token CSRF
            if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
                $error = 'Token de seguridad inválido. Por favor, intenta de nuevo.';
            } else {
                try {
                    $password = $_POST['password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    // Validar contraseñas
                    if (strlen($password) < 8) {
                        throw new Exception('La contraseña debe tener al menos 8 caracteres.');
                    }
                    
                    if ($password !== $confirmPassword) {
                        throw new Exception('Las contraseñas no coinciden.');
                    }
                    
                    // Actualizar la contraseña
                    $hashedPassword = password_hash($password, HASH_ALGO, HASH_OPTIONS);
                    
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET password = ?, 
                            reset_token = NULL,
                            reset_token_expires = NULL,
                            updated_at = NOW()
                        WHERE id = ? AND reset_token = ?
                    
                    ");
                    
                    if ($stmt->execute([$hashedPassword, $userId, $token])) {
                        $success = '¡Tu contraseña ha sido restablecida con éxito! Ahora puedes iniciar sesión con tu nueva contraseña.';
                        $validToken = false; // Marcar el token como usado
                    } else {
                        throw new Exception('Error al restablecer la contraseña. Por favor, inténtalo de nuevo.');
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    } else {
        $error = 'El enlace de restablecimiento no es válido o ha expirado.';
    }
} else {
    $error = 'No se proporcionó un token de restablecimiento.';
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
                <h1>Restablecer Contraseña</h1>
                <p>Crea una nueva contraseña para tu cuenta.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="forgot-password.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Solicitar nuevo enlace
                    </a>
                </div>
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo htmlspecialchars($success); ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Ir al inicio de sesión
                    </a>
                </div>
            <?php elseif ($validToken): ?>
                <form method="POST" action="" class="auth-form" id="resetPasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    
                    <div class="form-group">
                        <label for="password">Nueva Contraseña</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" 
                                   placeholder="Ingresa tu nueva contraseña" required minlength="8">
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
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Vuelve a escribir tu nueva contraseña" required minlength="8">
                            <button type="button" class="toggle-password" aria-label="Mostrar/ocultar contraseña">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-match">
                            <i class="fas fa-check-circle"></i>
                            <span>Las contraseñas coinciden</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-redo"></i> Restablecer contraseña
                    </button>
                    
                    <div class="auth-footer text-center">
                        <p>¿Recordaste tu contraseña? <a href="login.php">Inicia sesión</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="auth-illustration">
            <div class="illustration-content">
                <h2>Seguridad Primero</h2>
                <p>Crea una contraseña segura que sea única para esta cuenta.</p>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-lock"></i>
                        <span>Usa al menos 8 caracteres</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Incluye letras mayúsculas y minúsculas</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-key"></i>
                        <span>Agrega números y caracteres especiales</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/auth.js"></script>
</body>
</html>
