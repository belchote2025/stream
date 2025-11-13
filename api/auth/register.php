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
if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos son obligatorios.']);
    exit;
}

try {
    // Registrar el nuevo usuario
    $auth->register(
        $data['username'],
        $data['email'],
        $data['password'],
        $data['full_name'] ?? ''
    );
    
    // Obtener información del usuario registrado
    $user = $auth->getCurrentUser();
    unset($user['password']); // No devolver la contraseña
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Registro exitoso. ¡Bienvenido a nuestra plataforma!',
        'user' => $user
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
