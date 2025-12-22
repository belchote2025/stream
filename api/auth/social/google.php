<?php
/**
 * Autenticación con Google OAuth
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
    // Si es una petición GET, redirigir a Google OAuth
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Verificar si hay un código de autorización
        if (isset($_GET['code'])) {
            // Procesar el código de autorización
            $code = $_GET['code'];
            
            // Intercambiar código por token (esto requiere configuración OAuth real)
            // Por ahora, simulamos la obtención de datos del usuario
            $error = $_GET['error'] ?? null;
            
            if ($error) {
                throw new Exception('Error en la autenticación de Google: ' . $error);
            }
            
            // En una implementación real, aquí harías:
            // 1. Intercambiar el código por un access token
            // 2. Usar el access token para obtener información del usuario
            // 3. Crear o actualizar el usuario en la base de datos
            
            // Por ahora, redirigir a una página de configuración
            $_SESSION['oauth_provider'] = 'google';
            $_SESSION['oauth_code'] = $code;
            
            header('Location: ' . $baseUrl . '/api/auth/social/callback.php?provider=google');
            exit;
        }
        
        // Generar URL de autorización de Google
        // En producción, necesitarías configurar esto en Google Cloud Console
        $clientId = getenv('GOOGLE_CLIENT_ID') ?: '';
        $redirectUri = $baseUrl . '/api/auth/social/google.php';
        $scope = 'openid email profile';
        
        if (empty($clientId)) {
            // Si no hay configuración, redirigir de vuelta al login con mensaje
            $_SESSION['error'] = 'Autenticación con Google no está configurada. Por favor, contacta al administrador.';
            header('Location: ' . $baseUrl . '/login.php?error=google_not_configured');
            exit;
        }
        
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'online',
            'prompt' => 'select_account'
        ]);
        
        // Redirigir a Google
        header('Location: ' . $authUrl);
        exit;
    }
    
    // Si es POST, procesar datos del usuario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id_token']) && empty($data['access_token'])) {
            throw new Exception('Token de Google requerido');
        }
        
        // En una implementación real, verificarías el token con Google
        // Por ahora, usamos datos simulados para desarrollo
        
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        $googleId = $data['sub'] ?? $data['id'] ?? '';
        
        if (empty($email)) {
            throw new Exception('Email de Google requerido');
        }
        
        // Buscar o crear usuario
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? OR google_id = ?");
        $stmt->execute([$email, $googleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Usuario existe, actualizar google_id si no está y la columna existe
            if (empty($user['google_id'])) {
                try {
                    $updateStmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                    $updateStmt->execute([$googleId, $user['id']]);
                } catch (PDOException $e) {
                    // Si la columna no existe, solo loguear (no crítico)
                    error_log('Columna google_id no existe: ' . $e->getMessage());
                }
            }
            
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso con Google',
                'redirect' => $baseUrl . '/'
            ]);
        } else {
            // Crear nuevo usuario
            $username = strtolower(str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $name))) . '_' . substr($googleId, 0, 8);
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
            
            $password = bin2hex(random_bytes(16)); // Contraseña aleatoria
            
            if ($auth->register($username, $email, $password, $name)) {
                // Actualizar con google_id si la columna existe
                $newUser = $auth->getCurrentUser();
                try {
                    $updateStmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                    $updateStmt->execute([$googleId, $newUser['id']]);
                } catch (PDOException $e) {
                    // Si la columna no existe, solo loguear (no crítico)
                    error_log('Columna google_id no existe: ' . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Cuenta creada e inicio de sesión exitoso con Google',
                    'redirect' => $baseUrl . '/'
                ]);
            } else {
                throw new Exception('Error al crear cuenta con Google');
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

