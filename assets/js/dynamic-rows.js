/**
 * Dynamic Content Rows Loader
 * Handles loading of dynamic content rows based on data attributes
 */

document.addEventListener('DOMContentLoaded', function () {
    // Configuration
    const config = {
        // Get base URL using global helper or fallback
        apiBaseUrl: typeof getApiUrl === 'function'
            ? getApiUrl('').replace(/\/$/, '') // Remove trailing slash if present
            : (window.__APP_BASE_URL || window.location.origin + (window.location.pathname.includes('streaming-platform') ? '/streaming-platform' : '')),

        // API endpoints - relative to apiBaseUrl
        endpoints: {
            content: '/api/content',
            movies: '/api/content/movies',
            series: '/api/content/series',
            recent: '/api/content/recent',
            popular: '/api/content/popular'
        },

        // Default number of items to load
        defaultLimit: 12,

        // Default cache TTL (5 minutes)
        cacheTTL: 5 * 60 * 1000
    };

    // Initialize dynamic rows
    function initDynamicRows() {
        const dynamicRows = document.querySelectorAll('[data-dynamic="true"]');
        if (!dynamicRows.length) return;

        dynamicRows.forEach(row => {
            const type = row.dataset.type || 'content';
            const source = row.dataset.source || 'recent';
            const sort = row.dataset.sort || 'recent';
            const limit = parseInt(row.dataset.limit) || config.defaultLimit;
            const cacheKey = row.dataset.cacheKey || `${type}-${source}-${sort}`;

            // Set loading state
            if (!row.querySelector('.loading-placeholder')) {
                row.innerHTML = `
                    <div class="row-items" id="${cacheKey}-items">
                        ${Array(limit).fill('<div class="content-item loading"></div>').join('')}
                    </div>
                `;
            }

            // Load content
            loadRowContent({
                container: row,
                type,
                source,
                sort,
                limit,
                cacheKey
            });
        });
    }

    // Load content for a row
    async function loadRowContent({ container, type, source, sort, limit, cacheKey }) {
        try {
            // Build the correct API endpoint based on type, source, and sort
            let endpoint;
            const params = new URLSearchParams();

            // Determine which API endpoint to use based on sort/source
            if (sort === 'popular' || source === 'popular') {
                // Use popular endpoint
                endpoint = `${config.apiBaseUrl}/api/content/popular`;
                params.append('limit', limit);

                // Add type if specified
                if (type && type !== 'content') {
                    params.append('type', type);
                }

            } else if (sort === 'recent' || source === 'recent' || source === 'local') {
                // Use recent endpoint (incluye caso 'local')
                endpoint = `${config.apiBaseUrl}/api/content/recent`;
                params.append('limit', limit);

                // Add type if specified
                if (type && type !== 'content') {
                    params.append('type', type);
                }

                // Filtrar por origen local (solo uploads)
                if (source === 'local') {
                    params.append('source', 'local');
                }

            } else if (source === 'imdb') {
                // Use popular with type filter for IMDB content
                endpoint = `${config.apiBaseUrl}/api/content/popular`;
                params.append('limit', limit);

                if (type && type !== 'content') {
                    params.append('type', type);
                }

            } else {
                // Fallback to recent
                endpoint = `${config.apiBaseUrl}/api/content/recent`;
                params.append('limit', limit);

                if (type && type !== 'content') {
                    params.append('type', type);
                }
            }

            // Append parameters to endpoint
            endpoint += `?${params.toString()}`;

            // Check cache first
            const cachedData = getCachedData(cacheKey);
            if (cachedData) {
                renderRowContent(container, cachedData, cacheKey);
                return;
            }

            // Fetch from API
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            const items = data.success ? data.data : data;

            // Cache the response
            cacheData(cacheKey, items);

            // Render the content
            renderRowContent(container, items, cacheKey);

            // Initialize row navigation
            initRowNavigation(cacheKey);

        } catch (error) {
            console.error(`Error loading ${type} (${source}):`, error);
            showError(container, `Error al cargar el contenido: ${error.message}`);
        }
    }

    // Render content in a row
    function renderRowContent(container, items, cacheKey) {
        if (!items || !items.length) {
            container.innerHTML = '<p class="no-content">No se encontró contenido.</p>';
            return;
        }

        const itemsContainer = container.querySelector('.row-items') || document.createElement('div');
        itemsContainer.className = 'row-items';
        itemsContainer.id = `${cacheKey}-items`;

        const itemsHTML = items.map(item => createContentCard(item)).join('');
        itemsContainer.innerHTML = itemsHTML;

        if (!container.querySelector('.row-items')) {
            container.innerHTML = '';
            container.appendChild(itemsContainer);
        }

        // Lazy load images
        initLazyLoading(container);
    }

    // Create content card HTML
    function createContentCard(item) {
        const posterUrl = item.poster_url || item.poster || '';
        const title = item.title || 'Sin título';
        const year = item.release_year || item.year || '';
        const id = item.id || '';
        const type = item.type || 'content';
        const duration = item.duration || null;
        const videoUrl = item.video_url || '';
        
        // Detectar si es video local sin poster
        const isLocalVideo = videoUrl && (videoUrl.includes('/uploads/') || videoUrl.startsWith('/uploads/'));
        const hasNoPoster = !posterUrl || posterUrl.trim() === '' || posterUrl.includes('default-poster.svg');
        const showCustomPlaceholder = isLocalVideo && hasNoPoster;

        // Procesar URL del poster (normalizar rutas relativas y absolutas)
        function processImageUrl(url, defaultUrl) {
            if (!url || url.trim() === '' || url.includes('default-poster.svg')) {
                return defaultUrl;
            }
            
            // Si ya es una URL completa, usarla directamente
            if (url.startsWith('http://') || url.startsWith('https://')) {
                return url;
            }
            
            // Si es una ruta relativa que empieza con /, convertir a absoluta
            if (url.startsWith('/')) {
                return config.apiBaseUrl + url;
            }
            
            // Si es una ruta relativa sin /, añadirla
            return config.apiBaseUrl + '/' + url;
        }

        // Validar y sanitizar URLs
        const safePosterUrl = processImageUrl(posterUrl, `${config.apiBaseUrl}/assets/img/default-poster.svg`);
        const safeDefaultUrl = `${config.apiBaseUrl}/assets/img/default-poster.svg`;

        // Formatear duración
        let durationText = '';
        if (duration) {
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            if (hours > 0) {
                durationText = `${hours}h ${minutes}m`;
            } else {
                durationText = `${minutes}m`;
            }
        }

        return `
            <div class="content-item" data-id="${id}" data-type="${type}">
                <div class="content-card">
                    <div class="content-poster">
                        ${showCustomPlaceholder ? `
                            <div class="local-video-placeholder" style="
                                width: 100%;
                                height: 100%;
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
                                background-size: 400% 400%;
                                animation: gradientShift 15s ease infinite;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: center;
                                padding: 1.5rem;
                                text-align: center;
                                position: relative;
                                overflow: hidden;
                            ">
                                <div style="
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: rgba(0, 0, 0, 0.3);
                                    z-index: 1;
                                "></div>
                                <div style="position: relative; z-index: 2; width: 100%;">
                                    <div style="
                                        font-size: 3rem;
                                        margin-bottom: 1rem;
                                        color: rgba(255, 255, 255, 0.95);
                                        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
                                    ">
                                        <i class="fas fa-video"></i>
                                    </div>
                                    <h3 style="
                                        font-size: 1.1rem;
                                        font-weight: 600;
                                        color: #ffffff;
                                        margin: 0 0 0.5rem 0;
                                        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
                                        line-height: 1.3;
                                        display: -webkit-box;
                                        -webkit-line-clamp: 2;
                                        -webkit-box-orient: vertical;
                                        overflow: hidden;
                                    ">${title}</h3>
                                    ${year || durationText ? `
                                        <div style="
                                            display: flex;
                                            gap: 0.75rem;
                                            justify-content: center;
                                            align-items: center;
                                            margin-top: 0.5rem;
                                            flex-wrap: wrap;
                                        ">
                                            ${year ? `<span style="
                                                background: rgba(255, 255, 255, 0.2);
                                                backdrop-filter: blur(10px);
                                                padding: 0.25rem 0.75rem;
                                                border-radius: 12px;
                                                font-size: 0.85rem;
                                                color: #ffffff;
                                                font-weight: 500;
                                            ">${year}</span>` : ''}
                                            ${durationText ? `<span style="
                                                background: rgba(255, 255, 255, 0.2);
                                                backdrop-filter: blur(10px);
                                                padding: 0.25rem 0.75rem;
                                                border-radius: 12px;
                                                font-size: 0.85rem;
                                                color: #ffffff;
                                                font-weight: 500;
                                            "><i class="fas fa-clock" style="margin-right: 0.25rem;"></i>${durationText}</span>` : ''}
                                        </div>
                                    ` : ''}
                                    <div style="
                                        margin-top: 1rem;
                                        padding: 0.5rem 1rem;
                                        background: rgba(255, 255, 255, 0.15);
                                        backdrop-filter: blur(10px);
                                        border-radius: 20px;
                                        font-size: 0.75rem;
                                        color: #ffffff;
                                        font-weight: 500;
                                        text-transform: uppercase;
                                        letter-spacing: 0.5px;
                                    ">
                                        <i class="fas fa-cloud-upload-alt" style="margin-right: 0.5rem;"></i>Video Local
                                    </div>
                                </div>
                            </div>
                        ` : `
                            <img
                                src="${safePosterUrl}"
                                alt="${title}"
                                loading="lazy"
                                onerror="this.onerror=null; this.src='${safeDefaultUrl}'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
                                style="background: linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%);"
                            >
                        `}
                        <div class="content-overlay">
                            <button class="btn-play action-btn" data-action="play" data-id="${id}" data-type="${type}" title="Reproducir">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn-info action-btn" data-action="info" data-id="${id}" data-type="${type}" title="Más información">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="content-details">
                        <h3 class="content-title">${title}</h3>
                        ${year ? `<span class="content-year">${year}</span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    // Initialize row navigation
    function initRowNavigation(rowId) {
        const container = document.getElementById(rowId);
        if (!container) return;

        const itemsContainer = container.querySelector('.row-items');
        if (!itemsContainer) return;

        const prevBtn = container.previousElementSibling;
        const nextBtn = container.nextElementSibling;

        const updateNavButtons = () => {
            if (!itemsContainer.scrollWidth) return;

            const scrollLeft = itemsContainer.scrollLeft;
            const maxScroll = itemsContainer.scrollWidth - itemsContainer.clientWidth;

            if (prevBtn) {
                prevBtn.style.opacity = scrollLeft > 0 ? '1' : '0';
                prevBtn.style.pointerEvents = scrollLeft > 0 ? 'auto' : 'none';
            }

            if (nextBtn) {
                nextBtn.style.opacity = scrollLeft < maxScroll - 10 ? '1' : '0';
                nextBtn.style.pointerEvents = scrollLeft < maxScroll - 10 ? 'auto' : 'none';
            }
        };

        // Initial update
        updateNavButtons();

        // Update on scroll
        itemsContainer.addEventListener('scroll', updateNavButtons);

        // Handle navigation buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                itemsContainer.scrollBy({
                    left: -itemsContainer.clientWidth * 0.8,
                    behavior: 'smooth'
                });
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                itemsContainer.scrollBy({
                    left: itemsContainer.clientWidth * 0.8,
                    behavior: 'smooth'
                });
            });
        }

        // Update on window resize
        window.addEventListener('resize', updateNavButtons);
    }

    // Initialize lazy loading for images
    function initLazyLoading(container) {
        const lazyImages = container.querySelectorAll('img.lazyload');

        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazyload');
                        imageObserver.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(img => imageObserver.observe(img));
        } else {
            // Fallback for browsers that don't support IntersectionObserver
            lazyImages.forEach(img => {
                img.src = img.dataset.src;
                img.classList.remove('lazyload');
            });
        }
    }

    // Cache functions
    function cacheData(key, data) {
        try {
            const cache = {
                data: data,
                timestamp: Date.now()
            };
            localStorage.setItem(`cache_${key}`, JSON.stringify(cache));
        } catch (e) {
            console.error('Error caching data:', e);
        }
    }

    function getCachedData(key) {
        try {
            const cached = localStorage.getItem(`cache_${key}`);
            if (!cached) return null;

            const { data, timestamp } = JSON.parse(cached);
            const CACHE_DURATION = 30 * 60 * 1000; // 30 minutes

            if (Date.now() - timestamp < CACHE_DURATION) {
                return data;
            }

            // Clear expired cache
            localStorage.removeItem(`cache_${key}`);
            return null;
        } catch (e) {
            console.error('Error getting cached data:', e);
            return null;
        }
    }

    // Show error message
    function showError(container, message) {
        container.innerHTML = `
            <div class="error-message">
                <p>${message}</p>
                <button class="btn-retry" onclick="window.location.reload()">Reintentar</button>
            </div>
        `;
    }

    // Initialize everything
    initDynamicRows();
});
