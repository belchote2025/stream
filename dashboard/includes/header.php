<?php
// Verificar si el usuario está autenticado
if (!isset($user) || !$auth->isAuthenticated()) {
    redirect('/login.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Dashboard - ' . SITE_NAME); ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo rtrim(SITE_URL, '/'); ?>/favicon.ico" type="image/x-icon">
    
    <!-- Hojas de estilo -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(SITE_URL, '/'); ?>/css/dashboard.css">
    
    <!-- Scripts necesarios -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personalizados -->
    <script src="<?php echo rtrim(SITE_URL, '/'); ?>/js/dashboard.js" defer></script>
    
    <!-- Estilos personalizados -->
</head>
<body data-base-url="<?php echo rtrim(SITE_URL, '/'); ?>">
    <!-- Barra superior -->
    <div class="dashboard-topbar">
        <div class="topbar-left">
            <button class="toggle-sidebar d-lg-none">
                <i class="fas fa-bars"></i>
            </button>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Buscar en el dashboard...">
            </div>
        </div>
        
        <div class="topbar-right">
            <!-- Notificaciones -->
            <div class="notification-dropdown">
                <button class="notification-btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <h6 class="dropdown-header">Notificaciones</h6>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-film text-primary mr-2"></i>
                        <div>
                            <div>Nuevo episodio disponible</div>
                            <small class="text-muted">Hace 2 horas</small>
                        </div>
                    </a>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-tag text-success mr-2"></i>
                        <div>
                            <div>Oferta especial para ti</div>
                            <small class="text-muted">Ayer</small>
                        </div>
                    </a>
                    <a class="dropdown-item" href="#">
                        <i class="fas fa-user-plus text-info mr-2"></i>
                        <div>
                            <div>Nuevo seguidor</div>
                            <small class="text-muted">Hace 2 días</small>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-center" href="#">Ver todas las notificaciones</a>
                </div>
            </div>
            
            <!-- Menú de usuario -->
            <div class="user-dropdown">
                <div class="user-menu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <div class="user-avatar">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>">
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                    <span class="user-name d-none d-md-inline">
                        <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                    </span>
                    <i class="fas fa-chevron-down d-none d-md-inline ml-1" style="font-size: 0.8rem;"></i>
                </div>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="?tab=profile">
                        <i class="fas fa-user-circle mr-2"></i> Mi perfil
                    </a>
                    <a class="dropdown-item" href="?tab=settings">
                        <i class="fas fa-cog mr-2"></i> Configuración
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a class="dropdown-item" href="/admin/">
                        <i class="fas fa-shield-alt mr-2"></i> Panel de administración
                    </a>
                    <div class="dropdown-divider"></div>
                    <?php endif; ?>
                    <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#logoutModal">
                        <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contenido principal -->
    <div class="dashboard-content-wrapper">
