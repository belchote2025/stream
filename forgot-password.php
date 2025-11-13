<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Recuperar Contraseña - ' . SITE_NAME;
$error = '';
$success = '';

// Procesar el formulario de recuperación de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Por favor, intenta de nuevo.';
    } else {
        try {
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($email)) {
                throw new Exception('Por favor, introduce tu dirección de correo electrónico.');
            }
            
            // Verificar si el correo existe
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generar token de restablecimiento
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $db->prepare("
                    UPDATE users 
                    SET reset_token = ?, 
                        reset_token_expires = ?,
                        updated_at = NOW()
                    WHERE id = ?
                
                ");
                
                if ($stmt->execute([$token, $expires, $user['id']])) {
                    // Enviar correo electrónico con el enlace de restablecimiento
                    $resetLink = SITE_URL . "/reset-password.php?token=" . $token;
                    
                    // Aquí iría el código para enviar el correo electrónico
                    // mail($email, "Restablecer contraseña", "Para restablecer tu contraseña, haz clic en: " . $resetLink);
                    
                    // Por ahora, mostramos el enlace en pantalla para pruebas
                    $success = 'Se ha enviado un enlace de restablecimiento a tu correo electrónico.';
                    $debugInfo = "En un entorno real, se enviaría un correo a $email con el siguiente enlace: ";
                    $debugLink = "<a href='$resetLink'>$resetLink</a>";
                } else {
                    throw new Exception('Error al procesar la solicitud. Por favor, inténtalo de nuevo.');
                }
            } else {
                // Por seguridad, no revelamos si el correo existe o no
                $success = 'Si el correo electrónico existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
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
                <h1>Recuperar Contraseña</h1>
                <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <?php echo htmlspecialchars($success); ?>
                    <?php if (isset($debugInfo)): ?>
                        <div class="mt-2 p-2 bg-light border rounded">
                            <small class="text-muted">
                                <strong>Nota para desarrollo:</strong><br>
                                <?php echo $debugInfo; ?><br>
                                <?php echo $debugLink; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    
                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="tucorreo@ejemplo.com" required autofocus>
                        </div>
                        <small class="form-text">Te enviaremos un enlace para restablecer tu contraseña.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Enviar enlace
                    </button>
                    
                    <div class="auth-footer text-center">
                        <p>¿Recordaste tu contraseña? <a href="login.php">Inicia sesión</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="auth-illustration">
            <div class="illustration-content">
                <h2>¿Problemas para iniciar sesión?</h2>
                <p>No te preocupes, te ayudaremos a recuperar el acceso a tu cuenta.</p>
                <div class="features">
                    <div class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Seguridad garantizada</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-clock"></i>
                        <span>Proceso rápido y sencillo</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-headset"></i>
                        <span>Soporte 24/7</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/auth.js"></script>
</body>
</html>
