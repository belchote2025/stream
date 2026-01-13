/**
 * Cargador moderno y optimizado para la página principal
 * Carga contenido de forma asíncrona para mejorar el rendimiento
 */

(function () {
    'use strict';

    const BASE_URL = window.__APP_BASE_URL || '';
    const CACHE_DURATION = 5 * 60 * 1000; // 5 minutos
    const cache = new Map();

    // Configuración de secciones
    const sections = [
        {
            id: 'continue-watching',
            endpoint: '/api/continue-watching.php',
            limit: 12
        },
        {
            id: 'popular-movies',
            endpoint: '/api/content/popular.php',
            params: { type: 'movie', limit: 12 }
        },
        {
            id: 'popular-series',
            endpoint: '/api/content/popular.php',
            params: { type: 'series', limit: 12 }
        },
        {
            id: 'recent-movies',
            endpoint: '/api/content/recent.php',
            params: { type: 'movie', limit: 12 }
        },
        {
            id: 'recent-series',
            endpoint: '/api/content/recent.php',
            params: { type: 'series', limit: 12 }
        },
        {
            id: 'imdb-movies',
            endpoint: '/api/content/popular.php',
            params: { source: 'imdb', limit: 12 }
        },
        {
            id: 'local-videos',
            endpoint: '/api/content/recent.php',
            params: { source: 'local', limit: 12 }
        },
        {
            id: 'recommended',
            endpoint: '/api/recommendations/improved.php',
            params: { limit: 12 }
        }
    ];

    // Función para obtener datos con caché
    async function fetchWithCache(url, params = {}) {
        const cacheKey = url + JSON.stringify(params);
        const cached = cache.get(cacheKey);

        if (cached && (Date.now() - cached.timestamp) < CACHE_DURATION) {
            return cached.data;
        }

        try {
            const queryString = new URLSearchParams(params).toString();
            const fullUrl = BASE_URL + url + (queryString ? '?' + queryString : '');

            const response = await fetch(fullUrl);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const data = await response.json();
            cache.set(cacheKey, { data, timestamp: Date.now() });
            return data;
        } catch (error) {
            if (window.Logger) {
                Logger.error(`Error cargando ${url}:`, error);
            } else {
                console.error(`Error cargando ${url}:`, error);
            }
            return null;
        }
    }

    // Función para crear una ficha moderna
    function createModernCard(item) {
        const card = document.createElement('div');
        card.className = 'content-card';
        card.setAttribute('data-id', item.id);
        card.setAttribute('data-type', item.type || 'movie');

        // Obtener URL de imagen
        let posterUrl = item.poster_url || item.poster || '';
        if (posterUrl && !posterUrl.startsWith('http') && !posterUrl.startsWith('/')) {
            posterUrl = BASE_URL + '/' + posterUrl;
        } else if (!posterUrl || posterUrl === 'null') {
            posterUrl = BASE_URL + '/assets/img/default-poster.svg';
        }

        const watchUrl = item.episode_id
            ? `${BASE_URL}/watch.php?id=${item.id}&episode_id=${item.episode_id}`
            : `${BASE_URL}/watch.php?id=${item.id}`;

        const detailUrl = `${BASE_URL}/content-detail.php?id=${item.id}`;

        // Calcular progreso si existe
        let progressBar = '';
        if (item.progress_seconds && item.duration_seconds) {
            const progressPercent = Math.round((item.progress_seconds / item.duration_seconds) * 100);
            if (progressPercent > 0 && progressPercent < 100) {
                progressBar = `<div class="progress-bar"><div class="progress" style="width: ${progressPercent}%"></div></div>`;
            }
        } else if (item.progress && item.duration) {
            const progressPercent = Math.round((item.progress / item.duration) * 100);
            if (progressPercent > 0 && progressPercent < 100) {
                progressBar = `<div class="progress-bar"><div class="progress" style="width: ${progressPercent}%"></div></div>`;
            }
        } else if (item.progress_percent) {
            progressBar = `<div class="progress-bar"><div class="progress" style="width: ${item.progress_percent}%"></div></div>`;
        }

        // Badges
        const badges = [];
        if (item.is_premium) badges.push('<span class="content-badge premium">PREMIUM</span>');
        if (item.torrent_magnet) badges.push('<span class="content-badge" title="Disponible por Torrent"><i class="fas fa-magnet"></i></span>');
        if (item.release_year && new Date().getFullYear() - item.release_year <= 1) {
            badges.push('<span class="content-badge new">NUEVO</span>');
        }

        card.innerHTML = `
            ${progressBar}
            ${badges.join('')}
            <img 
                src="${posterUrl}" 
                alt="${item.title || 'Sin título'}"
                loading="lazy"
                onerror="this.onerror=null; this.src='${BASE_URL}/assets/img/default-poster.svg'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
            >
            <div class="content-info">
                <h3>${item.title || 'Sin título'}</h3>
                ${(item.episode_title || item.episode_info) ? `
                    <div style="font-size: 0.8rem; color: #999; margin-bottom: 0.5rem;">
                        ${item.episode_info || `T${item.season_number || 1}E${item.episode_number || 1}: ${item.episode_title}`}
                    </div>
                ` : ''}
                <div class="content-actions">
                    <button class="action-btn" data-action="play" data-id="${item.id}" title="Reproducir">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="action-btn" data-action="info" data-id="${item.id}" title="Más información">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
            </div>
        `;

        // Event listeners
        card.addEventListener('click', function (e) {
            if (!e.target.closest('.action-btn')) {
                window.location.href = watchUrl;
            }
        });

        // Botón play
        const playBtn = card.querySelector('[data-action="play"]');
        if (playBtn) {
            playBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                window.location.href = watchUrl;
            });
        }

        // Botón info
        const infoBtn = card.querySelector('[data-action="info"]');
        if (infoBtn) {
            infoBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                window.location.href = detailUrl;
            });
        }

        return card;
    }

    // Función para renderizar contenido en una sección
    function renderSection(sectionId, data) {
        const container = document.getElementById(sectionId);
        if (!container) return;

        let rowItems = container.querySelector('.row-items');
        if (!rowItems) {
            // Crear contenedor de items si no existe
            rowItems = document.createElement('div');
            rowItems.className = 'row-items';
            container.appendChild(rowItems);
        }

        // Limpiar skeleton loaders
        rowItems.innerHTML = '';

        if (!data) {
            rowItems.innerHTML = '<p class="loading-placeholder">Error al cargar contenido</p>';
            return;
        }

        // Manejar diferentes formatos de respuesta
        let items = [];
        if (data.success && data.data) {
            items = Array.isArray(data.data) ? data.data : [];
        } else if (Array.isArray(data)) {
            items = data;
        } else if (data.data && Array.isArray(data.data)) {
            items = data.data;
        }

        if (items.length === 0) {
            rowItems.innerHTML = '<p class="loading-placeholder">No hay contenido disponible</p>';
            return;
        }

        // Crear y añadir tarjetas
        items.forEach(item => {
            try {
                const card = createModernCard(item);
                rowItems.appendChild(card);
            } catch (error) {
                if (window.Logger) {
                    Logger.error('Error creando tarjeta:', error, item);
                } else {
                    console.error('Error creando tarjeta:', error, item);
                }
            }
        });

        // Inicializar navegación del carrusel
        initCarousel(sectionId);
    }

    // Función para inicializar navegación del carrusel
    function initCarousel(sectionId) {
        const container = document.querySelector(`#${sectionId}`).closest('.row-container');
        if (!container) return;

        const rowItems = container.querySelector('.row-items');
        const prevBtn = container.querySelector('.row-nav.prev');
        const nextBtn = container.querySelector('.row-nav.next');

        if (!rowItems || !prevBtn || !nextBtn) return;

        let scrollPosition = 0;
        const scrollAmount = 800;

        prevBtn.addEventListener('click', () => {
            scrollPosition = Math.max(0, scrollPosition - scrollAmount);
            rowItems.style.transform = `translateX(-${scrollPosition}px)`;
        });

        nextBtn.addEventListener('click', () => {
            const maxScroll = rowItems.scrollWidth - rowItems.clientWidth;
            scrollPosition = Math.min(maxScroll, scrollPosition + scrollAmount);
            rowItems.style.transform = `translateX(-${scrollPosition}px)`;
        });
    }

    // Función para cargar una sección
    async function loadSection(section) {
        const container = document.getElementById(section.id);
        if (!container || !container.hasAttribute('data-dynamic')) return;

        try {
            const data = await fetchWithCache(section.endpoint, section.params || {});
            if (data) {
                renderSection(section.id, data);
            }
        } catch (error) {
            if (window.Logger) {
                Logger.error(`Error cargando sección ${section.id}:`, error);
            } else {
                console.error(`Error cargando sección ${section.id}:`, error);
            }
        }
    }

    // Cargar secciones de forma progresiva
    function loadSectionsProgressive() {
        sections.forEach((section, index) => {
            // Cargar las primeras 2 secciones inmediatamente
            // Las demás con un pequeño delay para no saturar
            setTimeout(() => {
                loadSection(section);
            }, index < 2 ? 0 : index * 200);
        });
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadSectionsProgressive);
    } else {
        loadSectionsProgressive();
    }

    // Cargar secciones visibles cuando entran en viewport (lazy loading)
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const sectionId = entry.target.id;
                    const section = sections.find(s => s.id === sectionId);
                    if (section) {
                        loadSection(section);
                        observer.unobserve(entry.target);
                    }
                }
            });
        }, {
            rootMargin: '200px'
        });

        sections.forEach(section => {
            const container = document.getElementById(section.id);
            if (container) {
                observer.observe(container);
            }
        });
    }
})();
