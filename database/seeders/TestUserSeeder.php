<?php
require_once __DIR__ . '/../../includes/config.php';

// Verificar si el usuario de prueba ya existe
$db = getDbConnection();
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute(['test@example.com']);
$user = $stmt->fetch();

if (!$user) {
    try {
        // Iniciar transacción
        $db->beginTransaction();
        
        // Insertar usuario de prueba
        $hashedPassword = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
        
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, password, full_name, role, email_verified
            ) VALUES (
                :username, :email, :password, :full_name, :role, 1
            )
        ");
        
        $stmt->execute([
            ':username' => 'testuser',
            ':email' => 'test@example.com',
            ':password' => $hashedPassword,
            ':full_name' => 'Usuario de Prueba',
            ':role' => 'user'
        ]);
        
        $userId = $db->lastInsertId();
        
        // Insertar configuración por defecto
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id) VALUES (:user_id)
        ");
        
        $stmt->execute([':user_id' => $userId]);
        
        // Confirmar transacción
        $db->commit();
        
        echo "Usuario de prueba creado exitosamente.\n";
        echo "Email: test@example.com\n";
        echo "Contraseña: password123\n";
        
    } catch (Exception $e) {
        // Revertir en caso de error
        $db->rollBack();
        echo "Error al crear usuario de prueba: " . $e->getMessage() . "\n";
    }
} else {
    echo "El usuario de prueba ya existe.\n";
    echo "Email: test@example.com\n";
    echo "Contraseña: password123\n";
}

echo "Puedes iniciar sesión con estas credenciales.\n";
