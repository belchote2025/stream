/**
 * Dynamic Content Rows Loader
 * Handles loading of dynamic content rows based on data attributes
 */

document.addEventListener('DOMContentLoaded', function () {
    // Configuration
    const config = {
        // Get base URL from window.__APP_BASE_URL or detect from current location
        apiBaseUrl: window.__APP_BASE_URL || window.location.origin +
            (window.location.pathname.includes('streaming-platform') ? '/streaming-platform' : ''),

        // API endpoints - relative to apiBaseUrl
        endpoints: {
            content: '/api/content',
            movies: '/api/content/movies.php',
            series: '/api/content/series.php',
            recent: '/api/content/recent.php',
            popular: '/api/content/popular.php'
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
                // Use popular.php endpoint
                endpoint = `${config.apiBaseUrl}/api/content/popular.php`;
                params.append('limit', limit);

                // Add type if specified
                if (type && type !== 'content') {
                    params.append('type', type);
                }

            } else if (sort === 'recent' || source === 'recent' || source === 'local') {
                // Use recent.php endpoint
                endpoint = `${config.apiBaseUrl}/api/content/recent.php`;
                params.append('limit', limit);

                // Add type if specified
                if (type && type !== 'content') {
                    params.append('type', type);
                }

            } else if (source === 'imdb') {
                // Use popular.php with type filter for IMDB content
                endpoint = `${config.apiBaseUrl}/api/content/popular.php`;
                params.append('limit', limit);

                if (type && type !== 'content') {
                    params.append('type', type);
                }

            } else {
                // Fallback to recent.php
                endpoint = `${config.apiBaseUrl}/api/content/recent.php`;
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
        const posterUrl = item.poster_url || item.poster || `${config.apiBaseUrl}/assets/img/default-poster.svg`;
        const title = item.title || 'Sin título';
        const year = item.release_year || item.year || '';
        const id = item.id || '';
        const type = item.type || 'content';

        return `
            <div class="content-item" data-id="${id}" data-type="${type}">
                <div class="content-card">
                    <div class="content-poster">
                        <img 
                            src="${config.apiBaseUrl}/assets/img/placeholder.png" 
                            data-src="${posterUrl}" 
                            alt="${title}" 
                            class="lazyload"
                            onerror="this.onerror=null; this.src='${config.apiBaseUrl}/assets/img/default-poster.svg'"
                        >
                        <div class="content-overlay">
                            <button class="btn-play" data-action="play" data-id="${id}" data-type="${type}">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn-info" data-action="info" data-id="${id}" data-type="${type}">
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
