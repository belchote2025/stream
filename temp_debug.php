<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mostrar información de la sesión
echo '<h1>Depuración de Sesión</h1>';
echo '<h2>Estado de la Sesión</h2>';
echo '<pre>';
echo 'ID de sesión: ' . session_id() . "\n";
echo 'Nombre de sesión: ' . session_name() . "\n";

// Mostrar todas las variables de sesión
echo "\nVariables de sesión:\n";
if (empty($_SESSION)) {
    echo "No hay variables de sesión definidas.\n";
} else {
    print_r($_SESSION);
}

// Mostrar cookies
echo "\nCookies:\n";
if (empty($_COOKIE)) {
    echo "No hay cookies definidas.\n";
} else {
    print_r($_COOKIE);
}

// Mostrar encabezados
echo "\nEncabezados de la petición:\n";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value\n";
}

// Mostrar información del servidor
echo "\nInformación del servidor:\n";
echo 'PHP Version: ' . phpversion() . "\n";
echo 'SAPI: ' . php_sapi_name() . "\n";
?>
