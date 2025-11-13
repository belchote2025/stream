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
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    
    <!-- Hojas de estilo -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    
    <!-- Scripts necesarios -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personalizados -->
    <script src="/js/dashboard.js"></script>
    
    <!-- Estilos personalizados -->
    <style>
        :root {
            --primary-color: #e50914;
            --primary-dark: #b20710;
            --secondary-color: #141414;
            --text-color: #333;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --border-color: #e9ecef;
            --sidebar-width: 280px;
            --header-height: 60px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: var(--text-color);
            overflow-x: hidden;
        }
        
        /* Estilos para el sidebar */
        .dashboard-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            padding: 1.5rem;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        
        .user-profile {
            text-align: center;
            padding: 1rem 0 2rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            overflow: hidden;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #6c757d;
            border: 3px solid #fff;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #212529;
        }
        
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        
        .badge-premium {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: #fff;
        }
        
        /* Estilos para el menú de navegación */
        .dashboard-nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .dashboard-nav li {
            margin-bottom: 0.5rem;
        }
        
        .dashboard-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #495057;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .dashboard-nav a:hover, 
        .dashboard-nav a:focus {
            background-color: rgba(229, 9, 20, 0.1);
            color: var(--primary-color);
        }
        
        .dashboard-nav a i {
            width: 24px;
            margin-right: 10px;
            text-align: center;
        }
        
        .dashboard-nav .active a {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: 500;
        }
        
        .dashboard-nav .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 1rem 0;
        }
        
        .dashboard-nav .badge {
            margin-left: auto;
            background-color: var(--primary-color);
            color: #fff;
        }
        
        /* Estilos para el banner de actualización */
        .upgrade-banner {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-top: 2rem;
            color: #000;
            text-align: center;
        }
        
        .upgrade-banner h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .upgrade-banner p {
            font-size: 0.85rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .upgrade-banner .btn {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            font-weight: 500;
        }
        
        /* Estilos para el contenido principal */
        .dashboard-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        /* Estilos para la barra superior */
        .dashboard-topbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: var(--header-height);
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            z-index: 999;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .topbar-left {
            display: flex;
            align-items: center;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            margin-right: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .toggle-sidebar:hover {
            color: var(--primary-color);
        }
        
        .search-bar {
            position: relative;
            width: 300px;
        }
        
        .search-bar input {
            width: 100%;
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #dee2e6;
            border-radius: 2rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .search-bar input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(229, 9, 20, 0.25);
        }
        
        .search-bar i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .topbar-right {
            display: flex;
            align-items: center;
        }
        
        .notification-dropdown, .user-dropdown {
            position: relative;
            margin-left: 1rem;
        }
        
        .notification-btn, .user-btn {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.25rem;
            cursor: pointer;
            position: relative;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .notification-btn:hover, .user-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: var(--primary-color);
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Estilos para el menú desplegable de usuario */
        .user-menu {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 0.5rem;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: #343a40;
            margin-right: 0.5rem;
        }
        
        /* Estilos para el menú desplegable */
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            padding: 0.5rem 0;
            min-width: 200px;
            margin-top: 0.5rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            font-size: 0.9rem;
            color: #212529;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .dropdown-item:hover, 
        .dropdown-item:focus {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }
        
        .dropdown-divider {
            border-top: 1px solid #e9ecef;
            margin: 0.5rem 0;
        }
        
        /* Estilos para el contenido de la página */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-top: 1rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #212529;
            margin: 0;
        }
        
        .page-actions .btn {
            margin-left: 0.5rem;
        }
        
        /* Estilos para tarjetas */
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-top-left-radius: 0.5rem !important;
            border-top-right-radius: 0.5rem !important;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            color: #212529;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Estilos para botones */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover, 
        .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background-color: transparent;
        }
        
        .btn-outline-primary:hover, 
        .btn-outline-primary:focus {
            background-color: var(--primary-color);
            color: #fff;
        }
        
        /* Estilos para alertas */
        .alert {
            border: none;
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 1rem;
            font-size: 1.25rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        /* Estilos para pestañas */
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 1.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        
        .nav-tabs .nav-link:hover, 
        .nav-tabs .nav-link:focus {
            border-color: transparent;
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: transparent;
        }
        
        /* Estilos para tablas */
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }
        
        .table th, 
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* Estilos para formularios */
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #495057;
        }
        
        .form-control, 
        .form-select {
            padding: 0.6rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, 
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(229, 9, 20, 0.25);
        }
        
        .form-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* Estilos para el pie de página */
        .dashboard-footer {
            margin-top: 3rem;
            padding: 1.5rem 0;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 0.85rem;
            text-align: center;
        }
        
        /* Estilos responsivos */
        @media (max-width: 991.98px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .dashboard-sidebar.show {
                transform: translateX(0);
            }
            
            .dashboard-content, 
            .dashboard-topbar {
                margin-left: 0;
                left: 0;
            }
            
            .search-bar {
                width: 200px;
            }
        }
        
        @media (max-width: 767.98px) {
            .dashboard-content {
                padding: 1rem;
            }
            
            .dashboard-topbar {
                padding: 0 1rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 1.5rem;
            }
            
            .page-title {
                margin-bottom: 1rem;
            }
            
            .search-bar {
                display: none;
            }
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease forwards;
        }
        
        /* Utilidades */
        .text-primary { color: var(--primary-color) !important; }
        .bg-light { background-color: var(--bg-light) !important; }
        .rounded { border-radius: 0.5rem !important; }
        .shadow-sm { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important; }
        .shadow { box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important; }
        .shadow-lg { box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175) !important; }
        .w-100 { width: 100% !important; }
        .h-100 { height: 100% !important; }
        .m-0 { margin: 0 !important; }
        .mt-0 { margin-top: 0 !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .ml-0 { margin-left: 0 !important; }
        .mr-0 { margin-right: 0 !important; }
        .mx-0 { margin-left: 0 !important; margin-right: 0 !important; }
        .my-0 { margin-top: 0 !important; margin-bottom: 0 !important; }
        .m-1 { margin: 0.25rem !important; }
        .mt-1 { margin-top: 0.25rem !important; }
        .mb-1 { margin-bottom: 0.25rem !important; }
        .ml-1 { margin-left: 0.25rem !important; }
        .mr-1 { margin-right: 0.25rem !important; }
        .mx-1 { margin-left: 0.25rem !important; margin-right: 0.25rem !important; }
        .my-1 { margin-top: 0.25rem !important; margin-bottom: 0.25rem !important; }
        .m-2 { margin: 0.5rem !important; }
        .mt-2 { margin-top: 0.5rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .ml-2 { margin-left: 0.5rem !important; }
        .mr-2 { margin-right: 0.5rem !important; }
        .mx-2 { margin-left: 0.5rem !important; margin-right: 0.5rem !important; }
        .my-2 { margin-top: 0.5rem !important; margin-bottom: 0.5rem !important; }
        .m-3 { margin: 1rem !important; }
        .mt-3 { margin-top: 1rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .ml-3 { margin-left: 1rem !important; }
        .mr-3 { margin-right: 1rem !important; }
        .mx-3 { margin-left: 1rem !important; margin-right: 1rem !important; }
        .my-3 { margin-top: 1rem !important; margin-bottom: 1rem !important; }
        .m-4 { margin: 1.5rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .ml-4 { margin-left: 1.5rem !important; }
        .mr-4 { margin-right: 1.5rem !important; }
        .mx-4 { margin-left: 1.5rem !important; margin-right: 1.5rem !important; }
        .my-4 { margin-top: 1.5rem !important; margin-bottom: 1.5rem !important; }
        .m-5 { margin: 3rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .mb-5 { margin-bottom: 3rem !important; }
        .ml-5 { margin-left: 3rem !important; }
        .mr-5 { margin-right: 3rem !important; }
        .mx-5 { margin-left: 3rem !important; margin-right: 3rem !important; }
        .my-5 { margin-top: 3rem !important; margin-bottom: 3rem !important; }
        
        .p-0 { padding: 0 !important; }
        .pt-0 { padding-top: 0 !important; }
        .pb-0 { padding-bottom: 0 !important; }
        .pl-0 { padding-left: 0 !important; }
        .pr-0 { padding-right: 0 !important; }
        .px-0 { padding-left: 0 !important; padding-right: 0 !important; }
        .py-0 { padding-top: 0 !important; padding-bottom: 0 !important; }
        .p-1 { padding: 0.25rem !important; }
        .pt-1 { padding-top: 0.25rem !important; }
        .pb-1 { padding-bottom: 0.25rem !important; }
        .pl-1 { padding-left: 0.25rem !important; }
        .pr-1 { padding-right: 0.25rem !important; }
        .px-1 { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
        .py-1 { padding-top: 0.25rem !important; padding-bottom: 0.25rem !important; }
        .p-2 { padding: 0.5rem !important; }
        .pt-2 { padding-top: 0.5rem !important; }
        .pb-2 { padding-bottom: 0.5rem !important; }
        .pl-2 { padding-left: 0.5rem !important; }
        .pr-2 { padding-right: 0.5rem !important; }
        .px-2 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
        .py-2 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
        .p-3 { padding: 1rem !important; }
        .pt-3 { padding-top: 1rem !important; }
        .pb-3 { padding-bottom: 1rem !important; }
        .pl-3 { padding-left: 1rem !important; }
        .pr-3 { padding-right: 1rem !important; }
        .px-3 { padding-left: 1rem !important; padding-right: 1rem !important; }
        .py-3 { padding-top: 1rem !important; padding-bottom: 1rem !important; }
        .p-4 { padding: 1.5rem !important; }
        .pt-4 { padding-top: 1.5rem !important; }
        .pb-4 { padding-bottom: 1.5rem !important; }
        .pl-4 { padding-left: 1.5rem !important; }
        .pr-4 { padding-right: 1.5rem !important; }
        .px-4 { padding-left: 1.5rem !important; padding-right: 1.5rem !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .p-5 { padding: 3rem !important; }
        .pt-5 { padding-top: 3rem !important; }
        .pb-5 { padding-bottom: 3rem !important; }
        .pl-5 { padding-left: 3rem !important; }
        .pr-5 { padding-right: 3rem !important; }
        .px-5 { padding-left: 3rem !important; padding-right: 3rem !important; }
        .py-5 { padding-top: 3rem !important; padding-bottom: 3rem !important; }
        
        .text-center { text-align: center !important; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .text-justify { text-align: justify !important; }
        
        .d-none { display: none !important; }
        .d-inline { display: inline !important; }
        .d-inline-block { display: inline-block !important; }
        .d-block { display: block !important; }
        .d-flex { display: flex !important; }
        .d-inline-flex { display: inline-flex !important; }
        
        .flex-row { flex-direction: row !important; }
        .flex-column { flex-direction: column !important; }
        .flex-row-reverse { flex-direction: row-reverse !important; }
        .flex-column-reverse { flex-direction: column-reverse !important; }
        
        .justify-content-start { justify-content: flex-start !important; }
        .justify-content-end { justify-content: flex-end !important; }
        .justify-content-center { justify-content: center !important; }
        .justify-content-between { justify-content: space-between !important; }
        .justify-content-around { justify-content: space-around !important; }
        
        .align-items-start { align-items: flex-start !important; }
        .align-items-end { align-items: flex-end !important; }
        .align-items-center { align-items: center !important; }
        .align-items-baseline { align-items: baseline !important; }
        .align-items-stretch { align-items: stretch !important; }
        
        .flex-fill { flex: 1 1 auto !important; }
        .flex-grow-0 { flex-grow: 0 !important; }
        .flex-grow-1 { flex-grow: 1 !important; }
        .flex-shrink-0 { flex-shrink: 0 !important; }
        .flex-shrink-1 { flex-shrink: 1 !important; }
        
        .w-25 { width: 25% !important; }
        .w-50 { width: 50% !important; }
        .w-75 { width: 75% !important; }
        .w-100 { width: 100% !important; }
        .w-auto { width: auto !important; }
        
        .h-25 { height: 25% !important; }
        .h-50 { height: 50% !important; }
        .h-75 { height: 75% !important; }
        .h-100 { height: 100% !important; }
        .h-auto { height: auto !important; }
        
        .mw-100 { max-width: 100% !important; }
        .mh-100 { max-height: 100% !important; }
        
        .min-vw-100 { min-width: 100vw !important; }
        .min-vh-100 { min-height: 100vh !important; }
        
        .vw-100 { width: 100vw !important; }
        .vh-100 { height: 100vh !important; }
        
        .stretched-link::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1;
            pointer-events: auto;
            content: "";
            background-color: rgba(0, 0, 0, 0);
        }
        
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .visible { visibility: visible !important; }
        .invisible { visibility: hidden !important; }
        
        @media print {
            .d-print-none { display: none !important; }
            .d-print-inline { display: inline !important; }
            .d-print-inline-block { display: inline-block !important; }
            .d-print-block { display: block !important; }
            .d-print-flex { display: flex !important; }
            .d-print-inline-flex { display: inline-flex !important; }
        }
    </style>
</head>
<body>
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
