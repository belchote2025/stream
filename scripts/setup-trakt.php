<?php
/**
 * Script de ayuda para configurar Trakt.tv
 * 
 * Este script te guía para obtener el TRAKT_CLIENT_ID necesario.
 * 
 * Uso:
 *   php scripts/setup-trakt.php
 */

declare(strict_types=1);

echo "========================================\n";
echo "  Configuración de Trakt.tv\n";
echo "========================================\n\n";

echo "Para usar Trakt.tv necesitas crear una aplicación OAuth y obtener el Client ID.\n\n";

echo "PASOS:\n";
echo "1. Abre tu navegador y ve a: https://trakt.tv/oauth/applications\n";
echo "2. Inicia sesión con tu cuenta: edu300572@gmail.com\n";
echo "3. Haz clic en 'New Application' o 'Nueva Aplicación'\n";
echo "4. Completa el formulario:\n";
echo "   - Name: Streaming Platform (o el nombre que prefieras)\n";
echo "   - Description: Aplicación para streaming platform\n";
echo "   - Redirect uri: http://localhost/streaming-platform\n";
echo "   - Website: (opcional, déjalo vacío)\n";
echo "5. Haz clic en 'Save' o 'Guardar'\n";
echo "6. Copia el 'Client ID' que aparece\n\n";

echo "Una vez que tengas el Client ID, puedes:\n";
echo "  a) Agregarlo al archivo .env como: TRAKT_CLIENT_ID=tu_client_id_aqui\n";
echo "  b) O ejecutar este script con: php scripts/setup-trakt.php --client-id=TU_CLIENT_ID\n\n";

// Si se proporciona el Client ID como argumento, actualizar el .env
$options = getopt('', ['client-id::']);
if (!empty($options['client-id'])) {
    $clientId = trim($options['client-id']);
    $envFile = dirname(__DIR__) . '/.env';
    
    if (!file_exists($envFile)) {
        echo "ERROR: No se encontró el archivo .env\n";
        echo "Creando archivo .env...\n";
        // Crear archivo .env básico si no existe
        file_put_contents($envFile, "# Trakt.tv Configuration\nTRAKT_CLIENT_ID=\n");
    }
    
    $content = file_get_contents($envFile);
    
    // Buscar y reemplazar TRAKT_CLIENT_ID
    if (preg_match('/^TRAKT_CLIENT_ID=.*$/m', $content)) {
        $content = preg_replace('/^TRAKT_CLIENT_ID=.*$/m', "TRAKT_CLIENT_ID={$clientId}", $content);
    } else {
        // Si no existe, agregarlo al final
        $content .= "\nTRAKT_CLIENT_ID={$clientId}\n";
    }
    
    file_put_contents($envFile, $content);
    echo "✓ Client ID guardado en .env\n";
    echo "✓ Puedes ejecutar ahora: php scripts/fetch-new-content.php --type=movie --limit=10\n\n";
} else {
    echo "¿Tienes el Client ID ahora? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $answer = trim(strtolower($line));
    fclose($handle);
    
    if ($answer === 's' || $answer === 'si' || $answer === 'sí' || $answer === 'y' || $answer === 'yes') {
        echo "\nIngresa el Client ID: ";
        $handle = fopen("php://stdin", "r");
        $clientId = trim(fgets($handle));
        fclose($handle);
        
        if (!empty($clientId)) {
            $envFile = dirname(__DIR__) . '/.env';
            
            if (!file_exists($envFile)) {
                file_put_contents($envFile, "# Trakt.tv Configuration\nTRAKT_CLIENT_ID=\n");
            }
            
            $content = file_get_contents($envFile);
            
            if (preg_match('/^TRAKT_CLIENT_ID=.*$/m', $content)) {
                $content = preg_replace('/^TRAKT_CLIENT_ID=.*$/m', "TRAKT_CLIENT_ID={$clientId}", $content);
            } else {
                $content .= "\nTRAKT_CLIENT_ID={$clientId}\n";
            }
            
            file_put_contents($envFile, $content);
            echo "\n✓ Client ID guardado exitosamente!\n";
            echo "✓ Puedes ejecutar ahora: php scripts/fetch-new-content.php --type=movie --limit=10\n\n";
        } else {
            echo "\n✗ Client ID vacío. Ejecuta el script de nuevo cuando tengas el Client ID.\n\n";
        }
    } else {
        echo "\nCuando tengas el Client ID, ejecuta:\n";
        echo "  php scripts/setup-trakt.php --client-id=TU_CLIENT_ID\n\n";
    }
}

echo "========================================\n";
echo "  ¡Listo!\n";
echo "========================================\n";












