<?php
require_once __DIR__ . '/config.php';

class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    // Registrar un nuevo usuario
    public function register($username, $email, $password, $fullName = '') {
        // Validar entrada
        if (empty($username) || empty($email) || empty($password)) {
            throw new Exception("Todos los campos son obligatorios.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El formato del correo electrónico no es válido.");
        }
        
        if (strlen($password) < 8) {
            throw new Exception("La contraseña debe tener al menos 8 caracteres.");
        }
        
        // Verificar si el usuario ya existe
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception("El nombre de usuario o correo electrónico ya está en uso.");
        }
        
        // Hash de la contraseña
        $hashedPassword = password_hash($password, HASH_ALGO, HASH_OPTIONS);
        
        // Insertar el nuevo usuario
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, full_name, role, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'user', NOW(), NOW())
        ");
        
        try {
            $this->db->beginTransaction();
            
            $stmt->execute([
                $username,
                $email,
                $hashedPassword,
                $fullName
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Asignar plan gratuito por defecto
            $this->assignDefaultSubscription($userId);
            
            $this->db->commit();
            
            // Iniciar sesión automáticamente después del registro
            $this->login($email, $password);
            
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al registrar usuario: " . $e->getMessage());
            throw new Exception("Error al crear la cuenta. Por favor, inténtalo de nuevo.");
        }
    }
    
    // Iniciar sesión
    public function login($email, $password) {
        if (empty($email) || empty($password)) {
            throw new Exception("El correo electrónico y la contraseña son obligatorios.");
        }
        
        // Buscar usuario por email
        $stmt = $this->db->prepare("
            SELECT id, username, email, password, role, status, full_name, avatar_url,
                   subscription_plan_id, subscription_end_date
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verificar si el usuario existe y la contraseña es correcta
        if (!$user || !password_verify($password, $user['password'])) {
            throw new Exception("Credenciales inválidas. Por favor, verifica tu correo y contraseña.");
        }
        
        // Verificar si la cuenta está activa
        if ($user['status'] !== 'active') {
            throw new Exception("Tu cuenta ha sido suspendida o desactivada. Contacta al soporte para más información.");
        }
        
        // Verificar si la suscripción ha expirado
        if ($user['role'] === 'premium' && $user['subscription_end_date'] < date('Y-m-d')) {
            // Degradar a cuenta gratuita
            $this->downgradeToFree($user['id']);
            $user['role'] = 'free';
            $user['subscription_plan_id'] = null;
        }
        
        // Configurar la sesión del usuario
        $this->setUserSession($user);
        
        // Actualizar última hora de inicio de sesión
        $this->updateLastLogin($user['id']);
        
        return true;
    }
    
    // Cerrar sesión
    public function logout() {
        // Destruir todas las variables de sesión
        $_SESSION = [];
        
        // Borrar la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
        
        return true;
    }
    
    // Verificar si el usuario está autenticado
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }
    
    // Obtener información del usuario actual
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT u.*, p.name as plan_name, p.price as plan_price, p.billing_cycle
            FROM users u
            LEFT JOIN subscription_plans p ON u.subscription_plan_id = p.id
            WHERE u.id = ?
        ");
        
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    // Actualizar perfil de usuario
    public function updateProfile($userId, $data) {
        $allowedFields = ['full_name', 'email', 'avatar_url'];
        $updates = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields) && !empty($value)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            throw new Exception("No se proporcionaron datos para actualizar.");
        }
        
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        if (!$stmt->execute($params)) {
            throw new Exception("Error al actualizar el perfil. Por favor, inténtalo de nuevo.");
        }
        
        // Actualizar datos de sesión si es el usuario actual
        if ($this->isAuthenticated() && $_SESSION['user_id'] == $userId) {
            $user = $this->getCurrentUser();
            $this->setUserSession($user);
        }
        
        return true;
    }
    
    // Cambiar contraseña
    public function changePassword($userId, $currentPassword, $newPassword) {
        if (strlen($newPassword) < 8) {
            throw new Exception("La nueva contraseña debe tener al menos 8 caracteres.");
        }
        
        // Verificar la contraseña actual
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception("La contraseña actual es incorrecta.");
        }
        
        // Actualizar la contraseña
        $hashedPassword = password_hash($newPassword, HASH_ALGO, HASH_OPTIONS);
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        
        if (!$stmt->execute([$hashedPassword, $userId])) {
            throw new Exception("Error al cambiar la contraseña. Por favor, inténtalo de nuevo.");
        }
        
        return true;
    }
    
    // Asignar plan de suscripción por defecto (gratuito)
    private function assignDefaultSubscription($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET subscription_plan_id = (SELECT id FROM subscription_plans WHERE name = 'Básico' LIMIT 1),
                subscription_start_date = CURDATE(),
                subscription_end_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
            WHERE id = ?
        ");
        
        return $stmt->execute([$userId]);
    }
    
    // Degradar a cuenta gratuita
    private function downgradeToFree($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET role = 'free',
                subscription_plan_id = (SELECT id FROM subscription_plans WHERE name = 'Básico' LIMIT 1),
                subscription_start_date = NULL,
                subscription_end_date = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$userId]);
    }
    
    // Configurar la sesión del usuario
    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_fullname'] = $user['full_name'] ?? '';
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? '';
        
        // Regenerar el ID de sesión para prevenir fijación de sesión
        session_regenerate_id(true);
    }
    
    // Actualizar última hora de inicio de sesión
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    // Enviar correo de restablecimiento de contraseña
    public function sendPasswordResetEmail($email) {
        // Implementar lógica de envío de correo electrónico
        // Esto es un esqueleto básico
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Guardar token en la base de datos
        $stmt = $this->db->prepare("
            UPDATE users 
            SET reset_token = ?, 
                reset_token_expires = ?
            WHERE email = ?
        
        ");
        
        if ($stmt->execute([$token, $expires, $email])) {
            // Enviar correo electrónico con el enlace de restablecimiento
            $resetLink = SITE_URL . "/reset-password.php?token=" . $token;
            
            // Aquí iría el código para enviar el correo electrónico
            // mail($email, "Restablecer contraseña", "Para restablecer tu contraseña, haz clic en: " . $resetLink);
            
            return true;
        }
        
        return false;
    }
    
    // Restablecer contraseña con token
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            throw new Exception("La nueva contraseña debe tener al menos 8 caracteres.");
        }
        
        $hashedPassword = password_hash($newPassword, HASH_ALGO, HASH_OPTIONS);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password = ?, 
                reset_token = NULL,
                reset_token_expires = NULL,
                updated_at = NOW()
            WHERE reset_token = ? 
            AND reset_token_expires > NOW()
        ");
        
        if ($stmt->execute([$hashedPassword, $token]) && $stmt->rowCount() > 0) {
            return true;
        }
        
        throw new Exception("El enlace de restablecimiento no es válido o ha expirado.");
    }
}

// Inicializar autenticación
$auth = new Auth(getDbConnection());

// Funciones de autenticación ya están definidas en config.php
// Si necesitas versiones personalizadas, usa function_exists() para verificar antes de declarar

if (!function_exists('requireAuth')) {
    function requireAuth() {
        global $auth;
        if (!$auth->isAuthenticated()) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            header('Location: ' . SITE_URL . '/login.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        global $auth;
        requireAuth();
        
        if ($_SESSION['user_role'] !== 'admin') {
            $_SESSION['error'] = "No tienes permiso para acceder a esta página.";
            header('Location: ' . SITE_URL . '/');
            exit();
        }
    }
}

if (!function_exists('requirePremium')) {
    function requirePremium() {
        global $auth;
        requireAuth();
        
        if (!in_array($_SESSION['user_role'], ['premium', 'admin'])) {
            $_SESSION['error'] = "Esta función está disponible solo para usuarios premium.";
            header('Location: ' . SITE_URL . '/subscription.php');
            exit();
        }
    }
}
?>
