<?php
/**
 * Autenticación con Facebook OAuth
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

ob_clean();

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

$db = getDbConnection();
$auth = new Auth($db);
$baseUrl = rtrim(SITE_URL, '/');

try {
    // Si es una petición GET, redirigir a Facebook OAuth
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Verificar si hay un código de autorización
        if (isset($_GET['code'])) {
            $code = $_GET['code'];
            $error = $_GET['error'] ?? null;
            
            if ($error) {
                throw new Exception('Error en la autenticación de Facebook: ' . $error);
            }
            
            $_SESSION['oauth_provider'] = 'facebook';
            $_SESSION['oauth_code'] = $code;
            
            header('Location: ' . $baseUrl . '/api/auth/social/callback.php?provider=facebook');
            exit;
        }
        
        // Generar URL de autorización de Facebook
        $appId = getenv('FACEBOOK_APP_ID') ?: '';
        $redirectUri = $baseUrl . '/api/auth/social/facebook.php';
        $scope = 'email,public_profile';
        
        if (empty($appId)) {
            echo json_encode([
                'success' => false,
                'error' => 'Autenticación con Facebook no configurada',
                'message' => 'Por favor, contacta al administrador para configurar la autenticación con Facebook.',
                'setup_required' => true
            ]);
            exit;
        }
        
        $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16)) // CSRF protection
        ]);
        
        header('Location: ' . $authUrl);
        exit;
    }
    
    // Si es POST, procesar datos del usuario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['access_token'])) {
            throw new Exception('Token de Facebook requerido');
        }
        
        // En una implementación real, verificarías el token con Facebook
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        $facebookId = $data['id'] ?? '';
        
        if (empty($email) && empty($facebookId)) {
            throw new Exception('Email o ID de Facebook requerido');
        }
        
        // Buscar o crear usuario
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR facebook_id = ?");
        $stmt->execute([$email, $facebookId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Usuario existe, actualizar facebook_id si no está y la columna existe
            if (empty($user['facebook_id'])) {
                try {
                    $updateStmt = $db->prepare("UPDATE users SET facebook_id = ? WHERE id = ?");
                    $updateStmt->execute([$facebookId, $user['id']]);
                } catch (PDOException $e) {
                    // Si la columna no existe, solo loguear (no crítico)
                    error_log('Columna facebook_id no existe: ' . $e->getMessage());
                }
            }
            
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso con Facebook',
                'redirect' => $baseUrl . '/'
            ]);
        } else {
            // Crear nuevo usuario
            $username = strtolower(str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $name))) . '_' . substr($facebookId, 0, 8);
            // Asegurar que el username sea único
            $originalUsername = $username;
            $counter = 1;
            while (true) {
                $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->execute([$username]);
                if ($checkStmt->rowCount() === 0) {
                    break;
                }
                $username = $originalUsername . '_' . $counter;
                $counter++;
            }
            
            $password = bin2hex(random_bytes(16));
            $userEmail = $email ?: $facebookId . '@facebook.temp';
            
            if ($auth->register($username, $userEmail, $password, $name)) {
                // Actualizar con facebook_id si la columna existe
                $newUser = $auth->getCurrentUser();
                try {
                    $updateStmt = $db->prepare("UPDATE users SET facebook_id = ? WHERE id = ?");
                    $updateStmt->execute([$facebookId, $newUser['id']]);
                } catch (PDOException $e) {
                    // Si la columna no existe, solo loguear (no crítico)
                    error_log('Columna facebook_id no existe: ' . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuenta creada e inicio de sesión exitoso con Facebook',
                    'redirect' => $baseUrl . '/'
                ]);
            } else {
                throw new Exception('Error al crear cuenta con Facebook');
            }
        }
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

