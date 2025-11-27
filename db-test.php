<?php
require __DIR__ . '/includes/config.php';

try {
    getDbConnection();
    echo 'Conexión OK';
} catch (PDOException $e) {
    echo 'PDOException: ' . $e->getMessage();
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}

