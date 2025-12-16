const baseUrl = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
const DEFAULT_POSTER = typeof getAssetUrl === 'function'
    ? getAssetUrl('/assets/img/default-poster.svg')
    : `${baseUrl || ''}/assets/img/default-poster.svg`;

// Estado de la aplicación
const appState = {
    currentUser: {
        id: 1,
        name: 'Administrador',
        email: 'admin@urrestv.com',
        role: 'admin',
        avatar: DEFAULT_POSTER
    },
    currentSection: 'dashboard',
    currentSubsection: '',
    isSidebarCollapsed: false,
    editingItemId: null, // ID del item que se está editando
    userMenuOpen: false,
    notifications: {
        items: [],
        unreadCount: 0,
        isOpen: false
    },
    subscriptionsData: {
        stats: {},
        plans: [],
        list: [],
        filtered: [],
        payments: [],
        filteredPayments: [],
        filters: {
            search: '',
            plan: '',
            status: '',
            period: 'all'
        }
    }
};

// Elementos del DOM
const elements = {
    sidebar: document.querySelector('.sidebar'),
    mainContent: document.querySelector('.main-content'),
    toggleSidebar: document.querySelector('.toggle-sidebar'),
    searchInput: document.querySelector('.search-bar input'),
    searchButton: document.querySelector('.search-bar button'),
    userMenu: document.querySelector('.user-menu'),
    notifications: document.querySelector('.notifications'),
    modal: document.querySelector('.modal'),
    closeModal: document.querySelector('.close-modal'),
    closeModalBtn: document.querySelector('.close-modal-btn'),
    modalTitle: document.querySelector('.modal-header h2'),
    contentForm: document.getElementById('contentForm'),
    // Agrega más elementos según sea necesario
};

// Funciones auxiliares para eventos
function handleSearch() {
    const searchInput = document.querySelector('#admin-search') || elements.searchInput;
    const query = searchInput?.value.trim() || '';
    if (query.length >= 2) {
        console.log('Buscando:', query);
        // Implementar búsqueda aquí si es necesario
        // Por ahora solo muestra en consola
    }
}

function toggleUserMenu(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const userMenu = elements.userMenu || document.querySelector('.user-menu');
    if (!userMenu) return;

    closeNotifications();

    const dropdown = ensureUserDropdown(userMenu);
    if (!dropdown) return;

    dropdown.innerHTML = renderUserDropdown();

    const shouldOpen = !userMenu.classList.contains('active');
    userMenu.classList.toggle('active', shouldOpen);
    dropdown.classList.toggle('show', shouldOpen);
    appState.userMenuOpen = shouldOpen;
}

function toggleNotifications(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const notifications = elements.notifications || document.querySelector('#notifications');
    if (!notifications) return;

    closeUserMenu();
    loadNotificationsData();

    const panel = ensureNotificationsDropdown(notifications);
    if (!panel) return;

    panel.innerHTML = renderNotificationsDropdown();

    const shouldOpen = !notifications.classList.contains('active');
    notifications.classList.toggle('active', shouldOpen);
    panel.classList.toggle('show', shouldOpen);
    appState.notifications.isOpen = shouldOpen;
}

// Inicialización de la aplicación
function init() {
    // Cargar datos del usuario actual
    loadUserData();
    loadNotificationsData();

    // Configurar event listeners
    setupEventListeners();

    // Cargar la sección actual
    loadSection();
}

// Configurar event listeners
function setupEventListeners() {
    // Menú móvil (hamburguesa)
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Función para abrir/cerrar el menú
    function toggleSidebar() {
        if (sidebar) {
            sidebar.classList.toggle('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
            // Prevenir scroll del body cuando el menú está abierto
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    }

    // Función para cerrar el menú
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
            document.body.style.overflow = '';
        }
    }

    // Event listener para el botón hamburguesa
    if (menuToggle) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Menú hamburguesa clickeado'); // Debug
            toggleSidebar();
        });
    } else {
        console.warn('Botón menuToggle no encontrado'); // Debug
    }

    if (!sidebar) {
        console.warn('Sidebar no encontrado'); // Debug
    }

    if (!sidebarOverlay) {
        console.warn('SidebarOverlay no encontrado'); // Debug
    }

    // Event listener para el overlay (cerrar al hacer clic fuera)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }

    // Cerrar menú con la tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });

    // Cerrar menú al hacer clic en un enlace (móviles)
    const navLinks = document.querySelectorAll('.admin-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            // En móviles, cerrar el menú después de hacer clic
            if (window.innerWidth <= 992) {
                if (sidebar) sidebar.classList.remove('active');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            }

            e.preventDefault();
            const section = link.getAttribute('href').substring(1);
            navigateTo(section);
        });
    });

    // Submenús
    const hasSubmenu = document.querySelectorAll('.admin-nav > ul > li > a + ul');
    hasSubmenu.forEach(menu => {
        const parentLink = menu.previousElementSibling;
        parentLink.addEventListener('click', (e) => {
            if (window.innerWidth > 1200) {
                e.preventDefault();
                const parentLi = parentLink.parentElement;
                parentLi.classList.toggle('active');
            }
        });
    });

    // Búsqueda
    const searchButton = document.querySelector('#search-btn') || elements.searchButton;
    const searchInput = document.querySelector('#admin-search') || elements.searchInput;

    if (searchButton) {
        searchButton.addEventListener('click', handleSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });
    }

    // Menú de usuario
    const userMenu = document.querySelector('.user-menu') || elements.userMenu;
    if (userMenu) {
        userMenu.addEventListener('click', toggleUserMenu);
    }

    // Notificaciones
    const notifications = document.querySelector('#notifications') || elements.notifications;
    if (notifications) {
        notifications.addEventListener('click', toggleNotifications);
    }

    // Modal de contenido
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-modal') || e.target.classList.contains('close-modal-btn')) {
            closeModal();
        }
    });

    // Modal de usuarios
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-modal-user') || e.target.classList.contains('close-modal-user-btn')) {
            closeUserModal();
        }
    });

    document.addEventListener('click', handleGlobalMenusClose);
    document.addEventListener('keydown', handleEscapeKeyClose);

    // Formulario de contenido
    const contentForm = document.getElementById('contentForm');
    if (contentForm) {
        contentForm.addEventListener('submit', handleContentSubmit);

        // Opciones mutuamente excluyentes para póster y backdrop
        setupMutuallyExclusiveOptions('poster_source', {
            'url': { input: 'poster_url', otherInput: 'poster_file', otherOption: 'poster_source_file' },
            'file': { input: 'poster_file', otherInput: 'poster_url', otherOption: 'poster_source_url' }
        });

        setupMutuallyExclusiveOptions('backdrop_source', {
            'url': { input: 'backdrop_url', otherInput: 'backdrop_file', otherOption: 'backdrop_source_file' },
            'file': { input: 'backdrop_file', otherInput: 'backdrop_url', otherOption: 'backdrop_source_url' }
        });

        // Manejo de opciones mutuamente excluyentes para video
        setupMutuallyExclusiveOptions('video_source', {
            'url': {
                container: 'videoUrlContainer',
                input: 'video_url',
                previewBtn: 'previewVideoBtn',
                otherOption: 'videoFileOption',
                otherInput: 'video_file'
            },
            'file': {
                container: 'videoFileContainer',
                input: 'video_file',
                otherOption: 'videoUrlOption',
                otherInput: 'video_url',
                previewBtn: 'previewVideoBtn'
            }
        });

        // Manejo de opciones mutuamente excluyentes para tráiler
        setupMutuallyExclusiveOptions('trailer_source', {
            'url': {
                container: 'trailerUrlContainer',
                input: 'trailer_url',
                otherOption: 'trailerFileOption',
                otherInput: 'trailer_file'
            },
            'file': {
                container: 'trailerFileContainer',
                input: 'trailer_file',
                otherOption: 'trailerUrlOption',
                otherInput: 'trailer_url'
            },
            'none': {
                otherOption: 'trailerUrlOption',
                otherInput: 'trailer_url',
                otherOption2: 'trailerFileOption',
                otherInput2: 'trailer_file'
            }
        });

        // Validación de archivos de video
        const videoFileInput = document.getElementById('video_file');
        if (videoFileInput) {
            videoFileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    validateFileInput(e.target, 'video_file_info', 2147483648); // 2GB
                }
            });
        }

        // Validación de archivos de póster
        const posterFileInput = document.getElementById('poster_file');
        if (posterFileInput) {
            posterFileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    validateImageInput(e.target, 'poster_file_info', 5242880); // 5MB
                }
            });
        }

        // Validación de archivos de backdrop
        const backdropFileInput = document.getElementById('backdrop_file');
        if (backdropFileInput) {
            backdropFileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    validateImageInput(e.target, 'backdrop_file_info', 6291456); // 6MB
                }
            });
        }

        // Validación de archivos de tráiler
        const trailerFileInput = document.getElementById('trailer_file');
        if (trailerFileInput) {
            trailerFileInput.addEventListener('change', function (e) {
                if (e.target.files && e.target.files[0]) {
                    validateFileInput(e.target, 'trailer_file_info', 524288000); // 500MB
                }
            });
        }

        // Búsqueda de torrents
        const searchTorrentBtn = document.getElementById('searchTorrentBtn');
        if (searchTorrentBtn) {
            searchTorrentBtn.addEventListener('click', handleSearchTorrent);
        }
        const retryTorrentBtn = document.getElementById('retryTorrentBtn');
        if (retryTorrentBtn) {
            retryTorrentBtn.addEventListener('click', handleInvalidTorrent);
        }

        // Previsualización de video
        const previewVideoBtn = document.getElementById('previewVideoBtn');
        if (previewVideoBtn) {
            previewVideoBtn.addEventListener('click', handlePreviewVideo);
        }

        const closePreviewBtn = document.getElementById('closePreviewBtn');
        if (closePreviewBtn) {
            closePreviewBtn.addEventListener('click', closeVideoPreview);
        }
    }

    // Formulario de usuarios
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', handleUserSubmit);
    }

    // Botón para agregar usuario
    document.addEventListener('click', (e) => {
        if (e.target.closest('#add-user-btn')) {
            e.preventDefault();
            showUserModal();
        }
    });

    // Cerrar modal de contenido al hacer clic fuera
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('contentModal');
        if (e.target === modal) {
            closeModal();
        }
    });

    // Cerrar modal de usuarios al hacer clic fuera
    window.addEventListener('click', (e) => {
        const userModal = document.getElementById('userModal');
        if (e.target === userModal) {
            closeUserModal();
        }
    });

    // Botones de acción en tablas
    document.addEventListener('click', (e) => {
        // Botón de ver
        if (e.target.closest('.btn-view')) {
            const row = e.target.closest('tr');
            const id = row.dataset.id;
            // Intentar obtener el tipo de la tabla o del contexto
            const table = row.closest('table');
            const tableType = table?.dataset.type;
            // Si la tabla tiene data-type, usarlo; si no, usar el contexto
            const type = tableType || appState.currentSubsection || appState.currentSection;
            viewItem(id, type);
        }

        // Botón de editar
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const row = e.target.closest('tr');
            const id = btn.dataset.id || row?.dataset.id;
            if (id) {
                // Intentar obtener el tipo de la tabla o del contexto
                const table = row?.closest('table');
                const tableType = table?.dataset.type;
                const type = tableType || appState.currentSubsection || appState.currentSection;
                editItem(id, type);
            }
        }

        // Botón de eliminar
        if (e.target.closest('.btn-delete')) {
            const btn = e.target.closest('.btn-delete');
            const row = e.target.closest('tr');
            const id = btn.dataset.id || row?.dataset.id;
            if (id) {
                const title = row?.querySelector('td:nth-child(2)')?.textContent || 'este elemento';
                // Intentar obtener el tipo de la tabla o del contexto
                const table = row?.closest('table');
                const tableType = table?.dataset.type;
                const type = tableType || appState.currentSubsection || appState.currentSection;
                deleteItem(id, title, type);
            }
        }

        // Botón de agregar nuevo
        if (e.target.closest('.btn-add-new')) {
            showContentModal();
        }

        // Botón de agregar usuario
        if (e.target.closest('#add-user-btn')) {
            showUserModal();
        }
    });

    // Responsive: colapsar/expandir menú lateral
    window.addEventListener('resize', handleResize);
    handleResize(); // Ejecutar al cargar
}

// Cargar datos del usuario actual
function loadUserData() {
    const userInfo = document.querySelector('.user-info');
    if (userInfo) {
        const avatar = userInfo.querySelector('.avatar');
        const name = userInfo.querySelector('span');

        if (avatar) {
            avatar.src = appState.currentUser.avatar || DEFAULT_POSTER;
            avatar.onerror = function () {
                this.src = DEFAULT_POSTER;
            };
        }
        if (name) name.textContent = appState.currentUser.name;
    }
}

// Navegación entre secciones
function navigateTo(section) {
    // Actualizar estado
    const parts = section.split('/');
    appState.currentSection = parts[0];
    appState.currentSubsection = parts[1] || '';

    // Actualizar URL sin recargar la página
    history.pushState({}, '', `#${section}`);

    // Cargar la sección
    loadSection();

    // Actualizar menú activo
    updateActiveMenu();
}

// Cargar la sección actual
async function loadSection() {
    const { currentSection, currentSubsection } = appState;
    let content = '';
    const mainContent = document.querySelector('.dashboard') || document.querySelector('.main-content > div');

    // Mostrar un indicador de carga
    if (mainContent) {
        mainContent.innerHTML = '<h1><i class="fas fa-spinner fa-spin"></i> Cargando...</h1>';
    }

    switch (currentSection) {
        case 'dashboard':
            try {
                const statsResponse = await apiRequest('/api/admin/stats.php');
                const stats = statsResponse.success && statsResponse.data ? statsResponse.data : null;

                // Obtener usuarios recientes
                const usersResponse = await apiRequest('/api/users/index.php');
                const allUsers = usersResponse.success && usersResponse.data ? usersResponse.data : [];
                const recentUsers = allUsers.slice(0, 5);

                // Obtener contenido reciente
                const contentResponse = await apiRequest('/api/content/popular.php?limit=4');
                const recentContent = contentResponse.success && contentResponse.data ? contentResponse.data : [];

                content = renderDashboard(stats, recentUsers, recentContent);

                // Actualizar gráficos después de renderizar
                setTimeout(async () => {
                    if (typeof updateDashboardCharts === 'function') {
                        try {
                            await updateDashboardCharts(stats);
                        } catch (error) {
                            console.error('Error al actualizar gráficos:', error);
                        }
                    }
                }, 300);
            } catch (error) {
                console.error('Error cargando dashboard:', error);
                content = renderDashboard(null, [], []);
            }
            break;

        case 'contenido':
            if (currentSubsection === 'peliculas') {
                try {
                    const response = await apiRequest('/api/content/popular.php?type=movie&limit=100');
                    const movies = response.success && response.data ? response.data : [];
                    content = renderContentList('peliculas', 'Películas', movies);
                } catch (error) {
                    content = renderError('No se pudieron cargar las películas: ' + error.message);
                    console.error(error);
                }
            } else if (currentSubsection === 'series') {
                try {
                    const response = await apiRequest('/api/content/popular.php?type=series&limit=100');
                    const series = response.success && response.data ? response.data : [];
                    content = renderContentList('series', 'Series', series);
                } catch (error) {
                    content = renderError('No se pudieron cargar las series: ' + error.message);
                    console.error(error);
                }
            } else if (currentSubsection === 'episodios') {
                content = renderEpisodesList();
            } else {
                content = `
                    <div class="content-header">
                        <h1>Gestión de Contenido</h1>
                        <p>Selecciona una categoría para comenzar</p>
                    </div>
                    <div class="content-options">
                        <a href="#contenido/peliculas" class="content-option">
                            <i class="fas fa-film"></i>
                            <span>Películas</span>
                        </a>
                        <a href="#contenido/series" class="content-option">
                            <i class="fas fa-tv"></i>
                            <span>Series</span>
                        </a>
                        <a href="#contenido/episodios" class="content-option">
                            <i class="fas fa-list-ol"></i>
                            <span>Episodios</span>
                        </a>
                    </div>
                `;
            }
            break;

        case 'usuarios':
            try {
                const response = await apiRequest('/api/users/index.php');
                const users = response.success && response.data ? response.data : [];
                content = renderUsersList(users);
            } catch (error) {
                content = renderError('No se pudieron cargar los usuarios: ' + error.message);
                console.error(error);
            }
            break;

        case 'suscripciones':
            try {
                const response = await apiRequest('/api/subscriptions/index.php');
                const payload = response && response.success && response.data ? response.data : {};
                appState.subscriptionsData = prepareSubscriptionData(payload);
                content = renderSubscriptions(appState.subscriptionsData);
            } catch (error) {
                console.error('Error cargando suscripciones:', error);
                content = renderError('No se pudieron cargar las suscripciones: ' + error.message);
            }
            break;

        case 'reportes':
            content = renderReports();
            break;

        case 'configuracion':
            content = renderSettings();
            // Configurar pestañas después de renderizar
            setTimeout(() => {
                setupSettingsTabs();
            }, 100);
            break;

        default:
            content = '<h1>Sección no encontrada</h1><p>La página solicitada no existe.</p>';
    }

    // Actualizar el contenido principal
    if (mainContent) {
        mainContent.innerHTML = content;
    } else {
        document.querySelector('.main-content').innerHTML = `<div class="dashboard">${content}</div>`;
    }

    // Inicializar componentes dinámicos
    initDynamicComponents();

    // Inicializar funcionalidades mejoradas
    if (typeof initEnhancedFeatures === 'function') {
        initEnhancedFeatures();
    }

    // Inicializar actualización de contenido si estamos en el dashboard
    if (currentSection === 'dashboard') {
        setTimeout(() => {
            if (typeof initContentRefresh === 'function') {
                initContentRefresh();
            }
        }, 100);
    }
}

// Actualizar menú activo
function updateActiveMenu() {
    // Remover clase activa de todos los enlaces
    document.querySelectorAll('.admin-nav a').forEach(link => {
        link.classList.remove('active');
    });

    // Marcar como activo el enlace correspondiente a la sección actual
    const { currentSection, currentSubsection } = appState;
    const selector = currentSubsection
        ? `.admin-nav a[href="#${currentSection}/${currentSubsection}"]`
        : `.admin-nav a[href="#${currentSection}"]`;

    const activeLink = document.querySelector(selector);
    if (activeLink) {
        activeLink.classList.add('active');
        // Asegurarse de que el elemento padre también tenga la clase active
        let parent = activeLink.parentElement;
        while (parent && !parent.classList.contains('admin-nav')) {
            if (parent.tagName === 'LI') {
                parent.classList.add('active');
            }
            parent = parent.parentElement;
        }
    }
}

// Renderizar el dashboard
function renderDashboard(stats = null, recentUsers = [], recentContent = []) {
    // Valores por defecto si no hay datos
    const defaultStats = {
        totalUsers: 0,
        newUsersThisMonth: 0,
        usersChangePercent: 0,
        totalMovies: 0,
        newMoviesThisMonth: 0,
        totalSeries: 0,
        newSeriesThisMonth: 0,
        newContentThisMonth: 0,
        monthlyRevenue: 0,
        revenueChangePercent: 0,
        totalViews: 0,
        totalViewsThisMonth: 0
    };

    const finalStats = stats || defaultStats;

    // Generar actividades recientes desde el contenido reciente
    const recentActivities = recentContent.slice(0, 4).map((item, index) => {
        const createdDate = item.created_at ? new Date(item.created_at) : new Date();
        const now = new Date();
        const diff = now - createdDate;
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const days = Math.floor(hours / 24);

        let timeText = 'Hace ' + hours + ' horas';
        if (days === 0 && hours === 0) timeText = 'Hace menos de una hora';
        if (days === 1) timeText = 'Ayer';
        if (days > 1 && days < 7) timeText = `Hace ${days} días`;
        if (days >= 7) timeText = createdDate.toLocaleDateString('es-ES');

        return {
            type: item.type === 'movie' ? 'success' : 'info',
            icon: item.type === 'movie' ? 'film' : 'tv',
            title: item.type === 'movie' ? 'Nueva película añadida' : 'Nueva serie añadida',
            description: item.title || 'Sin título',
            time: timeText
        };
    });

    // Formatear usuarios recientes
    const formattedRecentUsers = recentUsers.map(user => {
        const createdDate = user.created_at || user.registrationDate ? new Date(user.created_at || user.registrationDate) : new Date();
        const now = new Date();
        const diff = now - createdDate;
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));

        let registrationDate = 'Hoy';
        if (days === 1) registrationDate = 'Ayer';
        if (days > 1 && days < 7) registrationDate = `Hace ${days} días`;
        if (days >= 7) registrationDate = createdDate.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' });

        return {
            id: user.id,
            name: user.full_name || user.username,
            email: user.email,
            registrationDate: registrationDate,
            plan: user.role === 'premium' || user.role === 'admin' ? 'Premium' : 'Gratis',
            status: user.status === 'active' ? 'Activo' : 'Inactivo',
            avatar: user.avatar_url || DEFAULT_POSTER
        };
    });

    // Calcular cambio porcentual para usuarios
    const usersChangeClass = finalStats.usersChangePercent >= 0 ? 'positive' : 'negative';
    const usersChangeText = finalStats.usersChangePercent >= 0
        ? `+${finalStats.usersChangePercent}% este mes`
        : `${finalStats.usersChangePercent}% este mes`;

    // Calcular cambio porcentual para ingresos
    const revenueChangeClass = finalStats.revenueChangePercent >= 0 ? 'positive' : 'negative';
    const revenueChangeText = finalStats.revenueChangePercent >= 0
        ? `+${finalStats.revenueChangePercent}% este mes`
        : `${finalStats.revenueChangePercent}% este mes`;

    // Generar HTML del dashboard
    return `
        <h1>Panel de Control</h1>
        
        <!-- Resumen -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);">
                        <i class="fas fa-users" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Usuarios Totales</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">${finalStats.totalUsers.toLocaleString()}</p>
                        <p class="stat-change" style="color: ${finalStats.usersChangePercent >= 0 ? '#46d369' : '#e50914'}; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-arrow-${finalStats.usersChangePercent >= 0 ? 'up' : 'down'}"></i>
                            ${finalStats.newUsersThisMonth} este mes
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #e50914 0%, #b20710 100%); box-shadow: 0 4px 12px rgba(229, 9, 20, 0.4);">
                        <i class="fas fa-film" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Películas</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">${finalStats.totalMovies.toLocaleString()}</p>
                        <p class="stat-change" style="color: #46d369; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-arrow-up"></i>
                            +${finalStats.newMoviesThisMonth} este mes
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 4px 12px rgba(79, 172, 254, 0.4);">
                        <i class="fas fa-tv" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Series</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">${finalStats.totalSeries.toLocaleString()}</p>
                        <p class="stat-change" style="color: #46d369; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-arrow-up"></i>
                            +${finalStats.newSeriesThisMonth} este mes
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); box-shadow: 0 4px 12px rgba(67, 233, 123, 0.4);">
                        <i class="fas fa-dollar-sign" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Ingresos Mensuales</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">$${finalStats.monthlyRevenue.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                        <p class="stat-change" style="color: ${finalStats.revenueChangePercent >= 0 ? '#46d369' : '#e50914'}; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-arrow-${finalStats.revenueChangePercent >= 0 ? 'up' : 'down'}"></i>
                            ${revenueChangeText}
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); box-shadow: 0 4px 12px rgba(250, 112, 154, 0.4);">
                        <i class="fas fa-eye" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Vistas Hoy</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">${(finalStats.viewsToday || 0).toLocaleString()}</p>
                        <p class="stat-change" style="color: #b3b3b3; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-chart-line"></i>
                            ${(finalStats.viewsThisMonth || 0).toLocaleString()} este mes
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="background: #141414; border: 1px solid #2a2a2a; border-radius: 8px; padding: 1.75rem; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="stat-icon" style="width: 56px; height: 56px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); box-shadow: 0 4px 12px rgba(48, 207, 208, 0.4);">
                        <i class="fas fa-user-check" style="color: #ffffff; font-size: 1.5rem;"></i>
                    </div>
                    <div class="stat-info" style="flex: 1;">
                        <h3 style="color: #b3b3b3; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.5rem 0;">Usuarios Activos</h3>
                        <p class="stat-number" style="color: #ffffff; font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -1px;">${(finalStats.activeUsersWeek || 0).toLocaleString()}</p>
                        <p class="stat-change" style="color: #46d369; font-size: 0.85rem; margin: 0; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="fas fa-clock"></i>
                            Últimos 7 días
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actualización de novedades -->
        <div class="quick-actions" style="margin: 2rem 0; padding: 2.5rem; background: #141414; border-radius: 12px; border: 1px solid #2a2a2a; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h2 style="color: #ffffff; font-size: 1.75rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; margin: 0; letter-spacing: -0.5px;">
                    <i class="fas fa-sync-alt" style="color: #e50914;"></i>
                    Actualización Automática de Contenido
                </h2>
            </div>
            <p style="color: #b3b3b3; margin-bottom: 2rem; line-height: 1.7; font-size: 0.95rem;">
                Busca y actualiza automáticamente novedades de películas y series desde <strong style="color: #ffffff;">Trakt.tv</strong> y <strong style="color: #ffffff;">TVMaze</strong> (ambas gratuitas), incluyendo portadas, trailers y enlaces torrent. El sistema prioriza torrents con más seeds para mejor calidad.
                <br><br>
                <small style="color: #808080;">💡 Para mejores resultados, configura TRAKT_CLIENT_ID (gratis en <a href="https://trakt.tv/oauth/applications" target="_blank" style="color: #e50914;">trakt.tv/oauth/applications</a>)</small>
                <br>
                <small style="color: #808080;">🔗 Addon Torrentio oficial para Stremio: <code style="color: #e50914;">https://torrentio.strem.fun/lite/manifest.json</code></small>
                <br>
                <small style="color: #808080;">(Opcional) Proxy interno de respaldo: <code style="color: #e50914;">/api/torrentio/manifest.json</code></small>
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #ffffff; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Tipo de contenido:</label>
                    <select id="refresh-type" style="width: 100%; padding: 0.875rem 1rem; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; color: #ffffff; font-size: 0.95rem; transition: all 0.2s ease; outline: none;">
                        <option value="movie" style="background: #1a1a1a;">Películas</option>
                        <option value="tv" style="background: #1a1a1a;">Series</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #ffffff; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Límite:</label>
                    <input type="number" id="refresh-limit" value="30" min="1" max="100" style="width: 100%; padding: 0.875rem 1rem; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; color: #ffffff; font-size: 0.95rem; transition: all 0.2s ease; outline: none;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #ffffff; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Últimos días:</label>
                    <input type="number" id="refresh-days" value="7" min="0" max="365" style="width: 100%; padding: 0.875rem 1rem; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; color: #ffffff; font-size: 0.95rem; transition: all 0.2s ease; outline: none;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; color: #ffffff; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Mín. seeds:</label>
                    <input type="number" id="refresh-seeds" value="10" min="0" style="width: 100%; padding: 0.875rem 1rem; border: 1px solid #333; border-radius: 6px; background: #1a1a1a; color: #ffffff; font-size: 0.95rem; transition: all 0.2s ease; outline: none;">
                </div>
            </div>
            
            <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                <button id="btn-refresh-content" class="btn btn-primary" style="padding: 0.875rem 2.5rem; font-size: 1rem; background: #e50914; border: none; border-radius: 6px; color: #ffffff; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 10px rgba(229, 9, 20, 0.4); text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-sync-alt"></i> Actualizar Novedades
                </button>
                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; color: #b3b3b3; font-size: 0.9rem;">
                    <input type="checkbox" id="refresh-dry-run" style="width: 20px; height: 20px; cursor: pointer; accent-color: #e50914;">
                    <span>Modo prueba (no guarda cambios)</span>
                </label>
                <div id="refresh-status" style="margin-left: auto; font-weight: 600; color: #e50914; font-size: 0.95rem;"></div>
            </div>
            
            <div id="refresh-output" style="margin-top: 2rem; padding: 1.5rem; background: #0a0a0a; border-radius: 8px; display: none; max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.85rem; white-space: pre-wrap; color: #b3b3b3; border: 1px solid #2a2a2a; line-height: 1.6;"></div>
        </div>
        
        <!-- Gráficos -->
        <div class="charts-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
            <div class="chart-card" style="background: #141414; padding: 2rem; border-radius: 8px; border: 1px solid #2a2a2a; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <h3 style="margin-bottom: 1.5rem; color: #ffffff; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-chart-line" style="color: #e50914;"></i>
                    Tendencia de Vistas (7 días)
                </h3>
                <div style="height: 280px; position: relative;">
                    <canvas id="viewsTrendChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card" style="background: #141414; padding: 2rem; border-radius: 8px; border: 1px solid #2a2a2a; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <h3 style="margin-bottom: 1.5rem; color: #ffffff; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-users" style="color: #e50914;"></i>
                    Nuevos Usuarios (7 días)
                </h3>
                <div style="height: 280px; position: relative;">
                    <canvas id="usersTrendChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card" style="background: #141414; padding: 2rem; border-radius: 8px; border: 1px solid #2a2a2a; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <h3 style="margin-bottom: 1.5rem; color: #ffffff; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-chart-pie" style="color: #e50914;"></i>
                    Distribución de Usuarios
                </h3>
                <div style="height: 280px; position: relative;">
                    <canvas id="usersDistributionChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card" style="background: #141414; padding: 2rem; border-radius: 8px; border: 1px solid #2a2a2a; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);">
                <h3 style="margin-bottom: 1.5rem; color: #ffffff; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-fire" style="color: #e50914;"></i>
                    Contenido Más Visto (30 días)
                </h3>
                <div style="height: 280px; position: relative;">
                    <canvas id="topContentChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Actividades recientes -->
        <div class="recent-activity">
            <div class="section-header">
                <h2>Actividad Reciente</h2>
                <a href="#" class="view-all">Ver todo</a>
            </div>
            
            <div class="activity-list">
                ${recentActivities.length > 0 ? recentActivities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon ${activity.type}">
                            <i class="fas fa-${activity.icon}"></i>
                        </div>
                        <div class="activity-details">
                            <p><strong>${escapeHtml(activity.title)}:</strong> ${escapeHtml(activity.description)}</p>
                            <span class="activity-time">${activity.time}</span>
                        </div>
                    </div>
                `).join('') : '<div class="activity-item"><p>No hay actividades recientes</p></div>'}
            </div>
        </div>

        <!-- Últimos usuarios registrados -->
        <div class="recent-users">
            <div class="section-header">
                <h2>Últimos Usuarios</h2>
                <a href="#usuarios" class="view-all">Ver todos</a>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" data-type="users">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Registro</th>
                            <th>Plan</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${formattedRecentUsers.length > 0 ? formattedRecentUsers.map(user => `
                            <tr data-id="${user.id}">
                                <td>
                                    <div class="user-cell">
                                        <img src="${user.avatar || DEFAULT_POSTER}" alt="${user.name || 'Usuario'}" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>${escapeHtml(user.name)}</span>
                                    </div>
                                </td>
                                <td>${escapeHtml(user.email)}</td>
                                <td>${user.registrationDate}</td>
                                <td><span class="badge ${user.plan.toLowerCase() === 'premium' ? 'premium' : 'free'}">${user.plan}</span></td>
                                <td><span class="status ${user.status.toLowerCase()}">${user.status}</span></td>
                                <td class="actions">
                                    <button class="btn btn-sm btn-view" title="Ver" data-id="${user.id}"><i class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-edit" title="Editar" data-id="${user.id}"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-delete" title="Eliminar" data-id="${user.id}"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `).join('') : '<tr><td colspan="6" class="text-center">No hay usuarios recientes</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Renderizar lista de contenido (películas o series)
function renderContentList(type, title, items = []) {
    const isMovie = type === 'peliculas';
    const totalItems = items.length;
    const premiumItems = items.filter(i => i.is_premium).length;
    const featuredItems = items.filter(i => i.is_featured).length;

    return `
        <div class="content-header">
            <h1>${title}</h1>
            <div class="header-actions">
                <button class="btn btn-outline" id="export-${type}-btn">
                    <i class="fas fa-download"></i> Exportar
                </button>
                <button class="btn btn-primary btn-add-new">
                    <i class="fas fa-plus"></i> Agregar Nuevo
                </button>
            </div>
        </div>
        
        <!-- Estadísticas rápidas -->
        <div class="content-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div class="stat-card-mini" style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-${isMovie ? 'film' : 'tv'}" style="font-size: 1.5rem; color: #667eea;"></i>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 600;">${totalItems}</div>
                        <div style="font-size: 0.85rem; color: #999;">Total ${title}</div>
                    </div>
                </div>
            </div>
            <div class="stat-card-mini" style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-crown" style="font-size: 1.5rem; color: #f093fb;"></i>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 600;">${premiumItems}</div>
                        <div style="font-size: 0.85rem; color: #999;">Premium</div>
                    </div>
                </div>
            </div>
            <div class="stat-card-mini" style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-star" style="font-size: 1.5rem; color: #ffc107;"></i>
                    <div>
                        <div style="font-size: 1.5rem; font-weight: 600;">${featuredItems}</div>
                        <div style="font-size: 0.85rem; color: #999;">Destacados</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Barra de búsqueda -->
        <div class="content-search" style="margin-bottom: 1.5rem;">
            <div class="search-input-wrapper" style="position: relative; max-width: 500px;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #999;"></i>
                <input type="text" id="${type}-search" placeholder="Buscar por título, año, género..." class="form-control" style="padding-left: 2.5rem; padding-right: 2.5rem;">
                <button class="search-clear" id="${type}-search-clear" style="display: none; position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #999; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="data-table" data-type="${type}">
                <thead>
                    <tr>
                        <th>Portada</th>
                        <th>Título</th>
                        <th>Año</th>
                        <th>Géneros</th>
                        <th>${isMovie ? 'Duración' : 'Episodios'}</th>
                        <th>IMDb</th>
                        <th>Premium</th>
                        <th>Destacado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.length > 0 ? items.map(item => {
        const itemId = item.id;
        const itemTitle = item.title || 'Sin título';
        const itemYear = item.release_year || '';
        const contentType = isMovie ? 'movie' : 'series';

        return `
                        <tr data-id="${itemId}" data-title="${escapeHtml(itemTitle)}" data-year="${itemYear}" data-type="${contentType}">
                            <td>
                                <img src="${item.poster_url || DEFAULT_POSTER}" 
                                     alt="${itemTitle}" 
                                     class="thumbnail poster-clickable" 
                                     style="cursor: pointer; transition: transform 0.2s;"
                                     onerror="this.src='${DEFAULT_POSTER}'"
                                     onclick="handlePosterClick(${itemId}, '${escapeHtml(itemTitle)}', ${itemYear}, '${contentType}')"
                                     onmouseover="this.style.transform='scale(1.1)'"
                                     onmouseout="this.style.transform='scale(1)'"
                                     title="Clic para buscar torrents">
                            </td>
                            <td>${itemTitle}</td>
                            <td>${itemYear || 'N/A'}</td>
                            <td>${item.genres ? (Array.isArray(item.genres) ? item.genres.join(', ') : item.genres) : 'N/A'}</td>
                            <td>${isMovie ? `${item.duration || 0} min` : (item.episodes || 'N/A')}</td>
                            <td>
                                <span class="imdb-info" data-id="${itemId}" style="color: #f5c518; cursor: pointer;" onclick="loadIMDbInfo(${itemId}, '${escapeHtml(itemTitle)}', ${itemYear}, '${contentType}')">
                                    <i class="fab fa-imdb"></i> Cargar
                                </span>
                            </td>
                            <td>${item.is_premium ? '<span class="badge premium">Sí</span>' : '<span class="badge free">No</span>'}</td>
                            <td>${item.is_featured ? '<span class="badge premium">Sí</span>' : '<span class="badge free">No</span>'}</td>
                            <td class="actions">
                                <button class="btn btn-sm btn-view" title="Ver" data-id="${itemId}"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-edit" title="Editar" data-id="${itemId}"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-delete" title="Eliminar" data-id="${itemId}"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `;
    }).join('') : '<tr><td colspan="9" class="text-center">No hay contenido para mostrar.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;
}

// Renderizar lista de episodios
function renderEpisodesList() {
    // Datos de ejemplo para episodios
    const episodes = [
        { id: 1, series: 'Breaking Bad', season: 1, episode: 1, title: 'Piloto', duration: '58 min', views: 15000 },
        { id: 2, series: 'Breaking Bad', season: 1, episode: 2, title: 'El gato está en la bolsa...', duration: '48 min', views: 12500 },
        { id: 3, series: 'Stranger Things', season: 1, episode: 1, title: 'Capítulo uno: La desaparición de Will Byers', duration: '52 min', views: 24500 },
        { id: 4, series: 'Stranger Things', season: 1, episode: 2, title: 'Capítulo dos: La loca de la calle Maple', duration: '55 min', views: 23100 }
    ];

    return `
        <div class="content-header">
            <h1>Episodios</h1>
            <button class="btn btn-primary btn-add-new" data-type="episode">
                <i class="fas fa-plus"></i> Agregar Nuevo
            </button>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label for="filter-series">Serie:</label>
                <select id="filter-series" class="form-control">
                    <option value="">Todas las series</option>
                    <option value="breaking-bad">Breaking Bad</option>
                    <option value="stranger-things">Stranger Things</option>
                    <option value="the-mandalorian">The Mandalorian</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-season">Temporada:</label>
                <select id="filter-season" class="form-control">
                    <option value="">Todas las temporadas</option>
                    <option value="1">Temporada 1</option>
                    <option value="2">Temporada 2</option>
                    <option value="3">Temporada 3</option>
                </select>
            </div>
            
            <button class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="data-table" data-type="episodes">
                <thead>
                    <tr>
                        <th>Serie</th>
                        <th>Temporada</th>
                        <th>Episodio</th>
                        <th>Título</th>
                        <th>Duración</th>
                        <th>Vistas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${episodes.map(ep => `
                        <tr data-id="${ep.id}">
                            <td>${ep.series}</td>
                            <td>${ep.season}</td>
                            <td>${ep.episode}</td>
                            <td>${ep.title}</td>
                            <td>${ep.duration}</td>
                            <td>${ep.views.toLocaleString()}</td>
                            <td class="actions">
                                <button class="btn btn-sm btn-view" title="Ver"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-edit" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <button class="btn btn-sm" disabled><i class="fas fa-chevron-left"></i> Anterior</button>
            <span>Página 1 de 3</span>
            <button class="btn btn-sm">Siguiente <i class="fas fa-chevron-right"></i></button>
        </div>
    `;
}

// Renderizar lista de usuarios
function renderUsersList(users = []) {
    // Calcular estadísticas
    const totalUsers = users.length;
    const activeUsers = users.filter(u => u.status === 'active').length;
    const premiumUsers = users.filter(u => u.role === 'premium' || u.role === 'admin').length;
    const newUsersToday = users.filter(u => {
        const created = new Date(u.created_at);
        const today = new Date();
        return created.toDateString() === today.toDateString();
    }).length;

    return `
        <div class="content-header">
            <h1>Gestión de Usuarios</h1>
            <div class="header-actions">
                <button class="btn btn-outline" id="export-users-btn">
                    <i class="fas fa-download"></i> Exportar
                </button>
            <button class="btn btn-primary" id="add-user-btn">
                <i class="fas fa-user-plus"></i> Agregar Usuario
            </button>
            </div>
        </div>
        
        <!-- Estadísticas de usuarios -->
        <div class="user-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>${totalUsers.toLocaleString()}</h3>
                    <p>Usuarios Totales</p>
                    <span class="trend positive">
                        <i class="fas fa-arrow-up"></i> +${newUsersToday} hoy
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3>${activeUsers}</h3>
                    <p>Usuarios Activos</p>
                    <span class="trend positive">
                        <i class="fas fa-check-circle"></i> ${Math.round((activeUsers / totalUsers) * 100) || 0}%
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-info">
                    <h3>${premiumUsers}</h3>
                    <p>Usuarios Premium</p>
                    <span class="trend positive">
                        <i class="fas fa-star"></i> ${Math.round((premiumUsers / totalUsers) * 100) || 0}%
                    </span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>${totalUsers - activeUsers}</h3>
                    <p>Usuarios Inactivos</p>
                    <span class="trend neutral">
                        <i class="fas fa-info-circle"></i> ${Math.round(((totalUsers - activeUsers) / totalUsers) * 100) || 0}%
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Filtros mejorados -->
        <div class="user-filters">
            <div class="filter-row">
            <div class="filter-group">
                    <label for="user-search">Buscar Usuario</label>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="user-search" placeholder="Nombre, email, username..." class="form-control">
                        <button class="search-clear" id="user-search-clear" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="filter-group">
                    <label for="filter-status">Estado</label>
                <select id="filter-status" class="form-control">
                        <option value="">Todos los estados</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                    <option value="suspended">Suspendidos</option>
                </select>
            </div>
            
            <div class="filter-group">
                    <label for="filter-role">Rol/Plan</label>
                    <select id="filter-role" class="form-control">
                        <option value="">Todos los roles</option>
                        <option value="admin">Administrador</option>
                    <option value="premium">Premium</option>
                        <option value="user">Usuario</option>
                </select>
            </div>
            
                <div class="filter-group">
                    <label for="filter-sort">Ordenar por</label>
                    <select id="filter-sort" class="form-control">
                        <option value="newest">Más recientes</option>
                        <option value="oldest">Más antiguos</option>
                        <option value="name">Nombre A-Z</option>
                        <option value="email">Email A-Z</option>
                        <option value="last-login">Último acceso</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button class="btn btn-primary" id="apply-filters-btn" style="margin-top: 1.75rem;">
                        <i class="fas fa-filter"></i> Aplicar Filtros
            </button>
                </div>
            </div>
        </div>
        
        <!-- Tabla de usuarios mejorada -->
        <div class="user-table-container">
            <div class="table-header">
                <h3>Lista de Usuarios</h3>
                <div class="table-actions">
                    <span class="user-count">${totalUsers} usuario${totalUsers !== 1 ? 's' : ''}</span>
                    <button class="btn-icon" id="refresh-users-btn" title="Actualizar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
        <div class="table-responsive">
            <table class="data-table" data-type="users">
                <thead>
                    <tr>
                            <th>
                                <input type="checkbox" id="select-all-users" title="Seleccionar todos">
                            </th>
                        <th>Usuario</th>
                        <th>Email</th>
                            <th>Rol</th>
                            <th>Contraseña</th>
                        <th>Registro</th>
                        <th>Último acceso</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                        ${users.length > 0 ? users.map((user, index) => {
        const createdDate = user.created_at ? new Date(user.created_at) : null;
        const lastLoginDate = user.last_login ? new Date(user.last_login) : null;
        const isActive = user.status === 'active';
        const isPremium = user.role === 'premium' || user.role === 'admin';

        return `
                                <tr data-id="${user.id}" class="${!isActive ? 'inactive-row' : ''}">
                                    <td>
                                        <input type="checkbox" class="user-checkbox" data-id="${user.id}">
                                    </td>
                            <td>
                                <div class="user-cell">
                                            <img 
                                                src="${user.avatar_url || DEFAULT_POSTER}" 
                                                alt="${user.full_name || user.username || 'Usuario'}" 
                                                class="user-avatar"
                                                onerror="this.src='${DEFAULT_POSTER}'"
                                            >
                                            <div class="user-info">
                                                <strong>${escapeHtml(user.full_name || user.username)}</strong>
                                                <span class="username">@${escapeHtml(user.username)}</span>
                                            </div>
                                </div>
                            </td>
                                    <td>
                                        <div class="email-cell">
                                            <i class="fas fa-envelope"></i>
                                            <span>${escapeHtml(user.email)}</span>
                                        </div>
                                    </td>
                                    <td>
                                        ${user.role === 'admin' ?
                '<span class="badge admin"><i class="fas fa-shield-alt"></i> Admin</span>' :
                isPremium ?
                    '<span class="badge premium"><i class="fas fa-crown"></i> Premium</span>' :
                    '<span class="badge free"><i class="fas fa-user"></i> Usuario</span>'
            }
                                    </td>
                                    <td>
                                        <div class="password-cell">
                                            <button class="btn-password-toggle" data-id="${user.id}" data-hash="${user.password_hash || ''}" title="Ver hash de contraseña">
                                                <i class="fas fa-eye"></i>
                                                <span class="password-text" style="display: none;">${user.password_hash ? escapeHtml(user.password_hash) : 'N/A'}</span>
                                            </button>
                                            <button class="btn-reset-password" data-id="${user.id}" title="Resetear contraseña">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <i class="fas fa-calendar"></i>
                                            <span>${createdDate ? formatDate(createdDate.toISOString()) : 'N/A'}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <i class="fas fa-clock"></i>
                                            <span>${lastLoginDate ? formatDateTime(lastLoginDate.toISOString()) : 'Nunca'}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status ${user.status || 'active'}">
                                            <i class="fas fa-circle"></i>
                                            ${formatStatus(user.status || 'active')}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" data-action="view" data-id="${user.id}" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon" data-action="edit" data-id="${user.id}" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon danger" data-action="delete" data-id="${user.id}" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                            </td>
                        </tr>
                            `;
    }).join('') : `
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <div class="empty-content">
                                        <i class="fas fa-users"></i>
                                        <h3>No hay usuarios</h3>
                                        <p>No se encontraron usuarios para mostrar.</p>
                                        <button class="btn btn-primary" id="add-user-empty-btn">
                                            <i class="fas fa-user-plus"></i> Agregar Primer Usuario
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `}
                </tbody>
            </table>
            </div>
        </div>
        
        <!-- Paginación mejorada -->
        <div class="pagination-container">
            <div class="pagination-info">
                <span>Mostrando <strong>1-${Math.min(20, totalUsers)}</strong> de <strong>${totalUsers}</strong> usuarios</span>
            </div>
        <div class="pagination">
                <button class="btn btn-outline" id="prev-page-btn" disabled>
                    <i class="fas fa-chevron-left"></i> Anterior
                </button>
                <div class="page-numbers">
                    <button class="page-btn active">1</button>
                    ${totalUsers > 20 ? '<button class="page-btn">2</button>' : ''}
                    ${totalUsers > 40 ? '<button class="page-btn">3</button>' : ''}
                </div>
                <button class="btn btn-outline" id="next-page-btn" ${totalUsers <= 20 ? 'disabled' : ''}>
                    Siguiente <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;
}

// Función helper para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Función helper para formatear fecha
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Función helper para formatear fecha y hora
function formatDateTime(dateString) {
    if (!dateString) return 'Nunca';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (days === 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 7) return `Hace ${days} días`;

    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Función helper para formatear estado
function formatStatus(status) {
    const statusMap = {
        'active': 'Activo',
        'inactive': 'Inactivo',
        'suspended': 'Suspendido',
        'banned': 'Baneado'
    };
    return statusMap[status] || status;
}

function formatRelativeTime(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return '';
    const diff = Date.now() - date.getTime();
    const minutes = Math.max(0, Math.floor(diff / (1000 * 60)));
    if (minutes < 1) return 'Justo ahora';
    if (minutes < 60) return `Hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `Hace ${hours} h`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `Hace ${days} d`;
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' });
}

function formatNumber(value = 0) {
    const number = Number(value) || 0;
    return number.toLocaleString('es-ES');
}

function formatCurrency(value = 0, currency = 'USD') {
    const amount = Number(value) || 0;
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2
    }).format(amount);
}

function formatDateShort(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatSubscriptionStatus(status) {
    const statusMap = {
        'active': 'Activa',
        'pending': 'Pendiente',
        'cancelled': 'Cancelada',
        'expired': 'Expirada',
        'paid': 'Pagado',
        'failed': 'Fallido',
        'refunded': 'Reembolsado'
    };
    return statusMap[status] || status;
}

function buildSubscriptionRows(subscriptions = []) {
    if (!subscriptions.length) {
        return `
            <tr>
                <td colspan="7" class="empty-state">
                    <div class="empty-content">
                        <i class="fas fa-users-slash"></i>
                        <h3>No hay suscripciones registradas</h3>
                        <p>Cuando agregues suscriptores aparecerán aquí.</p>
                        <button class="btn btn-primary" id="add-first-subscription-btn">
                            <i class="fas fa-user-plus"></i> Crear suscripción
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    return subscriptions.map(sub => `
        <tr data-id="${sub.id}" class="${sub.status !== 'active' ? 'inactive-row' : ''}">
            <td>
                <div class="user-cell">
                    <img 
                        src="${sub.avatar_url || DEFAULT_POSTER}" 
                        alt="${escapeHtml(sub.full_name || sub.username || 'Usuario')}" 
                        class="user-avatar"
                        onerror="this.src='${DEFAULT_POSTER}'"
                    >
                    <div class="user-info">
                        <strong>${escapeHtml(sub.full_name || sub.username || 'Usuario')}</strong>
                        <span class="username">@${escapeHtml(sub.username || '')}</span>
                    </div>
                </div>
            </td>
            <td>${escapeHtml(sub.email || 'Sin email')}</td>
            <td>${renderPlanBadge(sub.plan_name || '')}</td>
            <td>${formatDateShort(sub.start_date)}</td>
            <td>${formatDateShort(sub.next_payment_date)}</td>
            <td>
                <span class="status ${sub.status}">
                    <i class="fas fa-circle"></i> ${formatSubscriptionStatus(sub.status)}
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn-icon" data-subscription-action="view" data-id="${sub.id}" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon" data-subscription-action="edit" data-id="${sub.id}" title="Editar suscripción">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon danger" data-subscription-action="cancel" data-id="${sub.id}" title="Cancelar suscripción">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function buildPaymentsRows(payments = []) {
    if (!payments.length) {
        return `
            <tr>
                <td colspan="8" class="empty-state">
                    <div class="empty-content">
                        <i class="fas fa-file-invoice"></i>
                        <h3>Sin movimientos de facturación</h3>
                        <p>Aquí aparecerán los pagos registrados.</p>
                    </div>
                </td>
            </tr>
        `;
    }

    return payments.map(payment => `
        <tr data-id="${payment.id}">
            <td><strong>#INV-${String(payment.id).padStart(5, '0')}</strong></td>
            <td>${escapeHtml(payment.full_name || payment.username || 'Usuario')}</td>
            <td>${formatDateShort(payment.payment_date)}</td>
            <td>${escapeHtml(payment.plan_name || 'N/D')}</td>
            <td><strong>${formatCurrency(payment.amount, payment.currency || 'USD')}</strong></td>
            <td><i class="fas fa-credit-card"></i> ${escapeHtml(payment.payment_method || 'Manual')}</td>
            <td>
                <span class="status ${payment.status}">
                    ${formatSubscriptionStatus(payment.status)}
                </span>
            </td>
            <td>
                <button class="btn-link" data-payment-id="${payment.id}" title="Ver comprobante">
                    <i class="fas fa-file-invoice"></i> Ver
                </button>
            </td>
        </tr>
    `).join('');
}

function renderPlanBadge(planName = '') {
    const normalized = (planName || '').toLowerCase();
    let badgeClass = 'free';
    if (normalized.includes('premium')) {
        badgeClass = 'premium';
    } else if (normalized.includes('fam')) {
        badgeClass = 'family';
    } else if (normalized.includes('bas')) {
        badgeClass = 'basic';
    }

    return `<span class="badge ${badgeClass}">${escapeHtml(planName || 'Plan')}</span>`;
}

function prepareSubscriptionData(payload = {}) {
    const subscriptions = payload.subscriptions || [];
    const payments = payload.payments || [];

    return {
        stats: payload.stats || {},
        plans: payload.plans || [],
        list: subscriptions,
        filtered: subscriptions,
        payments,
        filteredPayments: payments,
        filters: {
            search: '',
            plan: '',
            status: '',
            period: 'all'
        }
    };
}

function ensureUserDropdown(userMenu) {
    if (!userMenu) return null;
    let dropdown = userMenu.querySelector('.user-dropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'user-dropdown';
        dropdown.addEventListener('click', (event) => {
            event.stopPropagation();
        });
        dropdown.addEventListener('click', handleUserDropdownAction);
        userMenu.appendChild(dropdown);
    }
    return dropdown;
}

function renderUserDropdown() {
    const name = appState.currentUser?.name || 'Administrador';
    const email = appState.currentUser?.email || 'admin@streamingplatform.com';
    return `
        <div class="user-summary">
            <strong>${escapeHtml(name)}</strong>
            <span>${escapeHtml(email)}</span>
        </div>
        <div class="user-actions">
            <button class="user-action" data-user-action="profile">
                <i class="fas fa-user"></i> Ver perfil
            </button>
            <button class="user-action" data-user-action="settings">
                <i class="fas fa-sliders-h"></i> Configuración
            </button>
            <button class="user-action" data-user-action="theme">
                <i class="fas fa-moon"></i> Cambiar tema
            </button>
        </div>
        <div class="user-actions">
            <button class="user-action danger" data-user-action="logout">
                <i class="fas fa-sign-out-alt"></i> Cerrar sesión
            </button>
        </div>
    `;
}

function handleUserDropdownAction(event) {
    const actionBtn = event.target.closest('[data-user-action]');
    if (!actionBtn) return;
    event.preventDefault();
    event.stopPropagation();

    const action = actionBtn.dataset.userAction;
    switch (action) {
        case 'profile':
            window.location.href = '/dashboard/?tab=profile';
            break;
        case 'settings':
            navigateTo('configuracion');
            closeUserMenu();
            break;
        case 'theme':
            document.body.classList.toggle('theme-alt');
            showNotification('Tema actualizado', 'success');
            break;
        case 'logout':
            window.location.href = '/api/auth/logout.php';
            break;
        default:
            break;
    }
}

function ensureNotificationsDropdown(container) {
    if (!container) return null;
    let dropdown = container.querySelector('.notifications-dropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'notifications-dropdown';
        dropdown.addEventListener('click', handleNotificationDropdownClick);
        container.appendChild(dropdown);
    }
    return dropdown;
}

function renderNotificationsDropdown() {
    const notifications = appState.notifications.items || [];
    if (!notifications.length) {
        return `
            <div class="notifications-header">
                <strong>Notificaciones</strong>
                <button class="btn-link" data-notification-action="refresh">Actualizar</button>
            </div>
            <div class="notifications-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No tienes notificaciones pendientes</p>
            </div>
        `;
    }

    const list = notifications.map(item => `
        <div class="notification-item ${item.read ? 'read' : 'unread'}" data-notification-id="${item.id}">
            <div class="notification-icon">
                <i class="fas ${item.icon || 'fa-info-circle'}"></i>
            </div>
            <div class="notification-body">
                <strong>${escapeHtml(item.title)}</strong>
                <p>${escapeHtml(item.message)}</p>
                <small>${item.timeAgo || formatRelativeTime(item.timestamp)}</small>
            </div>
            ${item.read ? '' : `<button class="btn-link" data-notification-action="mark-read" data-id="${item.id}">Marcar leído</button>`}
        </div>
    `).join('');

    return `
        <div class="notifications-header">
            <strong>Notificaciones</strong>
            <button class="btn-link" data-notification-action="mark-all">Marcar todo</button>
        </div>
        <div class="notifications-list">
            ${list}
        </div>
        <div class="notifications-footer">
            <button class="btn btn-outline" data-notification-action="view-all">
                Ver todo
            </button>
            <button class="btn btn-link danger" data-notification-action="clear-all">
                Limpiar
            </button>
        </div>
    `;
}

function handleNotificationDropdownClick(event) {
    event.stopPropagation();
    const actionBtn = event.target.closest('[data-notification-action]');
    if (actionBtn) {
        event.preventDefault();
        const action = actionBtn.dataset.notificationAction;
        const id = actionBtn.dataset.id;
        switch (action) {
            case 'mark-read':
                markNotificationAsRead(id);
                break;
            case 'mark-all':
                markAllNotificationsAsRead();
                break;
            case 'clear-all':
                clearNotifications();
                break;
            case 'refresh':
                loadNotificationsData(true);
                refreshNotificationsDropdown();
                break;
            case 'view-all':
                window.location.href = '/dashboard/?tab=overview';
                break;
            default:
                break;
        }
        return;
    }

    const item = event.target.closest('.notification-item');
    if (item) {
        const id = item.dataset.notificationId;
        markNotificationAsRead(id);
        showNotification('Notificación abierta.', 'info');
    }
}

function markNotificationAsRead(id) {
    if (!id) return;
    const items = appState.notifications.items || [];
    const notification = items.find(item => String(item.id) === String(id));
    if (notification && !notification.read) {
        notification.read = true;
        updateNotificationBadge();
        refreshNotificationsDropdown();
    }
}

function markAllNotificationsAsRead() {
    const items = appState.notifications.items || [];
    items.forEach(item => {
        item.read = true;
    });
    updateNotificationBadge();
    refreshNotificationsDropdown();
}

function clearNotifications() {
    appState.notifications.items = [];
    updateNotificationBadge();
    refreshNotificationsDropdown();
}

function refreshNotificationsDropdown() {
    if (!appState.notifications.isOpen) return;
    const notifications = elements.notifications || document.querySelector('#notifications');
    const panel = notifications?.querySelector('.notifications-dropdown');
    if (panel) {
        panel.innerHTML = renderNotificationsDropdown();
    }
}

function updateNotificationBadge() {
    const notifications = elements.notifications || document.querySelector('#notifications');
    const badge = notifications?.querySelector('.badge');
    const unread = (appState.notifications.items || []).filter(item => !item.read).length;
    appState.notifications.unreadCount = unread;
    if (badge) {
        badge.textContent = unread;
        badge.style.display = unread > 0 ? 'inline-flex' : 'none';
    }
}

function loadNotificationsData(force = false) {
    if (!force && appState.notifications.items.length) {
        updateNotificationBadge();
        return;
    }

    const now = Date.now();
    appState.notifications.items = [
        {
            id: 1,
            title: 'Nueva suscripción Premium',
            message: 'Sofía Lara se ha unido al plan Premium.',
            icon: 'fa-crown',
            timestamp: new Date(now - 2 * 60 * 1000).toISOString(),
            read: false
        },
        {
            id: 2,
            title: 'Contenido pendiente de revisión',
            message: '3 películas necesitan aprobación.',
            icon: 'fa-film',
            timestamp: new Date(now - 30 * 60 * 1000).toISOString(),
            read: false
        },
        {
            id: 3,
            title: 'Servidor estable',
            message: 'No se detectaron incidencias en las últimas 24h.',
            icon: 'fa-check-circle',
            timestamp: new Date(now - 3 * 60 * 60 * 1000).toISOString(),
            read: true
        }
    ];

    updateNotificationBadge();
}

function handleGlobalMenusClose(event) {
    const insideUserMenu = event.target.closest('.user-menu');
    if (!insideUserMenu) {
        closeUserMenu();
    }

    const insideNotifications = event.target.closest('#notifications') || event.target.closest('.notifications-dropdown');
    if (!insideNotifications) {
        closeNotifications();
    }
}

function handleEscapeKeyClose(event) {
    if (event.key === 'Escape') {
        closeUserMenu();
        closeNotifications();
    }
}

function closeUserMenu() {
    const userMenu = elements.userMenu || document.querySelector('.user-menu');
    if (!userMenu) return;
    userMenu.classList.remove('active');
    const dropdown = userMenu.querySelector('.user-dropdown');
    if (dropdown) {
        dropdown.classList.remove('show');
    }
    appState.userMenuOpen = false;
}

function closeNotifications() {
    const notifications = elements.notifications || document.querySelector('#notifications');
    if (!notifications) return;
    notifications.classList.remove('active');
    const panel = notifications.querySelector('.notifications-dropdown');
    if (panel) {
        panel.classList.remove('show');
    }
    appState.notifications.isOpen = false;
}

// Renderizar suscripciones
function renderSubscriptions(data = {}) {
    const {
        stats = {},
        plans = [],
        filtered = [],
        filteredPayments = [],
        filters = {}
    } = data;

    const planOptions = plans.map(plan => `
        <option value="${plan.id}" ${Number(filters.plan) === Number(plan.id) ? 'selected' : ''}>
            ${escapeHtml(plan.name)} (${plan.subscriber_count || 0})
        </option>
    `).join('');

    const minPrice = plans.length ? Math.min(...plans.map(plan => parseFloat(plan.price))) : null;

    const planCards = plans.length
        ? plans.map(plan => `
            <div class="plan-card ${plan.is_active ? '' : 'disabled'} ${minPrice !== null && parseFloat(plan.price) === minPrice ? 'featured' : ''}">
                ${minPrice !== null && parseFloat(plan.price) === minPrice ? '<div class="plan-badge">Recomendado</div>' : ''}
                <div class="plan-header">
                    <h3>${escapeHtml(plan.name)}</h3>
                    <div class="plan-price">
                        <span class="amount">${formatCurrency(plan.price)}</span>
                        <span class="period">/${plan.billing_cycle === 'yearly' ? 'año' : 'mes'}</span>
                    </div>
                    <p class="plan-description">${escapeHtml(plan.description || 'Plan personalizado')}</p>
                </div>
                <div class="plan-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Calidad ${escapeHtml(plan.video_quality || 'HD')}</li>
                        <li><i class="fas fa-check"></i> ${plan.max_screens || 1} pantallas simultáneas</li>
                        <li><i class="fas fa-check"></i> Descargas ${plan.download_limit ? `${plan.download_limit}` : 'limitadas'}</li>
                        <li>
                            <i class="fas ${plan.ads_enabled ? 'fa-ad' : 'fa-check'}"></i> 
                            ${plan.ads_enabled ? 'Incluye anuncios' : 'Sin anuncios'}
                        </li>
                    </ul>
                </div>
                <div class="plan-stats">
                    <span><strong>${plan.subscriber_count || 0}</strong> usuarios activos</span>
                </div>
                <div class="plan-actions">
                    <button class="btn btn-outline" data-plan-action="edit" data-id="${plan.id}">
                        <i class="fas fa-edit"></i> Editar plan
                    </button>
                </div>
            </div>
        `).join('')
        : `
            <div class="empty-state">
                <div class="empty-content">
                    <i class="fas fa-layer-group"></i>
                    <h3>No hay planes configurados</h3>
                    <p>Crea un plan para comenzar a vender suscripciones.</p>
                    <button class="btn btn-primary" id="create-first-plan-btn">
                        <i class="fas fa-plus"></i> Crear plan
                    </button>
                </div>
            </div>
        `;

    const subscriptionRows = buildSubscriptionRows(filtered);
    const paymentsRows = buildPaymentsRows(filteredPayments);

    return `
        <div class="subscription-view">
            <div class="content-header">
                <div>
                    <h1>Suscripciones</h1>
                    <p class="text-muted">Gestiona planes, suscriptores y cobros</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-outline" id="export-subscriptions">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                    <button class="btn btn-primary" id="add-subscription-btn">
                        <i class="fas fa-plus"></i> Nueva Suscripción
                    </button>
                </div>
            </div>
            
            <div class="subscription-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>${formatNumber(stats.totalSubscribers)}</h3>
                        <p>Suscriptores totales</p>
                        <span class="trend positive">
                            <i class="fas fa-chart-line"></i> ${stats.subscriberGrowth || '+0%'} este mes
                        </span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>${formatNumber(stats.activeSubscriptions)}</h3>
                        <p>Activas</p>
                        <span class="trend positive">
                            <i class="fas fa-check-circle"></i> ${stats.totalSubscribers
            ? Math.round((stats.activeSubscriptions / stats.totalSubscribers) * 100)
            : 0
        }% activas
                        </span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>${formatCurrency(stats.monthlyRecurringRevenue || 0)}</h3>
                        <p>MRR estimado</p>
                        <span class="trend positive">
                            <i class="fas fa-sync"></i> Renovaciones próximas: ${stats.upcomingRenewals || 0}
                        </span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>${formatNumber(stats.pendingPayments || 0)}</h3>
                        <p>Pagos pendientes</p>
                        <span class="trend negative">
                            <i class="fas fa-arrow-down"></i> ${stats.cancelledThisMonth || 0} canceladas este mes
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="subscription-filters">
                <div class="filter-group">
                    <input 
                        type="text" 
                        id="subscription-search" 
                        class="form-control" 
                        placeholder="Buscar por usuario, email o ID..." 
                        value="${filters.search || ''}"
                    >
                    <i class="fas fa-search"></i>
                </div>
                <div class="filter-group">
                    <select id="plan-filter" class="form-control">
                        <option value="">Todos los planes</option>
                        ${planOptions}
                    </select>
                </div>
                <div class="filter-group">
                    <select id="status-filter" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="active" ${filters.status === 'active' ? 'selected' : ''}>Activas</option>
                        <option value="pending" ${filters.status === 'pending' ? 'selected' : ''}>Pendientes</option>
                        <option value="cancelled" ${filters.status === 'cancelled' ? 'selected' : ''}>Canceladas</option>
                        <option value="expired" ${filters.status === 'expired' ? 'selected' : ''}>Expiradas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-outline" id="manage-plans-btn">
                        <i class="fas fa-cog"></i> Gestionar planes
                    </button>
                </div>
            </div>
            
            <div class="subscription-section">
                <div class="section-header">
                    <div>
                        <h2>Planes disponibles</h2>
                        <p class="text-muted">${plans.length} plan${plans.length === 1 ? '' : 'es'} configurado${plans.length === 1 ? '' : 's'}</p>
                    </div>
                    <div class="section-actions">
                        <button class="btn btn-primary" id="add-plan-btn">
                            <i class="fas fa-layer-plus"></i> Nuevo plan
                        </button>
                    </div>
                </div>
                <div class="subscription-plans">
                    ${planCards}
                </div>
            </div>
            
            <div class="subscription-section">
                <div class="section-header">
                    <div>
                        <h2>Suscripciones activas</h2>
                        <p class="text-muted" data-subscription-count>${filtered.length} resultado${filtered.length === 1 ? '' : 's'}</p>
                    </div>
                    <div class="section-actions">
                        <button class="btn btn-outline" id="refresh-subscriptions">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="data-table" data-type="subscriptions">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Plan</th>
                                    <th>Inicio</th>
                                    <th>Próximo pago</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="subscriptions-table-body">
                                ${subscriptionRows}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="subscription-section">
                <div class="section-header">
                    <div>
                        <h2>Historial de facturación</h2>
                        <p class="text-muted" data-payments-count>${filteredPayments.length} movimiento${filteredPayments.length === 1 ? '' : 's'}</p>
                    </div>
                    <div class="section-actions">
                        <select id="billing-period" class="form-control">
                            <option value="all" ${filters.period === 'all' ? 'selected' : ''}>Todos los períodos</option>
                            <option value="month" ${filters.period === 'month' ? 'selected' : ''}>Este mes</option>
                            <option value="quarter" ${filters.period === 'quarter' ? 'selected' : ''}>Este trimestre</option>
                            <option value="year" ${filters.period === 'year' ? 'selected' : ''}>Este año</option>
                        </select>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="data-table" data-type="payments">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Fecha</th>
                                    <th>Plan</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="payments-table-body">
                                ${paymentsRows}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Renderizar reportes
function renderReports() {
    return `
        <div class="content-header">
            <h1>Reportes y Análisis</h1>
            <div class="report-actions">
                <button class="btn btn-outline" id="export-pdf-btn">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
                <button class="btn btn-outline" id="export-excel-btn">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button class="btn btn-primary" id="refresh-reports-btn">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
        </div>
        
        <!-- Filtros de reportes -->
        <div class="report-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="report-type">Tipo de Reporte</label>
                    <select id="report-type" class="form-control">
                        <option value="general">General</option>
                        <option value="users">Usuarios</option>
                        <option value="content">Contenido</option>
                        <option value="revenue">Ingresos</option>
                        <option value="subscriptions">Suscripciones</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-range">Rango de Fechas</label>
                    <select id="date-range" class="form-control">
                        <option value="7">Últimos 7 días</option>
                        <option value="30" selected>Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                        <option value="365">Último año</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                
                <div class="filter-group date-range-custom" style="display: none;">
                    <label for="start-date">Desde</label>
                    <input type="date" id="start-date" class="form-control">
                </div>
                    
                <div class="filter-group date-range-custom" style="display: none;">
                    <label for="end-date">Hasta</label>
                    <input type="date" id="end-date" class="form-control">
                </div>
                
                <div class="filter-group">
                    <button class="btn btn-primary" id="generate-report-btn" style="margin-top: 1.75rem;">
                    <i class="fas fa-chart-bar"></i> Generar Reporte
                </button>
                </div>
            </div>
        </div>
        
        <!-- Resumen de métricas -->
            <div class="report-summary">
                <div class="summary-card">
                <div class="summary-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="summary-info">
                        <h3>1,248</h3>
                    <p>Usuarios Totales</p>
                    <span class="trend positive">
                        <i class="fas fa-arrow-up"></i> +12% este mes
                    </span>
                    </div>
                </div>
                
                <div class="summary-card">
                <div class="summary-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="summary-info">
                        <h3>24,589</h3>
                        <p>Reproducciones</p>
                    <span class="trend positive">
                        <i class="fas fa-arrow-up"></i> +8% este mes
                    </span>
                    </div>
                </div>
                
                <div class="summary-card">
                <div class="summary-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="summary-info">
                        <h3>$24,589</h3>
                    <p>Ingresos Totales</p>
                    <span class="trend negative">
                        <i class="fas fa-arrow-down"></i> -3% este mes
                    </span>
                    </div>
                </div>
                
                <div class="summary-card">
                <div class="summary-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <i class="fas fa-film"></i>
                    </div>
                    <div class="summary-info">
                        <h3>356</h3>
                        <p>Películas</p>
                    <span class="trend positive">
                        <i class="fas fa-arrow-up"></i> +5% este mes
                    </span>
                    </div>
                </div>
            </div>
            
        <!-- Gráficos -->
            <div class="report-charts">
                <div class="chart-container">
                    <div class="chart-header">
                    <h3>Actividad de Usuarios</h3>
                        <div class="chart-actions">
                        <button class="btn btn-sm btn-outline active" data-period="day">Día</button>
                        <button class="btn btn-sm btn-outline" data-period="week">Semana</button>
                        <button class="btn btn-sm btn-outline" data-period="month">Mes</button>
                        </div>
                    </div>
                    <div class="chart" id="user-activity-chart">
                        <div class="chart-placeholder">
                        <div class="placeholder-content">
                            <i class="fas fa-chart-line"></i>
                            <p>Gráfico de actividad de usuarios</p>
                            <small>Los datos se cargarán aquí</small>
                        </div>
                        </div>
                    </div>
                </div>
                
                <div class="chart-row">
                    <div class="chart-container half">
                        <div class="chart-header">
                        <h3>Contenido Más Visto</h3>
                        </div>
                        <div class="chart" id="top-content-chart">
                            <div class="chart-placeholder">
                            <div class="placeholder-content">
                                <i class="fas fa-chart-bar"></i>
                                <p>Top 10 contenido</p>
                                <small>Los datos se cargarán aquí</small>
                            </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container half">
                        <div class="chart-header">
                        <h3>Distribución de Suscriptores</h3>
                        </div>
                        <div class="chart" id="subscription-distribution-chart">
                            <div class="chart-placeholder">
                            <div class="placeholder-content">
                                <i class="fas fa-chart-pie"></i>
                                <p>Distribución por plan</p>
                                <small>Los datos se cargarán aquí</small>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <!-- Tablas de reportes -->
            <div class="report-tables">
                <div class="table-container">
                    <div class="table-header">
                    <h3>Películas Más Populares</h3>
                    <div class="table-actions">
                        <button class="btn-icon" title="Actualizar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="#contenido/peliculas" class="btn-link">Ver todo</a>
                    </div>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                <th>#</th>
                                    <th>Película</th>
                                    <th>Vistas</th>
                                    <th>Valoración</th>
                                    <th>Ingresos</th>
                                <th>Tendencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                <td><strong>1</strong></td>
                                <td>
                                    <div class="content-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Poster" class="content-thumb" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>El Padrino</span>
                                    </div>
                                </td>
                                <td><strong>12,458</strong></td>
                                <td>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> 4.8
                                    </span>
                                </td>
                                <td><strong class="revenue">$8,720.60</strong></td>
                                <td><span class="trend-indicator up"><i class="fas fa-arrow-up"></i></span></td>
                                </tr>
                                <tr>
                                <td><strong>2</strong></td>
                                <td>
                                    <div class="content-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Poster" class="content-thumb" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>El Caballero Oscuro</span>
                                    </div>
                                </td>
                                <td><strong>10,235</strong></td>
                                <td>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> 4.9
                                    </span>
                                </td>
                                <td><strong class="revenue">$7,164.50</strong></td>
                                <td><span class="trend-indicator up"><i class="fas fa-arrow-up"></i></span></td>
                                </tr>
                                <tr>
                                <td><strong>3</strong></td>
                                <td>
                                    <div class="content-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Poster" class="content-thumb" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>El Padrino: Parte II</span>
                                    </div>
                                </td>
                                <td><strong>9,874</strong></td>
                                <td>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> 4.7
                                    </span>
                                </td>
                                <td><strong class="revenue">$6,911.80</strong></td>
                                <td><span class="trend-indicator stable"><i class="fas fa-minus"></i></span></td>
                                </tr>
                                <tr>
                                <td><strong>4</strong></td>
                                <td>
                                    <div class="content-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Poster" class="content-thumb" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Cadena Perpetua</span>
                                    </div>
                                </td>
                                <td><strong>8,963</strong></td>
                                <td>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> 4.9
                                    </span>
                                </td>
                                <td><strong class="revenue">$6,274.10</strong></td>
                                <td><span class="trend-indicator up"><i class="fas fa-arrow-up"></i></span></td>
                                </tr>
                                <tr>
                                <td><strong>5</strong></td>
                                <td>
                                    <div class="content-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Poster" class="content-thumb" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Pulp Fiction</span>
                                    </div>
                                </td>
                                <td><strong>8,452</strong></td>
                                <td>
                                    <span class="rating-badge">
                                        <i class="fas fa-star"></i> 4.8
                                    </span>
                                </td>
                                <td><strong class="revenue">$5,916.40</strong></td>
                                <td><span class="trend-indicator down"><i class="fas fa-arrow-down"></i></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                    <h3>Usuarios Más Activos</h3>
                    <div class="table-actions">
                        <button class="btn-icon" title="Actualizar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <a href="#usuarios" class="btn-link">Ver todo</a>
                    </div>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                <th>#</th>
                                    <th>Usuario</th>
                                    <th>Plan</th>
                                    <th>Actividad</th>
                                <th>Último Acceso</th>
                                <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                <td><strong>1</strong></td>
                                <td>
                                    <div class="user-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Usuario" class="user-avatar" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Carlos López</span>
                                    </div>
                                </td>
                                    <td><span class="badge premium">Premium</span></td>
                                <td>
                                    <span class="activity-badge high">Alto</span>
                                </td>
                                    <td>Hoy, 14:30</td>
                                <td><span class="status active">Activo</span></td>
                                </tr>
                                <tr>
                                <td><strong>2</strong></td>
                                <td>
                                    <div class="user-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Usuario" class="user-avatar" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Ana Martínez</span>
                                    </div>
                                </td>
                                    <td><span class="badge premium">Premium</span></td>
                                <td>
                                    <span class="activity-badge high">Alto</span>
                                </td>
                                    <td>Hoy, 10:15</td>
                                <td><span class="status active">Activo</span></td>
                                </tr>
                                <tr>
                                <td><strong>3</strong></td>
                                <td>
                                    <div class="user-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Usuario" class="user-avatar" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Laura García</span>
                                    </div>
                                </td>
                                    <td><span class="badge free">Gratis</span></td>
                                <td>
                                    <span class="activity-badge medium">Medio</span>
                                </td>
                                    <td>Ayer, 09:45</td>
                                <td><span class="status active">Activo</span></td>
                                </tr>
                                <tr>
                                <td><strong>4</strong></td>
                                <td>
                                    <div class="user-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Usuario" class="user-avatar" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Miguel Ángel Ramírez</span>
                                    </div>
                                </td>
                                    <td><span class="badge premium">Premium</span></td>
                                <td>
                                    <span class="activity-badge low">Bajo</span>
                                </td>
                                    <td>Hace 2 días</td>
                                <td><span class="status inactive">Inactivo</span></td>
                                </tr>
                                <tr>
                                <td><strong>5</strong></td>
                                <td>
                                    <div class="user-cell">
                                        <img src="${DEFAULT_POSTER}" alt="Usuario" class="user-avatar" onerror="this.src='${DEFAULT_POSTER}'">
                                        <span>Roberto Sánchez</span>
                                    </div>
                                </td>
                                    <td><span class="badge premium">Premium</span></td>
                                <td>
                                    <span class="activity-badge low">Bajo</span>
                                </td>
                                    <td>Hace 3 días</td>
                                <td><span class="status inactive">Inactivo</span></td>
                                </tr>
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
    `;
}

// Renderizar configuración
function renderSettings() {
    return `
        <div class="content-header">
            <h1>Configuración</h1>
            <button class="btn btn-primary" id="save-settings">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
        
        <div class="settings-container">
            <div class="settings-sidebar">
                <ul class="settings-menu">
                    <li class="active" data-tab="general"><i class="fas fa-cog"></i> General</li>
                    <li data-tab="profile"><i class="fas fa-user"></i> Perfil</li>
                    <li data-tab="security"><i class="fas fa-shield-alt"></i> Seguridad</li>
                    <li data-tab="notifications"><i class="fas fa-bell"></i> Notificaciones</li>
                    <li data-tab="billing"><i class="fas fa-credit-card"></i> Facturación</li>
                    <li data-tab="api"><i class="fas fa-code"></i> API</li>
                </ul>
            </div>
            
            <div class="settings-content">
                <!-- Pestaña General -->
                <div class="settings-tab active" id="general-tab">
                    <h2>Configuración General</h2>
                    <p>Configura las opciones generales de la plataforma.</p>
                    
                    <form id="general-settings-form">
                        <div class="form-group">
                            <label for="site-title">Título del Sitio</label>
                            <input type="text" id="site-title" class="form-control" value="UrresTv">
                        </div>
                        
                        <div class="form-group">
                            <label for="site-description">Descripción</label>
                            <textarea id="site-description" class="form-control" rows="3">La mejor plataforma de streaming de películas y series.</textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="site-logo">Logo del Sitio</label>
                            <div class="file-upload">
                                <input type="file" id="site-logo" class="form-control">
                                <button type="button" class="btn btn-outline">Subir</button>
                            </div>
                            <small>Formatos aceptados: JPG, PNG. Tamaño máximo: 2MB</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Zona Horaria</label>
                            <select id="timezone" class="form-control">
                                <option value="-6">(GMT-06:00) Centro de México</option>
                                <option value="-5">(GMT-05:00) Este de México</option>
                                <option value="-7">(GMT-07:00) Noroeste de México</option>
                                <option value="-8">(GMT-08:00) Baja California</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date-format">Formato de Fecha</label>
                            <select id="date-format" class="form-control">
                                <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                                <option value="MM/DD/YYYY">MM/DD/YYYY</option>
                                <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                                <option value="DD MMM, YYYY">DD MMM, YYYY</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="items-per-page">Elementos por página</label>
                            <input type="number" id="items-per-page" class="form-control" min="5" max="100" value="20">
                        </div>
                        
                        <div class="form-group form-check">
                            <input type="checkbox" id="maintenance-mode" class="form-check-input">
                            <label class="form-check-label" for="maintenance-mode">Modo Mantenimiento</label>
                            <small class="form-text text-muted">Habilitar para poner el sitio en modo mantenimiento.</small>
                        </div>
                    </form>
                </div>
                
                <!-- Otras pestañas se cargarán dinámicamente -->
                <div class="settings-tab" id="profile-tab">
                    <h2>Perfil de Usuario</h2>
                    <p>Gestiona la información de tu perfil de administrador.</p>
                    <form id="profile-settings-form">
                        <div class="form-group">
                            <label for="admin-username">Nombre de Usuario</label>
                            <input type="text" id="admin-username" class="form-control" value="${appState.currentUser.name || 'Administrador'}" readonly>
                            <small>El nombre de usuario no se puede cambiar</small>
                        </div>
                        <div class="form-group">
                            <label for="admin-email">Correo Electrónico</label>
                            <input type="email" id="admin-email" class="form-control" value="${appState.currentUser.email || 'admin@streamingplatform.com'}">
                        </div>
                        <div class="form-group">
                            <label for="admin-avatar">Avatar</label>
                            <div class="file-upload">
                                <input type="file" id="admin-avatar" class="form-control" accept="image/*">
                                <button type="button" class="btn btn-outline">Subir</button>
                            </div>
                            <small>Formatos aceptados: JPG, PNG. Tamaño máximo: 2MB</small>
                        </div>
                    </form>
                </div>
                
                <div class="settings-tab" id="security-tab">
                    <h2>Seguridad</h2>
                    <p>Gestiona la seguridad de tu cuenta y la plataforma.</p>
                    <form id="security-settings-form">
                        <div class="form-group">
                            <label for="current-password">Contraseña Actual</label>
                            <input type="password" id="current-password" class="form-control" placeholder="Ingresa tu contraseña actual">
                        </div>
                        <div class="form-group">
                            <label for="new-password">Nueva Contraseña</label>
                            <input type="password" id="new-password" class="form-control" placeholder="Mínimo 8 caracteres">
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirmar Nueva Contraseña</label>
                            <input type="password" id="confirm-password" class="form-control" placeholder="Repite la nueva contraseña">
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="two-factor" class="form-check-input">
                            <label class="form-check-label" for="two-factor">Autenticación de dos factores (2FA)</label>
                            <small class="form-text text-muted">Añade una capa extra de seguridad a tu cuenta.</small>
                        </div>
                    </form>
                </div>
                
                <div class="settings-tab" id="notifications-tab">
                    <h2>Notificaciones</h2>
                    <p>Configura qué notificaciones deseas recibir.</p>
                    <form id="notifications-settings-form">
                        <div class="form-group form-check">
                            <input type="checkbox" id="notif-email" class="form-check-input" checked>
                            <label class="form-check-label" for="notif-email">Notificaciones por Email</label>
                            <small class="form-text text-muted">Recibe notificaciones importantes por correo electrónico.</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="notif-new-users" class="form-check-input" checked>
                            <label class="form-check-label" for="notif-new-users">Nuevos Usuarios</label>
                            <small class="form-text text-muted">Recibe notificaciones cuando se registren nuevos usuarios.</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="notif-new-content" class="form-check-input" checked>
                            <label class="form-check-label" for="notif-new-content">Nuevo Contenido</label>
                            <small class="form-text text-muted">Recibe notificaciones cuando se agregue nuevo contenido.</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="notif-errors" class="form-check-input" checked>
                            <label class="form-check-label" for="notif-errors">Errores del Sistema</label>
                            <small class="form-text text-muted">Recibe notificaciones sobre errores críticos del sistema.</small>
                        </div>
                    </form>
                </div>
                
                <div class="settings-tab" id="billing-tab">
                    <h2>Facturación</h2>
                    <p>Gestiona la información de facturación y suscripciones.</p>
                    <form id="billing-settings-form">
                        <div class="form-group">
                            <label for="billing-email">Email de Facturación</label>
                            <input type="email" id="billing-email" class="form-control" placeholder="facturacion@ejemplo.com">
                        </div>
                        <div class="form-group">
                            <label for="billing-address">Dirección de Facturación</label>
                            <textarea id="billing-address" class="form-control" rows="3" placeholder="Ingresa tu dirección completa"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="payment-method">Método de Pago</label>
                            <select id="payment-method" class="form-control">
                                <option value="">Selecciona un método</option>
                                <option value="credit-card">Tarjeta de Crédito</option>
                                <option value="paypal">PayPal</option>
                                <option value="bank-transfer">Transferencia Bancaria</option>
                            </select>
                        </div>
                    </form>
                </div>
                
                <div class="settings-tab" id="api-tab">
                    <h2>API y Integraciones</h2>
                    <p>Gestiona las claves API y las integraciones externas.</p>
                    <form id="api-settings-form">
                        <div class="form-group">
                            <label for="api-key">Clave API Principal</label>
                            <div class="file-upload">
                                <input type="text" id="api-key" class="form-control" value="sk_live_xxxxxxxxxxxxxxxxxxxxx" readonly>
                                <button type="button" class="btn btn-outline" onclick="copyApiKey()">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                </div>
                            <small>Usa esta clave para autenticar peticiones a la API</small>
                        </div>
                        <div class="form-group">
                            <label for="api-secret">Secreto API</label>
                            <div class="file-upload">
                                <input type="password" id="api-secret" class="form-control" value="••••••••••••••••••••" readonly>
                                <button type="button" class="btn btn-outline" onclick="regenerateApiSecret()">
                                    <i class="fas fa-sync"></i> Regenerar
                                </button>
                            </div>
                            <small>Mantén este secreto seguro y no lo compartas</small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" id="api-enabled" class="form-check-input" checked>
                            <label class="form-check-label" for="api-enabled">API Pública Habilitada</label>
                            <small class="form-text text-muted">Permite acceso público a la API (solo lectura).</small>
                        </div>
                        <div class="form-group">
                            <label for="webhook-url">URL de Webhook</label>
                            <input type="url" id="webhook-url" class="form-control" placeholder="https://ejemplo.com/webhook">
                            <small>URL para recibir notificaciones de eventos</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
}

// Función para configurar las pestañas de configuración
function setupSettingsTabs() {
    const tabItems = document.querySelectorAll('.settings-menu li');
    if (tabItems.length === 0) return;

    // Remover listeners anteriores si existen
    tabItems.forEach(item => {
        const newItem = item.cloneNode(true);
        item.parentNode.replaceChild(newItem, item);
    });

    // Añadir nuevos listeners
    const newTabItems = document.querySelectorAll('.settings-menu li');
    newTabItems.forEach(item => {
        item.addEventListener('click', () => {
            const tabId = item.getAttribute('data-tab');

            // Actualizar menú activo
            const activeMenuItem = document.querySelector('.settings-menu li.active');
            if (activeMenuItem) {
                activeMenuItem.classList.remove('active');
            }
            item.classList.add('active');

            // Ocultar todas las pestañas
            const allTabs = document.querySelectorAll('.settings-tab');
            allTabs.forEach(tab => {
                tab.classList.remove('active');
                tab.style.display = 'none';
            });

            // Mostrar la pestaña seleccionada
            const selectedTab = document.getElementById(`${tabId}-tab`);
            if (selectedTab) {
                selectedTab.classList.add('active');
                selectedTab.style.display = 'block';
            }
        });
    });

    // Asegurar que la primera pestaña esté visible
    const firstTab = document.querySelector('.settings-tab.active') || document.querySelector('.settings-tab');
    if (firstTab) {
        firstTab.style.display = 'block';
    }
}

// Función para copiar API key
window.copyApiKey = function () {
    const apiKeyInput = document.getElementById('api-key');
    if (apiKeyInput) {
        apiKeyInput.select();
        document.execCommand('copy');
        showNotification('Clave API copiada al portapapeles', 'success');
    }
};

// Función para regenerar API secret
window.regenerateApiSecret = function () {
    if (confirm('¿Estás seguro de que quieres regenerar el secreto API? Esto invalidará el secreto actual.')) {
        showNotification('Secreto API regenerado correctamente', 'success');
        // Aquí iría la lógica real para regenerar el secreto
    }
};

/**
 * Muestra el modal para agregar o editar contenido.
 * Si se proporciona un `itemData`, rellena el formulario para edición.
 * @param {object|null} itemData - Los datos del elemento a editar.
 */
function showContentModal(itemData = null) {
    appState.editingItemId = itemData ? itemData.id : null;
    const modalTitle = document.getElementById('modal-title');
    if (modalTitle) {
        modalTitle.textContent = itemData ? `Editar ${itemData.title || 'Contenido'}` : 'Agregar Nuevo Contenido';
    }

    const form = document.getElementById('contentForm');
    if (!form) return;

    form.reset();

    if (itemData) {
        // Rellenar el formulario con los datos existentes
        const fields = ['id', 'title', 'release_year', 'duration', 'description', 'poster_url', 'backdrop_url', 'age_rating', 'type'];
        fields.forEach(key => {
            const input = form.elements[key];
            if (input && itemData[key] !== undefined && itemData[key] !== null) {
                if (input.type === 'checkbox') {
                    input.checked = !!itemData[key];
                } else {
                    input.value = itemData[key];
                }
            }
        });

        // Configurar fuente de video (URL por defecto si existe video_url)
        const videoUrlRadio = document.getElementById('video_source_url');
        const videoFileRadio = document.getElementById('video_source_file');
        const videoUrlInput = document.getElementById('video_url');

        if (itemData.video_url && itemData.video_url.trim() !== '') {
            // Si hay URL de video, usar opción URL
            if (videoUrlRadio) {
                videoUrlRadio.checked = true;
                videoUrlRadio.dispatchEvent(new Event('change'));
            }
            if (videoUrlInput) {
                videoUrlInput.value = itemData.video_url;
            }
        } else {
            // Si no hay URL, dejar URL seleccionada pero vacía (para que el usuario elija)
            if (videoUrlRadio) {
                videoUrlRadio.checked = true;
                videoUrlRadio.dispatchEvent(new Event('change'));
            }
        }

        // Configurar fuente de tráiler
        const trailerUrlRadio = document.getElementById('trailer_source_url');
        const trailerFileRadio = document.getElementById('trailer_source_file');
        const trailerNoneRadio = document.getElementById('trailer_source_none');
        const trailerUrlInput = document.getElementById('trailer_url');

        if (itemData.trailer_url && itemData.trailer_url.trim() !== '') {
            // Si hay URL de tráiler, usar opción URL
            if (trailerUrlRadio) {
                trailerUrlRadio.checked = true;
                trailerUrlRadio.dispatchEvent(new Event('change'));
            }
            if (trailerUrlInput) {
                trailerUrlInput.value = itemData.trailer_url;
            }
        } else {
            // Si no hay tráiler, usar opción "none"
            if (trailerNoneRadio) {
                trailerNoneRadio.checked = true;
                trailerNoneRadio.dispatchEvent(new Event('change'));
            }
        }

        // Checkboxes especiales
        if (form.elements.is_featured) form.elements.is_featured.checked = !!itemData.is_featured;
        if (form.elements.is_trending) form.elements.is_trending.checked = !!itemData.is_trending;
        if (form.elements.is_premium) form.elements.is_premium.checked = !!itemData.is_premium;

        // Tipo de contenido
        if (form.elements.type) {
            form.elements.type.value = itemData.type || 'movie';
        }
    } else {
        // Modo creación: establecer valores por defecto
        const videoUrlRadio = document.getElementById('video_source_url');
        const trailerNoneRadio = document.getElementById('trailer_source_none');

        if (videoUrlRadio) {
            videoUrlRadio.checked = true;
            videoUrlRadio.dispatchEvent(new Event('change'));
        }
        if (trailerNoneRadio) {
            trailerNoneRadio.checked = true;
            trailerNoneRadio.dispatchEvent(new Event('change'));
        }
    }

    const modal = document.getElementById('contentModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Muestra el modal para agregar o editar usuarios.
 * @param {object|null} userData - Los datos del usuario a editar.
 */
function showUserModal(userData = null) {
    appState.editingItemId = userData ? userData.id : null;

    const modal = document.getElementById('userModal');
    const modalTitle = document.getElementById('user-modal-title');
    const form = document.getElementById('userForm');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const passwordRequired = document.getElementById('password-required');
    const passwordHelp = document.getElementById('password-help');

    if (!modal || !form) {
        console.error('Modal de usuario o formulario no encontrado');
        return;
    }

    // Establecer título
    if (modalTitle) {
        modalTitle.textContent = userData ? `Editar Usuario: ${userData.username || userData.email}` : 'Agregar Nuevo Usuario';
    }

    // Resetear formulario
    form.reset();

    if (userData) {
        // Modo edición: rellenar con datos existentes
        const fields = ['id', 'username', 'email', 'full_name', 'role', 'status'];
        fields.forEach(key => {
            const input = form.elements[key];
            if (input && userData[key] !== undefined && userData[key] !== null) {
                input.value = userData[key];
            }
        });

        // Contraseña no requerida en edición (solo si se quiere cambiar)
        if (passwordInput) {
            passwordInput.removeAttribute('required');
            passwordInput.placeholder = 'Dejar vacío para mantener la contraseña actual';
        }
        if (passwordConfirmInput) {
            passwordConfirmInput.removeAttribute('required');
        }
        if (passwordRequired) {
            passwordRequired.style.display = 'none';
        }
        if (passwordHelp) {
            passwordHelp.textContent = 'Dejar vacío para mantener la contraseña actual (mínimo 8 caracteres si se cambia)';
        }
    } else {
        // Modo creación: contraseña requerida
        if (passwordInput) {
            passwordInput.setAttribute('required', 'required');
            passwordInput.placeholder = '';
        }
        if (passwordConfirmInput) {
            passwordConfirmInput.setAttribute('required', 'required');
        }
        if (passwordRequired) {
            passwordRequired.style.display = 'inline';
        }
        if (passwordHelp) {
            passwordHelp.textContent = 'Mínimo 8 caracteres (requerida para nuevos usuarios)';
        }
    }

    // Mostrar modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Función para editar un item
async function editItem(id, type) {
    try {
        // Mapear tipos a endpoints correctos (igual que viewItem)
        const endpointMap = {
            'dashboard': '/api/content/index.php',
            'contenido': '/api/content/index.php',
            'peliculas': '/api/content/index.php',
            'movies': '/api/content/index.php',
            'series': '/api/content/index.php',
            'usuarios': '/api/users/index.php',
            'users': '/api/users/index.php',
            'user': '/api/users/index.php'
        };

        // Determinar el endpoint correcto
        let endpoint = endpointMap[type] || '/api/content/index.php';

        // Si el endpoint requiere ID en la URL, agregarlo
        if (endpoint.includes('index.php')) {
            endpoint = `${endpoint}?id=${id}`;
        } else {
            endpoint = `${endpoint}/${id}`;
        }

        const response = await apiRequest(endpoint);

        // Verificar si la respuesta tiene el formato esperado
        if (response && (response.data || response.success)) {
            const itemData = response.data || response;

            // Si es un usuario, mostrar modal de usuario
            if (type === 'users' || type === 'usuarios' || type === 'user') {
                showUserModal(itemData);
            } else {
                // Si es contenido, ajustar datos para el formulario
                const contentData = {
                    ...itemData,
                    is_featured: itemData.is_featured ? true : false,
                    is_trending: itemData.is_trending ? true : false,
                    is_premium: itemData.is_premium ? true : false
                };
                showContentModal(contentData);
            }
        } else {
            showNotification('No se pudo obtener la información del elemento.', 'error');
        }
    } catch (error) {
        console.error('Error en editItem:', error);
        showNotification(`Error al cargar el elemento: ${error.message}`, 'error');
    }
}

// Función para ver un item
async function viewItem(id, type) {
    try {
        // Mapear tipos a endpoints correctos
        const endpointMap = {
            'dashboard': '/api/content/index.php',
            'contenido': '/api/content/index.php',
            'peliculas': '/api/content/index.php',
            'movies': '/api/content/index.php',
            'series': '/api/content/index.php',
            'usuarios': '/api/users/index.php',
            'users': '/api/users/index.php',
            'user': '/api/users/index.php'
        };

        // Determinar el endpoint correcto
        let endpoint = endpointMap[type] || '/api/content/index.php';

        // Si el endpoint requiere ID en la URL, agregarlo
        if (endpoint.includes('index.php')) {
            endpoint = `${endpoint}?id=${id}`;
        } else {
            endpoint = `${endpoint}/${id}`;
        }

        const item = await apiRequest(endpoint);
        if (item && item.data) {
            // Por ahora, mostramos una alerta. Idealmente, esto abriría un modal de vista detallada.
            const details = Object.entries(item.data)
                .filter(([key]) => !['password', 'reset_token'].includes(key))
                .map(([key, value]) => {
                    if (typeof value === 'object' && value !== null) {
                        value = JSON.stringify(value);
                    }
                    return `${key}: ${value}`;
                })
                .join('\n');

            const title = item.data.title || item.data.username || item.data.email || `Elemento #${id}`;
            alert(`Detalles de ${title}:\n\n${details}`);
        } else {
            showNotification('No se pudo obtener la información del elemento.', 'error');
        }
    } catch (error) {
        showNotification(`Error al cargar el elemento: ${error.message}`, 'error');
    }
}

// Función para eliminar un item
async function deleteItem(id, title, type) {
    if (confirm(`¿Estás seguro de que quieres eliminar "${title}"? Esta acción no se puede deshacer.`)) {
        try {
            let endpoint;
            switch (type) {
                case 'usuarios':
                case 'users':
                case 'user':
                    endpoint = `/api/users/index.php?id=${id}`;
                    break;
                case 'peliculas':
                case 'movies':
                case 'series':
                    endpoint = `/api/movies/index.php?id=${id}`;
                    break;
                default:
                    endpoint = `/api/${type}/index.php?id=${id}`;
                    break;
            }

            const response = await apiRequest(endpoint, { method: 'DELETE' });
            if (response.success || response.status === 'success') {
                showNotification(response.message || 'Elemento eliminado correctamente.', 'success');
                loadSection(); // Recargar la sección para reflejar los cambios
            } else {
                throw new Error(response.error || 'Error desconocido');
            }
        } catch (error) {
            showNotification(`Error al eliminar: ${error.message}`, 'error');
        }
    }
}

// Cerrar modal de contenido
function closeModal() {
    const modal = document.getElementById('contentModal');
    if (modal) {
        modal.classList.remove('active');
    }
    document.body.style.overflow = '';
    appState.editingItemId = null; // Limpiar el ID de edición
}

// Cerrar modal de usuarios
function closeUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.classList.remove('active');
    }
    document.body.style.overflow = '';
    appState.editingItemId = null; // Limpiar el ID de edición
}

// Manejar envío del formulario de contenido
async function handleContentSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // Mostrar indicador de carga
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guardando...';
    }

    try {
        // Validar que se haya seleccionado una fuente de video
        const videoSource = formData.get('video_source');
        if (!videoSource) {
            showNotification('Por favor, selecciona una fuente de video (URL o archivo local)', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
            return;
        }

        // Validar archivos antes de subir (solo si se seleccionó archivo)
        if (videoSource === 'file') {
            const videoFileInput = document.getElementById('video_file');
            if (!videoFileInput || !videoFileInput.files || !videoFileInput.files[0]) {
                showNotification('Por favor, selecciona un archivo de video', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
            if (!validateFileInput(videoFileInput, 'video_file_info', 2147483648)) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
        } else if (videoSource === 'url') {
            const videoUrlInput = document.getElementById('video_url');
            if (!videoUrlInput || !videoUrlInput.value || videoUrlInput.value.trim() === '') {
                showNotification('Por favor, ingresa una URL de video', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
        }

        // Validar tráiler si se seleccionó archivo
        const trailerSource = formData.get('trailer_source') || 'none';
        if (trailerSource === 'file') {
            const trailerFileInput = document.getElementById('trailer_file');
            if (!trailerFileInput || !trailerFileInput.files || !trailerFileInput.files[0]) {
                showNotification('Por favor, selecciona un archivo de tráiler', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
            if (!validateFileInput(trailerFileInput, 'trailer_file_info', 524288000)) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
        }

        // Procesar póster
        const posterSource = formData.get('poster_source') || 'url';
        let posterUrl = '';
        if (posterSource === 'file') {
            const posterFileInput = document.getElementById('poster_file');
            if (!posterFileInput || !posterFileInput.files || !posterFileInput.files[0]) {
                throw new Error('Selecciona un archivo de póster');
            }
            if (!validateImageInput(posterFileInput, 'poster_file_info', 5242880)) {
                throw new Error('El póster no es válido');
            }
            showNotification('Subiendo póster...', 'info');
            const uploadData = new FormData();
            uploadData.append('file', posterFileInput.files[0]);
            const posterResp = await fetch(`${baseUrl}/api/upload/image.php`, {
                method: 'POST',
                body: uploadData,
                credentials: 'same-origin'
            });
            const posterJson = await posterResp.json();
            if (!posterJson.success || !posterJson.data?.url) {
                throw new Error(posterJson.error || 'Error al subir el póster');
            }
            posterUrl = posterJson.data.url;
        } else {
            posterUrl = (formData.get('poster_url') || '').trim();
        }

        // Procesar backdrop
        const backdropSource = formData.get('backdrop_source') || 'url';
        let backdropUrl = '';
        if (backdropSource === 'file') {
            const backdropFileInput = document.getElementById('backdrop_file');
            if (!backdropFileInput || !backdropFileInput.files || !backdropFileInput.files[0]) {
                throw new Error('Selecciona un archivo de backdrop');
            }
            if (!validateImageInput(backdropFileInput, 'backdrop_file_info', 6291456)) {
                throw new Error('El backdrop no es válido');
            }
            showNotification('Subiendo backdrop...', 'info');
            const uploadData = new FormData();
            uploadData.append('file', backdropFileInput.files[0]);
            const bdResp = await fetch(`${baseUrl}/api/upload/image.php`, {
                method: 'POST',
                body: uploadData,
                credentials: 'same-origin'
            });
            const bdJson = await bdResp.json();
            if (!bdJson.success || !bdJson.data?.url) {
                throw new Error(bdJson.error || 'Error al subir el backdrop');
            }
            backdropUrl = bdJson.data.url;
        } else {
            backdropUrl = (formData.get('backdrop_url') || '').trim();
        }

        // Determinar qué fuente de video usar (URL o archivo)
        let videoUrl = '';

        if (videoSource === 'file') {
            // Subir archivo de video
            const videoFile = formData.get('video_file');
            if (!videoFile || videoFile.size === 0) {
                throw new Error('Por favor, selecciona un archivo de video');
            }

            showNotification('Subiendo video...', 'info');
            const videoUploadData = new FormData();
            videoUploadData.append('file', videoFile);

            const uploadEndpoint = `${baseUrl}/api/upload/video.php`;
            const videoUploadResponse = await fetch(uploadEndpoint, {
                method: 'POST',
                body: videoUploadData,
                credentials: 'same-origin'
            });

            const videoUploadResult = await videoUploadResponse.json();

            if (videoUploadResult.success && videoUploadResult.data) {
                videoUrl = videoUploadResult.data.url;
                showNotification('Video subido correctamente', 'success');
            } else {
                throw new Error(videoUploadResult.error || 'Error al subir el video');
            }
        } else if (videoSource === 'url') {
            // Usar URL de video
            videoUrl = formData.get('video_url') || '';
            if (!videoUrl || videoUrl.trim() === '') {
                throw new Error('Por favor, ingresa una URL de video');
            }
            videoUrl = videoUrl.trim();
        } else {
            throw new Error('Por favor, selecciona una fuente de video (URL o archivo)');
        }

        // Determinar qué fuente de tráiler usar (URL, archivo o ninguno)
        let trailerUrl = '';

        if (trailerSource === 'file') {
            // Subir archivo de tráiler
            const trailerFile = formData.get('trailer_file');
            if (!trailerFile || trailerFile.size === 0) {
                throw new Error('Por favor, selecciona un archivo de tráiler');
            }

            showNotification('Subiendo tráiler...', 'info');
            const trailerUploadData = new FormData();
            trailerUploadData.append('file', trailerFile);
            trailerUploadData.append('is_trailer', '1');

            const uploadEndpoint = `${baseUrl}/api/upload/video.php`;
            const trailerUploadResponse = await fetch(uploadEndpoint, {
                method: 'POST',
                body: trailerUploadData,
                credentials: 'same-origin'
            });

            const trailerUploadResult = await trailerUploadResponse.json();

            if (trailerUploadResult.success && trailerUploadResult.data) {
                trailerUrl = trailerUploadResult.data.url;
                showNotification('Tráiler subido correctamente', 'success');
            } else {
                throw new Error(trailerUploadResult.error || 'Error al subir el tráiler');
            }
        } else if (trailerSource === 'url') {
            // Usar URL de tráiler
            trailerUrl = formData.get('trailer_url') || '';
            if (trailerUrl) {
                trailerUrl = trailerUrl.trim();
            }
        }
        // Si trailerSource === 'none', trailerUrl permanece vacío

        // Preparar datos del formulario
        const data = Object.fromEntries(formData.entries());

        // Convertir checkboxes a booleanos (0 o 1)
        data.is_featured = data.is_featured ? 1 : 0;
        data.is_trending = data.is_trending ? 1 : 0;
        data.is_premium = data.is_premium ? 1 : 0;

        // Determinar la URL y el método de la API
        const type = appState.currentSubsection || 'peliculas'; // 'peliculas', 'series', etc.
        const contentType = type === 'peliculas' ? 'movie' : 'series';

        // Preparar datos para la API
        const apiData = {
            title: data.title,
            description: data.description,
            release_year: parseInt(data.release_year),
            duration: parseInt(data.duration),
            type: contentType,
            poster_url: posterUrl,
            backdrop_url: backdropUrl,
            video_url: videoUrl,
            trailer_url: trailerUrl,
            torrent_magnet: data.torrent_magnet || null,
            age_rating: data.age_rating || null,
            is_featured: data.is_featured === '1' || data.is_featured === true ? 1 : 0,
            is_trending: data.is_trending === '1' || data.is_trending === true ? 1 : 0,
            is_premium: data.is_premium === '1' || data.is_premium === true ? 1 : 0
        };

        let url = '/api/movies/index.php';
        let method = 'POST';

        if (appState.editingItemId) {
            url = `/api/movies/index.php?id=${appState.editingItemId}`;
            method = 'PUT';
            apiData.id = parseInt(appState.editingItemId);
        }

        showNotification('Guardando contenido...', 'info');

        const response = await apiRequest(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiData)
        });

        if (response.success || response.data) {
            showNotification(response.message || 'Contenido guardado correctamente', 'success');
            closeModal();
            loadSection(); // Recargar la sección para ver los cambios
        } else {
            throw new Error(response.error || 'Ocurrió un error al guardar.');
        }
    } catch (error) {
        showNotification(`Error: ${error.message}`, 'error');
        console.error('Error al guardar:', error);
    } finally {
        // Restaurar botón
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
}

// Manejar envío del formulario de usuarios
async function handleUserSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    // Validaciones
    if (!data.username || data.username.trim().length < 3) {
        showNotification('El nombre de usuario debe tener al menos 3 caracteres.', 'error');
        return;
    }

    if (!data.email || !data.email.includes('@')) {
        showNotification('Por favor, ingresa un email válido.', 'error');
        return;
    }

    // Validar contraseña
    const isEditing = !!appState.editingItemId;
    if (!isEditing && (!data.password || data.password.length < 8)) {
        showNotification('La contraseña debe tener al menos 8 caracteres.', 'error');
        return;
    }

    if (data.password && data.password.length < 8) {
        showNotification('La contraseña debe tener al menos 8 caracteres.', 'error');
        return;
    }

    if (data.password && data.password !== data.password_confirm) {
        showNotification('Las contraseñas no coinciden.', 'error');
        return;
    }

    // Preparar datos para la API
    const apiData = {
        username: data.username.trim(),
        email: data.email.trim(),
        full_name: data.full_name ? data.full_name.trim() : '',
        role: data.role || 'user',
        status: data.status || 'active'
    };

    // Solo incluir contraseña si se proporcionó
    if (data.password && data.password.trim()) {
        apiData.password = data.password;
    }

    let url = '/api/users/index.php';
    let method = 'POST';

    if (appState.editingItemId) {
        url = `/api/users/index.php?id=${appState.editingItemId}`;
        method = 'PUT';
        apiData.id = parseInt(appState.editingItemId);
    }

    try {
        const response = await apiRequest(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(apiData)
        });

        if (response.success || response.status === 'success') {
            showNotification(response.message || (isEditing ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.'), 'success');
            closeUserModal();
            loadSection(); // Recargar la sección para reflejar los cambios
        } else {
            throw new Error(response.error || 'Error desconocido');
        }
    } catch (error) {
        showNotification(`Error al guardar usuario: ${error.message}`, 'error');
        console.error('Error al guardar usuario:', error);
    }
}

/**
 * Wrapper para peticiones fetch a la API
 * @param {string} endpoint - El endpoint de la API (ej. '/api/movies/')
 * @param {object} options - Opciones para fetch (method, body, headers, etc.)
 * @returns {Promise<any>} - La respuesta JSON de la API
 */
async function apiRequest(endpoint, options = {}) {
    try {
        // Asegurar que el endpoint tenga la ruta base correcta
        let fullEndpoint;

        if (typeof getApiUrl === 'function') {
            fullEndpoint = getApiUrl(endpoint);
        } else {
            // Fallback: lógica manual si getApiUrl no está disponible
            const baseUrl = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';

            if (endpoint.startsWith('http')) {
                fullEndpoint = endpoint;
            } else if (endpoint.startsWith('/streaming-platform')) {
                fullEndpoint = baseUrl + endpoint.replace('/streaming-platform', '');
            } else if (endpoint.startsWith('/')) {
                fullEndpoint = baseUrl + endpoint;
            } else {
                fullEndpoint = baseUrl + '/' + endpoint;
            }
        }

        // Log de depuración para ver la URL final
        console.log('[apiRequest] Llamando a:', fullEndpoint);

        // Añadir headers por defecto
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };

        options.headers = { ...defaultHeaders, ...(options.headers || {}) };

        // Incluir credenciales (cookies de sesión) en las peticiones
        // Usamos 'include' para funcionar incluso si el panel se sirve desde otro subdominio/origen
        options.credentials = 'include';

        const response = await fetch(fullEndpoint, options);

        if (!response.ok) {
            // Intentar obtener el mensaje de error del servidor
            let errorMessage = `Error HTTP ${response.status}`;
            const contentType = response.headers.get('content-type');

            // Clonar la respuesta para poder leerla múltiples veces si es necesario
            const clonedResponse = response.clone();

            try {
                if (contentType && contentType.includes('application/json')) {
                    const errorData = await response.json();
                    errorMessage = errorData.error || errorData.message || errorMessage;
                } else {
                    // Si no es JSON, intentar leer como texto
                    const text = await clonedResponse.text();
                    if (text && text.trim()) {
                        // Intentar parsear como JSON si parece ser JSON
                        try {
                            const parsed = JSON.parse(text);
                            errorMessage = parsed.error || parsed.message || errorMessage;
                        } catch {
                            // Si no es JSON válido, usar el texto (limitado a 200 caracteres)
                            errorMessage = text.length > 200 ? `Error ${response.status}` : text;
                        }
                    } else {
                        errorMessage = `Error HTTP ${response.status}: ${response.statusText}`;
                    }
                }
            } catch (e) {
                // Si todo falla, usar el mensaje por defecto
                errorMessage = `Error HTTP ${response.status}: ${response.statusText || 'Error desconocido'}`;
            }
            throw new Error(errorMessage);
        }

        // Si la respuesta es 204 No Content (como en un DELETE exitoso), no hay JSON que parsear
        if (response.status === 204) {
            return { success: true };
        }

        // Verificar que la respuesta tenga contenido antes de parsear
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            try {
                return await response.json();
            } catch (jsonError) {
                throw new Error('La respuesta del servidor no es un JSON válido');
            }
        } else {
            // Si no es JSON, devolver el texto
            const text = await response.text();
            return { success: true, message: text };
        }
    } catch (error) {
        console.error('Error en la petición API:', error);
        throw error;
    }
}

// Mostrar notificación
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Mostrar con animación
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Ocultar después de 3 segundos
    setTimeout(() => {
        notification.classList.remove('show');

        // Eliminar después de la animación
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Validar archivo de entrada y mostrar información
function validateFileInput(input, infoId, maxSize) {
    const file = input.files[0];
    const infoDiv = document.getElementById(infoId);

    if (!file) {
        if (infoDiv) infoDiv.style.display = 'none';
        return true;
    }

    // Validar tamaño
    if (file.size > maxSize) {
        const maxSizeMB = Math.round(maxSize / (1024 * 1024));
        showNotification(`El archivo es demasiado grande. Tamaño máximo: ${maxSizeMB}MB`, 'error');
        input.value = '';
        if (infoDiv) infoDiv.style.display = 'none';
        return false;
    }

    // Validar tipo
    const allowedTypes = ['video/mp4', 'video/webm', 'video/avi', 'video/x-msvideo', 'video/x-matroska', 'video/quicktime'];
    const allowedExtensions = ['mp4', 'webm', 'avi', 'mkv', 'mov'];
    const fileExtension = file.name.split('.').pop().toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
        showNotification('Tipo de archivo no permitido. Formatos permitidos: MP4, WebM, AVI, MKV, MOV', 'error');
        input.value = '';
        if (infoDiv) infoDiv.style.display = 'none';
        return false;
    }

    // Mostrar información del archivo
    if (infoDiv) {
        const fileName = infoDiv.querySelector('.file-name');
        const fileSize = infoDiv.querySelector('.file-size');

        if (fileName) {
            fileName.textContent = `Archivo: ${file.name}`;
        }

        if (fileSize) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            fileSize.textContent = `Tamaño: ${sizeMB} MB`;
        }

        infoDiv.style.display = 'block';
    }

    return true;
}

// Validar archivo de imagen (póster / backdrop)
function validateImageInput(input, infoId, maxSize) {
    const file = input.files[0];
    const infoDiv = document.getElementById(infoId);

    if (!file) {
        if (infoDiv) infoDiv.style.display = 'none';
        return true;
    }

    if (file.size > maxSize) {
        const maxSizeMB = Math.round(maxSize / (1024 * 1024));
        showNotification(`La imagen es demasiado grande. Máximo: ${maxSizeMB}MB`, 'error');
        input.value = '';
        if (infoDiv) infoDiv.style.display = 'none';
        return false;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    const allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExt.includes(ext)) {
        showNotification('Formato no permitido. Usa JPG, PNG o WEBP.', 'error');
        input.value = '';
        if (infoDiv) infoDiv.style.display = 'none';
        return false;
    }

    if (infoDiv) {
        const fileName = infoDiv.querySelector('.file-name');
        const fileSize = infoDiv.querySelector('.file-size');
        if (fileName) fileName.textContent = `Archivo: ${file.name}`;
        if (fileSize) fileSize.textContent = `Tamaño: ${(file.size / (1024 * 1024)).toFixed(2)} MB`;
        infoDiv.style.display = 'block';
    }

    return true;
}

// Limpiar archivo de imagen
window.clearImageFile = function (inputId) {
    const input = document.getElementById(inputId);
    const infoId = inputId === 'poster_file' ? 'poster_file_info' : 'backdrop_file_info';
    const infoDiv = document.getElementById(infoId);
    if (input) input.value = '';
    if (infoDiv) infoDiv.style.display = 'none';
};

// Configurar opciones mutuamente excluyentes
function setupMutuallyExclusiveOptions(radioGroupName, options) {
    const radioButtons = document.querySelectorAll(`input[name="${radioGroupName}"]`);

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function () {
            const selectedValue = this.value;
            const config = options[selectedValue];

            if (!config) return;

            // Ocultar y deshabilitar todas las opciones primero
            Object.keys(options).forEach(key => {
                const opt = options[key];
                if (opt.container) {
                    const container = document.getElementById(opt.container);
                    if (container) container.style.display = 'none';
                }
                if (opt.input) {
                    const input = document.getElementById(opt.input);
                    if (input) {
                        input.disabled = true;
                        if (input.type === 'file') {
                            input.value = '';
                        } else {
                            input.value = '';
                        }
                    }
                }
                if (opt.previewBtn) {
                    const btn = document.getElementById(opt.previewBtn);
                    if (btn) btn.disabled = true;
                }
            });

            // Mostrar y habilitar la opción seleccionada
            if (config.container) {
                const container = document.getElementById(config.container);
                if (container) container.style.display = 'block';
            }
            if (config.input) {
                const input = document.getElementById(config.input);
                if (input) {
                    input.disabled = false;
                    input.required = selectedValue !== 'none';
                }
            }
            if (config.previewBtn) {
                const btn = document.getElementById(config.previewBtn);
                if (btn) btn.disabled = false;
            }

            // Deshabilitar y limpiar la otra opción
            if (config.otherOption) {
                const otherOption = document.getElementById(config.otherOption);
                if (otherOption) {
                    otherOption.style.opacity = '0.5';
                    otherOption.style.pointerEvents = 'none';
                }
            }
            if (config.otherInput) {
                const otherInput = document.getElementById(config.otherInput);
                if (otherInput) {
                    otherInput.disabled = true;
                    if (otherInput.type === 'file') {
                        otherInput.value = '';
                        // Limpiar info de archivo si existe
                        const infoId = otherInput.id + '_info';
                        const infoDiv = document.getElementById(infoId);
                        if (infoDiv) {
                            infoDiv.style.display = 'none';
                        }
                    } else {
                        otherInput.value = '';
                    }
                }
            }

            // Si es "none", también limpiar la segunda opción si existe
            if (selectedValue === 'none' && config.otherOption2) {
                const otherOption2 = document.getElementById(config.otherOption2);
                if (otherOption2) {
                    otherOption2.style.opacity = '0.5';
                    otherOption2.style.pointerEvents = 'none';
                }
            }
            if (selectedValue === 'none' && config.otherInput2) {
                const otherInput2 = document.getElementById(config.otherInput2);
                if (otherInput2) {
                    otherInput2.disabled = true;
                    if (otherInput2.type === 'file') {
                        otherInput2.value = '';
                        const infoId = otherInput2.id + '_info';
                        const infoDiv = document.getElementById(infoId);
                        if (infoDiv) {
                            infoDiv.style.display = 'none';
                        }
                    } else {
                        otherInput2.value = '';
                    }
                }
            }

            // Restaurar opacidad de la opción seleccionada
            const selectedOption = document.querySelector(`input[name="${radioGroupName}"]:checked`);
            if (selectedOption) {
                const selectedCard = selectedOption.closest('.video-option-card, .trailer-option-card');
                if (selectedCard) {
                    selectedCard.style.opacity = '1';
                    selectedCard.style.pointerEvents = 'auto';
                    selectedCard.style.borderColor = '#e50914';
                    selectedCard.style.background = '#fff5f5';
                }
            }

            // Restaurar opacidad de otras opciones no seleccionadas
            radioButtons.forEach(rb => {
                if (rb !== this && rb.value !== 'none') {
                    const card = rb.closest('.video-option-card, .trailer-option-card');
                    if (card) {
                        card.style.opacity = '1';
                        card.style.pointerEvents = 'auto';
                        card.style.borderColor = '#ddd';
                        card.style.background = '#f9f9f9';
                    }
                }
            });
        });

        // Disparar el evento change si ya está seleccionado
        if (radio.checked) {
            radio.dispatchEvent(new Event('change'));
        }
    });
}

// Limpiar archivo de video
function clearVideoFile() {
    const videoFileInput = document.getElementById('video_file');
    const videoFileInfo = document.getElementById('video_file_info');

    if (videoFileInput) {
        videoFileInput.value = '';
    }
    if (videoFileInfo) {
        videoFileInfo.style.display = 'none';
    }

    // Cambiar a opción de URL
    const urlRadio = document.getElementById('video_source_url');
    if (urlRadio) {
        urlRadio.checked = true;
        urlRadio.dispatchEvent(new Event('change'));
    }
}

// Limpiar archivo de tráiler
function clearTrailerFile() {
    const trailerFileInput = document.getElementById('trailer_file');
    const trailerFileInfo = document.getElementById('trailer_file_info');

    if (trailerFileInput) {
        trailerFileInput.value = '';
    }
    if (trailerFileInfo) {
        trailerFileInfo.style.display = 'none';
    }

    // Cambiar a opción "none"
    const noneRadio = document.getElementById('trailer_source_none');
    if (noneRadio) {
        noneRadio.checked = true;
        noneRadio.dispatchEvent(new Event('change'));
    }
}

// Hacer funciones globales
window.clearVideoFile = clearVideoFile;
window.clearTrailerFile = clearTrailerFile;

// Formatear categoría para mostrar
function formatCategory(category) {
    const categories = {
        'action': 'Acción',
        'adventure': 'Aventura',
        'comedy': 'Comedia',
        'drama': 'Drama',
        'horror': 'Terror',
        'sci_fi': 'Ciencia Ficción',
        'thriller': 'Suspenso',
        'documentary': 'Documental',
        'animation': 'Animación',
        'romance': 'Romance'
    };

    return categories[category] || category;
}

// Formatear fecha
function formatDate(dateString) {
    if (!dateString) return 'N/A';

    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('es-ES', options);
}

// Formatear fecha y hora
function formatDateTime(dateTimeString) {
    if (!dateTimeString) return 'Nunca';

    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };

    return new Date(dateTimeString).toLocaleDateString('es-ES', options);
}

// Formatear estado
function formatStatus(status) {
    const statusMap = {
        'active': 'Activo',
        'inactive': 'Inactivo',
        'suspended': 'Suspendido',
        'pending': 'Pendiente',
        'banned': 'Bloqueado'
    };

    return statusMap[status] || status;
}

// Inicializar componentes dinámicos
function initDynamicComponents() {
    // Tabs de configuración
    setupSettingsTabs();

    // Selector de rango de fechas personalizado
    const dateRangeSelect = document.getElementById('date-range');
    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', (e) => {
            const customDateRange = document.querySelector('.date-range-custom');
            if (e.target.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }

    // Botón para agregar nuevo contenido
    const addNewButtons = document.querySelectorAll('.btn-add-new');
    addNewButtons.forEach(button => { // Solo debería haber uno por vista
        button.addEventListener('click', (e) => {
            e.preventDefault();
            showContentModal();
        });
    });

    // Cerrar modal al hacer clic en el botón de cerrar
    const closeButtons = document.querySelectorAll('.close-modal, .close-modal-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', closeModal);
    });

    // Evitar que el clic en el modal lo cierre
    const modalContent = document.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Inicializar tooltips
    const tooltipTriggers = document.querySelectorAll('[data-toggle="tooltip"]');
    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', showTooltip);
        trigger.addEventListener('mouseleave', hideTooltip);
    });

    // Interacciones específicas de la tabla de usuarios
    setupUserTableInteractions();
    setupSubscriptionInteractions();
}

// Configurar eventos de la tabla de usuarios
function setupUserTableInteractions() {
    const userTable = document.querySelector('.data-table[data-type="users"]');
    if (!userTable) {
        return;
    }

    const selectAllCheckbox = document.getElementById('select-all-users');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', handleSelectAllUsers);
    }

    userTable.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedUsersCount);
    });

    userTable.addEventListener('click', handleUserTableClick);

    const refreshUsersBtn = document.getElementById('refresh-users-btn');
    if (refreshUsersBtn) {
        refreshUsersBtn.addEventListener('click', handleRefreshUsers);
    }
}

function handleSelectAllUsers(event) {
    const userTable = document.querySelector('.data-table[data-type="users"]');
    if (!userTable) return;

    const allCheckboxes = userTable.querySelectorAll('.user-checkbox');
    allCheckboxes.forEach(checkbox => {
        checkbox.checked = event.target.checked;
    });

    updateSelectedUsersCount();
}

function updateSelectedUsersCount() {
    const userTable = document.querySelector('.data-table[data-type="users"]');
    if (!userTable) return;

    const totalUsers = userTable.querySelectorAll('.user-checkbox').length;
    const selectedUsers = userTable.querySelectorAll('.user-checkbox:checked').length;
    const counter = document.querySelector('.user-table-container .user-count');

    if (!counter) return;

    const baseLabel = `${totalUsers} usuario${totalUsers !== 1 ? 's' : ''}`;
    counter.textContent = selectedUsers > 0
        ? `${baseLabel} · ${selectedUsers} seleccionado${selectedUsers !== 1 ? 's' : ''}`
        : baseLabel;
}

function handleUserTableClick(event) {
    const passwordBtn = event.target.closest('.btn-password-toggle');
    if (passwordBtn) {
        event.preventDefault();
        togglePasswordView(passwordBtn);
        return;
    }

    const resetBtn = event.target.closest('.btn-reset-password');
    if (resetBtn) {
        event.preventDefault();
        const userId = resetBtn.dataset.id || resetBtn.closest('tr')?.dataset.id;
        resetUserPassword(userId);
        return;
    }

    const actionBtn = event.target.closest('.action-buttons .btn-icon');
    if (!actionBtn) return;

    event.preventDefault();

    const action = actionBtn.dataset.action;
    const row = actionBtn.closest('tr');
    const userId = actionBtn.dataset.id || row?.dataset.id;
    if (!action || !userId) return;

    switch (action) {
        case 'view':
            viewItem(userId, 'usuarios');
            break;
        case 'edit':
            editItem(userId, 'usuarios');
            break;
        case 'delete': {
            const username = row?.querySelector('.user-info strong')?.textContent?.trim() || 'este usuario';
            deleteItem(userId, username, 'usuarios');
            break;
        }
        default:
            break;
    }
}

function handleRefreshUsers(event) {
    event.preventDefault();

    const button = event.currentTarget;
    button.disabled = true;
    button.classList.add('is-refreshing');

    loadSection();

    // Evitar que el botón quede deshabilitado si la carga falla
    setTimeout(() => {
        button.disabled = false;
        button.classList.remove('is-refreshing');
    }, 1500);
}

// Configurar eventos de la sección de suscripciones
function setupSubscriptionInteractions() {
    const subscriptionView = document.querySelector('.subscription-view');
    if (!subscriptionView) {
        return;
    }

    const searchInput = document.getElementById('subscription-search');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                updateSubscriptionFilter('search', e.target.value.trim());
            }, 250);
        });
    }

    const planFilter = document.getElementById('plan-filter');
    if (planFilter) {
        planFilter.addEventListener('change', (e) => {
            updateSubscriptionFilter('plan', e.target.value);
        });
    }

    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            updateSubscriptionFilter('status', e.target.value);
        });
    }

    const billingPeriod = document.getElementById('billing-period');
    if (billingPeriod) {
        billingPeriod.addEventListener('change', (e) => {
            filterPaymentsByPeriod(e.target.value);
        });
    }

    const addSubscriptionBtn = document.getElementById('add-subscription-btn');
    if (addSubscriptionBtn) {
        addSubscriptionBtn.addEventListener('click', handleAddSubscription);
    }

    const addFirstSubscriptionBtn = document.getElementById('add-first-subscription-btn');
    if (addFirstSubscriptionBtn) {
        addFirstSubscriptionBtn.addEventListener('click', handleAddSubscription);
    }

    const refreshBtn = document.getElementById('refresh-subscriptions');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', handleRefreshSubscriptions);
    }

    const exportBtn = document.getElementById('export-subscriptions');
    if (exportBtn) {
        exportBtn.addEventListener('click', handleExportSubscriptions);
    }

    const managePlansBtn = document.getElementById('manage-plans-btn');
    if (managePlansBtn) {
        managePlansBtn.addEventListener('click', handleManagePlans);
    }

    const addPlanBtn = document.getElementById('add-plan-btn');
    if (addPlanBtn) {
        addPlanBtn.addEventListener('click', handleCreatePlan);
    }

    const createFirstPlanBtn = document.getElementById('create-first-plan-btn');
    if (createFirstPlanBtn) {
        createFirstPlanBtn.addEventListener('click', handleCreatePlan);
    }

    subscriptionView.addEventListener('click', handleSubscriptionActionClick);
}

function updateSubscriptionFilter(key, value) {
    if (!appState.subscriptionsData || !appState.subscriptionsData.filters) return;
    appState.subscriptionsData.filters[key] = value;
    applySubscriptionFilters();
}

function applySubscriptionFilters() {
    const data = appState.subscriptionsData;
    if (!data) return;

    const search = (data.filters.search || '').toLowerCase();
    const planFilter = data.filters.plan;
    const statusFilter = data.filters.status;

    let filtered = [...(data.list || [])];

    if (search) {
        filtered = filtered.filter(sub => {
            return (
                (sub.username && sub.username.toLowerCase().includes(search)) ||
                (sub.full_name && sub.full_name.toLowerCase().includes(search)) ||
                (sub.email && sub.email.toLowerCase().includes(search)) ||
                String(sub.id).includes(search)
            );
        });
    }

    if (planFilter) {
        filtered = filtered.filter(sub => String(sub.plan_id) === String(planFilter));
    }

    if (statusFilter) {
        filtered = filtered.filter(sub => sub.status === statusFilter);
    }

    data.filtered = filtered;
    updateSubscriptionTable(filtered);
    updateSubscriptionCounters();
}

function updateSubscriptionTable(list = []) {
    const tbody = document.getElementById('subscriptions-table-body');
    if (tbody) {
        tbody.innerHTML = buildSubscriptionRows(list);
    }
}

function filterPaymentsByPeriod(period) {
    const data = appState.subscriptionsData;
    if (!data) return;

    data.filters.period = period;
    const payments = data.payments || [];

    if (period === 'all') {
        data.filteredPayments = payments;
        updatePaymentsTable(payments);
        updateSubscriptionCounters();
        return;
    }

    const now = new Date();
    let startDate;

    switch (period) {
        case 'month':
            startDate = new Date(now.getFullYear(), now.getMonth(), 1);
            break;
        case 'quarter':
            const quarter = Math.floor(now.getMonth() / 3);
            startDate = new Date(now.getFullYear(), quarter * 3, 1);
            break;
        case 'year':
            startDate = new Date(now.getFullYear(), 0, 1);
            break;
        default:
            startDate = null;
            break;
    }

    const filtered = payments.filter(payment => {
        if (!startDate) return true;
        const paymentDate = new Date(payment.payment_date);
        return paymentDate >= startDate;
    });

    data.filteredPayments = filtered;
    updatePaymentsTable(filtered);
    updateSubscriptionCounters();
}

function updatePaymentsTable(list = []) {
    const tbody = document.getElementById('payments-table-body');
    if (tbody) {
        tbody.innerHTML = buildPaymentsRows(list);
    }
}

function updateSubscriptionCounters() {
    const data = appState.subscriptionsData;
    if (!data) return;

    const subscriptionLabel = document.querySelector('[data-subscription-count]');
    if (subscriptionLabel) {
        const count = data.filtered ? data.filtered.length : 0;
        subscriptionLabel.textContent = `${count} resultado${count === 1 ? '' : 's'}`;
    }

    const paymentsLabel = document.querySelector('[data-payments-count]');
    if (paymentsLabel) {
        const count = data.filteredPayments ? data.filteredPayments.length : 0;
        paymentsLabel.textContent = `${count} movimiento${count === 1 ? '' : 's'}`;
    }
}

function handleSubscriptionActionClick(event) {
    const actionBtn = event.target.closest('[data-subscription-action]');
    if (actionBtn) {
        const action = actionBtn.dataset.subscriptionAction;
        const subscriptionId = actionBtn.dataset.id;
        if (!action || !subscriptionId) return;

        switch (action) {
            case 'view':
                showSubscriptionDetails(subscriptionId);
                break;
            case 'edit':
                editSubscription(subscriptionId);
                break;
            case 'cancel':
                cancelSubscription(subscriptionId);
                break;
            default:
                break;
        }
        return;
    }

    const planBtn = event.target.closest('[data-plan-action]');
    if (planBtn) {
        const planId = planBtn.dataset.id;
        if (planId) {
            handlePlanInlineEdit(planId);
        }
    }
}

function showSubscriptionDetails(subscriptionId) {
    const subscription = (appState.subscriptionsData?.list || []).find(sub => String(sub.id) === String(subscriptionId));
    if (!subscription) {
        alert('No se encontró la suscripción solicitada.');
        return;
    }

    const details = [
        `Usuario: ${subscription.full_name || subscription.username || 'N/D'}`,
        `Email: ${subscription.email || 'N/D'}`,
        `Plan: ${subscription.plan_name} (${formatCurrency(subscription.plan_price)})`,
        `Estado: ${formatSubscriptionStatus(subscription.status)}`,
        `Inicio: ${formatDateShort(subscription.start_date)}`,
        `Próximo pago: ${formatDateShort(subscription.next_payment_date)}`,
        `Renovación automática: ${subscription.auto_renew ? 'Sí' : 'No'}`
    ].join('\n');

    alert(`Detalles de la suscripción #${subscription.id}\n\n${details}`);
}

async function editSubscription(subscriptionId) {
    const data = appState.subscriptionsData;
    if (!data) return;

    const plans = data.plans || [];
    const planList = plans.map(plan => `${plan.id}: ${plan.name} (${formatCurrency(plan.price)})`).join('\n');
    const newPlanId = prompt(`Ingresa el ID del nuevo plan:\n${planList}`);
    if (!newPlanId) return;

    const cycle = prompt('Ciclo de facturación (monthly/yearly):', 'monthly');
    if (!cycle) return;

    const status = prompt('Estado (active/pending/cancelled/expired):', 'active');
    if (!status) return;

    try {
        const response = await apiRequest(`/api/subscriptions/index.php?id=${subscriptionId}`, {
            method: 'PUT',
            body: JSON.stringify({
                plan_id: parseInt(newPlanId, 10),
                billing_cycle: cycle,
                status
            })
        });

        if (response.success) {
            showNotification('Suscripción actualizada correctamente.', 'success');
            await loadSection();
        } else {
            throw new Error(response.error || 'No se pudo actualizar la suscripción');
        }
    } catch (error) {
        console.error(error);
        showNotification(`Error al actualizar: ${error.message}`, 'error');
    }
}

async function cancelSubscription(subscriptionId) {
    if (!confirm('¿Seguro que deseas cancelar esta suscripción?')) {
        return;
    }

    try {
        const response = await apiRequest(`/api/subscriptions/index.php?id=${subscriptionId}`, {
            method: 'DELETE'
        });

        if (response.success) {
            showNotification('Suscripción cancelada correctamente.', 'success');
            await loadSection();
        } else {
            throw new Error(response.error || 'No se pudo cancelar la suscripción');
        }
    } catch (error) {
        console.error(error);
        showNotification(`Error al cancelar: ${error.message}`, 'error');
    }
}

async function handleAddSubscription() {
    const data = appState.subscriptionsData;
    if (!data) return;

    const userId = prompt('ID del usuario:');
    const parsedUserId = parseInt(userId, 10);
    if (!userId || Number.isNaN(parsedUserId)) {
        alert('ID de usuario inválido.');
        return;
    }

    const planList = (data.plans || []).map(plan => `${plan.id}: ${plan.name}`).join('\n');
    const planId = prompt(`ID del plan:\n${planList}`);
    const parsedPlanId = parseInt(planId, 10);
    if (!planId || Number.isNaN(parsedPlanId)) {
        alert('ID de plan inválido.');
        return;
    }

    const billingCycle = prompt('Ciclo de facturación (monthly/yearly):', 'monthly');
    if (!billingCycle || !['monthly', 'yearly'].includes(billingCycle)) {
        alert('Ciclo de facturación no válido.');
        return;
    }

    try {
        const response = await apiRequest('/api/subscriptions/index.php', {
            method: 'POST',
            body: JSON.stringify({
                user_id: parsedUserId,
                plan_id: parsedPlanId,
                billing_cycle: billingCycle,
                create_payment: true
            })
        });

        if (response.success) {
            showNotification('Suscripción creada correctamente.', 'success');
            await loadSection();
        } else {
            throw new Error(response.error || 'No se pudo crear la suscripción');
        }
    } catch (error) {
        console.error(error);
        showNotification(`Error al crear suscripción: ${error.message}`, 'error');
    }
}

async function handleRefreshSubscriptions() {
    const button = document.getElementById('refresh-subscriptions');
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
    }

    try {
        await loadSection();
        showNotification('Suscripciones actualizadas.', 'success');
    } catch (error) {
        console.error(error);
        showNotification('No se pudieron recargar las suscripciones.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar';
        }
    }
}

async function handleExportSubscriptions() {
    const data = appState.subscriptionsData;
    if (!data || !data.filtered || !data.filtered.length) {
        showNotification('No hay suscripciones para exportar.', 'warning');
        return;
    }

    const headers = ['ID', 'Usuario', 'Email', 'Plan', 'Estado', 'Inicio', 'Próximo pago'];
    const rows = data.filtered.map(sub => [
        sub.id,
        sub.full_name || sub.username || '',
        sub.email || '',
        sub.plan_name || '',
        sub.status,
        formatDateShort(sub.start_date),
        formatDateShort(sub.next_payment_date)
    ]);

    const csvContent = [headers, ...rows]
        .map(row => row.map(value => `"${String(value || '').replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `suscripciones_${Date.now()}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

async function handleManagePlans() {
    const data = appState.subscriptionsData;
    if (!data) return;

    const plans = data.plans || [];
    if (!plans.length) {
        alert('No hay planes disponibles. Crea uno nuevo.');
        handleCreatePlan();
        return;
    }

    const planList = plans.map(plan => `${plan.id}: ${plan.name} (${formatCurrency(plan.price)})`).join('\n');
    const selection = prompt(`Planes disponibles:\n${planList}\n\nIngresa el ID para editar o escribe "nuevo" para crear:`);
    if (!selection) return;

    if (selection.toLowerCase() === 'nuevo') {
        handleCreatePlan();
        return;
    }

    const planId = parseInt(selection, 10);
    if (Number.isNaN(planId)) {
        alert('ID inválido.');
        return;
    }

    handlePlanInlineEdit(planId);
}

async function handlePlanInlineEdit(planId) {
    const plans = appState.subscriptionsData?.plans || [];
    const plan = plans.find(p => Number(p.id) === Number(planId));
    if (!plan) {
        alert('Plan no encontrado. Actualiza la lista e inténtalo nuevamente.');
        return;
    }

    const newPrice = prompt(`Nuevo precio para ${plan.name}:`, plan.price);
    if (newPrice === null) return;
    const parsedPrice = parseFloat(newPrice);
    if (Number.isNaN(parsedPrice)) {
        alert('Precio inválido.');
        return;
    }

    const description = prompt('Descripción del plan:', plan.description || '');

    try {
        const response = await apiRequest(`/api/subscriptions/index.php?resource=plans&id=${planId}`, {
            method: 'PUT',
            body: JSON.stringify({
                price: parsedPrice,
                description
            })
        });

        if (response.success) {
            showNotification('Plan actualizado correctamente.', 'success');
            await loadSection();
        } else {
            throw new Error(response.error || 'No se pudo actualizar el plan');
        }
    } catch (error) {
        console.error(error);
        showNotification(`Error al actualizar plan: ${error.message}`, 'error');
    }
}

async function handleCreatePlan() {
    const name = prompt('Nombre del plan:');
    if (!name) return;

    const price = prompt('Precio mensual (ej. 9.99):', '9.99');
    if (price === null) return;
    const parsedPrice = parseFloat(price);
    if (Number.isNaN(parsedPrice)) {
        alert('Precio inválido.');
        return;
    }

    const billingCycle = prompt('Ciclo de facturación (monthly/yearly):', 'monthly');
    if (!billingCycle) return;

    const videoQuality = prompt('Calidad de video (HD/Full HD/4K):', 'HD');
    if (!videoQuality) return;

    try {
        const response = await apiRequest('/api/subscriptions/index.php?resource=plans', {
            method: 'POST',
            body: JSON.stringify({
                name,
                price: parsedPrice,
                billing_cycle: billingCycle,
                video_quality: videoQuality,
                description: `${name} - ${videoQuality}`,
                max_screens: 2,
                download_limit: 5,
                ads_enabled: 0
            })
        });

        if (response.success) {
            showNotification('Plan creado correctamente.', 'success');
            await loadSection();
        } else {
            throw new Error(response.error || 'No se pudo crear el plan');
        }
    } catch (error) {
        console.error(error);
        showNotification(`Error al crear plan: ${error.message}`, 'error');
    }
}

// Mostrar tooltip
function showTooltip(e) {
    const tooltipText = this.getAttribute('title');
    if (!tooltipText) return;

    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;

    document.body.appendChild(tooltip);

    const rect = this.getBoundingClientRect();
    const tooltipHeight = tooltip.offsetHeight;
    const tooltipWidth = tooltip.offsetWidth;

    let top = rect.top - tooltipHeight - 10;
    let left = rect.left + (this.offsetWidth / 2) - (tooltipWidth / 2);

    // Ajustar si el tooltip se sale por la izquierda
    if (left < 10) left = 10;

    // Ajustar si el tooltip se sale por arriba
    if (top < 10) {
        top = rect.bottom + 10;
    }

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
    tooltip.classList.add('show');

    // Eliminar el título para evitar el tooltip nativo
    this.removeAttribute('title');
    this.setAttribute('data-original-title', tooltipText);
}

// Ocultar tooltip
function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        // Restaurar el título original
        const originalTitle = this.getAttribute('data-original-title');
        if (originalTitle) {
            this.setAttribute('title', originalTitle);
            this.removeAttribute('data-original-title');
        }

        tooltip.remove();
    }
}

// Manejar redimensionamiento de la ventana
function handleResize() {
    if (window.innerWidth <= 1200) {
        document.body.classList.add('sidebar-collapsed');
    } else {
        document.body.classList.remove('sidebar-collapsed');
    }
}

// Renderizar un mensaje de error en el área de contenido
function renderError(message) {
    return `
        <div class="error-message-full">
            <i class="fas fa-exclamation-triangle"></i>
            <p>${message}</p>
        </div>`;
}
// Inicializar la aplicación cuando el DOM esté listo
// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    // DOM ya está listo
    init();
}

// Manejar navegación con el botón de retroceso/avanzar
// Toggle para ver/ocultar hash de contraseña
function togglePasswordView(button) {
    const passwordText = button.querySelector('.password-text');
    const icon = button.querySelector('i');

    if (passwordText && (passwordText.style.display === 'none' || !passwordText.style.display)) {
        passwordText.style.display = 'inline';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
        button.classList.add('active');
    } else if (passwordText) {
        passwordText.style.display = 'none';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
        button.classList.remove('active');
    }
}

// Resetear contraseña de usuario
async function resetUserPassword(userId) {
    if (!userId) return;

    const newPassword = prompt('Ingresa la nueva contraseña (mínimo 8 caracteres):');
    if (!newPassword) return;

    if (newPassword.length < 8) {
        alert('La contraseña debe tener al menos 8 caracteres.');
        return;
    }

    const confirmPassword = prompt('Confirma la nueva contraseña:');
    if (newPassword !== confirmPassword) {
        alert('Las contraseñas no coinciden.');
        return;
    }

    try {
        const response = await apiRequest(`/api/users/index.php?id=${userId}`, {
            method: 'PUT',
            body: JSON.stringify({
                password: newPassword
            })
        });

        if (response.success) {
            alert('Contraseña actualizada correctamente.');
            // Recargar la lista de usuarios
            if (appState.currentSection === 'usuarios') {
                loadSection();
            }
        } else {
            alert('Error al actualizar la contraseña: ' + (response.error || 'Error desconocido'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al actualizar la contraseña. Por favor, intenta de nuevo.');
    }
}

window.addEventListener('popstate', () => {
    const section = window.location.hash.substring(1) || 'dashboard';
    navigateTo(section);
});

/**
 * Buscar enlaces de torrents automáticamente
 */
// Previsualizar video
let previewPlayer = null;

async function handlePreviewVideo() {
    const videoUrlInput = document.getElementById('video_url');
    const videoFileInput = document.getElementById('video_file');
    const previewContainer = document.getElementById('videoPreviewContainer');
    const previewPlayerDiv = document.getElementById('videoPreviewPlayer');

    if (!previewContainer || !previewPlayerDiv) {
        showNotification('Error: Contenedor de previsualización no encontrado', 'error');
        return;
    }

    let videoUrl = null;

    // Obtener URL del video desde el input o archivo subido
    if (videoUrlInput && videoUrlInput.value.trim()) {
        videoUrl = videoUrlInput.value.trim();
    } else if (videoFileInput && videoFileInput.files && videoFileInput.files[0]) {
        // Si hay un archivo seleccionado, crear una URL local para previsualización
        const file = videoFileInput.files[0];
        videoUrl = URL.createObjectURL(file);
    } else {
        showNotification('Por favor, ingresa una URL de video o selecciona un archivo', 'warning');
        return;
    }

    if (!videoUrl) {
        return;
    }

    // Mostrar el contenedor de previsualización
    previewContainer.style.display = 'block';

    // Limpiar el reproductor anterior si existe
    if (previewPlayer) {
        try {
            previewPlayer.destroy();
        } catch (e) {
            console.warn('Error al destruir reproductor anterior:', e);
        }
        previewPlayer = null;
    }

    previewPlayerDiv.innerHTML = '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff;"><i class="fas fa-spinner fa-spin"></i> Cargando video...</div>';

    // Esperar a que UnifiedVideoPlayer esté disponible
    if (typeof UnifiedVideoPlayer === 'undefined') {
        // Cargar el script si no está disponible
        const script = document.createElement('script');
        script.src = `${baseUrl}/js/video-player.js`;
        script.onload = () => {
            initPreviewPlayer(videoUrl, previewPlayerDiv);
        };
        script.onerror = () => {
            previewPlayerDiv.innerHTML = '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center;"><i class="fas fa-exclamation-triangle"></i><br>Error al cargar el reproductor</div>';
        };
        document.head.appendChild(script);
    } else {
        initPreviewPlayer(videoUrl, previewPlayerDiv);
    }
}

function initPreviewPlayer(videoUrl, container) {
    // Crear un contenedor temporal para el reproductor
    const tempContainer = document.createElement('div');
    tempContainer.id = 'tempPreviewPlayer';
    tempContainer.style.position = 'absolute';
    tempContainer.style.top = '0';
    tempContainer.style.left = '0';
    tempContainer.style.width = '100%';
    tempContainer.style.height = '100%';
    container.innerHTML = '';
    container.appendChild(tempContainer);

    try {
        previewPlayer = new UnifiedVideoPlayer('tempPreviewPlayer', {
            autoplay: false,
            controls: true,
            onError: (error) => {
                console.error('Error en previsualización:', error);
                container.innerHTML = `<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; padding: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i><br>
                    <p>Error al cargar el video</p>
                    <small>${error.message || 'Error desconocido'}</small>
                </div>`;
            }
        });

        previewPlayer.loadVideo(videoUrl).then(() => {
            console.log('Video de previsualización cargado');
        }).catch(error => {
            console.error('Error al cargar video de previsualización:', error);
            container.innerHTML = `<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; padding: 1rem;">
                <i class="fas fa-exclamation-triangle"></i><br>
                <p>Error al cargar el video</p>
                <small>${error.message || 'Error desconocido'}</small>
            </div>`;
        });
    } catch (error) {
        console.error('Error al inicializar reproductor de previsualización:', error);
        container.innerHTML = `<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; padding: 1rem;">
            <i class="fas fa-exclamation-triangle"></i><br>
            <p>Error al inicializar el reproductor</p>
            <small>${error.message || 'Error desconocido'}</small>
        </div>`;
    }
}

function closeVideoPreview() {
    const previewContainer = document.getElementById('videoPreviewContainer');
    if (previewContainer) {
        previewContainer.style.display = 'none';
    }

    // Limpiar el reproductor
    if (previewPlayer) {
        try {
            previewPlayer.destroy();
        } catch (e) {
            console.warn('Error al destruir reproductor:', e);
        }
        previewPlayer = null;
    }

    const previewPlayerDiv = document.getElementById('videoPreviewPlayer');
    if (previewPlayerDiv) {
        previewPlayerDiv.innerHTML = '';
    }
}

// Función para manejar clic en poster (buscar torrents)
function handlePosterClick(id, title, year, type) {
    if (typeof showTorrentModal === 'function') {
        showTorrentModal(id, title, year || null, type);
    } else {
        // Fallback: abrir modal de búsqueda de torrents
        handleSearchTorrent(null, { title, year, type, contentId: id });
    }
}

// Función para cargar información de IMDb
async function loadIMDbInfo(id, title, year, type) {
    const imdbElement = document.querySelector(`.imdb-info[data-id="${id}"]`);
    if (!imdbElement) return;

    const originalHTML = imdbElement.innerHTML;
    imdbElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
    imdbElement.style.pointerEvents = 'none';

    try {
        const baseUrl = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
        const url = `${baseUrl}/api/imdb/search.php?title=${encodeURIComponent(title)}&year=${encodeURIComponent(year || '')}&type=${encodeURIComponent(type)}`;

        const response = await fetch(url, { credentials: 'same-origin' });
        const data = await response.json();

        if (data.success && data.data) {
            const info = data.data;
            const rating = info.imdb_rating || 'N/A';
            const source = info.source || 'imdb';

            imdbElement.innerHTML = `<i class="fab fa-imdb"></i> ${rating} (${source})`;
            imdbElement.title = `Rating: ${rating} | Fuente: ${source}`;

            // Actualizar rating en la base de datos si es diferente
            if (rating !== 'N/A' && parseFloat(rating) > 0) {
                updateContentRating(id, parseFloat(rating));
            }
        } else {
            imdbElement.innerHTML = '<i class="fab fa-imdb"></i> N/A';
            imdbElement.title = 'No se encontró información';
        }
    } catch (error) {
        console.error('Error cargando información de IMDb:', error);
        imdbElement.innerHTML = originalHTML;
        showNotification('Error al cargar información de IMDb', 'error');
    } finally {
        imdbElement.style.pointerEvents = 'auto';
    }
}

// Función para actualizar rating en la base de datos
async function updateContentRating(id, rating) {
    try {
        const baseUrl = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
        const response = await fetch(`${baseUrl}/api/movies/index.php?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: parseInt(id), rating: rating })
        });

        if (response.ok) {
            console.log(`Rating actualizado para contenido ${id}: ${rating}`);
        }
    } catch (error) {
        console.error('Error actualizando rating:', error);
    }
}

async function handleSearchTorrent(event, presetQuery = null) {
    if (event) {
        event.preventDefault();
    }
    const titleInput = document.getElementById('title');
    const yearInput = document.getElementById('release_year');
    const typeInput = document.getElementById('content-type');
    const torrentInput = document.getElementById('torrent_magnet');
    const resultsDiv = document.getElementById('torrent-results');
    const resultsContent = document.getElementById('torrent-results-content');
    const searchBtn = document.getElementById('searchTorrentBtn');
    const retryBtn = document.getElementById('retryTorrentBtn');

    if (!resultsDiv || !resultsContent) {
        return;
    }

    const title = presetQuery?.title ?? titleInput?.value.trim();
    if (!title) {
        showNotification('Por favor, ingresa un título primero', 'warning');
        return;
    }

    const year = presetQuery?.year ?? yearInput?.value ?? '';
    const type = presetQuery?.type ?? typeInput?.value ?? 'movie';

    window.__lastTorrentQuery = { title, year, type };

    const loadingBtn = presetQuery?.trigger === 'retry' ? retryBtn : searchBtn;
    if (loadingBtn) {
        loadingBtn.disabled = true;
        loadingBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    }
    resultsDiv.style.display = 'block';
    resultsContent.innerHTML = '<p style="text-align: center; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i> Buscando torrents...</p>';

    try {
        const url = `/api/torrent/search.php?title=${encodeURIComponent(title)}&year=${encodeURIComponent(year)}&type=${encodeURIComponent(type)}`;

        const response = await fetch(url, {
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success && data.results && data.results.length > 0) {
            let html = '<div style="margin-bottom: 0.5rem; font-weight: 600;">Encontrados ' + data.count + ' resultados:</div>';

            data.results.forEach((torrent) => {
                const safeMagnet = (torrent.magnet || '').replace(/'/g, "\\'");
                const qualityBadge = torrent.quality && torrent.quality !== 'Unknown' ? `<span style="background: #e50914; color: white; padding: 0.2rem 0.5rem; border-radius: 3px; font-size: 0.75rem; margin-right: 0.5rem;">${torrent.quality}</span>` : '';
                const seedsInfo = torrent.seeds > 0 ? `<span style="color: #28a745;"><i class="fas fa-arrow-up"></i> ${torrent.seeds}</span>` : '';
                const sizeInfo = torrent.size ? `<span style="color: #666; margin-left: 0.5rem;"><i class="fas fa-hdd"></i> ${torrent.size}</span>` : '';

                html += `
                    <div style="padding: 0.75rem; margin-bottom: 0.5rem; background: white; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.2s;" 
                         onmouseover="this.style.borderColor='#e50914'; this.style.boxShadow='0 2px 4px rgba(229,9,20,0.2)'"
                         onmouseout="this.style.borderColor='#ddd'; this.style.boxShadow='none'"
                         onclick="selectTorrent('${safeMagnet}')">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; margin-bottom: 0.25rem;">${torrent.title}</div>
                                <div style="font-size: 0.85rem; color: #666;">
                                    ${qualityBadge}
                                    ${seedsInfo}
                                    ${sizeInfo}
                                    <span style="margin-left: 0.5rem; color: #999;">${torrent.source}</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="event.stopPropagation(); selectTorrent('${safeMagnet}')">
                                <i class="fas fa-check"></i> Usar
                            </button>
                        </div>
                    </div>
                `;
            });

            resultsContent.innerHTML = html;
        } else {
            resultsContent.innerHTML = '<p style="text-align: center; padding: 1rem; color: #666;">No se encontraron resultados. Puedes ingresar el enlace magnet manualmente.</p>';
        }
    } catch (error) {
        console.error('Error al buscar torrents:', error);
        resultsContent.innerHTML = '<p style="text-align: center; padding: 1rem; color: #dc3545;">Error al buscar torrents. Intenta nuevamente o ingresa el enlace manualmente.</p>';
    } finally {
        if (loadingBtn) {
            loadingBtn.disabled = false;
            loadingBtn.innerHTML = loadingBtn === retryBtn ? '<i class="fas fa-redo"></i> Reintentar' : '<i class="fas fa-search"></i> Buscar';
        }
    }
}

function handleInvalidTorrent(e) {
    if (e) {
        e.preventDefault();
    }
    if (!window.__lastTorrentQuery) {
        showNotification('Busca torrents primero antes de reintentar.', 'warning');
        return;
    }
    showNotification('Rebuscando torrents en fuentes alternativas...', 'info');
    handleSearchTorrent(null, { ...window.__lastTorrentQuery, trigger: 'retry' });
}

/**
 * Seleccionar un torrent de los resultados
 */
function selectTorrent(magnetLink) {
    const torrentInput = document.getElementById('torrent_magnet');
    const resultsDiv = document.getElementById('torrent-results');

    if (torrentInput) {
        torrentInput.value = magnetLink;
        showNotification('Enlace magnet seleccionado', 'success');
        window.__selectedTorrent = magnetLink;
    }

    if (resultsDiv) {
        resultsDiv.style.display = 'none';
    }
}

// Hacer la función selectTorrent disponible globalmente
window.selectTorrent = selectTorrent;

// ============================================
// ACTUALIZACIÓN AUTOMÁTICA DE CONTENIDO
// ============================================
function initContentRefresh() {
    const btnRefresh = document.getElementById('btn-refresh-content');
    if (!btnRefresh) {
        console.log('Botón btn-refresh-content no encontrado, reintentando en 500ms...');
        setTimeout(initContentRefresh, 500);
        return;
    }

    // Si ya tiene el listener, no hacer nada
    if (btnRefresh.hasAttribute('data-listener-attached')) {
        return;
    }

    btnRefresh.setAttribute('data-listener-attached', 'true');
    console.log('Inicializando botón de actualización de contenido');

    btnRefresh.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Botón de actualización clickeado');
        const type = document.getElementById('refresh-type')?.value || 'movie';
        const limit = parseInt(document.getElementById('refresh-limit')?.value || '30');
        const sinceDays = parseInt(document.getElementById('refresh-days')?.value || '7');
        const minSeeds = parseInt(document.getElementById('refresh-seeds')?.value || '10');
        const dryRun = document.getElementById('refresh-dry-run')?.checked || false;
        const statusDiv = document.getElementById('refresh-status');
        const outputDiv = document.getElementById('refresh-output');
        const btn = this;

        // Validar parámetros
        if (limit < 1 || limit > 100) {
            showNotification('El límite debe estar entre 1 y 100', 'error');
            return;
        }
        if (sinceDays < 0 || sinceDays > 365) {
            showNotification('Los días deben estar entre 0 y 365', 'error');
            return;
        }

        // Deshabilitar botón y mostrar estado
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
        statusDiv.textContent = 'Procesando...';
        statusDiv.style.color = '#e50914';
        outputDiv.style.display = 'none';
        outputDiv.textContent = '';

        try {
            const apiUrl = (baseUrl || '') + '/api/content/refresh-latest.php';
            console.log('Llamando a:', apiUrl);
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({
                    type: type,
                    limit: limit,
                    since_days: sinceDays,
                    min_seeds: minSeeds,
                    dry_run: dryRun
                })
            });

            console.log('Respuesta recibida:', response.status, response.statusText);

            const responseText = await response.text();
            console.log('Respuesta texto:', responseText);
            
            if (!response.ok) {
                console.error('Error HTTP:', response.status, responseText);
                // Intentar parsear JSON de error
                try {
                    const errJson = JSON.parse(responseText);
                    throw new Error(`Error ${response.status}: ${errJson.error || response.statusText}`);
                } catch (_) {
                    throw new Error(`Error ${response.status}: ${response.statusText}`);
                }
            }

            let data;
            try {
                data = JSON.parse(responseText);
                console.log('Datos parseados:', data);
            } catch (parseError) {
                console.error('Error al parsear JSON:', parseError);
                console.error('Texto recibido:', responseText);
                throw new Error('Error al parsear respuesta del servidor: ' + parseError.message);
            }

            if (data.success) {
                statusDiv.textContent = '✅ Completado';
                statusDiv.style.color = '#46d369';
                
                const result = data.data;
                const summary = `Creados: ${result.created || 0} | Actualizados: ${result.updated || 0} | Episodios nuevos: ${result.new_episodes || 0} | Tiempo: ${result.execution_time || 'N/A'}`;
                
                outputDiv.textContent = summary + '\n\n' + (result.output || '');
                outputDiv.style.display = 'block';
                
                showNotification(
                    `Actualización completada: ${result.created || 0} creados, ${result.updated || 0} actualizados, ${result.new_episodes || 0} episodios nuevos`,
                    'success'
                );

                // Recargar página después de 3 segundos si no es dry-run
                if (!dryRun && (result.created > 0 || result.updated > 0)) {
                    setTimeout(() => {
                        if (confirm('¿Recargar la página para ver los cambios?')) {
                            window.location.reload();
                        }
                    }, 3000);
                }
            } else {
                statusDiv.textContent = '⚠️ Completado con advertencias';
                statusDiv.style.color = '#ffa500';
                
                const result = data.data || {};
                const summary = `Creados: ${result.created || 0} | Actualizados: ${result.updated || 0} | Episodios nuevos: ${result.new_episodes || 0} | Tiempo: ${result.execution_time || 'N/A'}`;
                
                let errorMsg = data.error || data.message || 'Error desconocido';
                if (result.output) {
                    errorMsg += '\n\n' + result.output;
                }
                
                outputDiv.textContent = summary + '\n\n' + errorMsg;
                outputDiv.style.display = 'block';
                outputDiv.style.color = '#ffa500';
                
                showNotification('Actualización completada con advertencias. Revisa la salida para más detalles.', 'warning');
            }
        } catch (error) {
            console.error('Error en actualización:', error);
            statusDiv.textContent = '❌ Error';
            statusDiv.style.color = '#e50914';
            outputDiv.textContent = 'Error de conexión: ' + error.message;
            outputDiv.style.display = 'block';
            showNotification('Error de conexión al actualizar contenido', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar Novedades';
        }
    });
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initContentRefresh);
} else {
    initContentRefresh();
}
