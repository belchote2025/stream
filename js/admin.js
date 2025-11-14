// Estado de la aplicación
const appState = {
    currentUser: {
        id: 1,
        name: 'Administrador',
        email: 'admin@urrestv.com',
        role: 'admin',
        avatar: '/streaming-platform/assets/img/default-poster.svg'
    },
    currentSection: 'dashboard',
    currentSubsection: '',
    isSidebarCollapsed: false,
    editingItemId: null // ID del item que se está editando
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

function toggleUserMenu() {
    const userMenu = elements.userMenu || document.querySelector('.user-menu');
    if (userMenu) {
        userMenu.classList.toggle('active');
    }
}

function toggleNotifications() {
    const notifications = elements.notifications || document.querySelector('.notifications');
    if (notifications) {
        notifications.classList.toggle('active');
    }
}

// Inicialización de la aplicación
function init() {
    // Cargar datos del usuario actual
    loadUserData();
    
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
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }
    
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
    
    // Formulario de contenido
    const contentForm = document.getElementById('contentForm');
    if (contentForm) {
        contentForm.addEventListener('submit', handleContentSubmit);
        
        // Validación de archivos de video
        const videoFileInput = document.getElementById('video_file');
        if (videoFileInput) {
            videoFileInput.addEventListener('change', function(e) {
                validateFileInput(e.target, 'video_file_info', 2147483648); // 2GB
            });
        }
        
        // Validación de archivos de tráiler
        const trailerFileInput = document.getElementById('trailer_file');
        if (trailerFileInput) {
            trailerFileInput.addEventListener('change', function(e) {
                validateFileInput(e.target, 'trailer_file_info', 524288000); // 500MB
            });
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
            avatar.src = appState.currentUser.avatar || '/streaming-platform/assets/img/default-poster.svg';
            avatar.onerror = function() {
                this.src = '/streaming-platform/assets/img/default-poster.svg';
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
            content = renderSubscriptions();
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
            avatar: user.avatar_url || '/streaming-platform/assets/img/default-poster.svg'
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
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Usuarios Totales</h3>
                    <p class="stat-number">${finalStats.totalUsers.toLocaleString()}</p>
                    <p class="stat-change ${usersChangeClass}">
                        <i class="fas fa-arrow-${finalStats.usersChangePercent >= 0 ? 'up' : 'down'}"></i>
                        ${finalStats.newUsersThisMonth} este mes (${usersChangeText})
                    </p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-film"></i>
                </div>
                <div class="stat-info">
                    <h3>Películas</h3>
                    <p class="stat-number">${finalStats.totalMovies.toLocaleString()}</p>
                    <p class="stat-change ${finalStats.newMoviesThisMonth > 0 ? 'positive' : 'neutral'}">
                        <i class="fas fa-arrow-up"></i>
                        +${finalStats.newMoviesThisMonth} este mes
                    </p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-tv"></i>
                </div>
                <div class="stat-info">
                    <h3>Series</h3>
                    <p class="stat-number">${finalStats.totalSeries.toLocaleString()}</p>
                    <p class="stat-change ${finalStats.newSeriesThisMonth > 0 ? 'positive' : 'neutral'}">
                        <i class="fas fa-arrow-up"></i>
                        +${finalStats.newSeriesThisMonth} este mes
                    </p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Ingresos Mensuales</h3>
                    <p class="stat-number">$${finalStats.monthlyRevenue.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                    <p class="stat-change ${revenueChangeClass}">
                        <i class="fas fa-arrow-${finalStats.revenueChangePercent >= 0 ? 'up' : 'down'}"></i>
                        ${revenueChangeText}
                    </p>
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
                                        <img src="${user.avatar}" alt="${user.name}" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
    
    return `
        <div class="content-header">
            <h1>${title}</h1>
            <button class="btn btn-primary btn-add-new">
                <i class="fas fa-plus"></i> Agregar Nuevo
            </button>
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
                        <th>Premium</th>
                        <th>Destacado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.length > 0 ? items.map(item => `
                        <tr data-id="${item.id}">
                            <td>
                                <img src="${item.poster_url || '/streaming-platform/assets/img/default-poster.svg'}" alt="${item.title || 'Sin título'}" class="thumbnail" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
                            </td>
                            <td>${item.title || 'Sin título'}</td>
                            <td>${item.release_year || 'N/A'}</td>
                            <td>${item.genres ? (Array.isArray(item.genres) ? item.genres.join(', ') : item.genres) : 'N/A'}</td>
                            <td>${isMovie ? `${item.duration || 0} min` : (item.episodes || 'N/A')}</td>
                            <td>${item.is_premium ? '<span class="badge premium">Sí</span>' : '<span class="badge free">No</span>'}</td>
                            <td>${item.is_featured ? '<span class="badge premium">Sí</span>' : '<span class="badge free">No</span>'}</td>
                            <td class="actions">
                                <button class="btn btn-sm btn-view" title="Ver" data-id="${item.id}"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-edit" title="Editar" data-id="${item.id}"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-delete" title="Eliminar" data-id="${item.id}"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('') : '<tr><td colspan="8" class="text-center">No hay contenido para mostrar.</td></tr>'}
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
                                                src="${user.avatar_url || '/streaming-platform/assets/img/default-poster.svg'}" 
                                                alt="${user.full_name || user.username}" 
                                                class="user-avatar"
                                                onerror="this.src='/streaming-platform/assets/img/default-poster.svg'"
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

// Renderizar suscripciones
function renderSubscriptions() {
    return `
        <div class="content-header">
            <h1>Suscripciones</h1>
            <button class="btn btn-primary" id="add-subscription-btn">
                <i class="fas fa-plus"></i> Nueva Suscripción
            </button>
        </div>
        
        <!-- Estadísticas de suscripciones -->
        <div class="subscription-stats">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Suscriptores</h3>
                    <p class="stat-number">1,234</p>
                    <p class="stat-change positive">+12% este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-info">
                    <h3>Premium</h3>
                    <p class="stat-number">856</p>
                    <p class="stat-change positive">+8% este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-home"></i>
                </div>
                <div class="stat-info">
                    <h3>Familiar</h3>
                    <p class="stat-number">234</p>
                    <p class="stat-change positive">+5% este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Ingresos Mensuales</h3>
                    <p class="stat-number">$12,456</p>
                    <p class="stat-change positive">+18% este mes</p>
                </div>
            </div>
        </div>
        
        <!-- Filtros y búsqueda -->
        <div class="subscription-filters">
            <div class="filter-group">
                <input type="text" id="subscription-search" class="form-control" placeholder="Buscar por usuario, email o ID...">
                <i class="fas fa-search"></i>
            </div>
            <div class="filter-group">
                <select id="plan-filter" class="form-control">
                    <option value="">Todos los planes</option>
                    <option value="free">Básico</option>
                    <option value="premium">Premium</option>
                    <option value="family">Familiar</option>
                </select>
            </div>
            <div class="filter-group">
                <select id="status-filter" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="active">Activas</option>
                    <option value="cancelled">Canceladas</option>
                    <option value="expired">Expiradas</option>
                </select>
            </div>
            <div class="filter-group">
                <button class="btn btn-outline" id="export-subscriptions">
                    <i class="fas fa-download"></i> Exportar
                </button>
            </div>
        </div>
        
        <!-- Planes disponibles -->
        <div class="subscription-section">
            <div class="section-header">
                <h2>Planes Disponibles</h2>
                <button class="btn btn-outline" id="manage-plans-btn">
                    <i class="fas fa-cog"></i> Gestionar Planes
                </button>
        </div>
        
        <div class="subscription-plans">
            <div class="plan-card">
                <div class="plan-header">
                    <h3>Plan Básico</h3>
                    <div class="plan-price">
                        <span class="amount">$0</span>
                        <span class="period">/mes</span>
                    </div>
                        <p class="plan-description">Ideal para empezar</p>
                </div>
                <div class="plan-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Acceso a contenido básico</li>
                        <li><i class="fas fa-check"></i> Calidad estándar (SD)</li>
                        <li><i class="fas fa-check"></i> Un dispositivo a la vez</li>
                            <li class="disabled"><i class="fas fa-times"></i> Sin anuncios</li>
                            <li class="disabled"><i class="fas fa-times"></i> Sin descargas</li>
                    </ul>
                </div>
                    <div class="plan-stats">
                        <span><strong>145</strong> usuarios</span>
                    </div>
                <div class="plan-actions">
                    <button class="btn btn-outline" disabled>Plan Actual</button>
                </div>
            </div>
            
            <div class="plan-card featured">
                <div class="plan-badge">Recomendado</div>
                <div class="plan-header">
                    <h3>Plan Premium</h3>
                    <div class="plan-price">
                        <span class="amount">$9.99</span>
                        <span class="period">/mes</span>
                    </div>
                        <p class="plan-description">La mejor experiencia</p>
                </div>
                <div class="plan-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Acceso a todo el contenido</li>
                        <li><i class="fas fa-check"></i> Calidad Full HD</li>
                        <li><i class="fas fa-check"></i> Hasta 4 dispositivos</li>
                        <li><i class="fas fa-check"></i> Sin anuncios</li>
                        <li><i class="fas fa-check"></i> Descargas ilimitadas</li>
                    </ul>
                </div>
                    <div class="plan-stats">
                        <span><strong>856</strong> usuarios</span>
                    </div>
                <div class="plan-actions">
                    <button class="btn btn-primary">Actualizar a Premium</button>
                </div>
            </div>
            
            <div class="plan-card">
                <div class="plan-header">
                    <h3>Plan Familiar</h3>
                    <div class="plan-price">
                        <span class="amount">$14.99</span>
                        <span class="period">/mes</span>
                    </div>
                        <p class="plan-description">Para toda la familia</p>
                </div>
                <div class="plan-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Acceso a todo el contenido</li>
                        <li><i class="fas fa-check"></i> Calidad 4K Ultra HD</li>
                        <li><i class="fas fa-check"></i> Hasta 6 perfiles</li>
                        <li><i class="fas fa-check"></i> Sin anuncios</li>
                        <li><i class="fas fa-check"></i> Descargas ilimitadas</li>
                    </ul>
                </div>
                    <div class="plan-stats">
                        <span><strong>234</strong> usuarios</span>
                    </div>
                <div class="plan-actions">
                    <button class="btn btn-outline">Seleccionar Plan</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de suscripciones activas -->
        <div class="subscription-section">
            <div class="section-header">
                <h2>Suscripciones Activas</h2>
                <div class="section-actions">
                    <button class="btn btn-outline" id="refresh-subscriptions">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
            </div>
            
            <div class="table-container">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Plan</th>
                                <th>Fecha Inicio</th>
                                <th>Próximo Pago</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
                                        <span>Juan Pérez</span>
                                    </div>
                                </td>
                                <td>juan.perez@email.com</td>
                                <td><span class="badge premium">Premium</span></td>
                                <td>15/10/2023</td>
                                <td>15/11/2023</td>
                                <td><span class="status active">Activa</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" title="Ver detalles"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon danger" title="Cancelar"><i class="fas fa-times"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
                                        <span>María García</span>
                                    </div>
                                </td>
                                <td>maria.garcia@email.com</td>
                                <td><span class="badge family">Familiar</span></td>
                                <td>01/10/2023</td>
                                <td>01/11/2023</td>
                                <td><span class="status active">Activa</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" title="Ver detalles"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon danger" title="Cancelar"><i class="fas fa-times"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
                                        <span>Carlos López</span>
                                    </div>
                                </td>
                                <td>carlos.lopez@email.com</td>
                                <td><span class="badge premium">Premium</span></td>
                                <td>20/09/2023</td>
                                <td>20/10/2023</td>
                                <td><span class="status active">Activa</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon" title="Ver detalles"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Editar"><i class="fas fa-edit"></i></button>
                                        <button class="btn-icon danger" title="Cancelar"><i class="fas fa-times"></i></button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Historial de facturación -->
        <div class="subscription-section">
            <div class="section-header">
                <h2>Historial de Facturación</h2>
                <div class="section-actions">
                    <select id="billing-period" class="form-control">
                        <option value="all">Todos los períodos</option>
                        <option value="month">Este mes</option>
                        <option value="quarter">Este trimestre</option>
                        <option value="year">Este año</option>
                    </select>
                </div>
            </div>
            
            <div class="table-container">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID de Factura</th>
                                <th>Usuario</th>
                            <th>Fecha</th>
                            <th>Plan</th>
                            <th>Monto</th>
                                <th>Método de Pago</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                                <td><strong>#INV-2023-001</strong></td>
                                <td>Juan Pérez</td>
                            <td>10/10/2023</td>
                            <td>Premium Mensual</td>
                                <td><strong>$9.99</strong></td>
                                <td><i class="fab fa-cc-visa"></i> Visa •••• 4242</td>
                            <td><span class="status active">Pagado</span></td>
                                <td>
                                    <button class="btn-link" title="Ver factura">
                                        <i class="fas fa-file-invoice"></i> Ver
                                    </button>
                                </td>
                        </tr>
                        <tr>
                                <td><strong>#INV-2023-002</strong></td>
                                <td>María García</td>
                                <td>10/10/2023</td>
                                <td>Familiar Mensual</td>
                                <td><strong>$14.99</strong></td>
                                <td><i class="fab fa-cc-paypal"></i> PayPal</td>
                                <td><span class="status active">Pagado</span></td>
                                <td>
                                    <button class="btn-link" title="Ver factura">
                                        <i class="fas fa-file-invoice"></i> Ver
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#INV-2023-003</strong></td>
                                <td>Carlos López</td>
                            <td>10/09/2023</td>
                            <td>Premium Mensual</td>
                                <td><strong>$9.99</strong></td>
                                <td><i class="fab fa-cc-mastercard"></i> Mastercard •••• 8888</td>
                            <td><span class="status active">Pagado</span></td>
                                <td>
                                    <button class="btn-link" title="Ver factura">
                                        <i class="fas fa-file-invoice"></i> Ver
                                    </button>
                                </td>
                        </tr>
                        <tr>
                                <td><strong>#INV-2023-004</strong></td>
                                <td>Ana Martínez</td>
                                <td>09/10/2023</td>
                            <td>Premium Mensual</td>
                                <td><strong>$9.99</strong></td>
                                <td><i class="fab fa-cc-visa"></i> Visa •••• 1234</td>
                                <td><span class="status warning">Pendiente</span></td>
                                <td>
                                    <button class="btn-link" title="Ver factura">
                                        <i class="fas fa-file-invoice"></i> Ver
                                    </button>
                                </td>
                        </tr>
                    </tbody>
                </table>
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Poster" class="content-thumb" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Poster" class="content-thumb" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Poster" class="content-thumb" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Poster" class="content-thumb" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Poster" class="content-thumb" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
                                        <img src="/streaming-platform/assets/img/default-poster.svg" alt="Usuario" class="user-avatar" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
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
window.copyApiKey = function() {
    const apiKeyInput = document.getElementById('api-key');
    if (apiKeyInput) {
        apiKeyInput.select();
        document.execCommand('copy');
        showNotification('Clave API copiada al portapapeles', 'success');
    }
};

// Función para regenerar API secret
window.regenerateApiSecret = function() {
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
        const fields = ['id', 'title', 'release_year', 'duration', 'description', 'poster_url', 'backdrop_url', 'video_url', 'trailer_url', 'age_rating', 'type'];
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
        
        // Checkboxes especiales
        if (form.elements.is_featured) form.elements.is_featured.checked = !!itemData.is_featured;
        if (form.elements.is_trending) form.elements.is_trending.checked = !!itemData.is_trending;
        if (form.elements.is_premium) form.elements.is_premium.checked = !!itemData.is_premium;
        
        // Tipo de contenido
        if (form.elements.type) {
            form.elements.type.value = itemData.type || 'movie';
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
            const endpoint = type === 'peliculas' || type === 'series' 
                ? `/api/movies/index.php?id=${id}`
                : `/api/${type}/${id}`;
            
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
        // Validar archivos antes de subir
        const videoFileInput = document.getElementById('video_file');
        const trailerFileInput = document.getElementById('trailer_file');
        
        if (videoFileInput && videoFileInput.files[0]) {
            if (!validateFileInput(videoFileInput, 'video_file_info', 2147483648)) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
        }
        
        if (trailerFileInput && trailerFileInput.files[0]) {
            if (!validateFileInput(trailerFileInput, 'trailer_file_info', 524288000)) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
                return;
            }
        }
        
        // Subir archivos de video si existen
        let videoUrl = formData.get('video_url') || '';
        let trailerUrl = formData.get('trailer_url') || '';
        
        const videoFile = formData.get('video_file');
        if (videoFile && videoFile.size > 0) {
            showNotification('Subiendo video...', 'info');
            const videoUploadData = new FormData();
            videoUploadData.append('file', videoFile);
            
            const videoUploadResponse = await fetch('/streaming-platform/api/upload/video.php', {
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
        }
        
        const trailerFile = formData.get('trailer_file');
        if (trailerFile && trailerFile.size > 0) {
            showNotification('Subiendo tráiler...', 'info');
            const trailerUploadData = new FormData();
            trailerUploadData.append('file', trailerFile);
            trailerUploadData.append('is_trailer', '1');
            
            const trailerUploadResponse = await fetch('/streaming-platform/api/upload/video.php', {
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
        }
        
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
            poster_url: data.poster_url || '',
            backdrop_url: data.backdrop_url || '',
            video_url: videoUrl,
            trailer_url: trailerUrl,
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
        const baseUrl = '/streaming-platform';
        
        // Si el endpoint ya incluye la ruta base, no duplicarla
        let fullEndpoint;
        if (endpoint.startsWith('http')) {
            fullEndpoint = endpoint;
        } else if (endpoint.startsWith('/streaming-platform')) {
            // Ya tiene la ruta base, usarla tal cual
            fullEndpoint = endpoint;
        } else if (endpoint.startsWith('/')) {
            // Ruta absoluta, añadir base
            fullEndpoint = baseUrl + endpoint;
        } else {
            // Ruta relativa, añadir base y slash
            fullEndpoint = baseUrl + '/' + endpoint;
        }
        
        // Añadir headers por defecto
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        options.headers = { ...defaultHeaders, ...(options.headers || {}) };
        
        // Incluir credenciales (cookies de sesión) en las peticiones
        options.credentials = 'same-origin';
        
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
document.addEventListener('DOMContentLoaded', init);

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
