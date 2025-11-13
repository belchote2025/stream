<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/streaming-platform/assets/css/netflix-gallery.css">
    <link rel="stylesheet" href="/streaming-platform/assets/css/movies-table.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/streaming-platform/css/styles.css">
    <link rel="stylesheet" href="/streaming-platform/css/animations.css">
    <link rel="stylesheet" href="/streaming-platform/css/responsive.css">
    <link rel="stylesheet" href="/streaming-platform/css/mobile-improvements.css">
</head>
<body>
    <!-- Barra de navegación estilo Netflix -->
    <nav class="navbar" id="mainNavbar">
        <div class="navbar-left">
            <a class="navbar-brand" href="/streaming-platform">
                <?php if (file_exists(__DIR__ . '/../assets/img/logo.png')): ?>
                    <img src="/streaming-platform/assets/img/logo.png" alt="<?php echo SITE_NAME; ?>" class="brand-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                <?php endif; ?>
                <span class="brand-text" style="<?php echo file_exists(__DIR__ . '/../assets/img/logo.png') ? 'display:none;' : ''; ?>"><?php echo SITE_NAME; ?></span>
            </a>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menú">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="navbar-nav" id="navbarNav">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/streaming-platform">
                        <i class="fas fa-home"></i>
                        <span>Inicio</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'movies.php' ? 'active' : ''; ?>" href="/streaming-platform/movies.php">
                        <i class="fas fa-film"></i>
                        <span>Películas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'series.php' ? 'active' : ''; ?>" href="/streaming-platform/series.php">
                        <i class="fas fa-tv"></i>
                        <span>Series</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" href="/streaming-platform/categories.php">
                        <i class="fas fa-th-large"></i>
                        <span>Categorías</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="nav-right">
            <div class="search-container" id="searchContainer">
                <button class="search-toggle" id="searchToggle" aria-label="Buscar">
                    <i class="fas fa-search"></i>
                </button>
                <div class="search-input-wrapper">
                    <input 
                        type="search" 
                        id="searchInput" 
                        placeholder="Títulos, personas, géneros..." 
                        autocomplete="off"
                        aria-label="Buscar contenido"
                    >
                    <button class="search-clear" id="searchClear" aria-label="Limpiar búsqueda" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="autocomplete-results" id="autocompleteResults"></div>
            </div>
            
            <?php if (isLoggedIn()): ?>
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-toggle" aria-label="Menú de usuario">
                        <img 
                            src="<?php echo !empty($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar']) : '/streaming-platform/assets/img/default-poster.svg'; ?>" 
                            alt="Perfil" 
                            class="avatar"
                            onerror="this.src='/streaming-platform/assets/img/default-poster.svg';"
                        >
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <div class="user-info-dropdown">
                            <div class="user-avatar-large">
                                <img 
                                    src="<?php echo !empty($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar']) : '/streaming-platform/assets/img/default-poster.svg'; ?>" 
                                    alt="Perfil"
                                    onerror="this.src='/streaming-platform/assets/img/default-poster.svg';"
                                >
                            </div>
                            <div class="user-details">
                                <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?></strong>
                                <span><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></span>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="/streaming-platform/dashboard/" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>Mi cuenta</span>
                        </a>
                        <a href="/streaming-platform/my-list.php" class="dropdown-item">
                            <i class="fas fa-list"></i>
                            <span>Mi lista</span>
                        </a>
                        <a href="/streaming-platform/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                        <?php if (isAdmin()): ?>
                            <div class="dropdown-divider"></div>
                            <a href="/streaming-platform/admin/" class="dropdown-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Panel de administración</span>
                            </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="/streaming-platform/api/auth/logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar sesión</span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/streaming-platform/login.php" class="nav-link login-link">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Iniciar sesión</span>
                </a>
                <a href="/streaming-platform/register.php" class="btn btn-primary register-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>Registrarse</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Contenido principal -->
    <main>
        <div class="container-fluid p-0">
