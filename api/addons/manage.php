<?php
// Evitar cualquier salida antes del JSON
ob_start();

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/addons/AddonManager.php';
require_once __DIR__ . '/../../includes/auth/APIAuth.php';

// Limpiar cualquier salida accidental
ob_clean();

// Establecer headers JSON
header('Content-Type: application/json');

// Initialize response array
$response = [
    'status' => 'error',
    'message' => 'Unknown error',
    'data' => []
];

try {
    // Check if user is authenticated and has admin privileges
    $auth = APIAuth::getInstance();
    if (!$auth->isAuthenticated() || !$auth->hasRole('admin')) {
        http_response_code(403);
        $response['message'] = 'Unauthorized';
        echo json_encode($response);
        exit;
    }
    
    // Get request method
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $addonManager = AddonManager::getInstance();
    $addonManager->loadAddons();
    
    switch ($method) {
        case 'POST':
            // Enable/Disable addon
            if (!isset($input['addon_id']) || !isset($input['action'])) {
                throw new Exception('Missing required parameters');
            }
            
            $addon = $addonManager->getAddon($input['addon_id']);
            if (!$addon) {
                throw new Exception('Addon not found');
            }
            
            if ($input['action'] === 'enable') {
                if ($addonManager->enableAddon($input['addon_id'])) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Addon enabled successfully',
                        'data' => [
                            'id' => $addon->getId(),
                            'enabled' => true
                        ]
                    ];
                } else {
                    throw new Exception('Failed to enable addon');
                }
            } elseif ($input['action'] === 'disable') {
                if ($addonManager->disableAddon($input['addon_id'])) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Addon disabled successfully',
                        'data' => [
                            'id' => $addon->getId(),
                            'enabled' => false
                        ]
                    ];
                } else {
                    throw new Exception('Failed to disable addon');
                }
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            // Uninstall addon
            if (!isset($input['addon_id'])) {
                throw new Exception('Addon ID is required');
            }
            
            $addonId = $input['addon_id'];
            $addonDir = __DIR__ . '/../../addons/' . $addonId;
            
            // Verificar que el addon existe
            if (!file_exists($addonDir)) {
                throw new Exception('Addon not found');
            }
            
            // Eliminar directorio del addon
            function removeDirectory($dir) {
                if (!file_exists($dir)) return true;
                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    $path = $dir . '/' . $file;
                    is_dir($path) ? removeDirectory($path) : unlink($path);
                }
                return rmdir($dir);
            }
            
            if (removeDirectory($addonDir)) {
                // Eliminar de la base de datos
                try {
                    $db = getDbConnection();
                    $stmt = $db->prepare("DELETE FROM addons WHERE id = ?");
                    $stmt->execute([$addonId]);
                } catch (Exception $e) {
                    error_log("Error eliminando addon de BD: " . $e->getMessage());
                }
                
                $response = [
                    'status' => 'success',
                    'message' => 'Addon uninstalled successfully'
                ];
            } else {
                throw new Exception('Failed to remove addon directory');
            }
            break;
            
        case 'GET':
            // Get addon details
            if (!isset($_GET['addon_id'])) {
                throw new Exception('Addon ID is required');
            }
            
            $addon = $addonManager->getAddon($_GET['addon_id']);
            if (!$addon) {
                throw new Exception('Addon not found');
            }
            
            $response = [
                'status' => 'success',
                'message' => 'Addon details retrieved',
                'data' => [
                    'id' => $addon->getId(),
                    'name' => $addon->getName(),
                    'version' => $addon->getVersion(),
                    'description' => $addon->getDescription(),
                    'author' => $addon->getAuthor(),
                    'enabled' => $addon->isEnabled()
                ]
            ];
            break;
            
        default:
            http_response_code(405);
            $response['message'] = 'Method not allowed';
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error en api/addons/manage.php: ' . $e->getMessage());
}

// Asegurar que solo se devuelva JSON
ob_clean();
echo json_encode($response);
exit;
?>
