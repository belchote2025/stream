<?php
require_once __DIR__ . '/../config.php';

/**
 * APIAuth - Clase para autenticación de la API
 */
class APIAuth {
    private static $instance = null;
    private $db;
    private $currentUser = null;
    private $authenticated = false;
    
    private function __construct() {
        $this->db = getDbConnection();
        $this->checkAuthentication();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function checkAuthentication() {
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Primero verificar sesión PHP (para panel de administración)
        // Verificar si hay user_id en la sesión
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $this->authenticated = true;
                    $this->currentUser = $user;
                    return;
                }
            } catch (Exception $e) {
                // Continuar con verificación de token
            }
        }
        
        // También verificar sesión de admin (admin_logged_in)
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            try {
                // Si hay admin_user_id, usarlo; si no, buscar por username
                if (isset($_SESSION['admin_user_id'])) {
                    $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
                    $stmt->execute([$_SESSION['admin_user_id']]);
                } elseif (isset($_SESSION['admin_username'])) {
                    $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
                    $stmt->execute([$_SESSION['admin_username']]);
                } else {
                    // Si no hay user_id ni username, crear un usuario temporal para admin
                    $this->authenticated = true;
                    $this->currentUser = [
                        'id' => 0,
                        'username' => $_SESSION['admin_username'] ?? 'admin',
                        'role' => $_SESSION['admin_role'] ?? 'admin',
                        'status' => 'active'
                    ];
                    return;
                }
                
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $this->authenticated = true;
                    $this->currentUser = $user;
                    return;
                }
            } catch (Exception $e) {
                // Continuar con verificación de token
            }
        }
        
        // Si no hay sesión PHP, intentar con token
        $token = null;
        $headers = getallheaders();
        
        // Buscar el token en el encabezado de autorización
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }
        
        // Si no se encontró en el encabezado, buscar en las cookies
        if (!$token && isset($_COOKIE['auth_token'])) {
            $token = $_COOKIE['auth_token'];
        }
        
        // Si hay token, verificar
        if ($token) {
            try {
                $stmt = $this->db->prepare("
                    SELECT u.*, at.token, at.expires_at
                    FROM auth_tokens at
                    JOIN users u ON at.user_id = u.id
                    WHERE at.token = ? 
                    AND at.is_revoked = FALSE 
                    AND at.expires_at > NOW()
                    AND u.status = 'active'
                ");
                
                $stmt->execute([$token]);
                $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tokenData) {
                    // Verificar si el token ha expirado
                    $now = new DateTime();
                    $expiresAt = new DateTime($tokenData['expires_at']);
                    
                    if ($now <= $expiresAt) {
                        $this->authenticated = true;
                        $this->currentUser = $tokenData;
                        
                        // Actualizar la última vez que se usó el token
                        $updateStmt = $this->db->prepare("
                            UPDATE auth_tokens 
                            SET last_used_at = NOW() 
                            WHERE token = ?
                        ");
                        $updateStmt->execute([$token]);
                    }
                }
            } catch (Exception $e) {
                // Error al verificar token
                error_log("Error verificando token: " . $e->getMessage());
            }
        }
    }
    
    public function isAuthenticated() {
        return $this->authenticated;
    }
    
    public function hasRole($role) {
        if (!$this->authenticated || !$this->currentUser) {
            return false;
        }
        
        // Verificar rol directo
        if (isset($this->currentUser['role']) && $this->currentUser['role'] === $role) {
            return true;
        }
        
        // Super admin tiene todos los roles
        if (isset($this->currentUser['role']) && $this->currentUser['role'] === 'super_admin') {
            return true;
        }
        
        return false;
    }
    
    public function getCurrentUser() {
        return $this->currentUser;
    }
    
    public function getUserId() {
        return $this->currentUser['id'] ?? null;
    }
}

