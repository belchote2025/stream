<?php
require_once __DIR__ . '/../config.php';

class AdminAuth {
    private $db;
    
    public function __construct() {
        // Inicializar conexión a la base de datos
        $this->db = getDbConnection();
    }
    
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    public function hasRole($role) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar rol en la sesión o en la base de datos
        if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === $role) {
            return true;
        }
        
        // Si hay user_id, verificar en la base de datos
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && $user['role'] === $role) {
                    return true;
                }
            } catch (Exception $e) {
                // Error al verificar
            }
        }
        
        return false;
    }
    
    public function login($username, $password) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        try {
            // Buscar usuario por username o email
            $stmt = $this->db->prepare("
                SELECT id, username, email, password, role, status 
                FROM users 
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Verificar que el usuario tenga rol de admin
                if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role'] = $user['role'];
                    $_SESSION['user_id'] = $user['id']; // Establecer user_id para APIAuth
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Actualizar última hora de inicio de sesión
                    $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    return true;
                }
            }
            
            // Fallback: credenciales hardcodeadas para desarrollo (solo si no hay usuarios en BD)
            if ($username === 'admin' && $password === 'admin123') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_role'] = 'admin';
                $_SESSION['user_role'] = 'admin'; // Establecer también user_role para requireAdmin()
                // Intentar encontrar o crear un usuario admin
                try {
                    $stmt = $this->db->prepare("SELECT id FROM users WHERE username = 'admin' AND role = 'admin' LIMIT 1");
                    $stmt->execute();
                    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($adminUser) {
                        $_SESSION['user_id'] = $adminUser['id'];
                        $_SESSION['username'] = $adminUser['username'] ?? $username;
                    } else {
                        // Si no existe el usuario, establecer valores por defecto
                        $_SESSION['user_id'] = 0;
                        $_SESSION['username'] = $username;
                    }
                } catch (Exception $e) {
                    // No se pudo obtener user_id, establecer valores por defecto
                    $_SESSION['user_id'] = 0;
                    $_SESSION['username'] = $username;
                }
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error en AdminAuth::login: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }
}
