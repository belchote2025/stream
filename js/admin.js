// Estado de la aplicación
const appState = {
    currentUser: {
        id: 1,
        name: 'Administrador',
        email: 'admin@streamingplus.com',
        role: 'admin',
        avatar: 'assets/images/avatar.png'
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
    // Menú de navegación
    const navLinks = document.querySelectorAll('.admin-nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
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
    if (elements.searchButton) {
        elements.searchButton.addEventListener('click', handleSearch);
    }
    
    if (elements.searchInput) {
        elements.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });
    }
    
    // Menú de usuario
    if (elements.userMenu) {
        elements.userMenu.addEventListener('click', toggleUserMenu);
    }
    
    // Notificaciones
    if (elements.notifications) {
        elements.notifications.addEventListener('click', toggleNotifications);
    }
    
    // Modal
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('close-modal') || e.target.classList.contains('close-modal-btn')) {
            closeModal();
        }
    });
    
    // Formulario de contenido
    const contentForm = document.getElementById('contentForm');
    if (contentForm) {
        contentForm.addEventListener('submit', handleContentSubmit);
    }
    
    // Cerrar modal al hacer clic fuera
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('contentModal');
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // Botones de acción en tablas
    document.addEventListener('click', (e) => {
        // Botón de editar
        if (e.target.closest('.btn-view')) {
            const row = e.target.closest('tr');
            const id = row.dataset.id;
            const type = appState.currentSubsection || appState.currentSection;
            viewItem(id, type);
        }

        // Botón de editar
        if (e.target.closest('.btn-edit')) {
            const btn = e.target.closest('.btn-edit');
            const id = btn.dataset.id || e.target.closest('tr')?.dataset.id;
            if (id) {
                const type = appState.currentSubsection || appState.currentSection;
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
                const type = appState.currentSubsection || appState.currentSection;
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
        
        if (avatar) avatar.src = appState.currentUser.avatar || 'assets/images/avatar.png';
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
            content = renderDashboard();
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
                const response = await apiRequest('/streaming-platform/js/index.php');
                const users = response.data || [];
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
function renderDashboard() {
    // Estadísticas
    const stats = {
        totalUsers: 1248,
        totalMovies: 356,
        totalSeries: 124,
        totalRevenue: 24589,
        newUsersThisMonth: 124,
        newContentThisMonth: 24,
        activeSubscriptions: 985,
        monthlyRevenue: 24589
    };
    
    // Actividades recientes
    const recentActivities = [
        {
            type: 'success',
            icon: 'plus',
            title: 'Nueva película añadida',
            description: 'Duna: Parte Dos',
            time: 'Hace 2 horas'
        },
        {
            type: 'warning',
            icon: 'exclamation-triangle',
            title: 'Problema de carga',
            description: 'Episodio 3 de La Casa de Papel',
            time: 'Hace 5 horas'
        },
        {
            type: 'info',
            icon: 'user-plus',
            title: 'Nuevo usuario registrado',
            description: 'usuario123',
            time: 'Ayer'
        },
        {
            type: 'primary',
            icon: 'credit-card',
            title: 'Suscripción premium activada',
            description: 'maria.garcia@email.com',
            time: 'Ayer'
        }
    ];
    
    // Usuarios recientes
    const recentUsers = [
        {
            id: 1,
            name: 'Carlos López',
            email: 'carlos@email.com',
            registrationDate: 'Hoy',
            plan: 'Gratis',
            status: 'Activo',
            avatar: 'assets/images/avatar1.jpg'
        },
        {
            id: 2,
            name: 'Ana Martínez',
            email: 'ana.mtz@email.com',
            registrationDate: 'Ayer',
            plan: 'Premium',
            status: 'Activo',
            avatar: 'assets/images/avatar2.jpg'
        },
        {
            id: 3,
            name: 'Roberto Sánchez',
            email: 'rsanchez@email.com',
            registrationDate: 'Hace 2 días',
            plan: 'Premium',
            status: 'Inactivo',
            avatar: 'assets/images/avatar3.jpg'
        },
        {
            id: 4,
            name: 'Laura García',
            email: 'laura.g@email.com',
            registrationDate: 'Hace 3 días',
            plan: 'Gratis',
            status: 'Activo',
            avatar: 'assets/images/avatar4.jpg'
        }
    ];
    
    // Generar HTML del dashboard
    return `
        <h1>Panel de Control</h1>
        
        <!-- Resumen -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3>Usuarios Totales</h3>
                    <p class="stat-number">${stats.totalUsers.toLocaleString()}</p>
                    <p class="stat-change positive">+${stats.newUsersThisMonth} este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-film"></i>
                </div>
                <div class="stat-info">
                    <h3>Películas</h3>
                    <p class="stat-number">${stats.totalMovies}</p>
                    <p class="stat-change positive">+${Math.floor(stats.newContentThisMonth / 2)} este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tv"></i>
                </div>
                <div class="stat-info">
                    <h3>Series</h3>
                    <p class="stat-number">${stats.totalSeries}</p>
                    <p class="stat-change positive">+${Math.ceil(stats.newContentThisMonth / 2)} este mes</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>Ingresos Mensuales</h3>
                    <p class="stat-number">$${stats.monthlyRevenue.toLocaleString()}</p>
                    <p class="stat-change positive">+5% este mes</p>
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
                ${recentActivities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon ${activity.type}">
                            <i class="fas fa-${activity.icon}"></i>
                        </div>
                        <div class="activity-details">
                            <p><strong>${activity.title}:</strong> ${activity.description}</p>
                            <span class="activity-time">${activity.time}</span>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>

        <!-- Últimos usuarios registrados -->
        <div class="recent-users">
            <div class="section-header">
                <h2>Últimos Usuarios</h2>
                <a href="#usuarios" class="view-all">Ver todos</a>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
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
                        ${recentUsers.map(user => `
                            <tr data-id="${user.id}">
                                <td>
                                    <div class="user-cell">
                                        <img src="${user.avatar}" alt="${user.name}">
                                        <span>${user.name}</span>
                                    </div>
                                </td>
                                <td>${user.email}</td>
                                <td>${user.registrationDate}</td>
                                <td><span class="badge ${user.plan.toLowerCase() === 'premium' ? 'premium' : 'free'}">${user.plan}</span></td>
                                <td><span class="status ${user.status.toLowerCase()}">${user.status}</span></td>
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
    return `
        <div class="content-header">
            <h1>Gestión de Usuarios</h1>
            <button class="btn btn-primary" id="add-user-btn">
                <i class="fas fa-user-plus"></i> Agregar Usuario
            </button>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <input type="text" id="user-search" placeholder="Buscar usuarios...">
                <button class="btn"><i class="fas fa-search"></i></button>
            </div>
            
            <div class="filter-group">
                <label for="filter-status">Estado:</label>
                <select id="filter-status" class="form-control">
                    <option value="">Todos</option>
                    <option value="active">Activos</option>
                    <option value="inactive">Inactivos</option>
                    <option value="suspended">Suspendidos</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter-plan">Plan:</label>
                <select id="filter-plan" class="form-control">
                    <option value="">Todos</option>
                    <option value="free">Gratis</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
            
            <button class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="data-table" data-type="users">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Registro</th>
                        <th>Último acceso</th>
                        <th>Plan</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${users.length > 0 ? users.map(user => `
                        <tr data-id="${user.id}">
                            <td>
                                <div class="user-cell">
                                    <img src="${user.avatar_url || 'assets/images/avatar.png'}" alt="${user.full_name || user.username}">
                                    <span>${user.full_name || user.username}</span>
                                </div>
                            </td>
                            <td>${user.email}</td>
                            <td>${formatDate(user.registrationDate)}</td>
                            <td>${formatDateTime(user.lastLogin)}</td>
                            <td><span class="badge ${user.plan}">${user.plan === 'premium' ? 'Premium' : 'Gratis'}</span></td>
                            <td><span class="status ${user.status}">${formatStatus(user.status)}</span></td>
                            <td class="actions">
                                <button class="btn btn-sm btn-view" title="Ver"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-edit" title="Editar"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('') : '<tr><td colspan="7" class="text-center">No hay usuarios para mostrar.</td></tr>'}
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <button class="btn btn-sm" disabled><i class="fas fa-chevron-left"></i> Anterior</button>
            <span>Página 1 de 2</span>
            <button class="btn btn-sm">Siguiente <i class="fas fa-chevron-right"></i></button>
        </div>
    `;
}

// Renderizar suscripciones
function renderSubscriptions() {
    return `
        <div class="content-header">
            <h1>Suscripciones</h1>
        </div>
        
        <div class="subscription-plans">
            <div class="plan-card">
                <div class="plan-header">
                    <h3>Plan Básico</h3>
                    <div class="plan-price">
                        <span class="amount">$0</span>
                        <span class="period">/mes</span>
                    </div>
                    <p>Ideal para empezar</p>
                </div>
                <div class="plan-features">
                    <ul>
                        <li><i class="fas fa-check"></i> Acceso a contenido básico</li>
                        <li><i class="fas fa-check"></i> Calidad estándar (SD)</li>
                        <li><i class="fas fa-check"></i> Un dispositivo a la vez</li>
                        <li><i class="fas fa-times"></i> Sin anuncios</li>
                        <li><i class="fas fa-times"></i> Sin descargas</li>
                    </ul>
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
                    <p>La mejor experiencia</p>
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
                    <p>Para toda la familia</p>
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
                <div class="plan-actions">
                    <button class="btn btn-outline">Seleccionar Plan</button>
                </div>
            </div>
        </div>
        
        <div class="billing-history">
            <h2>Historial de facturación</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID de factura</th>
                            <th>Fecha</th>
                            <th>Plan</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>#INV-2023-001</td>
                            <td>10/10/2023</td>
                            <td>Premium Mensual</td>
                            <td>$9.99</td>
                            <td><span class="status active">Pagado</span></td>
                            <td><a href="#" class="btn-link">Ver factura</a></td>
                        </tr>
                        <tr>
                            <td>#INV-2023-002</td>
                            <td>10/09/2023</td>
                            <td>Premium Mensual</td>
                            <td>$9.99</td>
                            <td><span class="status active">Pagado</span></td>
                            <td><a href="#" class="btn-link">Ver factura</a></td>
                        </tr>
                        <tr>
                            <td>#INV-2023-003</td>
                            <td>10/08/2023</td>
                            <td>Premium Mensual</td>
                            <td>$9.99</td>
                            <td><span class="status active">Pagado</span></td>
                            <td><a href="#" class="btn-link">Ver factura</a></td>
                        </tr>
                    </tbody>
                </table>
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
                <button class="btn btn-secondary">
                    <i class="fas fa-download"></i> Exportar PDF
                </button>
                <button class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
        
        <div class="report-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="report-type">Tipo de reporte:</label>
                    <select id="report-type" class="form-control">
                        <option value="general">General</option>
                        <option value="users">Usuarios</option>
                        <option value="content">Contenido</option>
                        <option value="revenue">Ingresos</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-range">Rango de fechas:</label>
                    <select id="date-range" class="form-control">
                        <option value="7">Últimos 7 días</option>
                        <option value="30" selected>Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                
                <div class="filter-group date-range-custom" style="display: none;">
                    <label for="start-date">Desde:</label>
                    <input type="date" id="start-date" class="form-control">
                    
                    <label for="end-date">Hasta:</label>
                    <input type="date" id="end-date" class="form-control">
                </div>
                
                <button class="btn btn-primary">
                    <i class="fas fa-chart-bar"></i> Generar Reporte
                </button>
            </div>
        </div>
        
        <div class="report-content">
            <div class="report-summary">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="summary-info">
                        <h3>1,248</h3>
                        <p>Usuarios totales</p>
                        <span class="trend positive">+12% este mes</span>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="summary-info">
                        <h3>24,589</h3>
                        <p>Reproducciones</p>
                        <span class="trend positive">+8% este mes</span>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="summary-info">
                        <h3>$24,589</h3>
                        <p>Ingresos</p>
                        <span class="trend negative">-3% este mes</span>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-film"></i>
                    </div>
                    <div class="summary-info">
                        <h3>356</h3>
                        <p>Películas</p>
                        <span class="trend positive">+5% este mes</span>
                    </div>
                </div>
            </div>
            
            <div class="report-charts">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Actividad de usuarios</h3>
                        <div class="chart-actions">
                            <button class="btn btn-sm btn-outline active">Día</button>
                            <button class="btn btn-sm btn-outline">Semana</button>
                            <button class="btn btn-sm btn-outline">Mes</button>
                        </div>
                    </div>
                    <div class="chart" id="user-activity-chart">
                        <!-- Gráfico se generará con una biblioteca como Chart.js -->
                        <div class="chart-placeholder">
                            <p>Gráfico de actividad de usuarios</p>
                        </div>
                    </div>
                </div>
                
                <div class="chart-row">
                    <div class="chart-container half">
                        <div class="chart-header">
                            <h3>Contenido más visto</h3>
                        </div>
                        <div class="chart" id="top-content-chart">
                            <div class="chart-placeholder">
                                <p>Gráfico de contenido más visto</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container half">
                        <div class="chart-header">
                            <h3>Distribución de suscriptores</h3>
                        </div>
                        <div class="chart" id="subscription-distribution-chart">
                            <div class="chart-placeholder">
                                <p>Gráfico de distribución de suscriptores</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="report-tables">
                <div class="table-container">
                    <div class="table-header">
                        <h3>Películas más populares</h3>
                        <a href="#" class="btn-link">Ver todo</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Película</th>
                                    <th>Vistas</th>
                                    <th>Valoración</th>
                                    <th>Ingresos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>El Padrino</td>
                                    <td>12,458</td>
                                    <td>4.8 <i class="fas fa-star"></i></td>
                                    <td>$8,720.60</td>
                                </tr>
                                <tr>
                                    <td>El Caballero Oscuro</td>
                                    <td>10,235</td>
                                    <td>4.9 <i class="fas fa-star"></i></td>
                                    <td>$7,164.50</td>
                                </tr>
                                <tr>
                                    <td>El Padrino: Parte II</td>
                                    <td>9,874</td>
                                    <td>4.7 <i class="fas fa-star"></i></td>
                                    <td>$6,911.80</td>
                                </tr>
                                <tr>
                                    <td>Cadena Perpetua</td>
                                    <td>8,963</td>
                                    <td>4.9 <i class="fas fa-star"></i></td>
                                    <td>$6,274.10</td>
                                </tr>
                                <tr>
                                    <td>Pulp Fiction</td>
                                    <td>8,452</td>
                                    <td>4.8 <i class="fas fa-star"></i></td>
                                    <td>$5,916.40</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="table-container">
                    <div class="table-header">
                        <h3>Usuarios más activos</h3>
                        <a href="#" class="btn-link">Ver todo</a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Plan</th>
                                    <th>Actividad</th>
                                    <th>Último acceso</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Carlos López</td>
                                    <td><span class="badge premium">Premium</span></td>
                                    <td>Alto</td>
                                    <td>Hoy, 14:30</td>
                                </tr>
                                <tr>
                                    <td>Ana Martínez</td>
                                    <td><span class="badge premium">Premium</span></td>
                                    <td>Alto</td>
                                    <td>Hoy, 10:15</td>
                                </tr>
                                <tr>
                                    <td>Laura García</td>
                                    <td><span class="badge free">Gratis</span></td>
                                    <td>Medio</td>
                                    <td>Ayer, 09:45</td>
                                </tr>
                                <tr>
                                    <td>Miguel Ángel Ramírez</td>
                                    <td><span class="badge premium">Premium</span></td>
                                    <td>Bajo</td>
                                    <td>Hace 2 días</td>
                                </tr>
                                <tr>
                                    <td>Roberto Sánchez</td>
                                    <td><span class="badge premium">Premium</span></td>
                                    <td>Bajo</td>
                                    <td>Hace 3 días</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
                            <input type="text" id="site-title" class="form-control" value="StreamingPlus">
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
                <div class="settings-tab" id="profile-tab" style="display: none;">
                    <!-- Contenido de la pestaña de perfil -->
                </div>
                
                <div class="settings-tab" id="security-tab" style="display: none;">
                    <!-- Contenido de la pestaña de seguridad -->
                </div>
                
                <div class="settings-tab" id="notifications-tab" style="display: none;">
                    <!-- Contenido de la pestaña de notificaciones -->
                </div>
                
                <div class="settings-tab" id="billing-tab" style="display: none;">
                    <!-- Contenido de la pestaña de facturación -->
                </div>
                
                <div class="settings-tab" id="api-tab" style="display: none;">
                    <!-- Contenido de la pestaña de API -->
                </div>
            </div>
        </div>
    `;
}

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
    // Esta función es un placeholder. Necesitarías un modal y formulario diferente para usuarios.
    // Por ahora, reutilizaremos el modal de contenido como ejemplo.
    appState.editingItemId = userData ? userData.id : null;
    elements.modalTitle.textContent = userData ? `Editar Usuario ${userData.username}` : 'Agregar Nuevo Usuario';
    
    // Aquí deberías mostrar un formulario específico para usuarios.
    alert('Funcionalidad para agregar/editar usuarios necesita un modal y formulario dedicados.');
}

// Función para editar un item
async function editItem(id, type) {
    try {
        const endpoint = type === 'peliculas' || type === 'series' 
            ? `/api/content/index.php?id=${id}`
            : `/api/${type}/${id}`;
        
        const response = await apiRequest(endpoint);
        if (response && response.data) {
            // Ajustar datos para el formulario
            const itemData = {
                ...response.data,
                is_featured: response.data.is_featured ? true : false,
                is_trending: response.data.is_trending ? true : false,
                is_premium: response.data.is_premium ? true : false
            };
            showContentModal(itemData);
        } else {
            showNotification('No se pudo obtener la información del elemento.', 'error');
        }
    } catch (error) {
        showNotification(`Error al cargar el elemento: ${error.message}`, 'error');
    }
}

// Función para ver un item
async function viewItem(id, type) {
    try {
        const item = await apiRequest(`/api/${type}/${id}`);
        if (item && item.data) {
            // Por ahora, mostramos una alerta. Idealmente, esto abriría un modal de vista detallada.
            const details = Object.entries(item.data)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\n');
            alert(`Detalles de ${item.data.title}:\n\n${details}`);
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

// Cerrar modal
function closeModal() {
    const modal = document.getElementById('contentModal');
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
        video_url: data.video_url || '',
        trailer_url: data.trailer_url || '',
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

    try {
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
        const fullEndpoint = endpoint.startsWith('http') ? endpoint : baseUrl + endpoint;
        
        // Añadir headers por defecto
        const defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        options.headers = { ...defaultHeaders, ...(options.headers || {}) };
        
        const response = await fetch(fullEndpoint, options);
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Respuesta no válida del servidor' }));
            throw new Error(errorData.error || `Error HTTP ${response.status}`);
        }

        // Si la respuesta es 204 No Content (como en un DELETE exitoso), no hay JSON que parsear
        if (response.status === 204) {
            return { success: true };
        }

        return await response.json();
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
    const tabItems = document.querySelectorAll('.settings-menu li');
    tabItems.forEach(item => {
        item.addEventListener('click', () => {
            const tabId = item.getAttribute('data-tab');
            
            // Actualizar pestaña activa
            document.querySelector('.settings-menu li.active').classList.remove('active');
            item.classList.add('active');
            
            // Mostrar contenido de la pestaña
            document.querySelector('.settings-tab.active').classList.remove('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        });
    });
    
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
window.addEventListener('popstate', () => {
    const section = window.location.hash.substring(1) || 'dashboard';
    navigateTo(section);
});
