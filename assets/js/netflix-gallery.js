/**
 * Netflix-style Gallery Functionality
 */

document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
    const FALLBACK_BACKDROP = `${BASE_URL}/assets/img/default-backdrop.svg`;
    const FALLBACK_POSTER = `${BASE_URL}/assets/img/default-poster.svg`;
    // Configuration
    const config = {
        api: {
            featured: `${BASE_URL}/api/content/featured.php`,
            recentlyAdded: `${BASE_URL}/api/content/recent.php`,
            mostViewed: `${BASE_URL}/api/content/popular.php`,
            contentDetails: `${BASE_URL}/api/content/index.php?id=`
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
            modalPlayer: '#contentPlayer',
            videoModal: '#videoPlayerModal',
            videoTitle: '#videoPlayerTitle',
            videoMeta: '#videoPlayerMeta',
            videoDescription: '#videoPlayerDescription',
            videoLoading: '#videoPlayerLoading',
            videoAction: '#videoOpenPageBtn'
        },
        defaultBackdrop: FALLBACK_BACKDROP,
        defaultPoster: FALLBACK_POSTER,
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
        currentVideo: null,
        currentContentId: null,
        currentContentType: null,
        currentContentData: null,
        lastProgressSave: null
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

        // Preparar modal de video si existe
        if (elements.videoModal) {
            // Limpiar video al cerrar el modal
            elements.videoModal.addEventListener('hidden.bs.modal', () => {
                resetVideoPlayer();
            });

            // Cerrar con tecla Escape
            elements.videoModal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const modalInstance = bootstrap.Modal.getInstance(elements.videoModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            });
        }

        // Botón "Ver ficha completa"
        if (elements.videoAction) {
            elements.videoAction.addEventListener('click', () => {
                const contentId = elements.videoAction.dataset.id;
                if (contentId) {
                    // Cerrar modal primero
                    const modalInstance = bootstrap.Modal.getInstance(elements.videoModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    // Redirigir después de un pequeño delay para que el modal se cierre suavemente
                    setTimeout(() => {
                        window.location.href = `${BASE_URL}/content.php?id=${contentId}`;
                    }, 300);
                }
            });
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

            // Verificar que la respuesta sea JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Respuesta no es JSON:', text.substring(0, 200));
                throw new Error('El servidor devolvió HTML en lugar de JSON');
            }

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

            // NO sobrescribir título y descripción si ya tienen contenido del PHP
            // Solo actualizar si están vacíos
            const titleEl = document.querySelector('.hero-slide.active .hero-title');
            const descEl = document.querySelector('.hero-slide.active .hero-description');

            if (titleEl && (!titleEl.textContent || titleEl.textContent.trim() === '') && item.title) {
                titleEl.textContent = item.title;
            }
            if (descEl && (!descEl.textContent || descEl.textContent.trim() === '') && item.description) {
                descEl.textContent = item.description;
            }

            // NO sobrescribir los botones de acción - mantener los que vienen del PHP
            // Solo actualizar data-id si no existe
            const actionsEl = document.querySelector('.hero-slide.active .hero-actions');
            if (actionsEl && item.id) {
                const playBtn = actionsEl.querySelector('[data-action="play"], .btn-primary');
                const infoBtn = actionsEl.querySelector('[data-action="info"], .btn-outline');
                if (playBtn && !playBtn.dataset.id) {
                    playBtn.dataset.id = item.id;
                }
                if (infoBtn && !infoBtn.dataset.id) {
                    infoBtn.dataset.id = item.id;
                }
            }

            return; // Salir temprano si hay slides existentes
        }

        let backdropUrl = item.backdrop_url || config.defaultBackdrop;

        // Si es una URL de TMDB, usar proxy
        if (backdropUrl.match(/^https?:\/\/(image\.tmdb\.org|via\.placeholder\.com|images\.unsplash\.com)/)) {
            backdropUrl = `${BASE_URL}/api/image-proxy.php?url=` + encodeURIComponent(backdropUrl);
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

        // NO sobrescribir si ya hay contenido del PHP
        if (elements.heroTitle && (!elements.heroTitle.textContent || elements.heroTitle.textContent.trim() === '')) {
            elements.heroTitle.textContent = item.title || '';
        }

        if (elements.heroDescription && (!elements.heroDescription.textContent || elements.heroDescription.textContent.trim() === '')) {
            elements.heroDescription.textContent = item.description || '';
        }

        // NO reemplazar los botones de acción si ya existen - mantener los del PHP
        // Solo actualizar si no hay botones
        if (elements.heroActions && elements.heroActions.children.length === 0) {
            const safeId = item.id || 0;
            const baseUrl = BASE_URL || window.location.origin + (window.location.pathname.includes('streaming-platform') ? '/streaming-platform' : '');
            elements.heroActions.innerHTML = `
                <a href="${baseUrl}/watch.php?id=${safeId}" class="btn btn-primary" data-action="play" data-id="${safeId}">
                    <i class="fas fa-play"></i> Reproducir
                </a>
                <button class="btn btn-outline" data-action="info" data-id="${safeId}">
                    <i class="fas fa-info-circle"></i> Más información
                </button>
            `;
        } else if (elements.heroActions) {
            // Solo actualizar data-id si no existe
            const playBtn = elements.heroActions.querySelector('[data-action="play"], .btn-primary');
            const infoBtn = elements.heroActions.querySelector('[data-action="info"], .btn-outline');
            if (playBtn && !playBtn.dataset.id && item.id) {
                playBtn.dataset.id = item.id;
            }
            if (infoBtn && !infoBtn.dataset.id && item.id) {
                infoBtn.dataset.id = item.id;
            }
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
            posterUrl = `${BASE_URL}/api/image-proxy.php?url=` + encodeURIComponent(posterUrl);
        }

        const year = item.release_year || '';
        const type = item.type === 'movie' ? 'Película' : 'Serie';
        const title = item.title || 'Sin título';

        // Escapar comillas y caracteres especiales
        const safePosterUrl = posterUrl.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeDefaultPoster = config.defaultPoster.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeTitle = title.replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const safeAlt = title.replace(/"/g, '&quot;');

        // Verificar si hay rating válido
        const rating = item.rating && parseFloat(item.rating) > 0 ? parseFloat(item.rating).toFixed(1) : null;
        const ratingHtml = rating ? `<span>•</span><span>⭐ ${rating}</span>` : '';

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
                        ${year ? `<span>${year}</span>` : ''}
                        ${year && type ? '<span>•</span>' : ''}
                        <span>${type}</span>
                        ${ratingHtml}
                    </div>
                    <div class="content-actions">
                        <button class="action-btn" data-action="play" data-id="${item.id}" data-type="${item.type || 'movie'}" title="Reproducir">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="action-btn" data-action="add" data-id="${item.id}" data-type="${item.type || 'movie'}" title="Añadir a Mi lista">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="action-btn" data-action="info" data-id="${item.id}" data-type="${item.type || 'movie'}" title="Más información">
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
        const prevBtn = container.querySelector('.row-nav.prev');
        const nextBtn = container.querySelector('.row-nav.next');

        if (!prevBtn || !nextBtn) return;

        const scrollAmount = row.offsetWidth * 0.8; // Scroll 80% of container width

        // Función para actualizar visibilidad de botones
        const updateNavButtons = () => {
            const { scrollLeft, scrollWidth, clientWidth } = row;
            const hasOverflow = scrollWidth > clientWidth;

            if (!hasOverflow) {
                // Si no hay desbordamiento, ocultar ambos botones
                prevBtn.classList.remove('visible');
                nextBtn.classList.remove('visible');
                return;
            }

            // Actualizar visibilidad basada en posición de scroll
            const canScrollLeft = scrollLeft > 0;
            const canScrollRight = scrollLeft < (scrollWidth - clientWidth - 10);

            if (canScrollLeft) {
                prevBtn.classList.add('visible');
            } else {
                prevBtn.classList.remove('visible');
            }

            if (canScrollRight) {
                nextBtn.classList.add('visible');
            } else {
                nextBtn.classList.remove('visible');
            }
        };

        // Event listeners para navegación
        nextBtn.addEventListener('click', () => {
            row.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        prevBtn.addEventListener('click', () => {
            row.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });

        // Actualizar botones en eventos de scroll y resize
        row.addEventListener('scroll', updateNavButtons);
        window.addEventListener('resize', updateNavButtons);

        // Estado inicial - verificar después de que el contenido se haya cargado
        setTimeout(updateNavButtons, 100);
        setTimeout(updateNavButtons, 500); // Verificar nuevamente después de carga completa
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
        // Usar capture phase para asegurar que se ejecute antes que otros listeners
        document.addEventListener('click', (e) => {
            // Buscar el botón más cercano con data-action
            const button = e.target.closest('[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const contentId = button.getAttribute('data-id');
            
            // Si no hay contentId, no hacer nada
            if (!contentId) {
                console.warn('Botón con data-action pero sin data-id:', button);
                return;
            }

            // Obtener el tipo de contenido desde el botón o desde la tarjeta padre
            let contentType = button.getAttribute('data-type');
            if (!contentType) {
                const contentCard = button.closest('.content-card');
                contentType = contentCard?.getAttribute('data-type') || 'movie';
            }

            // Prevenir el comportamiento por defecto y propagación si es necesario
            if (action === 'play' || action === 'info' || action === 'add') {
                e.stopPropagation();
            }

            switch (action) {
                case 'play':
                    console.log('Reproducir contenido:', { id: contentId, type: contentType });
                    playContent(contentId, contentType);
                    break;
                case 'info':
                    showContentInfo(contentId);
                    break;
                case 'add':
                    addToMyList(contentId);
                    break;
            }
        }, true); // Usar capture phase

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

        // Event listeners para el modal de video
        if (elements.videoModal) {
            // Cerrar con clic fuera del modal
            elements.videoModal.addEventListener('click', (e) => {
                if (e.target === elements.videoModal) {
                    const modalInstance = bootstrap.Modal.getInstance(elements.videoModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            });

            // Prevenir cierre accidental con teclas
            elements.videoModal.addEventListener('keydown', (e) => {
                // Permitir Escape para cerrar
                if (e.key === 'Escape') {
                    const modalInstance = bootstrap.Modal.getInstance(elements.videoModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
                // Prevenir que el espacio pause/reproduzca cuando se está escribiendo
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                // Espacio para play/pause
                if (e.key === ' ' && state.currentVideo) {
                    e.preventDefault();
                    if (state.currentVideo.paused) {
                        state.currentVideo.play();
                    } else {
                        state.currentVideo.pause();
                    }
                }
            });
        }
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
        console.log('[playContent] Iniciando reproducción:', { contentId, contentType });
        
        const hasVideoModal = elements.videoModal && elements.modalPlayer;
        console.log('[playContent] Verificando modal:', {
            hasVideoModal,
            videoModal: !!elements.videoModal,
            modalPlayer: !!elements.modalPlayer
        });
        
        // Si tenemos el modal disponible, usarlo directamente (no redirigir a window.playContent)
        if (hasVideoModal) {
            console.log('[playContent] Usando modal de netflix-gallery.js');
            // Continuar con la lógica del modal
        } else if (typeof window.playContent === 'function' && window.playContent !== playContent) {
            // Solo redirigir a window.playContent si NO tenemos modal disponible
            console.log('[playContent] No hay modal disponible, redirigiendo a window.playContent global');
            window.playContent(contentId, contentType);
            return;
        } else {
            // Si no hay modal ni función global, redirigir a watch.php
            console.warn('[playContent] No hay modal disponible, redirigiendo a watch.php');
            window.location.href = `${BASE_URL}/watch.php?id=${contentId}`;
            return;
        }

        try {
            stopCurrentVideo();
            showVideoLoading('Preparando reproducción...');

            let contentData = null;
            try {
                const apiUrl = `${config.api.contentDetails}${contentId}`;
                console.log('[playContent] Obteniendo datos del contenido desde:', apiUrl);
                const response = await fetch(apiUrl);
                const apiData = await response.json();
                console.log('[playContent] Datos recibidos:', apiData);
                
                if (apiData.success && apiData.data) {
                    contentData = apiData.data;
                } else if (apiData.data) {
                    contentData = apiData;
                }
            } catch (error) {
                console.error('[playContent] Error fetching content details:', error);
            }

            if (!contentData) {
                console.warn('[playContent] No se obtuvieron datos del contenido, redirigiendo a watch.php');
                redirectToWatch(contentId);
                return;
            }

            console.log('[playContent] Datos del contenido obtenidos:', contentData);
            updateVideoModalInfo(contentData, contentType);
            const playbackUrl = resolvePlaybackUrl(contentData);
            console.log('[playContent] URL de reproducción:', playbackUrl);
            
            if (!playbackUrl) {
                console.warn('[playContent] No hay URL de reproducción, redirigiendo a watch.php');
                showVideoLoading('Abriendo reproductor avanzado...');
                redirectToWatch(contentId);
                return;
            }

            console.log('[playContent] Abriendo modal...');
            const modalInstance = bootstrap.Modal.getOrCreateInstance(elements.videoModal);
            modalInstance.show();
            console.log('[playContent] Modal abierto correctamente');

            const videoEl = elements.modalPlayer;
            videoEl.pause();
            videoEl.removeAttribute('src');
            videoEl.load();

            const normalizedUrl = normalizeVideoUrl(playbackUrl);
            videoEl.src = normalizedUrl;
            if (contentData.poster_url) {
                videoEl.setAttribute('poster', contentData.poster_url);
            } else {
                videoEl.removeAttribute('poster');
            }

            // Guardar referencia al contenido actual para eventos
            state.currentContentId = contentId;
            state.currentContentType = contentType;
            state.currentContentData = contentData;

            // Eventos del video
            videoEl.onloadeddata = () => {
                hideVideoLoading();
                videoEl.play().catch(() => { });
            };

            videoEl.onplay = () => {
                hideVideoLoading();
            };

            videoEl.onpause = () => {
                // Guardar progreso al pausar
                savePlaybackProgress(contentId, videoEl.currentTime, videoEl.duration);
            };

            videoEl.ontimeupdate = () => {
                // Guardar progreso cada 5 segundos
                if (!state.lastProgressSave || Date.now() - state.lastProgressSave > 5000) {
                    savePlaybackProgress(contentId, videoEl.currentTime, videoEl.duration);
                    state.lastProgressSave = Date.now();
                }
            };

            videoEl.onended = () => {
                // Marcar como completado
                markContentAsCompleted(contentId);
                // Ocultar loading si está visible
                hideVideoLoading();
            };

            videoEl.onerror = () => {
                console.error('Video error, abriendo página completa');
                showVideoLoading('Redirigiendo al reproductor completo...');
                redirectToWatch(contentId);
            };

            // Cargar progreso guardado si existe
            loadPlaybackProgress(contentId).then(progress => {
                if (progress && progress > 0 && videoEl.readyState >= 2) {
                    videoEl.currentTime = progress;
                }
            });

            state.currentVideo = videoEl;
        } catch (error) {
            console.error('Error playing content:', error);
            redirectToWatch(contentId);
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

    function updateVideoModalInfo(content, contentType) {
        if (elements.videoTitle) {
            elements.videoTitle.textContent = content.title || 'Reproduciendo';
        }

        if (elements.videoMeta) {
            const parts = [];
            if (content.release_year) parts.push(content.release_year);
            if (content.duration) {
                const durationLabel = contentType === 'series'
                    ? `${content.duration} min`
                    : `${content.duration} min`;
                parts.push(durationLabel);
            }
            if (content.rating) parts.push(`⭐ ${parseFloat(content.rating).toFixed(1)}`);
            elements.videoMeta.textContent = parts.join(' • ') || 'Preparando video';
        }

        if (elements.videoDescription) {
            elements.videoDescription.textContent = content.description || '';
            // Mostrar/ocultar descripción según si tiene contenido
            if (elements.videoDescription) {
                elements.videoDescription.style.display = content.description ? 'block' : 'none';
            }
        }

        if (elements.videoAction) {
            elements.videoAction.dataset.id = content.id || '';
            // Habilitar/deshabilitar botón según si hay ID
            if (content.id) {
                elements.videoAction.disabled = false;
                elements.videoAction.style.opacity = '1';
                elements.videoAction.style.cursor = 'pointer';
            } else {
                elements.videoAction.disabled = true;
                elements.videoAction.style.opacity = '0.5';
                elements.videoAction.style.cursor = 'not-allowed';
            }
        }
    }

    function resolvePlaybackUrl(content) {
        if (content.video_url) return content.video_url;
        if (content.preview_url) return content.preview_url;
        if (content.trailer_url && isDirectVideo(content.trailer_url)) return content.trailer_url;
        return null;
    }

    function normalizeVideoUrl(url) {
        if (!url) return '';
        if (url.startsWith('http://') || url.startsWith('https://')) return url;
        if (url.startsWith('//')) return `${window.location.protocol}${url}`;
        if (url.startsWith('/')) return `${window.location.origin}${url}`;
        return `${window.location.origin}/${url.replace(/^\/+/, '')}`;
    }

    function isDirectVideo(url) {
        return /\.(mp4|webm|ogg|mov|mkv)(\?.*)?$/i.test(url || '');
    }

    function redirectToWatch(contentId) {
        window.location.href = `${BASE_URL}/watch.php?id=${contentId}`;
    }

    function showVideoLoading(message = 'Cargando video...') {
        if (elements.videoLoading) {
            elements.videoLoading.style.display = 'flex';
            const text = elements.videoLoading.querySelector('p');
            if (text) text.textContent = message;
        }
    }

    function hideVideoLoading() {
        if (elements.videoLoading) {
            elements.videoLoading.style.display = 'none';
        }
    }

    // Stop currently playing video
    function stopCurrentVideo() {
        if (state.currentVideo) {
            // Guardar progreso antes de detener
            if (state.currentContentId && state.currentVideo.currentTime > 0) {
                savePlaybackProgress(state.currentContentId, state.currentVideo.currentTime, state.currentVideo.duration);
            }
            state.currentVideo.pause();
            state.currentVideo.currentTime = 0;
            state.currentVideo.removeAttribute('src');
            state.currentVideo.load();
            state.currentVideo = null;
        }
        // Limpiar referencias
        state.currentContentId = null;
        state.currentContentType = null;
        state.currentContentData = null;
        state.lastProgressSave = null;
        hideVideoLoading();
    }

    // Reset video player completamente
    function resetVideoPlayer() {
        stopCurrentVideo();
        // Limpiar información del modal
        if (elements.videoTitle) elements.videoTitle.textContent = 'Reproduciendo...';
        if (elements.videoMeta) elements.videoMeta.textContent = '';
        if (elements.videoDescription) elements.videoDescription.textContent = '';
        if (elements.videoAction) elements.videoAction.dataset.id = '';
    }

    // Guardar progreso de reproducción
    async function savePlaybackProgress(contentId, currentTime, totalDuration) {
        if (!contentId || !currentTime || !totalDuration) return;

        try {
            const progressPercent = (currentTime / totalDuration) * 100;
            // Solo guardar si se ha visto al menos 5 segundos y menos del 90% (para no marcar como completado)
            if (currentTime < 5 || progressPercent >= 90) return;

            const response = await fetch(`${BASE_URL}/api/playback/save.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    content_id: contentId,
                    progress: currentTime,
                    duration: totalDuration
                })
            });

            if (!response.ok) {
                console.warn('No se pudo guardar el progreso');
            }
        } catch (error) {
            console.error('Error guardando progreso:', error);
        }
    }

    // Cargar progreso guardado
    async function loadPlaybackProgress(contentId) {
        if (!contentId) return null;

        try {
            const response = await fetch(`${BASE_URL}/api/playback/get-progress.php?content_id=${contentId}`, {
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success && data.progress) {
                    return parseFloat(data.progress);
                }
            }
        } catch (error) {
            console.error('Error cargando progreso:', error);
        }
        return null;
    }

    // Marcar contenido como completado
    async function markContentAsCompleted(contentId) {
        if (!contentId) return;

        try {
            // Usar el endpoint de save con progreso >= 90% para marcar como completado
            const response = await fetch(`${BASE_URL}/api/playback/save.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    content_id: contentId,
                    progress: 999999, // Valor alto para forzar completed = 1
                    duration: 1
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    console.log('Contenido marcado como completado');
                }
            }
        } catch (error) {
            console.error('Error marcando como completado:', error);
        }
    }

    // Initialize the gallery when the DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
});
