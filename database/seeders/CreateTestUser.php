<?php
require_once __DIR__ . '/../../includes/config.php';

// Obtener conexión a la base de datos
$db = getDbConnection();

try {
    // Iniciar transacción
    $db->beginTransaction();

    // Verificar si el usuario de prueba ya existe
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['test@example.com']);
    
    if ($stmt->fetch()) {
        echo "El usuario de prueba ya existe.\n";
        exit(0);
    }

    // Insertar usuario de prueba
    $hashedPassword = password_hash('password123', HASH_ALGO, HASH_OPTIONS);
    
    $userStmt = $db->prepare("
        INSERT INTO users (
            username, email, password, full_name, role, email_verified, created_at, updated_at
        ) VALUES (
            :username, :email, :password, :full_name, :role, 1, NOW(), NOW()
        )
    ");
    
    $userStmt->execute([
        ':username' => 'testuser',
        ':email' => 'test@example.com',
        ':password' => $hashedPassword,
        ':full_name' => 'Usuario de Prueba',
        ':role' => 'user'
    ]);
    
    $userId = $db->lastInsertId();
    
    // Insertar configuración por defecto
    $settingsStmt = $db->prepare("
        INSERT INTO user_settings (user_id) VALUES (:user_id)
    ");
    
    $settingsStmt->execute([':user_id' => $userId]);
    
    // Confirmar transacción
    $db->commit();
    
    echo "¡Usuario de prueba creado exitosamente!\n";
    echo "Email: test@example.com\n";
    echo "Contraseña: password123\n";
    
} catch (Exception $e) {
    // Revertir en caso de error
    $db->rollBack();
    echo "Error al crear el usuario de prueba: " . $e->getMessage() . "\n";
    exit(1);
}
