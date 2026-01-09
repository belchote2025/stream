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
    
    // Load addons
    $addonManager = AddonManager::getInstance();
    $addons = $addonManager->loadAddons();
    
    // Prepare addon data for response
    $addonData = [];
    foreach ($addons as $addon) {
        $addonData[] = [
            'id' => $addon->getId(),
            'name' => $addon->getName(),
            'version' => $addon->getVersion(),
            'description' => $addon->getDescription(),
            'author' => $addon->getAuthor(),
            'enabled' => $addon->isEnabled()
        ];
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Addons retrieved successfully',
        'data' => $addonData
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
    error_log('Error en api/addons/list.php: ' . $e->getMessage());
}

// Asegurar que solo se devuelva JSON
ob_clean();
echo json_encode($response);
exit;
?>
