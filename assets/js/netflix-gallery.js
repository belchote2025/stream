/**
 * Netflix-style Gallery Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const config = {
        api: {
            featured: '/streaming-platform/api/content/featured.php',
            recentlyAdded: '/streaming-platform/api/content/recent.php',
            mostViewed: '/streaming-platform/api/content/popular.php',
            contentDetails: '/streaming-platform/api/content/index.php?id='
        },
        selectors: {
            hero: '.hero',
            heroBackdrop: '.hero-slide.active .hero-backdrop, .hero-backdrop',
            heroTitle: '.hero-title',
            heroDescription: '.hero-description',
            heroActions: '.hero-actions',
            contentRows: '.content-rows',
            rowTemplate: '#content-row-template',
            modal: '#contentModal',
            modalTitle: '#contentModalLabel',
            modalBody: '.modal-body',
            modalPlayer: '#contentPlayer'
        },
        defaultBackdrop: '/streaming-platform/assets/img/default-backdrop.svg',
        defaultPoster: '/streaming-platform/assets/img/default-poster.svg',
        autoPlayTrailer: true,
        autoPlayDelay: 5000, // 5 seconds
        lazyLoadOffset: 200 // pixels from viewport to start loading
    };

    // State
    let state = {
        currentHeroIndex: 0,
        heroItems: [],
        autoPlayInterval: null,
        isLoading: false,
        currentVideo: null
    };

    // DOM Elements
    const elements = {};

    // Initialize the gallery
    function init() {
        // Cache DOM elements
        for (const [key, selector] of Object.entries(config.selectors)) {
            const element = document.querySelector(selector);
            if (element) {
                elements[key] = element;
            } else {
                console.warn(`Element not found for selector: ${selector}`);
            }
        }

        // Solo inicializar hero si los elementos existen
        if (elements.hero || elements.heroBackdrop) {
            initHero();
        }
        
        // Inicializar filas de contenido
        if (elements.contentRows) {
            initContentRows();
        }
        
        initEventListeners();
        initIntersectionObserver();
    }

    // Initialize hero carousel
    async function initHero() {
        try {
            const response = await fetch(config.api.featured);
            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                state.heroItems = data.data;
                renderHero();
                startAutoPlay();
            }
        } catch (error) {
            console.error('Error loading featured content:', error);
        }
    }

    // Render hero section with current item
    function renderHero() {
        if (!state.heroItems.length) return;
        
        const item = state.heroItems[state.currentHeroIndex];
        if (!item) return;
        
        let backdropUrl = item.backdrop_url || config.defaultBackdrop;
        
        // Si es una URL de TMDB, usar proxy
        if (backdropUrl.match(/^https?:\/\/(image\.tmdb\.org|via\.placeholder\.com|images\.unsplash\.com)/)) {
            backdropUrl = '/streaming-platform/api/image-proxy.php?url=' + encodeURIComponent(backdropUrl);
        }
        
        const safeBackdropUrl = backdropUrl.replace(/'/g, "\\'");
        
        // Update hero content - verificar que los elementos existan
        if (elements.heroBackdrop) {
            elements.heroBackdrop.style.backgroundImage = `url('${safeBackdropUrl}')`;
        } else {
            // Intentar encontrar el backdrop de otra forma
            const heroBackdrop = document.querySelector('.hero-slide.active .hero-backdrop') || 
                                document.querySelector('.hero-backdrop');
            if (heroBackdrop) {
                heroBackdrop.style.backgroundImage = `url('${safeBackdropUrl}')`;
            }
        }
        
        if (elements.heroTitle) {
            elements.heroTitle.textContent = item.title || '';
        }
        
        if (elements.heroDescription) {
            elements.heroDescription.textContent = item.description || '';
        }
        
        // Update action buttons
        if (elements.heroActions) {
            const safeId = item.id || 0;
            elements.heroActions.innerHTML = `
                <button class="btn btn-primary" data-action="play" data-id="${safeId}">
                    <i class="fas fa-play"></i> Reproducir
                </button>
                <button class="btn btn-secondary" data-action="info" data-id="${safeId}">
                    <i class="fas fa-info-circle"></i> Más información
                </button>
            `;
        }
    }

    // Start auto-play for hero carousel
    function startAutoPlay() {
        if (state.heroItems.length <= 1) return;
        
        clearInterval(state.autoPlayInterval);
        
        state.autoPlayInterval = setInterval(() => {
            state.currentHeroIndex = (state.currentHeroIndex + 1) % state.heroItems.length;
            renderHero();
        }, config.autoPlayDelay);
    }

    // Initialize content rows
    async function initContentRows() {
        if (!elements.contentRows) return;
        
        const rowConfigs = [
            { title: 'Tendencias ahora', endpoint: config.api.mostViewed, type: 'movie' },
            { title: 'Nuevos lanzamientos', endpoint: config.api.recentlyAdded, type: 'series' },
            { title: 'Populares en tu país', endpoint: config.api.mostViewed, type: 'series' },
            { title: 'Continuar viendo', endpoint: config.api.recentlyAdded, type: 'movie' }
        ];
        
        for (const config of rowConfigs) {
            await createContentRow(config);
        }
    }

    // Create a content row
    async function createContentRow({ title, endpoint, type }) {
        try {
            if (!elements.contentRows) {
                console.warn('contentRows element not found');
                return;
            }
            
            const response = await fetch(`${endpoint}?type=${type}&limit=10`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
                const rowId = `row-${title.toLowerCase().replace(/\s+/g, '-')}`;
                const safeTitle = title.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                
                const cards = data.data
                    .map(item => createContentCard(item))
                    .filter(card => card !== '')
                    .join('');
                
                if (!cards) {
                    console.warn(`No valid cards generated for ${title}`);
                    return;
                }
                
                const rowHTML = `
                    <div class="row-container">
                        <div class="row-header">
                            <h2 class="row-title">${safeTitle}</h2>
                            <a href="#" class="row-link">Ver todo</a>
                        </div>
                        <div class="row-nav prev">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        <div class="row-content" id="${rowId}">
                            ${cards}
                        </div>
                        <div class="row-nav next">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                `;
                
                elements.contentRows.insertAdjacentHTML('beforeend', rowHTML);
                initRowNavigation(rowId);
            }
        } catch (error) {
            console.error(`Error loading content for ${title}:`, error);
        }
    }

    // Create content card HTML
    function createContentCard(item) {
        if (!item || !item.id) return '';
        
        let posterUrl = (item.poster_url && item.poster_url.trim() !== '') ? item.poster_url : config.defaultPoster;
        
        // Si es una URL de TMDB, usar proxy
        if (posterUrl.match(/^https?:\/\/(image\.tmdb\.org|via\.placeholder\.com|images\.unsplash\.com)/)) {
            posterUrl = '/streaming-platform/api/image-proxy.php?url=' + encodeURIComponent(posterUrl);
        }
        
        const year = item.release_year || '';
        const type = item.type === 'movie' ? 'Película' : 'Serie';
        const title = item.title || 'Sin título';
        
        // Escapar comillas y caracteres especiales
        const safePosterUrl = posterUrl.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeDefaultPoster = config.defaultPoster.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeTitle = title.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const safeAlt = title.replace(/"/g, '&quot;');
        
        return `
            <div class="content-card" data-id="${item.id}" data-type="${item.type || 'movie'}">
                <img 
                    src="${safePosterUrl}" 
                    alt="${safeAlt}" 
                    loading="lazy"
                    onerror="this.onerror=null; this.src='${safeDefaultPoster}'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
                    style="background: linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%);"
                >
                <div class="content-info">
                    <h3 class="content-title">${safeTitle}</h3>
                    <div class="content-meta">
                        <span>${year}</span>
                        <span>•</span>
                        <span>${type}</span>
                        ${item.rating ? `<span>•</span><span>⭐ ${parseFloat(item.rating).toFixed(1)}</span>` : ''}
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
                    </div>
                </div>
            </div>
        `;
    }

    // Initialize row navigation
    function initRowNavigation(rowId) {
        const row = document.getElementById(rowId);
        if (!row) return;
        
        const container = row.closest('.row-container');
        const prevBtn = container.querySelector('.prev');
        const nextBtn = container.querySelector('.next');
        
        const scrollAmount = row.offsetWidth * 0.8; // Scroll 80% of container width
        
        nextBtn.addEventListener('click', () => {
            row.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });
        
        prevBtn.addEventListener('click', () => {
            row.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });
        
        // Show/hide navigation buttons based on scroll position
        row.addEventListener('scroll', () => {
            const { scrollLeft, scrollWidth, clientWidth } = row;
            
            prevBtn.style.opacity = scrollLeft > 0 ? '1' : '0';
            nextBtn.style.opacity = scrollLeft < (scrollWidth - clientWidth - 10) ? '1' : '0';
        });
        
        // Initial state
        prevBtn.style.opacity = '0';
        nextBtn.style.opacity = '1';
    }

    // Initialize event listeners
    function initEventListeners() {
        // Hero play button
        document.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (!button) return;
            
            const action = button.getAttribute('data-action');
            const contentId = button.getAttribute('data-id');
            const contentType = button.closest('.content-card')?.getAttribute('data-type') || 'movie';
            
            switch (action) {
                case 'play':
                    playContent(contentId, contentType);
                    break;
                case 'info':
                    showContentInfo(contentId);
                    break;
                case 'add':
                    addToMyList(contentId);
                    break;
            }
        });
        
        // Pause auto-play on hover
        elements.hero?.addEventListener('mouseenter', () => {
            clearInterval(state.autoPlayInterval);
        });
        
        elements.hero?.addEventListener('mouseleave', () => {
            startAutoPlay();
        });
        
        // Modal close button
        elements.modal?.addEventListener('hidden.bs.modal', () => {
            stopCurrentVideo();
        });
    }

    // Initialize Intersection Observer for lazy loading
    function initIntersectionObserver() {
        if (!('IntersectionObserver' in window)) return;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                }
            });
        }, {
            rootMargin: `${config.lazyLoadOffset}px`
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            observer.observe(img);
        });
    }

    // Play content
    async function playContent(contentId, contentType = 'movie') {
        try {
            // Stop any currently playing video
            stopCurrentVideo();
            
            // In a real app, you would fetch the actual video URL from your API
            const videoUrl = `/content/stream/${contentType}/${contentId}`;
            
            // Update the modal with video player
            if (elements.modal) {
                const modal = new bootstrap.Modal(elements.modal);
                elements.modalTitle.textContent = 'Reproduciendo...';
                elements.modalBody.innerHTML = `
                    <div class="ratio ratio-16x9">
                        <video id="contentPlayer" class="w-100" controls autoplay>
                            <source src="${videoUrl}" type="video/mp4">
                            Tu navegador no soporta el elemento de video.
                        </video>
                    </div>
                `;
                modal.show();
                
                // Store reference to the video element
                state.currentVideo = document.getElementById('contentPlayer');
                
                // Handle video end
                state.currentVideo.addEventListener('ended', () => {
                    // You could implement "Next episode" or "Play next in series" logic here
                });
            }
        } catch (error) {
            console.error('Error playing content:', error);
            alert('No se pudo reproducir el contenido. Por favor, inténtalo de nuevo.');
        }
    }

    // Show content info in modal
    async function showContentInfo(contentId) {
        try {
            // Fetch detailed content info from API
            const response = await fetch(`${config.api.contentDetails}${contentId}`);
            if (!response.ok) throw new Error('Error al cargar información');
            const apiData = await response.json();
            
            if (apiData.success && apiData.data) {
                const data = apiData.data;
                const duration = data.type === 'movie' 
                    ? `${Math.floor(data.duration / 60)}h ${data.duration % 60}m`
                    : `${data.duration} min`;
                
                if (elements.modal) {
                    const modal = new bootstrap.Modal(elements.modal);
                    elements.modalTitle.textContent = data.title;
                    elements.modalBody.innerHTML = `
                        <div class="row">
                            <div class="col-md-4">
                                <img src="${data.poster_url || config.defaultPoster}" alt="${data.title}" class="img-fluid rounded">
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex gap-3 mb-3">
                                    <span class="badge bg-primary">${data.release_year}</span>
                                    <span class="text-muted">${duration}</span>
                                    ${data.rating ? `<span>⭐ ${data.rating}/10</span>` : ''}
                                </div>
                                <p>${data.description || 'Sin descripción disponible.'}</p>
                                <div class="mt-3">
                                    <h6>Géneros:</h6>
                                    <div class="d-flex flex-wrap gap-2">
                                        ${(data.genres || []).map(genre => `<span class="badge bg-secondary">${genre}</span>`).join('')}
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button class="btn btn-primary me-2" data-action="play" data-id="${contentId}">
                                        <i class="fas fa-play me-2"></i>Reproducir
                                    </button>
                                    <button class="btn btn-outline-secondary" data-action="add" data-id="${contentId}">
                                        <i class="fas fa-plus me-2"></i>Mi lista
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    modal.show();
                }
            } else {
                throw new Error('Datos no válidos');
            }
        } catch (error) {
            console.error('Error loading content details:', error);
            alert('No se pudieron cargar los detalles del contenido.');
        }
    }

    // Add content to user's list
    async function addToMyList(contentId) {
        try {
            // In a real app, you would make an API call to add to user's list
            // const response = await fetch('/api/user/list/add', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({ contentId })
            // });
            
            // For demo, just show a success message
            console.log(`Added content ${contentId} to My List`);
            
            // You could add visual feedback here
            const button = document.querySelector(`[data-action="add"][data-id="${contentId}"]`);
            if (button) {
                const icon = button.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-check';
                    setTimeout(() => {
                        icon.className = 'fas fa-plus';
                    }, 2000);
                }
            }
        } catch (error) {
            console.error('Error adding to list:', error);
        }
    }

    // Stop currently playing video
    function stopCurrentVideo() {
        if (state.currentVideo) {
            state.currentVideo.pause();
            state.currentVideo.currentTime = 0;
            state.currentVideo = null;
        }
    }

    // Initialize the gallery when the DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
});
