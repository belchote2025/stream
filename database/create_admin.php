<?php
/**
 * Script para crear usuario administrador
 * Ejecuta este archivo si necesitas crear/resetear el usuario admin
 */

require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = $_POST['username'] ?? 'admin';
    $email = $_POST['email'] ?? 'admin@streamingplatform.com';
    $password = $_POST['password'] ?? 'admin123';
    $full_name = $_POST['full_name'] ?? 'Administrador';
    
    try {
        $db = getDbConnection();
        
        // Verificar si el usuario ya existe
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Actualizar contraseña
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, role = 'admin', status = 'active' WHERE id = ?");
            $stmt->execute([$passwordHash, $existing['id']]);
            $message = "Usuario admin actualizado correctamente.";
        } else {
            // Crear nuevo usuario
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                INSERT INTO users (username, email, password, full_name, role, status, email_verified) 
                VALUES (?, ?, ?, ?, 'admin', 'active', 1)
            ");
            $stmt->execute([$username, $email, $passwordHash, $full_name]);
            $message = "Usuario admin creado correctamente.";
        }
        
        $success = true;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Usuario Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #141414 0%, #1f1f1f 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(31, 31, 31, 0.95);
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        h1 { color: #e50914; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #e5e5e5; }
        input { width: 100%; padding: 12px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; color: #fff; }
        input:focus { outline: none; border-color: #e50914; }
        .btn { background: #e50914; color: #fff; border: none; padding: 12px 30px; border-radius: 4px; font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { background: #f40612; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: rgba(40,167,69,0.2); border-left: 4px solid #28a745; color: #28a745; }
        .alert-error { background: rgba(220,53,69,0.2); border-left: 4px solid #dc3545; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Crear Usuario Administrador</h1>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
            <a href="/streaming-platform/" class="btn" style="text-decoration: none; display: block; text-align: center;">Ir a la aplicación</a>
        <?php else: ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Usuario:</label>
                    <input type="text" name="username" value="admin" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="admin@streamingplatform.com" required>
                </div>
                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="password" value="admin123" required>
                </div>
                <div class="form-group">
                    <label>Nombre completo:</label>
                    <input type="text" name="full_name" value="Administrador">
                </div>
                <button type="submit" name="create_admin" class="btn">Crear/Actualizar Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

