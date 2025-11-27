<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../middleware/auth.php';

// Obtener conexión a la base de datos
$db = getDbConnection();

// Solo los administradores pueden acceder a este endpoint
requireRole('admin');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = explode('/', $uri);
$userId = isset($uri[array_search('users', $uri) + 1]) ? $uri[array_search('users', $uri) + 1] : null;

switch ($method) {
    case 'GET':
        if ($userId) {
            getUser($userId);
        } else {
            getUsers();
        }
        break;
    case 'POST':
        createUser();
        break;
    case 'PUT':
        if ($userId) {
            updateUser($userId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Se requiere un ID de usuario']);
        }
        break;
    case 'DELETE':
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
        $stmt = $db->query("SELECT id, username, email, full_name, role, status, created_at, last_login FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $users]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener usuarios: ' . $e->getMessage()]);
    }
}

function getUser($id) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT id, username, email, full_name, role, status, created_at, last_login, avatar_url FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode(['data' => $user]);
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

    // Validación básica
    if (empty($data['username']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: username, email, password, role']);
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

    // Verificar si el email o username ya existen
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode(['error' => 'El nombre de usuario o el email ya están en uso.']);
            return;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al verificar el usuario: ' . $e->getMessage()]);
        return;
    }

    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    try {
        $query = "INSERT INTO users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $data['username'],
            $data['email'],
            $password_hash,
            $data['full_name'] ?? null,
            $data['role'],
            $data['status'] ?? 'active'
        ]);

        $userId = $db->lastInsertId();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'id' => $userId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear el usuario: ' . $e->getMessage()]);
    }
}

function updateUser($id) {
    global $db;
    $data = json_decode(file_get_contents('php://input'), true);

    // No permitir cambiar el username
    if (isset($data['username'])) {
        unset($data['username']);
    }

    // Validar email si se está cambiando
    if (isset($data['email'])) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'El formato del email no es válido.']);
            return;
        }
        // Verificar si el nuevo email ya está en uso por otro usuario
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'El email ya está en uso por otro usuario.']);
            return;
        }
    }

    // Si se proporciona una nueva contraseña, hashearla
    if (!empty($data['password'])) {
        if (strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
            return;
        }
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }

    $allowedFields = ['email', 'full_name', 'role', 'status', 'password', 'avatar_url'];
    $setClauses = [];
    $params = [];

    foreach ($data as $key => $value) {
        if (in_array($key, $allowedFields)) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
    }

    if (empty($setClauses)) {
        http_response_code(400);
        echo json_encode(['error' => 'No hay datos válidos para actualizar.']);
        return;
    }

    $params[] = $id;

    try {
        $query = "UPDATE users SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado correctamente'
            ]);
        } else {
            // No se actualizó nada, pero no es un error si el usuario existe
            $stmtCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetch()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'No se realizaron cambios en el usuario.'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado.']);
            }
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar el usuario: ' . $e->getMessage()]);
    }
}

function deleteUser($id) {
    global $db;

    // Evitar que un admin se elimine a sí mismo
    $sessionUser = get_current_user_from_session(); // Necesitarías una función que te dé el usuario de la sesión
    if ($sessionUser && $sessionUser['id'] == $id) {
        http_response_code(403);
        echo json_encode(['error' => 'No puedes eliminar tu propia cuenta de administrador.']);
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            http_response_code(204); // No Content
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Usuario no encontrado']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar el usuario: ' . $e->getMessage()]);
    }
}

// Función de ayuda para obtener el usuario de la sesión (debe ser implementada en tu auth.php)
function get_current_user_from_session() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    return null;
}
?>