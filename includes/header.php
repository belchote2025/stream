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
        <a class="navbar-brand" href="/streaming-platform">
            <?php if (file_exists(__DIR__ . '/../assets/img/logo.png')): ?>
                <img src="/streaming-platform/assets/img/logo.png" alt="<?php echo SITE_NAME; ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
            <?php endif; ?>
            <span style="<?php echo file_exists(__DIR__ . '/../assets/img/logo.png') ? 'display:none;' : ''; ?>"><?php echo SITE_NAME; ?></span>
        </a>
        
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menú">
            <i class="fas fa-bars"></i>
        </button>
        
        <ul class="navbar-nav" id="navbarNav">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="/streaming-platform">Inicio</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/streaming-platform/movies.php">Películas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/streaming-platform/series.php">Series</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/streaming-platform/categories.php">Categorías</a>
            </li>
        </ul>
        
        <div class="nav-right">
            <div class="search-container" id="searchContainer" style="position: relative;">
                <input type="search" id="searchInput" placeholder="Títulos, personas, géneros..." autocomplete="off">
                <i class="fas fa-search" id="searchIcon"></i>
            </div>
            
            <?php if (isLoggedIn()): ?>
                <div class="user-menu">
                    <img src="<?php echo !empty($_SESSION['user_avatar']) ? htmlspecialchars($_SESSION['user_avatar']) : '/streaming-platform/assets/images/avatar.png'; ?>" alt="Perfil" class="avatar">
                    <div class="dropdown">
                        <a href="/streaming-platform/dashboard/">Mi cuenta</a>
                        <a href="/streaming-platform/my-list.php">Mi lista</a>
                        <hr>
                        <?php if (isAdmin()): ?>
                            <a href="/streaming-platform/admin/">Panel de administración</a>
                            <hr>
                        <?php endif; ?>
                        <a href="/streaming-platform/api/auth/logout.php">Cerrar sesión</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/streaming-platform/login.php" class="nav-link">Iniciar sesión</a>
                <a href="/streaming-platform/register.php" class="btn btn-primary">Registrarse</a>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Contenido principal -->
    <main>
        <div class="container-fluid p-0">
