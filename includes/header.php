<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Suprimir warnings y errores no críticos -->
    <script>
        (function() {
            'use strict';
            
            // Verificar si ya se ha inicializado para evitar duplicados
            if (window.__consoleSuppressionInitialized) {
                return;
            }
            window.__consoleSuppressionInitialized = true;
            
            // Interceptar console.warn para suprimir warnings no críticos
            const originalWarn = console.warn;
            console.warn = function(...args) {
                const message = args[0];
                if (typeof message === 'string') {
                    // Suprimir warnings de YouTube postMessage (normales en localhost)
                    if (message.includes('Failed to execute \'postMessage\'') || 
                        message.includes('www-widgetapi.js') ||
                        message.includes('widgetapi.js') ||
                        (message.includes('postMessage') && message.includes('DOMWindow')) ||
                        (message.includes('postMessage') && message.includes('youtube.com'))) {
                        return;
                    }
                    // Suprimir warnings de "unreachable code" de archivos minificados
                    if (message.includes('unreachable code after return statement')) {
                        return;
                    }
                    // Suprimir warnings de asm.js (no críticos)
                    if (message.includes('Invalid asm.js') || message.includes('Unexpected token')) {
                        return;
                    }
                    // Suprimir warnings de console-test.js y mensajes de prueba
                    if (message.includes('console-test.js') || 
                        message.includes('Console.warn está disponible') ||
                        message.includes('Console.info está disponible') ||
                        message.includes('Console.error está disponible') ||
                        message.includes('Console.log está disponible') ||
                        message.includes('Consola funcionando correctamente') ||
                        message.includes('Todos los métodos de consola funcionan')) {
                        return;
                    }
                }
                // Mostrar todos los demás warnings normalmente
                originalWarn.apply(console, args);
            };
            
            // Interceptar console.error para suprimir errores no críticos de YouTube
            const originalError = console.error;
            console.error = function(...args) {
                const message = args[0];
                if (typeof message === 'string') {
                    // Suprimir errores de YouTube postMessage (normales en localhost)
                    if (message.includes('Failed to execute \'postMessage\'') || 
                        message.includes('www-widgetapi.js') ||
                        message.includes('widgetapi.js') ||
                        (message.includes('postMessage') && message.includes('DOMWindow')) ||
                        (message.includes('postMessage') && message.includes('youtube.com'))) {
                        return;
                    }
                    // Suprimir errores de console-test.js y mensajes de prueba
                    if (message.includes('console-test.js') || 
                        message.includes('Console.error está disponible') ||
                        message.includes('Test de objeto') ||
                        message.includes('Test de array') ||
                        message.includes('Múltiples argumentos')) {
                        return;
                    }
                }
                // Mostrar todos los demás errores normalmente
                originalError.apply(console, args);
            };
        })();
    </script>
    
    <!-- Estilos críticos inline para evitar FOUC -->
    <style id="critical-styles">
        *{margin:0;padding:0;box-sizing:border-box}
        html{scroll-behavior:smooth;background:#141414}
        html:not(.styles-ready){overflow:hidden}
        body{background-color:#141414!important;color:#fff!important;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif!important;font-size:16px!important;line-height:1.6!important;overflow-x:hidden!important}
        html.styles-ready{overflow:auto}
        .navbar{position:fixed!important;top:0!important;left:0!important;right:0!important;z-index:1000!important;padding:0 4%!important;height:70px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;background:rgba(0,0,0,0.7)!important}
        .hero{position:relative!important;width:100%!important;height:80vh!important;min-height:500px!important;max-height:900px!important;overflow:hidden!important;margin-top:70px!important;background:#000!important}
    </style>
    
    <!-- Preload de recursos críticos -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    
    <!-- CSS crítico cargado síncronamente -->
    <link rel="stylesheet" href="/streaming-platform/css/critical.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome - versión estable sin errores de glifos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" integrity="sha512-MV7K8+y+gLIBoVD59lQIYicR65iaqukzvf/nwasF0nqhPay5w/9lJmVM2hMDcnK1OnMGCdVK+iQrJ7lzPJQd1w==" crossorigin="anonymous" referrerpolicy="no-referrer">
    
    <!-- Preload de estilos no críticos -->
    <link rel="preload" href="/streaming-platform/css/styles.css" as="style">
    
    <!-- Fallback para Font Awesome si falla -->
    <script>
        // Detectar si Font Awesome falla y usar fallback
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const testIcon = document.createElement('i');
                testIcon.className = 'fas fa-check';
                testIcon.style.position = 'absolute';
                testIcon.style.visibility = 'hidden';
                document.body.appendChild(testIcon);
                
                const computedStyle = window.getComputedStyle(testIcon, ':before');
                const fontFamily = computedStyle.getPropertyValue('font-family');
                
                if (!fontFamily || !fontFamily.includes('Font Awesome')) {
                    console.warn('Font Awesome no cargó correctamente, usando fallback');
                    // Agregar fallback visual
                    document.body.classList.add('fa-fallback');
                }
                
                document.body.removeChild(testIcon);
            }, 1000);
        });
    </script>
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/streaming-platform/assets/css/netflix-gallery.css">
    <link rel="stylesheet" href="/streaming-platform/assets/css/movies-table.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/streaming-platform/css/styles.css">
    <link rel="stylesheet" href="/streaming-platform/css/animations.css">
    <link rel="stylesheet" href="/streaming-platform/css/responsive.css">
    <link rel="stylesheet" href="/streaming-platform/css/mobile-improvements.css">
    <link rel="stylesheet" href="/streaming-platform/css/hero-optimizations.css">
    <link rel="stylesheet" href="/streaming-platform/css/navbar-enhancements.css">
    <link rel="stylesheet" href="/streaming-platform/css/hero-trailer.css">
    <link rel="stylesheet" href="/streaming-platform/css/font-awesome-fallback.css">
    <link rel="stylesheet" href="/streaming-platform/css/fix-visibility.css">
    
    <script>
        // Marcar que los estilos están cargados - versión simplificada
        (function() {
            function markStylesReady() {
                document.documentElement.classList.add('styles-ready');
            }
            
            // Marcar inmediatamente si el DOM ya está listo
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                markStylesReady();
            } else {
                // Esperar a que el DOM esté listo
                document.addEventListener('DOMContentLoaded', markStylesReady);
            }
            
            // Fallback de seguridad
            setTimeout(markStylesReady, 100);
        })();
    </script>
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
