// Estado de la aplicación
const appState = {
    currentUser: null,
    currentContent: 'movies', // 'movies' o 'series'
    currentSlide: 0,
    isLoggedIn: false,
    isAdmin: false,
    carouselInterval: null,
    contentCache: {
        movies: [],
        series: [],
        imdbMovies: [],
        localVideos: []
    },
    imdbCache: {},
    torrentCache: {}
};

const APP_BASE_URL = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';

// Elementos del DOM
const elements = {
    carouselInner: document.querySelector('.carousel-inner'),
    popularMovies: document.getElementById('popular-movies'),
    popularSeries: document.getElementById('popular-series'),
    imdbMovies: document.getElementById('imdb-movies'),
    localVideos: document.getElementById('local-videos'),
    navLinks: document.querySelectorAll('.nav-links a'),
    searchInput: document.querySelector('.search-container input'),
    userMenu: document.querySelector('.user-menu'),
    dropdown: document.querySelector('.dropdown'),
    logoutBtn: document.getElementById('logout'),
    carouselPrev: document.querySelector('.carousel-control.prev'),
    carouselNext: document.querySelector('.carousel-control.next'),
    videoPlayer: document.querySelector('.video-player'),
    closeVideo: document.querySelector('.close-video'),
    videoElement: document.getElementById('videoElement'),
    loginModal: document.querySelector('.login-modal'),
    closeModal: document.querySelector('.close-modal'),
    loginForm: document.getElementById('loginForm'),
    torrentModal: document.getElementById('torrentModal'),
    torrentModalTitle: document.getElementById('torrentModalTitle'),
    torrentSearchStatus: document.getElementById('torrentSearchStatus'),
    torrentResultsContainer: document.getElementById('torrentResultsContainer'),
    torrentResultsList: document.getElementById('torrentResultsList'),
    torrentIMDbContainer: document.getElementById('torrentIMDbContainer'),
    torrentModalClose: document.getElementById('torrentModalClose'),
    recommendedRow: document.getElementById('recommended-row')
};

// Inicialización de la aplicación
function init() {
    try {
        // Cargar contenido inicial solo si los elementos existen
        if (elements.carouselInner) {
            loadCarousel();
        }
        
        const hasDynamicRows = document.querySelector('.row-content[data-dynamic="true"]');
        if (hasDynamicRows) {
            // Cargar contenido dinámico con un pequeño delay para asegurar que el DOM esté listo
            setTimeout(() => {
                loadPopularContent();
            }, 100);
        }

        // Configurar event listeners
        setupEventListeners();
        
        // Inicializar controles del reproductor de video
        initVideoPlayerControls();
        
        // Simular inicio de sesión (en un entorno real, esto vendría de una autenticación real)
        simulateLogin();
    } catch (error) {
        console.error('Error en init():', error);
    }
}

// Configurar event listeners
function setupEventListeners() {
    // Navegación
    if (elements.navLinks && elements.navLinks.length > 0) {
        elements.navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.getAttribute('href').substring(1);
                navigateTo(target);
            });
        });
    }
    
    // Menú de usuario
    if (elements.userMenu) {
        elements.userMenu.addEventListener('click', toggleDropdown);
    }
    
    // Cerrar sesión
    if (elements.logoutBtn) {
        elements.logoutBtn.addEventListener('click', logout);
    }
    
    // Controles del carrusel
    if (elements.carouselPrev) {
        elements.carouselPrev.addEventListener('click', prevSlide);
    }
    if (elements.carouselNext) {
        elements.carouselNext.addEventListener('click', nextSlide);
    }
    
    // Búsqueda
    if (elements.searchInput) {
        elements.searchInput.addEventListener('input', handleSearch);
    }
    
    // Cerrar reproductor de video
    if (elements.closeVideo) {
        elements.closeVideo.addEventListener('click', closeVideoPlayer);
    }
    
    // Cerrar modal al hacer clic fuera
    window.addEventListener('click', (e) => {
        if (elements.loginModal && e.target === elements.loginModal) {
            closeLoginModal();
        }
    });
    
    // Cerrar modal con botón si existe
    if (elements.closeModal) {
        elements.closeModal.addEventListener('click', closeLoginModal);
    }
    
    // Enviar formulario de inicio de sesión
    if (elements.loginForm) {
        elements.loginForm.addEventListener('submit', handleLogin);
    }
    
    // Cambiar el estilo de la barra de navegación al hacer scroll
    window.addEventListener('scroll', handleScroll);

    // Modal de torrents
    if (elements.torrentModalClose) {
        elements.torrentModalClose.addEventListener('click', closeTorrentModal);
    }
    if (elements.torrentModal) {
        elements.torrentModal.addEventListener('click', (event) => {
            if (event.target === elements.torrentModal) {
                closeTorrentModal();
            }
        });
    }
    if (elements.torrentResultsList) {
        elements.torrentResultsList.addEventListener('click', handleTorrentResultClick);
    }
}

// Cargar el carrusel con contenido
async function loadCarousel() {
    if (!elements.carouselInner) {
        console.warn('Elemento carousel-inner no encontrado');
        return;
    }
    
    console.log('Cargando carrusel...');
    elements.carouselInner.innerHTML = '';

    try {
        const response = await fetch(`${APP_BASE_URL}/api/movies/?featured=true&limit=5`);
        if (!response.ok) throw new Error('Error al cargar el contenido del carrusel');
        const result = await response.json();
        const content = result.data;

        if (!content || content.length === 0) {
            elements.carouselInner.innerHTML = '<div class="carousel-item active" style="background-image: url(\'assets/images/placeholder-hero.jpg\');"><div class="carousel-content"><h1>Bienvenido a UrresTv</h1><p>Tu plataforma de streaming favorita. Contenido nuevo próximamente.</p></div></div>';
            return;
        }

        appState.contentCache.movies = [...appState.contentCache.movies, ...content];

        content.forEach((item, index) => {
            const slide = createCarouselSlide(item, index);
            elements.carouselInner.appendChild(slide);
        });

        updateCarousel();
        initCarouselControls();

    } catch (error) {
        console.error('Error en loadCarousel:', error);
        elements.carouselInner.innerHTML = '<div class="carousel-item active" style="background-image: url(\'assets/images/placeholder-hero.jpg\');"><div class="carousel-content"><h1>Error al cargar</h1><p>No se pudo conectar con el servidor. Inténtalo más tarde.</p></div></div>';
    }
}

function createCarouselSlide(item, index) {
        const slide = document.createElement('div');
        slide.className = index === 0 ? 'carousel-item active' : 'carousel-item';
        slide.style.backgroundImage = `linear-gradient(to right, rgba(0, 0, 0, 0.8) 20%, transparent 100%), url('${item.backdrop_url || item.poster_url}')`;

        slide.innerHTML = `
            <div class="carousel-content">
                <h1>${item.title}</h1>
                <p>${item.description}</p>
                <div class="carousel-buttons">
                    <button class="btn play-btn" data-id="${item.id}" data-type="movie">
                        <i class="fas fa-play"></i> Reproducir
                    </button>
                    <button class="btn btn-outline" 
                            style="padding: 12px 25px; font-size: 1.1em; background: rgba(255, 255, 255, 0.2);">
                        <i class="fas fa-info-circle"></i> Más información
                    </button>
                </div>
            </div>
        `;

    slide.querySelector('.play-btn').addEventListener('click', () => playContent(item.id, 'movie'));
    slide.querySelector('.btn-outline').addEventListener('click', () => {
        // Redirigir a la página de detalles del contenido
        window.location.href = `/content.php?id=${item.id}`;
    });

    return slide;
}

// Inicializar controles del carrusel
function initCarouselControls() {
    if (appState.carouselInterval) {
        clearInterval(appState.carouselInterval);
    }
    appState.carouselInterval = setInterval(nextSlide, 8000);
}

// Cargar contenido dinámico para las filas
async function loadPopularContent() {
    const dynamicContainers = document.querySelectorAll('.row-content[data-dynamic="true"]');
    if (!dynamicContainers.length) {
        console.log('No se encontraron contenedores dinámicos');
        return;
    }

    console.log(`Encontrados ${dynamicContainers.length} contenedores dinámicos`);
    
    // Cargar cada contenedor de forma secuencial para evitar sobrecarga
    for (const container of dynamicContainers) {
        await loadDynamicRow(container);
        // Pequeña pausa entre cargas para no sobrecargar el servidor
        await new Promise(resolve => setTimeout(resolve, 100));
    }
}

async function loadDynamicRow(container) {
    const type = container.dataset.type || '';
    const sort = container.dataset.sort || 'popular';
    const source = container.dataset.source || '';
    const limit = parseInt(container.dataset.limit || '10', 10);
    const cacheKey = container.dataset.cacheKey || '';
    const endpoint = container.dataset.endpoint || '/api/content/popular.php';

    // Mostrar mensaje de carga
    const containerId = container.id || 'sin-id';
    console.log(`Iniciando carga para: ${containerId}`, { type, source, sort, limit });
    
    // Verificar si ya tiene contenido cargado
    const hasContent = container.querySelector('.content-card');
    if (!hasContent) {
        // Limpiar y mostrar mensaje de carga
        const loadingText = container.querySelector('.loading-placeholder')?.textContent || 'Cargando...';
        container.innerHTML = `<div class="row-loading" style="padding: 2rem; text-align: center; color: #999;"><i class="fas fa-spinner fa-spin"></i> ${loadingText}</div>`;
    }

    try {
        const params = new URLSearchParams({ limit: limit.toString() });
        if (type) params.append('type', type);
        if (source) params.append('source', source);
        if (sort && sort !== 'popular') params.append('sort', sort);

        const url = `${APP_BASE_URL}${endpoint}?${params.toString()}`;
        console.log(`[${containerId}] Cargando desde:`, url);
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'same-origin'
        });
        
        console.log(`[${containerId}] Respuesta HTTP:`, response.status, response.statusText);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error(`[${containerId}] Error HTTP ${response.status}:`, errorText.substring(0, 200));
            throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error(`[${containerId}] Respuesta no es JSON (${contentType}):`, text.substring(0, 300));
            throw new Error('El servidor devolvió HTML en lugar de JSON');
        }
        
        const result = await response.json();
        console.log(`[${containerId}] Datos recibidos:`, result);
        
        const items = result.data || result;

        if (!Array.isArray(items)) {
            console.error(`[${containerId}] Los datos no son un array:`, result);
            container.innerHTML = '<p class="no-content">Error en el formato de datos.</p>';
            return;
        }

        if (cacheKey && Array.isArray(items)) {
            appState.contentCache[cacheKey] = items;
        }

        container.innerHTML = '';

        if (!items.length) {
            const message = source === 'imdb' 
                ? 'No hay películas destacadas en IMDb disponibles (rating >= 7.0).' 
                : source === 'local'
                ? 'No hay videos locales disponibles.'
                : 'Sin contenido disponible.';
            container.innerHTML = `<p class="no-content">${message}</p>`;
            console.warn(`[${containerId}] Sin resultados para mostrar`);
            return;
        }

        console.log(`[${containerId}] Cargando ${items.length} items`);
        
        items.forEach((item, index) => {
            try {
                const cardType = item.type || type || 'movie';
                const card = createContentCard(item, cardType);
                container.appendChild(card);
            } catch (cardError) {
                console.error(`[${containerId}] Error creando tarjeta ${index}:`, cardError, item);
            }
        });
        
        // Mejorar tarjetas después de añadirlas
        enhanceExistingContentCards();
        
        // Asegurar que el hover funcione en las nuevas tarjetas
        container.querySelectorAll('.content-card').forEach(card => {
            const trailerUrl = card.dataset.trailerUrl || '';
            if (trailerUrl && !card.dataset.trailerHoverSetup) {
                setupTrailerHover(card, trailerUrl);
                card.dataset.trailerHoverSetup = 'true';
            }
        });
        
        console.log(`[${containerId}] Carga completada exitosamente`);
    } catch (error) {
        console.error(`[${containerId}] Error cargando fila dinámica:`, error);
        const errorMessage = error.message || 'Error desconocido';
        container.innerHTML = `<p class="no-content" style="color: #e50914;">Error: ${errorMessage}<br><small>Revisa la consola para más detalles.</small></p>`;
    }
}

// Crear tarjeta de contenido
function createContentCard(item, type) {
    const card = document.createElement('div');
    card.className = 'content-card';
    card.dataset.id = item.id;
    card.dataset.type = type;
    card.dataset.title = item.title || '';
    card.dataset.year = item.release_year || '';
    card.dataset.detailUrl = `/content.php?id=${item.id}`;
    card.dataset.trailerUrl = item.trailer_url || '';
    
    const isPremium = item.is_premium ? '<span class="premium-badge">PREMIUM</span>' : '';
    const hasTorrent = item.torrent_magnet ? '<span class="torrent-badge" title="Disponible por Torrent"><i class="fas fa-magnet"></i></span>' : '';
    const year = item.release_year ? `<span>${item.release_year}</span>` : '';
    const durationLabel = type === 'movie'
        ? `${item.duration || 'N/D'} min`
        : (item.seasons ? `${item.seasons} Temporada${item.seasons > 1 ? 's' : ''}` : 'Serie');
    const rating = item.rating ? `<span>•</span><span>⭐ ${parseFloat(item.rating).toFixed(1)}</span>` : '';
    const contentType = type === 'movie' ? 'movie' : 'series';
    const titleEscaped = (item.title || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    
    card.innerHTML = `
        <div class="content-card-media">
            <img src="${item.poster_url || 'assets/images/placeholder-poster.png'}" alt="${titleEscaped}" class="content-poster-clickable" style="cursor: pointer;" data-trailer-url="${item.trailer_url || ''}">
            <div class="content-trailer-container" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;">
                <div class="content-trailer-wrapper"></div>
            </div>
        </div>
        <div class="content-badges">
            ${isPremium}
            ${hasTorrent}
        </div>
        <div class="content-info">
            <h3 class="content-title">${titleEscaped}</h3>
            <div class="content-meta">
                ${year}
                ${year ? '<span>•</span>' : ''}
                <span>${durationLabel}</span>
                ${rating}
            </div>
            <div class="imdb-badge" data-id="${item.id}" style="display: inline-flex; align-items: center; gap: 0.35rem; margin: 0.4rem 0; padding: 0.2rem 0.6rem; border-radius: 4px; background: rgba(245,197,24,0.15); color: #f5c518; font-weight: 600; font-size: 0.85rem;">
                <i class="fab fa-imdb"></i>
                <span class="imdb-text">IMDb: —</span>
            </div>
            <div class="content-actions">
                <button class="action-btn" data-action="play" data-id="${item.id}" title="Reproducir">
                    <i class="fas fa-play"></i>
                </button>
                <button class="action-btn" data-action="add" data-id="${item.id}" title="Añadir a Mi lista">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="action-btn" data-action="info" data-id="${item.id}" title="Más información">
                    <i class="fas fa-info-circle"></i>
                </button>
                <button class="action-btn torrent-btn" data-action="torrent" data-id="${item.id}" title="Buscar torrents" data-title="${encodeURIComponent(item.title || '')}" data-year="${item.release_year || ''}" data-type="${contentType}">
                    <i class="fas fa-magnet"></i>
                </button>
            </div>
        </div>
    `;
    
    // Redirigir a detalles cuando se hace clic fuera de los botones
    card.addEventListener('click', (event) => {
        if (event.target.closest('.action-btn') || event.target.closest('.content-trailer-container')) {
            return;
        }
        window.location.href = `/content.php?id=${item.id}`;
    });
    
    // Añadir handler al poster para buscar torrents
    const poster = card.querySelector('.content-poster-clickable');
    if (poster) {
        poster.addEventListener('click', (e) => {
            e.stopPropagation();
            if (typeof showTorrentModal === 'function') {
                showTorrentModal(item.id, item.title || '', item.release_year || null, contentType);
            } else {
                window.location.href = `/content.php?id=${item.id}`;
            }
        });
    }
    
    // Añadir funcionalidad de hover para mostrar trailer
    if (item.trailer_url) {
        setupTrailerHover(card, item.trailer_url);
    }
    
    enhanceCardWithIMDb(card, item, contentType);
    attachTorrentHandlers(card, item, contentType);
    
    return card;
}

// Configurar hover para mostrar trailer
function setupTrailerHover(card, trailerUrl) {
    let trailerPlayer = null;
    let hoverTimeout = null;
    let isHovering = false;
    
    const trailerContainer = card.querySelector('.content-trailer-container');
    const trailerWrapper = card.querySelector('.content-trailer-wrapper');
    const poster = card.querySelector('.content-poster-clickable') || card.querySelector('img');
    
    if (!trailerContainer || !trailerWrapper || !poster) return;
    
    // Detectar si es YouTube
    const isYouTube = /youtube\.com|youtu\.be/.test(trailerUrl);
    
    card.addEventListener('mouseenter', () => {
        isHovering = true;
        hoverTimeout = setTimeout(() => {
            if (!isHovering) return;
            
            if (isYouTube) {
                loadYouTubeTrailer(trailerUrl, trailerWrapper, () => {
                    if (isHovering && poster) {
                        poster.style.opacity = '0';
                        poster.style.transition = 'opacity 0.3s ease';
                        trailerContainer.style.display = 'block';
                    }
                });
            } else {
                const video = loadVideoTrailer(trailerUrl, trailerWrapper, () => {
                    if (isHovering && poster) {
                        poster.style.opacity = '0';
                        poster.style.transition = 'opacity 0.3s ease';
                        trailerContainer.style.display = 'block';
                    }
                });
                trailerPlayer = video;
            }
        }, 500); // Delay de 500ms antes de cargar
    });
    
    card.addEventListener('mouseleave', () => {
        isHovering = false;
        if (hoverTimeout) {
            clearTimeout(hoverTimeout);
            hoverTimeout = null;
        }
        
        // Ocultar trailer y mostrar poster
        if (poster) {
            poster.style.opacity = '1';
            poster.style.transition = 'opacity 0.3s ease';
        }
        trailerContainer.style.display = 'none';
        
        // Detener reproducción
        if (trailerPlayer) {
            if (trailerPlayer.pause && typeof trailerPlayer.pause === 'function') {
                trailerPlayer.pause();
            } else if (trailerPlayer.stopVideo && typeof trailerPlayer.stopVideo === 'function') {
                trailerPlayer.stopVideo();
            }
            trailerPlayer = null;
        }
        
        // Limpiar contenedor
        trailerWrapper.innerHTML = '';
    });
}

// Cargar trailer de YouTube
function loadYouTubeTrailer(url, container, onReady) {
    try {
        // Extraer ID de YouTube
        let videoId = '';
        if (url.includes('youtube.com/watch?v=')) {
            videoId = url.split('v=')[1].split('&')[0];
        } else if (url.includes('youtu.be/')) {
            videoId = url.split('youtu.be/')[1].split('?')[0];
        } else if (url.includes('youtube.com/embed/')) {
            videoId = url.split('embed/')[1].split('?')[0];
        } else if (/^[\w-]{11}$/.test(url)) {
            videoId = url;
        }
        
        if (!videoId) {
            console.warn('No se pudo extraer el ID de YouTube:', url);
            return;
        }
        
        // Crear iframe de YouTube
        const iframe = document.createElement('iframe');
        iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&controls=0&loop=1&playlist=${videoId}&modestbranding=1&rel=0&playsinline=1&enablejsapi=1&origin=${window.location.origin}`;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.border = 'none';
        iframe.allow = 'autoplay; encrypted-media';
        iframe.allowFullscreen = true;
        
        container.innerHTML = '';
        container.appendChild(iframe);
        
        if (onReady) onReady();
    } catch (error) {
        console.error('Error cargando trailer de YouTube:', error);
    }
}

// Cargar video directo (MP4, etc.)
function loadVideoTrailer(url, container, onReady) {
    try {
        // Normalizar URL
        let normalizedUrl = url;
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            if (url.startsWith('/')) {
                normalizedUrl = window.location.origin + url;
            } else {
                normalizedUrl = window.location.origin + '/' + url;
            }
        }
        
        const video = document.createElement('video');
        video.src = normalizedUrl;
        video.autoplay = true;
        video.muted = true;
        video.loop = true;
        video.playsInline = true;
        video.style.width = '100%';
        video.style.height = '100%';
        video.style.objectFit = 'cover';
        
        video.addEventListener('loadeddata', () => {
            if (onReady) onReady();
        });
        
        video.addEventListener('error', (e) => {
            console.error('Error cargando video trailer:', e);
        });
        
        container.innerHTML = '';
        container.appendChild(video);
        
        // Retornar el video para que pueda ser detenido
        return video;
    } catch (error) {
        console.error('Error cargando trailer de video:', error);
        return null;
    }
}

function enhanceCardWithIMDb(card, item, contentType) {
    const badge = card.querySelector('.imdb-badge .imdb-text');
    if (!badge) return;
    
    const title = item.title || '';
    const year = item.release_year || '';
    if (!title) {
        badge.textContent = 'IMDb: N/A';
        return;
    }
    
    // Si el item ya tiene rating, usarlo directamente
    if (item.rating && parseFloat(item.rating) > 0) {
        badge.textContent = `IMDb: ${parseFloat(item.rating).toFixed(1)}`;
        badge.parentElement.title = `Rating: ${item.rating} (local)`;
        return;
    }
    
    const cacheKey = `${contentType}:${title.toLowerCase()}:${year}`;
    if (appState.imdbCache[cacheKey]) {
        const info = appState.imdbCache[cacheKey];
        const rating = info && info.imdb_rating ? info.imdb_rating : null;
        const source = info && info.source ? info.source : 'imdb';
        
        if (rating) {
            badge.textContent = `IMDb: ${rating}`;
            badge.parentElement.title = `Rating: ${rating} (${source})`;
        } else {
            badge.textContent = 'IMDb: N/A';
            badge.parentElement.title = 'No disponible';
        }
        return;
    }
    
    badge.textContent = 'IMDb: ...';
    
    // Intentar obtener datos con timeout
    const fetchPromise = fetchImdbData(title, year, contentType);
    const timeoutPromise = new Promise((resolve) => {
        setTimeout(() => resolve(null), 5000); // Timeout de 5 segundos
    });
    
    Promise.race([fetchPromise, timeoutPromise])
        .then(info => {
            if (info) {
                appState.imdbCache[cacheKey] = info;
                const rating = info.imdb_rating || null;
                const source = info.source || 'imdb';
                
                if (rating) {
                    badge.textContent = `IMDb: ${rating}`;
                    badge.parentElement.title = `Rating: ${rating} (${source})`;
                } else {
                    badge.textContent = 'IMDb: N/A';
                    badge.parentElement.title = 'No disponible';
                }
            } else {
                // Timeout o sin datos
                badge.textContent = 'IMDb: N/A';
                badge.parentElement.title = 'No disponible o timeout';
            }
        })
        .catch((error) => {
            console.warn(`Error mejorando tarjeta con IMDb para "${title}":`, error);
            badge.textContent = 'IMDb: N/A';
            badge.parentElement.title = 'No disponible (usando datos locales)';
        });
}

function attachTorrentHandlers(card, item, contentType) {
    const title = item.title || '';
    const year = item.release_year || '';
    const poster = card.querySelector('img');
    const torrentBtn = card.querySelector('.torrent-btn');
    
    const openModal = (event) => {
        event.preventDefault();
        event.stopPropagation();
        openTorrentModal({
            id: item.id,
            title,
            year,
            type: contentType
        });
    };
    
    if (poster) {
        poster.style.cursor = 'pointer';
        poster.addEventListener('click', openModal);
    }
    
    if (torrentBtn) {
        torrentBtn.addEventListener('click', openModal);
    }
}

async function fetchImdbData(title, year = '', type = 'movie') {
    if (!title) return null;
    const cacheKey = `${type}:${title.toLowerCase()}:${year}`;
    if (appState.imdbCache[cacheKey]) {
        return appState.imdbCache[cacheKey];
    }
    
    const baseUrl = (APP_BASE_URL || '').replace(/\/$/, '');
    const url = `${baseUrl}/api/imdb/search.php?title=${encodeURIComponent(title)}&year=${encodeURIComponent(year || '')}&type=${encodeURIComponent(type)}`;
    
    try {
        const response = await fetch(url, { 
            credentials: 'same-origin',
            timeout: 5000 // Timeout de 5 segundos
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.data) {
            appState.imdbCache[cacheKey] = data.data;
            return data.data;
        }
        
        // Si no hay éxito pero hay datos básicos, usarlos
        if (data.data && (data.data.title || data.data.imdb_rating)) {
            appState.imdbCache[cacheKey] = data.data;
            return data.data;
        }
        
        // Retornar null en lugar de lanzar error para que no rompa la UI
        console.warn(`No se encontró información para: ${title} (${year})`);
        return null;
    } catch (error) {
        console.warn(`Error al buscar información de IMDb para "${title}":`, error.message);
        // Retornar null en lugar de lanzar error
        return null;
    }
}

async function fetchTorrentResults(title, year = '', type = 'movie') {
    if (!title) return [];
    const cacheKey = `${type}:${title.toLowerCase()}:${year}`;
    if (appState.torrentCache[cacheKey]) {
        return appState.torrentCache[cacheKey];
    }
    
    const baseUrl = (APP_BASE_URL || '').replace(/\/$/, '');
    const url = `${baseUrl}/api/torrent/search.php?title=${encodeURIComponent(title)}&year=${encodeURIComponent(year || '')}&type=${encodeURIComponent(type)}`;
    
    const response = await fetch(url, { credentials: 'same-origin' });
    const data = await response.json();
    
    if (data.success && data.results) {
        appState.torrentCache[cacheKey] = data.results;
        return data.results;
    }
    
    return [];
}

function openTorrentModal(content) {
    if (!elements.torrentModal) {
        console.warn('Modal de torrents no disponible');
        return;
    }
    
    appState.activeTorrentContent = content;
    
    elements.torrentModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    if (elements.torrentModalTitle) {
        elements.torrentModalTitle.textContent = `Buscar torrents: ${content.title}${content.year ? ` (${content.year})` : ''}`;
    }
    
    if (elements.torrentIMDbContainer) {
        elements.torrentIMDbContainer.innerHTML = `
            <div class="torrent-imdb-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Cargando información de IMDb...</p>
            </div>
        `;
    }
    
    if (elements.torrentSearchStatus) {
        elements.torrentSearchStatus.style.display = 'block';
        elements.torrentSearchStatus.innerHTML = `
            <div class="torrent-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Buscando enlaces torrent...</p>
            </div>
        `;
    }
    
    if (elements.torrentResultsContainer) {
        elements.torrentResultsContainer.style.display = 'none';
    }
    
    // Cargar información de IMDb con timeout y manejo de errores mejorado
    const imdbPromise = fetchImdbData(content.title, content.year, content.type);
    const timeoutPromise = new Promise((resolve) => {
        setTimeout(() => resolve(null), 5000);
    });
    
    Promise.race([imdbPromise, timeoutPromise])
        .then(info => {
            if (info) {
                renderImdbInfo(info);
            } else {
                // Mostrar información básica si IMDb falla
                if (elements.torrentIMDbContainer) {
                    elements.torrentIMDbContainer.innerHTML = `
                        <div class="torrent-imdb-info">
                            <h3>${content.title}${content.year ? ` (${content.year})` : ''}</h3>
                            <p>Información de IMDb no disponible. Puedes buscar torrents igualmente.</p>
                        </div>
                    `;
                }
            }
        })
        .catch((error) => {
            console.warn('Error cargando IMDb en modal:', error);
            if (elements.torrentIMDbContainer) {
                elements.torrentIMDbContainer.innerHTML = `
                    <div class="torrent-imdb-loading error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>No se pudo cargar la información de IMDb. Puedes buscar torrents igualmente.</p>
                    </div>
                `;
            }
        });
    
    fetchTorrentResults(content.title, content.year, content.type)
        .then(results => {
            if (elements.torrentSearchStatus) {
                elements.torrentSearchStatus.style.display = 'none';
            }
            if (elements.torrentResultsContainer) {
                elements.torrentResultsContainer.style.display = 'block';
            }
            renderTorrentResults(results, content);
        })
        .catch(() => {
            if (elements.torrentSearchStatus) {
                elements.torrentSearchStatus.style.display = 'block';
                elements.torrentSearchStatus.innerHTML = `
                    <div class="torrent-loading error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Error al buscar torrents. Intenta nuevamente.</p>
                    </div>
                `;
            }
        });
}

// Alias para compatibilidad
function showTorrentModal(id, title, year, type) {
    openTorrentModal({
        id: id,
        title: title,
        year: year,
        type: type || 'movie'
    });
}

// Hacer disponible globalmente
window.showTorrentModal = showTorrentModal;

function renderImdbInfo(info) {
    if (!elements.torrentIMDbContainer) return;
    
    if (!info) {
        elements.torrentIMDbContainer.innerHTML = `
            <div class="torrent-imdb-loading error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Sin información de IMDb disponible.</p>
            </div>
        `;
        return;
    }
    
    elements.torrentIMDbContainer.innerHTML = `
        <div class="imdb-modal-info">
            <div class="imdb-poster">
                ${info.poster && info.poster !== 'N/A'
                    ? `<img src="${info.poster}" alt="${info.title || ''}">`
                    : '<div class="imdb-poster-placeholder"><i class="fas fa-image"></i></div>'}
            </div>
            <div class="imdb-details">
                <h3>${info.title || 'Título desconocido'} ${info.year ? `(${info.year})` : ''}</h3>
                ${info.imdb_rating ? `<p><strong>IMDb:</strong> ${info.imdb_rating} / 10 ${info.imdb_votes ? `(${info.imdb_votes} votos)` : ''}</p>` : ''}
                ${info.rated ? `<p><strong>Clasificación:</strong> ${info.rated}</p>` : ''}
                ${info.runtime ? `<p><strong>Duración:</strong> ${info.runtime}</p>` : ''}
                ${info.genre ? `<p><strong>Género:</strong> ${info.genre}</p>` : ''}
                ${info.director ? `<p><strong>Director:</strong> ${info.director}</p>` : ''}
                ${info.actors ? `<p><strong>Actores:</strong> ${info.actors}</p>` : ''}
                ${info.plot && info.plot !== 'N/A' ? `<p class="imdb-plot">${info.plot}</p>` : ''}
                ${info.imdb_id ? `<a href="https://www.imdb.com/title/${info.imdb_id}" target="_blank" rel="noopener" class="imdb-link"><i class="fab fa-imdb"></i> Ver en IMDb</a>` : ''}
            </div>
        </div>
    `;
}

function renderTorrentResults(results, content) {
    if (!elements.torrentResultsList) return;
    
    if (!results || results.length === 0) {
        elements.torrentResultsList.innerHTML = `
            <div class="torrent-empty">
                <i class="fas fa-search"></i>
                <p>No se encontraron torrents para esta búsqueda.</p>
            </div>
        `;
        return;
    }
    
    const html = results.map(result => {
        const safeMagnet = encodeURIComponent(result.magnet || '');
        const quality = result.quality && result.quality !== 'Unknown' ? result.quality : 'Desconocida';
        const seeds = result.seeds || 0;
        const size = result.size || 'N/A';
        const source = result.source || 'Fuente desconocida';
        
        return `
            <div class="torrent-result">
                <div class="torrent-info">
                    <div class="torrent-title">${result.title || 'Torrent sin título'}</div>
                    <div class="torrent-meta">
                        <span class="torrent-quality">${quality}</span>
                        <span><i class="fas fa-arrow-up"></i> Seeds: ${seeds}</span>
                        <span><i class="fas fa-hdd"></i> ${size}</span>
                        <span><i class="fas fa-database"></i> ${source}</span>
                    </div>
                </div>
                <div class="torrent-actions">
                    <button class="play-torrent-btn" data-magnet="${safeMagnet}" data-content-id="${content.id}" data-title="${encodeURIComponent(content.title)}">
                        <i class="fas fa-play"></i> Reproducir
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    elements.torrentResultsList.innerHTML = html;
}

function handleTorrentResultClick(event) {
    const btn = event.target.closest('.play-torrent-btn');
    if (!btn) return;
    event.preventDefault();
    
    const magnet = btn.dataset.magnet ? decodeURIComponent(btn.dataset.magnet) : '';
    const contentId = btn.dataset.contentId;
    
    if (!magnet) {
        alert('Enlace magnet no disponible.');
        return;
    }
    
    selectTorrentForPlayback(magnet, contentId);
}

function selectTorrentForPlayback(magnetLink, contentId) {
    if (!magnetLink) return;
    closeTorrentModal();
    
    const baseUrl = (APP_BASE_URL || '').replace(/\/$/, '');
    const url = `${baseUrl}/watch.php?id=${contentId || ''}&torrent=${encodeURIComponent(magnetLink)}`;
    window.open(url, '_blank');
}

function closeTorrentModal() {
    if (elements.torrentModal) {
        elements.torrentModal.classList.remove('active');
    }
    document.body.style.overflow = '';
}

function triggerTorrentModal(id, title, year = '', type = 'movie') {
    openTorrentModal({
        id,
        title,
        year,
        type
    });
}

window.closeTorrentModal = closeTorrentModal;
window.selectTorrentForPlayback = selectTorrentForPlayback;
window.triggerTorrentModal = triggerTorrentModal;
window.handleMoviePosterClick = (id, title, year) => triggerTorrentModal(id, title, year, 'movie');
window.handleSeriesPosterClick = (id, title, year) => triggerTorrentModal(id, title, year, 'series');

// Navegación
function navigateTo(section) {
    // Actualizar enlace activo
    elements.navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${section}`) {
            link.classList.add('active');
        }
    });
    
    // Actualizar contenido según la sección
    if (section === 'peliculas' || section === 'series') {
        appState.currentContent = section === 'peliculas' ? 'movies' : 'series';
        loadCarousel();
        
        // Desplazarse a la sección de contenido
        document.querySelector(`#${section}`)?.scrollIntoView({ behavior: 'smooth' });
    } else if (section === 'inicio') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// Control del carrusel
function nextSlide() {
    if (document.hidden) return; // No cambiar si la pestaña no está visible
    const items = document.querySelectorAll('.carousel-item');
    if (items.length <= 1) return;
    appState.currentSlide = (appState.currentSlide + 1) % items.length;
    updateCarousel();
}

function prevSlide() {
    const items = document.querySelectorAll('.carousel-item');
    if (items.length <= 1) return;
    appState.currentSlide = (appState.currentSlide - 1 + items.length) % items.length;
    updateCarousel();
}

function updateCarousel() {
    const items = document.querySelectorAll('.carousel-item');
    const heroSlides = document.querySelectorAll('.hero-slide');
    
    // Actualizar carousel items si existen
    if (items.length > 0) {
        items.forEach((item, index) => {
            item.classList.toggle('active', index === appState.currentSlide);
        });
    }
    
    // Actualizar hero slides si existen y usar optimizador
    if (heroSlides.length > 0) {
        // Usar el optimizador del hero si está disponible
        if (window.HeroOptimizer && typeof window.HeroOptimizer.changeSlide === 'function') {
            window.HeroOptimizer.changeSlide(appState.currentSlide);
        } else {
            // Fallback: actualización manual
            heroSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === appState.currentSlide);
            });
        }
    }
}

// Búsqueda
function handleSearch(e) {
    const query = e.target.value.toLowerCase();
    
    if (query.length < 2) {
        loadPopularContent();
        return;
    }
    
    // Filtrar contenido cacheado
    const filteredMovies = appState.contentCache.movies.filter(movie =>
        movie.title.toLowerCase().includes(query)
    );
    
    const filteredSeries = appState.contentCache.series.filter(series =>
        series.title.toLowerCase().includes(query)
    );

    // Aquí se podría añadir una llamada a la API para una búsqueda más completa
    // fetch(`/api/search?q=${query}`).then(...)
    
    // Actualizar la interfaz con los resultados
    updateSearchResults(filteredMovies, filteredSeries);
}

function updateSearchResults(movies, series) {
    // Limpiar resultados anteriores
    elements.popularMovies.innerHTML = '';
    elements.popularSeries.innerHTML = '';
    
    // Mostrar películas encontradas
    if (movies.length > 0) {
        const title = document.createElement('h2>');
        title.textContent = 'Películas';
        elements.popularMovies.appendChild(title);
        
        movies.forEach(movie => {
            elements.popularMovies.appendChild(createContentCard(movie, 'movie'));
        });
    }
    
    // Mostrar series encontradas
    if (series.length > 0) {
        const title = document.createElement('h2>');
        title.textContent = 'Series';
        elements.popularSeries.appendChild(title);
        
        series.forEach(series => {
            elements.popularSeries.appendChild(createContentCard(series, 'series'));
        });
    }
    
    // Mostrar mensaje si no hay resultados
    if (movies.length === 0 && series.length === 0) {
        elements.popularMovies.innerHTML = '<p class="no-results">No se encontraron resultados para tu búsqueda.</p>';
    }
}

// Menú desplegable de usuario
function toggleDropdown(e) {
    e.stopPropagation();
    if (elements.dropdown) {
        elements.dropdown.style.display = elements.dropdown.style.display === 'block' ? 'none' : 'block';
    }
}

// Cerrar menú desplegable al hacer clic fuera
document.addEventListener('click', () => {
    if (elements.dropdown && elements.dropdown.style.display === 'block') {
        elements.dropdown.style.display = 'none';
    }
});

// Iniciar sesión
function login(user) {
    appState.currentUser = user;
    appState.isLoggedIn = true;
    appState.isAdmin = user.role === 'admin';
    
    // Actualizar interfaz de usuario
    updateUIAfterLogin();
    
    // Cerrar modal de inicio de sesión
    closeLoginModal();
    
    // Mostrar mensaje de bienvenida
    showNotification(`¡Bienvenido, ${user.name}!`);
}

// Cerrar sesión
function logout() {
    appState.currentUser = null;
    appState.isLoggedIn = false;
    appState.isAdmin = false;
    
    // Actualizar interfaz de usuario
    updateUIAfterLogout();
    
    // Mostrar mensaje de despedida
    showNotification('Has cerrado sesión correctamente.');
}

// Simular inicio de sesión (en un entorno real, esto vendría de un formulario)
function simulateLogin() {
    // Simular un usuario administrador
    const adminUser = {
        id: 1,
        name: 'Administrador',
        email: 'admin@example.com',
        role: 'admin',
        subscription: 'premium',
        avatar: 'assets/images/avatar.png'
    };
    
    login(adminUser);
}

// Manejador de inicio de sesión
function handleLogin(e) {
    e.preventDefault();
    
    // En un entorno real, aquí se validarían las credenciales con el servidor
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    // Validación básica
    if (!email || !password) {
        showNotification('Por favor, completa todos los campos.', 'error');
        return;
    }
    
    // Simular autenticación exitosa
    const user = {
        id: 2,
        name: email.split('@')[0],
        email: email,
        role: 'user',
        subscription: 'free',
        avatar: 'assets/images/avatar.png'
    };
    
    login(user);
}

// Actualizar interfaz después del inicio de sesión
function updateUIAfterLogin() {
    const user = appState.currentUser;
    
    // Actualizar avatar
    const avatar = document.querySelector('.avatar');
    if (avatar) {
        avatar.src = user.avatar;
        avatar.alt = user.name;
    }
    
    // Mostrar opciones de administrador si es necesario
    if (appState.isAdmin) {
        // Aquí podrías agregar un enlace al panel de administración
    }
    
    // Actualizar botones de suscripción
    updateSubscriptionUI();
}

// Actualizar interfaz después de cerrar sesión
function updateUIAfterLogout() {
    // Restablecer avatar
    const avatar = document.querySelector('.avatar');
    if (avatar) {
        avatar.src = 'assets/images/avatar.png';
        avatar.alt = 'Perfil';
    }
    
    // Ocultar opciones de administrador si las hay
    
    // Mostrar botón de inicio de sesión
    showLoginModal();
}

// Actualizar interfaz según la suscripción del usuario
function updateSubscriptionUI() {
    const user = appState.currentUser;
    if (!user) return;
    
    // Actualizar elementos de la interfaz según el tipo de suscripción
    const premiumElements = document.querySelectorAll('.premium-content');
    premiumElements.forEach(el => {
        if (user.subscription !== 'premium') {
            el.classList.add('locked');
            el.innerHTML = '<div class="premium-lock"><i class="fas fa-lock"></i><span>Contenido Premium</span></div>';
        }
    });
}

// Mostrar notificación
function showNotification(message, type = 'success') {
    // Crear elemento de notificación
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Agregar al documento
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

// Manejar scroll de la ventana
function handleScroll() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
}

// Variables globales del reproductor
let torrentClient = null;
let currentTorrent = null;

// Inicializar WebTorrent solo si está disponible
function initWebTorrent() {
    if (typeof WebTorrent !== 'undefined') {
        torrentClient = new WebTorrent();
    } else {
        console.warn('WebTorrent no está disponible. El streaming P2P no funcionará.');
    }
}

let videoPlayerState = {
    isPlaying: false,
    isMuted: false,
    volume: 1,
    isFullscreen: false,
    hideControlsTimeout: null,
    currentTime: 0,
    duration: 0,
    isDragging: false
};

// Función helper para obtener el elemento de video activo
function getActiveVideoElement() {
    // Primero intentar obtener el video de torrent
    const torrentVideo = document.getElementById('torrent-player');
    if (torrentVideo && torrentVideo.style.display !== 'none') {
        return torrentVideo;
    }
    // Si hay un reproductor de YouTube activo, retornar null (se maneja diferente)
    if (window.player && typeof window.player.getCurrentTime === 'function') {
        return null; // YouTube player se maneja diferente
    }
    return torrentVideo;
}

// Inicializar controles del reproductor
function initVideoPlayerControls() {
    const videoPlayer = document.querySelector('.video-player');
    if (!videoPlayer) return;
    
    const playPauseBtn = document.querySelector('.play-pause');
    const volumeBtn = document.querySelector('.volume');
    const volumeSlider = document.querySelector('.volume-slider');
    const progressBar = document.querySelector('.progress-bar');
    const fullscreenBtn = document.querySelector('.fullscreen');
    const closeVideoBtn = document.querySelector('.close-video');
    const loadingIndicator = document.querySelector('.loading-indicator');
    const errorMessage = document.querySelector('.error-message');
    const retryBtn = document.querySelector('.retry-btn');

    // Mostrar/ocultar controles al mover el ratón
    videoPlayer.addEventListener('mousemove', showVideoControls);
    videoPlayer.addEventListener('mouseleave', () => {
        if (videoPlayerState.isPlaying) {
            startHideControlsTimer();
        }
    });

    // Reproducir/Pausar
    if (playPauseBtn) {
        playPauseBtn.addEventListener('click', togglePlayPause);
    }
    
    const videoWrapper = document.querySelector('.video-wrapper');
    if (videoWrapper) {
        videoWrapper.addEventListener('click', (e) => {
            if (e.target === videoWrapper || e.target.classList.contains('video-iframe-container')) {
                togglePlayPause();
            }
        });
    }
    
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && videoPlayer.classList.contains('active')) {
            e.preventDefault();
            togglePlayPause();
        }
    });

    // Control de volumen
    if (volumeBtn) {
        volumeBtn.addEventListener('click', toggleMute);
    }
    
    if (volumeSlider) {
        volumeSlider.addEventListener('input', (e) => {
            videoPlayerState.volume = parseFloat(e.target.value);
            const videoElement = getActiveVideoElement();
            if (videoElement) {
                videoElement.volume = videoPlayerState.volume;
            }
            updateVolumeUI();
        });
    }

    // Barra de progreso
    if (progressBar) {
        progressBar.addEventListener('click', (e) => {
            const videoElement = getActiveVideoElement();
            if (videoElement && videoElement.duration) {
                const rect = progressBar.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                videoElement.currentTime = pos * videoElement.duration;
            } else if (window.player && typeof window.player.seekTo === 'function') {
                const rect = progressBar.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                const duration = window.player.getDuration();
                if (duration) {
                    window.player.seekTo(duration * pos, true);
                }
            }
        });
    }

    // Pantalla completa
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    document.addEventListener('fullscreenchange', () => {
        videoPlayerState.isFullscreen = !!document.fullscreenElement;
        updateFullscreenUI();
    });

    // Cerrar reproductor
    if (closeVideoBtn) {
        closeVideoBtn.addEventListener('click', closeVideoPlayer);
    }
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && videoPlayer.classList.contains('active')) {
            closeVideoPlayer();
        }
    });

    // Reintentar reproducción
    if (retryBtn) {
        retryBtn.addEventListener('click', () => {
            if (errorMessage) errorMessage.style.display = 'none';
            const videoElement = getActiveVideoElement();
            if (videoElement) {
                videoElement.load();
                videoElement.play().catch(handlePlaybackError);
            }
        });
    }

    // Eventos del video (solo para video HTML5, no YouTube)
    const videoElement = document.getElementById('torrent-player');
    if (videoElement) {
        videoElement.addEventListener('play', () => {
            videoPlayerState.isPlaying = true;
            updatePlayPauseUI();
            startHideControlsTimer();
        });

        videoElement.addEventListener('pause', () => {
            videoPlayerState.isPlaying = false;
            updatePlayPauseUI();
            showVideoControls();
        });

        videoElement.addEventListener('timeupdate', updateProgressBar);
        videoElement.addEventListener('progress', updateBufferBar);
        videoElement.addEventListener('loadedmetadata', () => {
            videoPlayerState.duration = videoElement.duration;
            updateTimeDisplay();
        });

        videoElement.addEventListener('waiting', () => {
            if (loadingIndicator) loadingIndicator.style.display = 'flex';
        });

        videoElement.addEventListener('playing', () => {
            if (loadingIndicator) loadingIndicator.style.display = 'none';
        });

        videoElement.addEventListener('error', handlePlaybackError);
    }

    // Atajos de teclado
    document.addEventListener('keydown', (e) => {
        if (!videoPlayer.classList.contains('active')) return;
        
        switch (e.key.toLowerCase()) {
            case ' ':
                e.preventDefault();
                togglePlayPause();
                showKeyboardShortcut(`<kbd>Espacio</kbd> ${videoPlayerState.isPlaying ? 'Pausar' : 'Reproducir'}`);
                break;
            case 'm':
                toggleMute();
                showKeyboardShortcut(`<kbd>M</kbd> ${videoPlayerState.isMuted ? 'Activar sonido' : 'Silenciar'}`);
                break;
            case 'f':
                toggleFullscreen();
                showKeyboardShortcut(`<kbd>F</kbd> ${videoPlayerState.isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}`);
                break;
            case 'arrowleft':
                seek(-5);
                showKeyboardShortcut(`<kbd>←</kbd> Retroceder 5 segundos`);
                break;
            case 'arrowright':
                seek(5);
                showKeyboardShortcut(`<kbd>→</kbd> Adelantar 5 segundos`);
                break;
            case 'arrowup':
                changeVolume(0.1);
                showKeyboardShortcut(`<kbd>↑</kbd> Subir volumen`);
                break;
            case 'arrowdown':
                changeVolume(-0.1);
                showKeyboardShortcut(`<kbd>↓</kbd> Bajar volumen`);
                break;
        }
    });
}

// Cerrar reproductor de video
function closeVideoPlayer() {
    // Detener y limpiar el torrent actual
    if (currentTorrent) {
        currentTorrent.destroy();
        currentTorrent = null;
    }
    
    // Detener el reproductor de video
    const video = document.getElementById('torrent-player');
    if (video) {
        video.pause();
        video.src = '';
        video.load();
    }
    
    // Detener el reproductor de YouTube si está en uso
    if (window.player && typeof window.player.pauseVideo === 'function') {
        window.player.pauseVideo();
    }
    
    // Limpiar intervalo de progreso de YouTube
    if (window.youtubeProgressInterval) {
        clearInterval(window.youtubeProgressInterval);
        window.youtubeProgressInterval = null;
    }
    
    const videoPlayer = document.querySelector('.video-player');
    if (videoPlayer) {
        videoPlayer.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Resetear estado
    videoPlayerState.isPlaying = false;
    videoPlayerState.isFullscreen = false;
    if (videoPlayerState.hideControlsTimeout) {
        clearTimeout(videoPlayerState.hideControlsTimeout);
    }
}

// Funciones auxiliares del reproductor
function togglePlayPause() {
    const videoElement = getActiveVideoElement();
    if (videoElement) {
        if (videoElement.paused) {
            videoElement.play().catch(handlePlaybackError);
        } else {
            videoElement.pause();
        }
    } else if (window.player && typeof window.player.getPlayerState === 'function') {
        // Manejar reproductor de YouTube
        const state = window.player.getPlayerState();
        if (state === YT.PlayerState.PLAYING) {
            window.player.pauseVideo();
            videoPlayerState.isPlaying = false;
        } else {
            window.player.playVideo();
            videoPlayerState.isPlaying = true;
        }
        updatePlayPauseUI();
    }
}

function toggleMute() {
    const videoElement = getActiveVideoElement();
    videoPlayerState.isMuted = !videoPlayerState.isMuted;
    if (videoElement) {
        videoElement.muted = videoPlayerState.isMuted;
    } else if (window.player && typeof window.player.isMuted === 'function') {
        if (videoPlayerState.isMuted) {
            window.player.mute();
        } else {
            window.player.unMute();
        }
    }
    updateVolumeUI();
}

function changeVolume(delta) {
    videoPlayerState.volume = Math.min(1, Math.max(0, videoPlayerState.volume + delta));
    const videoElement = getActiveVideoElement();
    if (videoElement) {
        videoElement.volume = videoPlayerState.volume;
        videoElement.muted = videoPlayerState.volume === 0;
        videoPlayerState.isMuted = videoElement.muted;
    } else if (window.player && typeof window.player.setVolume === 'function') {
        window.player.setVolume(videoPlayerState.volume * 100);
        if (videoPlayerState.volume === 0) {
            window.player.mute();
            videoPlayerState.isMuted = true;
        } else {
            window.player.unMute();
            videoPlayerState.isMuted = false;
        }
    }
    updateVolumeUI();
}

function updateVolumeUI() {
    const volumeBtn = document.querySelector('.volume');
    if (!volumeBtn) return;
    
    const volumeIcon = volumeBtn.querySelector('i');
    const volumeSlider = document.querySelector('.volume-slider');
    
    if (volumeIcon) {
        if (videoPlayerState.isMuted || videoPlayerState.volume === 0) {
            volumeIcon.className = 'fas fa-volume-mute';
        } else if (videoPlayerState.volume < 0.5) {
            volumeIcon.className = 'fas fa-volume-down';
        } else {
            volumeIcon.className = 'fas fa-volume-up';
        }
    }
    
    if (volumeSlider) {
        volumeSlider.value = videoPlayerState.volume;
    }
}

function updateProgressBar() {
    const videoElement = getActiveVideoElement();
    const progress = document.querySelector('.progress-bar .progress');
    const currentTimeEl = document.querySelector('.current-time');
    const timeElapsed = document.querySelector('.time-elapsed');
    
    let currentTime = 0;
    let duration = 0;
    
    if (videoElement && videoElement.duration) {
        currentTime = videoElement.currentTime;
        duration = videoElement.duration;
    } else if (window.player && typeof window.player.getCurrentTime === 'function') {
        currentTime = window.player.getCurrentTime();
        duration = window.player.getDuration();
    } else {
        return;
    }
    
    if (isNaN(duration) || !isFinite(duration) || duration === 0) return;
    
    const percent = (currentTime / duration) * 100;
    if (progress) progress.style.width = percent + '%';
    
    if (currentTimeEl) currentTimeEl.textContent = formatTime(currentTime);
    if (timeElapsed) timeElapsed.textContent = `${formatTime(currentTime)} / ${formatTime(duration)}`;
}

function updateBufferBar() {
    const videoElement = getActiveVideoElement();
    const buffer = document.querySelector('.progress-bar .buffer');
    
    if (videoElement && videoElement.buffered && videoElement.buffered.length > 0) {
        const bufferedEnd = videoElement.buffered.end(videoElement.buffered.length - 1);
        const duration = videoElement.duration;
        if (duration > 0 && buffer) {
            const bufferedPercent = (bufferedEnd / duration) * 100;
            buffer.style.width = bufferedPercent + '%';
        }
    }
}

function updatePlayPauseUI() {
    const playPauseBtn = document.querySelector('.play-pause');
    if (!playPauseBtn) return;
    
    const icon = playPauseBtn.querySelector('i');
    if (!icon) return;
    
    if (videoPlayerState.isPlaying) {
        icon.className = 'fas fa-pause';
    } else {
        icon.className = 'fas fa-play';
    }
}

function toggleFullscreen() {
    const videoPlayer = document.querySelector('.video-player');
    
    if (!document.fullscreenElement) {
        if (videoPlayer.requestFullscreen) {
            videoPlayer.requestFullscreen();
        } else if (videoPlayer.webkitRequestFullscreen) { /* Safari */
            videoPlayer.webkitRequestFullscreen();
        } else if (videoPlayer.msRequestFullscreen) { /* IE11 */
            videoPlayer.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
    }
    
    videoPlayerState.isFullscreen = !videoPlayerState.isFullscreen;
    updateFullscreenUI();
}

function updateFullscreenUI() {
    const fullscreenBtn = document.querySelector('.fullscreen');
    const icon = fullscreenBtn.querySelector('i');
    
    if (videoPlayerState.isFullscreen) {
        icon.className = 'fas fa-compress';
    } else {
        icon.className = 'fas fa-expand';
    }
}

function showVideoControls() {
    const videoPlayer = document.querySelector('.video-player');
    videoPlayer.classList.remove('hidden-controls');
    
    if (videoPlayerState.hideControlsTimeout) {
        clearTimeout(videoPlayerState.hideControlsTimeout);
    }
    
    if (videoPlayerState.isPlaying) {
        startHideControlsTimer();
    }
}

function startHideControlsTimer() {
    const videoPlayer = document.querySelector('.video-player');
    
    if (videoPlayerState.hideControlsTimeout) {
        clearTimeout(videoPlayerState.hideControlsTimeout);
    }
    
    videoPlayerState.hideControlsTimeout = setTimeout(() => {
        if (videoPlayerState.isPlaying) {
            videoPlayer.classList.add('hidden-controls');
        }
    }, 3000);
}

function updateTimeDisplay() {
    const durationEl = document.querySelector('.duration');
    if (!durationEl) return;
    
    const videoElement = getActiveVideoElement();
    let duration = 0;
    
    if (videoElement && videoElement.duration) {
        duration = videoElement.duration;
    } else if (window.player && typeof window.player.getDuration === 'function') {
        duration = window.player.getDuration();
    }
    
    if (duration > 0) {
        durationEl.textContent = formatTime(duration);
    }
}

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function seek(seconds) {
    const videoElement = getActiveVideoElement();
    if (videoElement && videoElement.duration) {
        videoElement.currentTime = Math.max(0, Math.min(videoElement.currentTime + seconds, videoElement.duration));
    } else if (window.player && typeof window.player.getCurrentTime === 'function') {
        const currentTime = window.player.getCurrentTime();
        const duration = window.player.getDuration();
        const newTime = Math.max(0, Math.min(currentTime + seconds, duration));
        window.player.seekTo(newTime, true);
    }
}

function handlePlaybackError(error) {
    console.error('Error de reproducción:', error);
    const errorMessage = document.querySelector('.error-message');
    if (errorMessage) {
        errorMessage.style.display = 'block';
    }
}

function showKeyboardShortcut(message) {
    const keyboardShortcut = document.querySelector('.keyboard-shortcut');
    if (!keyboardShortcut) return;
    
    keyboardShortcut.innerHTML = message;
    keyboardShortcut.classList.add('show');
    
    if (keyboardShortcut.timeout) {
        clearTimeout(keyboardShortcut.timeout);
    }
    
    keyboardShortcut.timeout = setTimeout(() => {
        keyboardShortcut.classList.remove('show');
    }, 2000);
}

function loadRecommendations(contentId, type) {
    // En un entorno real, aquí se haría una llamada a la API
    // para obtener recomendaciones basadas en el contenido actual
    const recommendations = type === 'movie' 
        ? sampleContent.movies.filter(m => m.id !== contentId).slice(0, 5)
        : sampleContent.series.filter(s => s.id !== contentId).slice(0, 5);
    
    const recommendationsList = document.querySelector('.recommendations-list');
    if (!recommendationsList) return;
    
    recommendationsList.innerHTML = '';
    
    recommendations.forEach(item => {
        const recommendationItem = document.createElement('div');
        recommendationItem.className = 'recommendation-item';
        recommendationItem.innerHTML = `
            <img src="${item.image}" alt="${item.title}">
            <div class="recommendation-info">
                <h5>${item.title}</h5>
                <p>${item.year} • ${item.category}</p>
            </div>
        `;
        
        recommendationItem.addEventListener('click', () => {
            playContent(item.id, type);
        });
        
        recommendationsList.appendChild(recommendationItem);
    });
}

// Función para ocultar el indicador de carga
function hideLoadingIndicator(playButton = null, originalButtonHTML = '') {
    const loadingIndicator = document.querySelector('.loading-indicator');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'none';
    }
    if (playButton) {
        playButton.disabled = false;
        playButton.innerHTML = originalButtonHTML;
    }
}

// Reproducir contenido torrent
function playTorrent(magnetLink, content, playButton = null, originalButtonHTML = '') {
    const video = document.getElementById('torrent-player');
    const youtubePlayer = document.getElementById('player');
    const loadingIndicator = document.querySelector('.loading-indicator');

    // Ocultar reproductor de YouTube si está visible
    if (youtubePlayer) youtubePlayer.style.display = 'none';
    
    // Mostrar el reproductor de torrent
    if (video) video.style.display = 'block';

    // Mostrar indicador de carga
    showLoadingIndicator(playButton, 'Conectando con la red P2P...');

    // Detener cualquier torrent actual
    if (currentTorrent) {
        currentTorrent.destroy();
        currentTorrent = null;
    }

    // Iniciar la descarga del torrent
    currentTorrent = torrentClient.add(magnetLink, torrent => {
        // El torrent ha sido descargado
        console.log('Torrent descargado:', torrent);
        
        // Buscar el archivo de video en el torrent
        const file = torrent.files.find(file => file.name.endsWith('.mp4') || file.name.endsWith('.webm') || file.name.endsWith('.mkv'));
        
        if (!file) {
            console.error('No se encontró ningún archivo de video en el torrent');
            hideLoadingIndicator(playButton, originalButtonHTML);
            showNotification('No se pudo encontrar un archivo de video en el torrent', 'error');
            return;
        }

        // Reproducir el archivo de video
        file.renderTo(video, {
            autoplay: true,
            controls: true
        }, (err, elem) => {
            if (err) {
                console.error('Error al reproducir el video:', err);
                hideLoadingIndicator(playButton, originalButtonHTML);
                showNotification('Error al reproducir el video', 'error');
                return;
            }
            
            console.log('Reproduciendo video del torrent');
            hideLoadingIndicator(playButton, originalButtonHTML);
            
            // Actualizar la interfaz
            const videoTitle = document.querySelector('.video-title');
            if (videoTitle && content) {
                videoTitle.textContent = content.title || 'Reproduciendo desde torrent';
            }
            
            // Enfocar el reproductor
            video.focus();
        });
    });

    // Manejar errores
    currentTorrent.on('error', err => {
        console.error('Error en el torrent:', err);
        hideLoadingIndicator(playButton, originalButtonHTML);
        showNotification('Error al cargar el torrent: ' + (err.message || 'Error desconocido'), 'error');
    });

    // Actualizar la interfaz durante la descarga
    currentTorrent.on('download', bytes => {
        const percent = Math.round(currentTorrent.progress * 100);
        if (loadingIndicator) {
            loadingIndicator.innerHTML = `
                <div class="spinner"></div>
                <p>Descargando... ${percent}%</p>
                <div class="progress-bar">
                    <div class="progress" style="width: ${percent}%"></div>
                </div>
                <p>Velocidad: ${formatBytes(currentTorrent.downloadSpeed)}/s - Peers: ${currentTorrent.numPeers}</p>
            `;
        }
    });
}

// Función para formatear bytes a un formato legible
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Reproducir contenido
async function playContent(id, type, videoData = null) {
    const playButton = document.querySelector(`.play-btn[data-id="${id}"][data-type="${type}"]`);
    const originalButtonHTML = playButton?.innerHTML;
    const videoPlayer = document.querySelector('.video-player');
    const videoElement = document.getElementById('videoElement');
    const videoTitle = document.querySelector('.video-title');
    const loadingIndicator = document.querySelector('.loading-indicator');
    const errorMessage = document.querySelector('.error-message');
    const recommendationsList = document.querySelector('.recommendations-list');

    const showLoading = (message = 'Cargando contenido...') => {
        if (playButton) {
            playButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
            playButton.disabled = true;
        }
        if (loadingIndicator) {
            loadingIndicator.style.display = 'flex';
            loadingIndicator.innerHTML = `<div class="spinner"></div><p>${message}</p>`;
        }
        if (errorMessage) errorMessage.style.display = 'none';
    };

    const hideLoading = () => {
        if (playButton) {
            playButton.innerHTML = originalButtonHTML;
            playButton.disabled = false;
        }
        if (loadingIndicator) loadingIndicator.style.display = 'none';
    };

    const showError = (message) => {
        hideLoading();
        showNotification(message, 'error');
    };

    showLoading();

    if (!appState.isLoggedIn) {
        showError('Por favor inicia sesión para reproducir este contenido');
        showLoginModal();
        return;
    }

    let content = videoData;
    if (!content && appState.contentCache && appState.contentCache[`${type}s`]) {
        content = appState.contentCache[`${type}s`].find(item => item.id === id);
    }

    if (!content) {
        // Si no está en caché, buscar en la API
        try {
            const response = await fetch(`${APP_BASE_URL}/api/content/${id}`);
            if (!response.ok) throw new Error('Contenido no encontrado en el servidor');
            const result = await response.json();
            content = result.data;
        } catch (error) {
            console.error(error);
            showError('No se pudo encontrar el contenido solicitado.');
            return;
        }
    }

    if (content.premium && !isUserPremium()) {
        showError('Este contenido es exclusivo para usuarios premium.');
        showPremiumModal();
        return;
    }

    // Mostrar el reproductor
    if (videoPlayer) {
        videoPlayer.classList.add('active');
        document.body.style.overflow = 'hidden';
        videoPlayer.focus();
    }

    if (videoTitle) videoTitle.textContent = content.title || 'Video';
    document.title = `${content.title || 'Video'} - UrresTv`;

    if (content.torrent_magnet) {
        showLoading('Conectando a la red P2P...');
        playTorrent(content.torrent_magnet, content, playButton, originalButtonHTML);
        return;
    }

    if (content.trailer_url) {
        showLoading('Cargando video...');
        try {
            let trailerUrl = String(content.trailer_url || '').trim();
            
            if (!trailerUrl) {
                throw new Error('URL vacía');
            }

            // Normalizar URL si falta el esquema o solo recibe un ID de YouTube
            if (!/^[a-zA-Z][a-zA-Z\d+\-.]*:/.test(trailerUrl)) {
                if (trailerUrl.startsWith('//')) {
                    trailerUrl = `${window.location.protocol}${trailerUrl}`;
                } else if (trailerUrl.startsWith('www.')) {
                    trailerUrl = `https://${trailerUrl}`;
                } else if (/^[\w-]{11}$/.test(trailerUrl)) {
                    trailerUrl = `https://www.youtube.com/watch?v=${trailerUrl}`;
                } else {
                    trailerUrl = `https://${trailerUrl}`;
                }
            }

            const url = new URL(trailerUrl);
            let videoId = '';

            if (url.pathname.startsWith('/embed/')) {
                videoId = url.pathname.split('/')[2];
            } else if (url.hostname.includes('youtube.com') && url.searchParams.has('v')) {
                videoId = url.searchParams.get('v').split('&')[0];
            } else if (url.hostname.includes('youtu.be')) {
                videoId = url.pathname.split('/')[1].split('?')[0];
            }

            if (videoId) {
                videoId = videoId.split('?')[0];
                console.log(`Cargando video de YouTube con ID: ${videoId}`);

                const createYouTubePlayer = () => {
                    window.player = new YT.Player('player', {
                        host: 'https://www.youtube.com',
                        height: '100%',
                        width: '100%',
                        videoId: videoId,
                        playerVars: {
                            'autoplay': 1, 'controls': 0, 'disablekb': 1, 'fs': 0,
                            'modestbranding': 1, 'rel': 0, 'playsinline': 1,
                            'enablejsapi': 1, 'origin': window.location.origin
                        },
                        events: {
                            'onReady': function(event) {
                                console.log('Reproductor de YouTube listo');
                                event.target.playVideo();
                                hideLoading();
                                videoPlayerState.isPlaying = true;
                                updatePlayPauseUI();
                                
                                // Actualizar duración
                                const duration = event.target.getDuration();
                                if (duration) {
                                    videoPlayerState.duration = duration;
                                    updateTimeDisplay();
                                }
                                
                                // Iniciar actualización periódica de la barra de progreso
                                const progressInterval = setInterval(() => {
                                    if (window.player && typeof window.player.getCurrentTime === 'function') {
                                        updateProgressBar();
                                        const state = window.player.getPlayerState();
                                        if (state === YT.PlayerState.PLAYING) {
                                            videoPlayerState.isPlaying = true;
                                        } else if (state === YT.PlayerState.PAUSED) {
                                            videoPlayerState.isPlaying = false;
                                        } else if (state === YT.PlayerState.ENDED) {
                                            videoPlayerState.isPlaying = false;
                                            clearInterval(progressInterval);
                                        }
                                    } else {
                                        clearInterval(progressInterval);
                                    }
                                }, 250);
                                
                                // Guardar referencia al intervalo para limpiarlo después
                                window.youtubeProgressInterval = progressInterval;
                            },
                            'onStateChange': function(event) {
                                console.log('Estado del reproductor:', event.data);
                                if (event.data === YT.PlayerState.PLAYING) {
                                    hideLoading();
                                    videoPlayerState.isPlaying = true;
                                    updatePlayPauseUI();
                                    startHideControlsTimer();
                                } else if (event.data === YT.PlayerState.PAUSED) {
                                    videoPlayerState.isPlaying = false;
                                    updatePlayPauseUI();
                                    showVideoControls();
                                } else if (event.data === YT.PlayerState.ENDED) {
                                    videoPlayerState.isPlaying = false;
                                    updatePlayPauseUI();
                                    if (window.youtubeProgressInterval) {
                                        clearInterval(window.youtubeProgressInterval);
                                    }
                                }
                            },
                            'onError': function(event) {
                                console.error('Error en el reproductor de YouTube:', event.data);
                                showError(`Error al cargar el video (${event.data})`);
                                if (window.youtubeProgressInterval) {
                                    clearInterval(window.youtubeProgressInterval);
                                }
                            }
                        }
                    });
                };

                if (window.player && typeof window.player.loadVideoById === 'function') {
                    window.player.loadVideoById(videoId);
                    const playPromise = window.player.playVideo?.();
                    if (playPromise && typeof playPromise.then === 'function') {
                        playPromise.then(() => {
                            hideLoading();
                        }).catch(error => {
                            console.error('Error al reproducir el video:', error);
                            showError('Error al cargar el video.');
                        });
                    } else {
                        hideLoading();
                    }
                } else if (window.YT && typeof window.YT.Player === 'function') {
                    createYouTubePlayer();
                } else if (window.YouTubeAPIManager && typeof window.YouTubeAPIManager.register === 'function') {
                    window.YouTubeAPIManager.register(createYouTubePlayer);
                    if (typeof window.YouTubeAPIManager.ensureScriptLoaded === 'function') {
                        window.YouTubeAPIManager.ensureScriptLoaded();
                    }
                } else {
                    const previousReady = window.onYouTubeIframeAPIReady;
                    window.onYouTubeIframeAPIReady = function() {
                        if (typeof previousReady === 'function') {
                            previousReady();
                        }
                        createYouTubePlayer();
                    };

                    if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
                        const tag = document.createElement('script');
                        tag.src = 'https://www.youtube.com/iframe_api';
                        const firstScriptTag = document.getElementsByTagName('script')[0];
                        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                    }
                }
            } else {
                throw new Error('No se pudo extraer el ID del video');
            }
        } catch (e) {
            console.error('Error al procesar la URL del video:', e);
            showError('La URL del video no es válida.');
        }
    } else {
        showError('No hay una fuente de video disponible para este contenido.');
    }

    if (id && recommendationsList) {
        loadRecommendations(id, type);
    } else {
        const recommendationsSection = document.querySelector('.recommendations');
        if (recommendationsSection) {
            recommendationsSection.style.display = 'none';
        }
    }

    const retryButton = document.querySelector('.retry-btn');
    if (retryButton) {
        retryButton.onclick = () => playContent(id, type, videoData);
    }

    startHideControlsTimer();
}

// Inicialización de la aplicación cuando el DOM esté listo
// Función para mejorar tarjetas existentes en el DOM
function enhanceExistingContentCards() {
    document.querySelectorAll('.content-card:not([data-enhanced])').forEach(card => {
        const id = card.dataset.id;
        const title = card.dataset.title || card.querySelector('.content-title')?.textContent || '';
        const year = card.dataset.year || '';
        const type = card.dataset.type || 'movie';
        
        if (!id) return;
        
        // Añadir atributos si no existen
        if (!card.dataset.title && title) card.dataset.title = title;
        if (!card.dataset.year && year) card.dataset.year = year;
        if (!card.dataset.detailUrl) card.dataset.detailUrl = `/content.php?id=${id}`;
        
        // Añadir badge de IMDb si no existe
        let imdbBadge = card.querySelector('.imdb-badge');
        if (!imdbBadge) {
            const contentInfo = card.querySelector('.content-info');
            if (contentInfo) {
                const meta = contentInfo.querySelector('.content-meta');
                if (meta && meta.nextElementSibling && !meta.nextElementSibling.classList.contains('imdb-badge')) {
                    imdbBadge = document.createElement('div');
                    imdbBadge.className = 'imdb-badge';
                    imdbBadge.setAttribute('data-id', id);
                    imdbBadge.style.cssText = 'display: inline-flex; align-items: center; gap: 0.35rem; margin: 0.4rem 0; padding: 0.2rem 0.6rem; border-radius: 4px; background: rgba(245,197,24,0.15); color: #f5c518; font-weight: 600; font-size: 0.85rem;';
                    imdbBadge.innerHTML = '<i class="fab fa-imdb"></i><span class="imdb-text">IMDb: —</span>';
                    meta.insertAdjacentElement('afterend', imdbBadge);
                }
            }
        }
        
        // Mejorar con IMDb si hay badge
        if (imdbBadge) {
            const badgeText = imdbBadge.querySelector('.imdb-text');
            if (badgeText && (badgeText.textContent.includes('—') || badgeText.textContent.includes('IMDb: —'))) {
                enhanceCardWithIMDb(card, {id, title, release_year: year}, type);
            }
        }
        
        // Añadir botón de torrent si no existe
        let torrentBtn = card.querySelector('.torrent-btn');
        const actionsContainer = card.querySelector('.content-actions');
        if (!torrentBtn && actionsContainer) {
            torrentBtn = document.createElement('button');
            torrentBtn.className = 'action-btn torrent-btn';
            torrentBtn.setAttribute('data-action', 'torrent');
            torrentBtn.setAttribute('data-id', id);
            torrentBtn.setAttribute('data-title', encodeURIComponent(title));
            torrentBtn.setAttribute('data-year', year);
            torrentBtn.setAttribute('data-type', type);
            torrentBtn.setAttribute('title', 'Buscar torrents');
            torrentBtn.innerHTML = '<i class="fas fa-magnet"></i>';
            actionsContainer.appendChild(torrentBtn);
        }
        
        // Añadir handlers de torrent
        const poster = card.querySelector('img');
        
        if (poster && !poster.dataset.torrentHandler) {
            poster.classList.add('content-poster-clickable');
            poster.style.cursor = 'pointer';
            poster.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof showTorrentModal === 'function') {
                    showTorrentModal(id, title, year || null, type);
                } else {
                    window.location.href = card.dataset.detailUrl || `/content.php?id=${id}`;
                }
            });
            poster.dataset.torrentHandler = 'true';
        }
        
        if (torrentBtn && !torrentBtn.dataset.torrentHandler) {
            torrentBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof showTorrentModal === 'function') {
                    showTorrentModal(id, title, year || null, type);
                }
            });
            torrentBtn.dataset.torrentHandler = 'true';
        }
        
        // Añadir funcionalidad de hover para mostrar trailer si existe
        const trailerUrl = card.dataset.trailerUrl || '';
        if (trailerUrl && !card.dataset.trailerHoverSetup) {
            // Asegurar que existe el contenedor de trailer
            let mediaContainer = card.querySelector('.content-card-media');
            if (!mediaContainer) {
                const img = card.querySelector('img');
                if (img) {
                    mediaContainer = document.createElement('div');
                    mediaContainer.className = 'content-card-media';
                    img.parentNode.insertBefore(mediaContainer, img);
                    mediaContainer.appendChild(img);
                    
                    const trailerContainer = document.createElement('div');
                    trailerContainer.className = 'content-trailer-container';
                    trailerContainer.style.cssText = 'display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;';
                    const trailerWrapper = document.createElement('div');
                    trailerWrapper.className = 'content-trailer-wrapper';
                    trailerContainer.appendChild(trailerWrapper);
                    mediaContainer.appendChild(trailerContainer);
                }
            }
            
            if (mediaContainer && trailerUrl) {
                setupTrailerHover(card, trailerUrl);
                card.dataset.trailerHoverSetup = 'true';
            }
        }
        
        card.dataset.enhanced = 'true';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar WebTorrent si está disponible
    initWebTorrent();
    
    // Inicializar la aplicación
    init();
    
    // Cargar la API de YouTube
    if (window.YouTubeAPIManager && typeof window.YouTubeAPIManager.ensureScriptLoaded === 'function') {
        window.YouTubeAPIManager.ensureScriptLoaded();
    } else if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
    }
    
    // Inicializar controles del reproductor
    initVideoPlayerControls();
    
    // Mejorar tarjetas existentes que fueron cargadas desde PHP
    enhanceExistingContentCards();
    
    // Observar nuevas tarjetas añadidas dinámicamente
    const observer = new MutationObserver(() => {
        enhanceExistingContentCards();
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
}); // Cierre del evento DOMContentLoaded

// Mostrar modal de inicio de sesión
function showLoginModal() {
    if (elements.loginModal) {
        elements.loginModal.classList.add('active');
    }
}

// Cerrar modal de inicio de sesión
function closeLoginModal() {
    if (elements.loginModal) {
        elements.loginModal.classList.remove('active');
    }
}

// Inicializar el reproductor
function initVideoPlayer() {
    // Código de inicialización del reproductor
}
