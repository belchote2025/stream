<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar autenticación
if (!$auth->isAuthenticated()) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    redirect('/login.php');
}

$user = $auth->getCurrentUser();
$pageTitle = 'Mi Cuenta - ' . SITE_NAME;
$activeTab = $_GET['tab'] ?? 'overview';
$allowedTabs = ['overview', 'profile', 'watchlist', 'history', 'subscription', 'settings'];

if (!function_exists('countUserWatchlist')) {
    function countUserWatchlist($userId) {
        static $cache = [];
        $userId = (int)$userId;
        if ($userId <= 0) {
            return 0;
        }
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        try {
            $db = getDbConnection();
            $stmt = $db->prepare('SELECT COUNT(*) FROM user_watchlist WHERE user_id = ?');
            $stmt->execute([$userId]);
            $cache[$userId] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('countUserWatchlist error: ' . $e->getMessage());
            $cache[$userId] = 0;
        }
        return $cache[$userId];
    }
}

// Validar pestaña
if (!in_array($activeTab, $allowedTabs)) {
    $activeTab = 'overview';
}

// Incluir el encabezado del dashboard
include_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="dashboard-sidebar">
        <div class="user-profile">
            <div class="avatar">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>">
                <?php else: ?>
                    <div class="avatar-initials">
                        <?php 
                        $initials = '';
                        if (!empty($user['full_name'])) {
                            $names = explode(' ', $user['full_name']);
                            $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                        } else {
                            $initials = strtoupper(substr($user['username'], 0, 2));
                        }
                        echo htmlspecialchars($initials);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h3><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h3>
                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'premium' ? 'premium' : 'secondary'); ?>">
                    <?php 
                    $roleLabels = [
                        'admin' => 'Administrador',
                        'premium' => 'Premium',
                        'free' => 'Gratis'
                    ];
                    echo $roleLabels[$user['role']];
                    ?>
                </span>
            </div>
        </div>
        
        <nav class="dashboard-nav">
            <ul>
                <li class="<?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
                    <a href="?tab=overview">
                        <i class="fas fa-home"></i> Resumen
                    </a>
                </li>
                <li class="<?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                    <a href="?tab=profile">
                        <i class="fas fa-user"></i> Mi Perfil
                    </a>
                </li>
                <li class="<?php echo $activeTab === 'watchlist' ? 'active' : ''; ?>">
                    <a href="?tab=watchlist">
                        <i class="fas fa-bookmark"></i> Mi Lista
                        <span class="badge"><?php echo countUserWatchlist($user['id']); ?></span>
                    </a>
                </li>
                <li class="<?php echo $activeTab === 'history' ? 'active' : ''; ?>">
                    <a href="?tab=history">
                        <i class="fas fa-history"></i> Historial
                    </a>
                </li>
                <li class="<?php echo $activeTab === 'subscription' ? 'active' : ''; ?>">
                    <a href="?tab=subscription">
                        <i class="fas fa-crown"></i> Suscripción
                    </a>
                </li>
                <li class="<?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
                    <a href="?tab=settings">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                </li>
                <?php if ($user['role'] === 'admin'): ?>
                <li class="divider"></li>
                <li>
                    <a href="/admin/" target="_blank">
                        <i class="fas fa-shield-alt"></i> Panel de Administración
                    </a>
                </li>
                <?php endif; ?>
                <li class="divider"></li>
                <li>
                    <a href="#" id="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </li>
            </ul>
        </nav>
        
        <?php if ($user['role'] !== 'admin'): ?>
        <div class="upgrade-banner">
            <h4>¿Quieres más beneficios?</h4>
            <p>Actualiza a Premium para disfrutar de contenido exclusivo sin anuncios.</p>
            <a href="?tab=subscription" class="btn btn-primary btn-sm btn-block">Actualizar Ahora</a>
        </div>
        <?php endif; ?>
    </aside>
    
    <!-- Contenido principal -->
    <main class="dashboard-content">
        <?php
        // Cargar el contenido de la pestaña activa
        $tabFile = __DIR__ . "/tabs/{$activeTab}.php";
        if (file_exists($tabFile)) {
            include $tabFile;
        } else {
            echo '<div class="alert alert-warning">Página no encontrada.</div>';
        }
        ?>
    </main>
</div>

<!-- Modal para confirmar cierre de sesión -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Cerrar Sesión</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas cerrar sesión?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <a href="/logout.php" class="btn btn-danger">Cerrar Sesión</a>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir el pie de página del dashboard
include_once __DIR__ . '/includes/footer.php';
?>

<!-- Scripts específicos del dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar el botón de cierre de sesión
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            $('#logoutModal').modal('show');
        });
    }
    
    // Inicializar tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Inicializar popovers
    $('[data-toggle="popover"]').popover({
        trigger: 'hover',
        placement: 'top',
        html: true
    });
});
</script>
