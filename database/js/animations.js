/**
 * Animaciones y efectos mejorados estilo Netflix
 */

(function() {
    const BASE_URL = (typeof window !== 'undefined' && window.__APP_BASE_URL) ? window.__APP_BASE_URL : '';
    'use strict';

    // ============================================
    // FADE IN ON SCROLL
    // ============================================
    function initFadeInOnScroll() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target); // Dejar de observar una vez visible
                }
            });
        }, observerOptions);

        // Observar elementos con clase fade-in
        document.querySelectorAll('.fade-in').forEach(el => {
            // Asegurar que el elemento tenga los estilos iniciales
            if (!el.classList.contains('visible')) {
                observer.observe(el);
            }
        });
    }

    // ============================================
    // SMOOTH SCROLL
    // ============================================
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // ============================================
    // LOADING SKELETON
    // ============================================
    function createSkeletonLoader(count = 6) {
        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton-loader';
        skeleton.innerHTML = Array(count).fill(0).map(() => `
            <div class="skeleton-card">
                <div class="skeleton-poster"></div>
                <div class="skeleton-title"></div>
                <div class="skeleton-meta"></div>
            </div>
        `).join('');
        return skeleton;
    }

    // ============================================
    // TOAST NOTIFICATIONS
    // ============================================
    function showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${getToastIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Animar entrada
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Remover después de la duración
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    function getToastIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // ============================================
    // LAZY LOAD IMAGES
    // ============================================
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            img.classList.add('loaded');
                            observer.unobserve(img);
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // ============================================
    // PARALLAX EFFECT
    // ============================================
    function initParallax() {
        const parallaxElements = document.querySelectorAll('.parallax');
        
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(element => {
                const speed = element.dataset.speed || 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        });
    }

    // ============================================
    // CARD HOVER EFFECTS
    // ============================================
    function initCardHoverEffects() {
        const cards = document.querySelectorAll('.content-card');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '100';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });
    }

    // ============================================
    // SEARCH AUTOCOMPLETE
    // ============================================
    function initSearchAutocomplete() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        let timeout;
        const autocompleteContainer = document.createElement('div');
        autocompleteContainer.className = 'autocomplete-results';
        searchInput.parentElement.appendChild(autocompleteContainer);

        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();

            if (query.length < 2) {
                autocompleteContainer.style.display = 'none';
                return;
            }

            timeout = setTimeout(async () => {
                try {
                    const response = await fetch(`${BASE_URL}/api/content/popular.php?limit=5`);
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const results = data.data.filter(item => 
                            item.title.toLowerCase().includes(query.toLowerCase())
                        ).slice(0, 5);

                        if (results.length > 0) {
                            autocompleteContainer.innerHTML = results.map(item => `
                                <div class="autocomplete-item" onclick="window.location.href='${BASE_URL}/content-detail.php?id=${item.id}'">
                                    <img src="${item.poster_url || `${BASE_URL}/assets/img/default-poster.svg`}" alt="${item.title}">
                                    <div>
                                        <strong>${item.title}</strong>
                                        <span>${item.type === 'movie' ? 'Película' : 'Serie'} • ${item.release_year}</span>
                                    </div>
                                </div>
                            `).join('');
                            autocompleteContainer.style.display = 'block';
                        } else {
                            autocompleteContainer.style.display = 'none';
                        }
                    }
                } catch (error) {
                    console.error('Error en autocompletado:', error);
                }
            }, 300);
        });

        // Ocultar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !autocompleteContainer.contains(e.target)) {
                autocompleteContainer.style.display = 'none';
            }
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        initFadeInOnScroll();
        initSmoothScroll();
        initLazyLoad();
        initParallax();
        initCardHoverEffects();
        initSearchAutocomplete();
        
        // Asegurar que el contenido principal sea visible inmediatamente si está en viewport
        setTimeout(() => {
            const contentRows = document.querySelector('.content-rows.fade-in');
            if (contentRows) {
                const rect = contentRows.getBoundingClientRect();
                // Si está visible o cerca del viewport, hacerlo visible inmediatamente
                if (rect.top < window.innerHeight + 200) {
                    contentRows.classList.add('visible');
                }
            }
        }, 100);
    }

    // Flag para prevenir múltiples inicializaciones
    let initialized = false;
    
    function safeInit() {
        if (initialized) return;
        initialized = true;
        init();
    }
    
    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeInit);
    } else {
        safeInit();
    }

    // Exponer funciones globales
    window.showToast = showToast;
    window.createSkeletonLoader = createSkeletonLoader;

    // Añadir estilos CSS
    const style = document.createElement('style');
    style.textContent = `
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(20, 20, 20, 0.95);
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            min-width: 300px;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .toast-success {
            border-left: 4px solid #28a745;
        }
        
        .toast-error {
            border-left: 4px solid #dc3545;
        }
        
        .toast-warning {
            border-left: 4px solid #ffc107;
        }
        
        .toast-info {
            border-left: 4px solid #17a2b8;
        }
        
        .skeleton-loader {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .skeleton-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .skeleton-poster {
            width: 100%;
            aspect-ratio: 2/3;
            background: linear-gradient(90deg, #2a2a2a 25%, #333 50%, #2a2a2a 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        .skeleton-title {
            height: 20px;
            background: #2a2a2a;
            margin: 1rem 1rem 0.5rem;
            border-radius: 4px;
        }
        
        .skeleton-meta {
            height: 16px;
            background: #2a2a2a;
            margin: 0 1rem 1rem;
            border-radius: 4px;
            width: 60%;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(20, 20, 20, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-top: 0.5rem;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .autocomplete-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .autocomplete-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .autocomplete-item img {
            width: 50px;
            height: 75px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .autocomplete-item div {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .autocomplete-item strong {
            color: #fff;
        }
        
        .autocomplete-item span {
            color: #999;
            font-size: 0.85rem;
        }
    `;
    document.head.appendChild(style);

})();

