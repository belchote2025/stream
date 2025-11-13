<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Obtener la conexión a la base de datos
$db = getDbConnection();
$auth = new Auth($db);

// Obtener datos del cuerpo de la petición
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos de entrada
if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo electrónico y la contraseña son obligatorios.']);
    exit;
}

try {
    // Iniciar sesión
    $auth->login($data['email'], $data['password']);
    
    // Obtener información del usuario
    $user = $auth->getCurrentUser();
    unset($user['password']); // No devolver la contraseña
    
    // Generar token de autenticación
    $token = bin2hex(random_bytes(32));
    $expiresAt = new DateTime('+30 days');
    
    // Guardar token en la base de datos
    $stmt = $db->prepare("
        INSERT INTO auth_tokens (user_id, token, expires_at, user_agent, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user['id'],
        $token,
        $expiresAt->format('Y-m-d H:i:s'),
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // Configurar cookie de autenticación
    setcookie(
        'auth_token',
        $token,
        [
            'expires' => $expiresAt->getTimestamp(),
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
    
    echo json_encode([
        'message' => 'Inicio de sesión exitoso',
        'user' => $user,
        'token' => $token
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
