<?php

/**
 * Bootstrap para tests PHPUnit
 * Configura el entorno de pruebas
 */

// Configurar entorno de pruebas
putenv('APP_ENV=testing');
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=');
putenv('DB_NAME=streaming_platform_test');

// Incluir configuración
require_once __DIR__ . '/../includes/config.php';

/**
 * Configurar base de datos de pruebas
 */
function setupTestDatabase()
{
    try {
        // Conectar a MySQL sin base de datos específica
        $pdo = new PDO(
            'mysql:host=127.0.0.1;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Crear base de datos de pruebas si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS streaming_platform_test");

        // Seleccionar la base de datos
        $pdo->exec("USE streaming_platform_test");

        // Leer el esquema de la base de datos
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');

        // Ejecutar el esquema (solo las partes relevantes para tests)
        $statements = array_filter(array_map('trim', explode(';', $schema)));

        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/i', $statement)) {
                $pdo->exec($statement);
            }
        }

        echo "Base de datos de pruebas configurada correctamente.\n";

    } catch (PDOException $e) {
        echo "Error configurando base de datos de pruebas: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * Limpiar base de datos de pruebas
 */
function cleanTestDatabase()
{
    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=streaming_platform_test;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Limpiar tablas
        $tables = [
            'watch_history',
            'ratings',
            'comments',
            'notifications',
            'playlist_content',
            'user_playlists',
            'episodes',
            'seasons',
            'content_categories',
            'categories',
            'content',
            'users',
            'subscription_plans',
            'roles',
            'permissions',
            'role_permissions',
            'auth_tokens',
            'login_attempts'
        ];

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        // Recrear esquema
        setupTestDatabase();

    } catch (PDOException $e) {
        echo "Error limpiando base de datos de pruebas: " . $e->getMessage() . "\n";
    }
}

// Solo ejecutar setup si se llama directamente
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    if ($argc > 1 && $argv[1] === 'clean') {
        cleanTestDatabase();
    } else {
        setupTestDatabase();
    }
}
