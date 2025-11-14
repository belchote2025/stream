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

    // Flag para prevenir múltiples inicializaciones
    let galleryInitialized = false;
    
    // Initialize the gallery
    function init() {
        // Prevenir múltiples inicializaciones
        if (galleryInitialized) {
            return;
        }
        galleryInitialized = true;
        
        // Selectores opcionales que no deben mostrar warning si no existen
        // Estos elementos solo existen en index.php, no en watch.php u otras páginas
        const optionalSelectors = [
            'rowTemplate', 
            'modal', 
            'modalTitle', 
            'modalBody', 
            'modalPlayer',
            'hero',
            'heroBackdrop',
            'heroTitle',
            'heroDescription',
            'heroActions',
            'contentRows'
        ];
        
        // Cache DOM elements
        for (const [key, selector] of Object.entries(config.selectors)) {
            const element = document.querySelector(selector);
            if (element) {
                elements[key] = element;
            }
            // Los selectores opcionales se ignoran silenciosamente (no mostrar warnings)
            // Solo mostrar warning si es un selector crítico que debería existir
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
        // Primero verificar si ya hay slides en el HTML
        const existingSlides = document.querySelectorAll('.hero-slide');
        
        if (existingSlides.length > 0) {
            // Usar los slides existentes del HTML
            state.heroItems = Array.from(existingSlides).map((slide, index) => {
                const backdrop = slide.querySelector('.hero-backdrop');
                const title = document.querySelector('.hero-title');
                const description = document.querySelector('.hero-description');
                const trailer = slide.dataset.trailer || '';
                
                return {
                    id: slide.dataset.index || index,
                    title: title ? title.textContent : '',
                    description: description ? description.textContent : '',
                    backdrop_url: backdrop ? backdrop.style.backgroundImage.match(/url\(['"]?([^'"]+)['"]?\)/)?.[1] : '',
                    trailer_url: trailer
                };
            });
            
            // Inicializar el carousel con los slides existentes
            state.currentHeroIndex = Array.from(existingSlides).findIndex(slide => slide.classList.contains('active'));
            if (state.currentHeroIndex === -1) state.currentHeroIndex = 0;
            
            // Asegurar que el slide activo esté correctamente marcado
            existingSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === state.currentHeroIndex);
            });
            
            // Inicializar reproductor de trailers si existe
            if (window.HeroTrailerPlayer && typeof window.HeroTrailerPlayer.init === 'function') {
                setTimeout(() => {
                    window.HeroTrailerPlayer.init();
                }, 100);
            }
            
            startAutoPlay();
            return;
        }
        
        // Si no hay slides, intentar cargar desde la API
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
        
        // Actualizar slides existentes en lugar de crear nuevos
        const existingSlides = document.querySelectorAll('.hero-slide');
        if (existingSlides.length > 0) {
            // Actualizar la clase active en los slides
            existingSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === state.currentHeroIndex);
            });
            
            // Actualizar título y descripción si existen
            const titleEl = document.querySelector('.hero-title');
            const descEl = document.querySelector('.hero-description');
            
            if (titleEl && item.title) {
                titleEl.textContent = item.title;
            }
            if (descEl && item.description) {
                descEl.textContent = item.description;
            }
            
            // Actualizar botones de acción si existen
            const actionsEl = document.querySelector('.hero-actions');
            if (actionsEl && item.id) {
                const playBtn = actionsEl.querySelector('[data-action="play"]');
                const infoBtn = actionsEl.querySelector('[data-action="info"]');
                if (playBtn) playBtn.dataset.id = item.id;
                if (infoBtn) infoBtn.dataset.id = item.id;
            }
            
            return; // Salir temprano si hay slides existentes
        }
        
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
        // Limpiar intervalo anterior si existe
        if (state.autoPlayInterval) {
            clearInterval(state.autoPlayInterval);
            state.autoPlayInterval = null;
        }
        
        // Verificar slides existentes en el DOM primero
        const existingSlides = document.querySelectorAll('.hero-slide');
        const slideCount = existingSlides.length > 0 ? existingSlides.length : state.heroItems.length;
        
        // Solo iniciar si hay más de un slide
        if (slideCount <= 1) {
            return;
        }
        
        // Usar slides del DOM si existen, sino usar heroItems
        if (existingSlides.length > 0) {
            state.autoPlayInterval = setInterval(() => {
                const slides = document.querySelectorAll('.hero-slide');
                if (slides.length === 0) {
                    clearInterval(state.autoPlayInterval);
                    state.autoPlayInterval = null;
                    return;
                }
                
                const currentActive = document.querySelector('.hero-slide.active');
                if (currentActive) {
                    const currentIndex = Array.from(slides).indexOf(currentActive);
                    const nextIndex = (currentIndex + 1) % slides.length;
                    
                    // Remover active de todos
                    slides.forEach(s => s.classList.remove('active'));
                    // Agregar active al siguiente
                    slides[nextIndex].classList.add('active');
                    
                    // Actualizar índice
                    state.currentHeroIndex = nextIndex;
                } else {
                    // Si no hay activo, activar el primero
                    slides[0].classList.add('active');
                    state.currentHeroIndex = 0;
                }
            }, config.autoPlayDelay);
        } else if (state.heroItems.length > 1) {
            state.autoPlayInterval = setInterval(() => {
                if (state.heroItems.length === 0) {
                    clearInterval(state.autoPlayInterval);
                    state.autoPlayInterval = null;
                    return;
                }
                state.currentHeroIndex = (state.currentHeroIndex + 1) % state.heroItems.length;
                renderHero();
            }, config.autoPlayDelay);
        }
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

    // Flag para prevenir múltiples event listeners
    let eventListenersInitialized = false;
    
    // Initialize event listeners
    function initEventListeners() {
        // Prevenir agregar listeners múltiples veces
        if (eventListenersInitialized) {
            return;
        }
        eventListenersInitialized = true;
        
        // Hero play button - usar delegación de eventos en el documento (solo una vez)
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
        
        // Pause auto-play on hover (solo si hero existe)
        if (elements.hero) {
            let hoverTimeout = null;
            elements.hero.addEventListener('mouseenter', () => {
                if (state.autoPlayInterval) {
                    clearInterval(state.autoPlayInterval);
                    state.autoPlayInterval = null;
                }
            });
            
            elements.hero.addEventListener('mouseleave', () => {
                // Delay para evitar reiniciar inmediatamente
                if (hoverTimeout) clearTimeout(hoverTimeout);
                hoverTimeout = setTimeout(() => {
                    startAutoPlay();
                }, 1000);
            });
        }
        
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
