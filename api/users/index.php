<?php
/**
 * API para gestionar usuarios (usando sesiones)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
requireAdmin();

$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getUsers();
        break;
    
    case 'POST':
        createUser();
        break;
    
    case 'PUT':
        $userId = $_GET['id'] ?? null;
        if ($userId) {
            updateUser($userId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere un ID de usuario']);
        }
        break;
    
    case 'DELETE':
        $userId = $_GET['id'] ?? null;
        if ($userId) {
            deleteUser($userId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere un ID de usuario']);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        break;
}

function getUsers() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                id, 
                username, 
                email, 
                full_name, 
                role, 
                status, 
                created_at, 
                last_login, 
                avatar_url,
                password
            FROM users 
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear datos para el frontend
        $formattedUsers = [];
        foreach ($users as $user) {
            $formattedUsers[] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'] ?: $user['username'],
                'role' => $user['role'],
                'status' => $user['status'],
                'registrationDate' => $user['created_at'],
                'lastLogin' => $user['last_login'],
                'avatar_url' => $user['avatar_url'] ?: '/streaming-platform/assets/img/default-poster.svg',
                'plan' => $user['role'] === 'premium' ? 'premium' : ($user['role'] === 'admin' ? 'admin' : 'free'),
                'password_hash' => $user['password'] // Hash de la contraseña (no la contraseña original)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $formattedUsers
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener usuarios: ' . $e->getMessage()]);
    }
}

function getUser($id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT 
                id, 
                username, 
                email, 
                full_name, 
                role, 
                status, 
                created_at, 
                last_login, 
                avatar_url 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'data' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener el usuario: ' . $e->getMessage()]);
    }
}

function createUser() {
    global $db;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validación básica
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: username, email, password']);
        return;
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'El formato del email no es válido.']);
        return;
    }
    
    if (strlen($data['password']) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres.']);
        return;
    }
    
    try {
        // Verificar si el email o username ya existen
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'El nombre de usuario o el email ya están en uso.']);
            return;
        }
        
        // Crear usuario
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = $data['role'] ?? 'user';
        $fullName = $data['full_name'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, full_name, role, status, email_verified, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())
        ");
        
        $stmt->execute([$data['username'], $data['email'], $passwordHash, $fullName, $role]);
        $userId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'data' => ['id' => $userId]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear usuario: ' . $e->getMessage()]);
    }
}

function updateUser($id) {
    global $db;
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    try {
        // Verificar que el usuario existe
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmtCheck->execute([$id]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        $updates = [];
        $params = [];
        
        $allowedFields = ['username', 'email', 'full_name', 'role', 'status'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        // Si se proporciona una nueva contraseña
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                http_response_code(400);
                echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres.']);
                return;
            }
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No se proporcionaron datos para actualizar']);
            return;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado correctamente'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar usuario: ' . $e->getMessage()]);
    }
}

function deleteUser($id) {
    global $db;
    try {
        // Verificar que el usuario existe
        $stmtCheck = $db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmtCheck->execute([$id]);
        $user = $stmtCheck->fetch();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
            return;
        }
        
        // No permitir eliminar el propio usuario admin
        if ($id == $_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'No puedes eliminar tu propio usuario']);
            return;
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado correctamente'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar usuario: ' . $e->getMessage()]);
    }
}

