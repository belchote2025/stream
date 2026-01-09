<?php
// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Acceso denegado. Por favor <a href="login.php">inicia sesión</a>.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Addons - Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Gestión de Addons</h1>
        <p>¡Bienvenido a la sección de addons!</p>
        
        <div class="alert alert-success">
            <h4>¡Funciona!</h4>
            <p>Si puedes ver este mensaje, la página de addons está funcionando correctamente.</p>
            <p>Tu ID de sesión es: <?php echo session_id(); ?></p>
        </div>
        
        <a href="index.php" class="btn btn-primary">Volver al Panel</a>
    </div>
</body>
</html>
