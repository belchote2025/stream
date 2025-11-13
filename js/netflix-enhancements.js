/**
 * Mejoras estilo Netflix para la plataforma de streaming
 */

(function() {
    'use strict';

    // ============================================
    // NAVBAR SCROLL EFFECT
    // ============================================
    function initNavbarScroll() {
        const navbar = document.getElementById('mainNavbar');
        if (!navbar) return;

        let lastScroll = 0;
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
    }

    // ============================================
    // SEARCH FUNCTIONALITY MEJORADA
    // ============================================
    function initSearch() {
        const searchContainer = document.getElementById('searchContainer');
        const searchInput = document.getElementById('searchInput');
        const searchToggle = document.getElementById('searchToggle');
        const searchClear = document.getElementById('searchClear');
        const autocompleteResults = document.getElementById('autocompleteResults');
        
        if (!searchContainer || !searchInput) return;

        // Toggle search on icon click
        if (searchToggle) {
            searchToggle.addEventListener('click', () => {
                searchContainer.classList.toggle('active');
                if (searchContainer.classList.contains('active')) {
                    searchInput.focus();
                } else {
                    closeSearch();
                }
            });
        }

        // Clear search
        if (searchClear) {
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.focus();
                closeAutocomplete();
            });
        }

        // Show/hide clear button
        if (searchInput && searchClear) {
            searchInput.addEventListener('input', () => {
                if (searchInput.value.length > 0) {
                    searchClear.style.display = 'flex';
                } else {
                    searchClear.style.display = 'none';
                }
            });
        }

        // Close search on outside click
        document.addEventListener('click', (e) => {
            if (!searchContainer.contains(e.target) && searchContainer.classList.contains('active')) {
                closeSearch();
            }
        });

        // Handle search input with autocomplete
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    performSearch(query);
                }, 300);
            } else {
                closeAutocomplete();
            }
        });

        // Handle Enter key
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `/streaming-platform/search.php?q=${encodeURIComponent(query)}`;
                }
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });

        // Handle arrow keys in autocomplete
        let selectedIndex = -1;
        searchInput.addEventListener('keydown', (e) => {
            const results = autocompleteResults?.querySelectorAll('.result-item');
            if (!results || results.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, results.length - 1);
                updateSelection(results, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelection(results, selectedIndex);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                results[selectedIndex].click();
            }
        });
    }

    function updateSelection(results, index) {
        results.forEach((item, i) => {
            item.classList.toggle('selected', i === index);
        });
    }

    async function performSearch(query) {
        const autocompleteResults = document.getElementById('autocompleteResults');
        if (!autocompleteResults) return;

        try {
            const response = await fetch(`/streaming-platform/api/search.php?q=${encodeURIComponent(query)}&limit=5`);
            const data = await response.json();
            
            if (data.success && data.data && data.data.length > 0) {
                displayAutocompleteResults(data.data);
            } else {
                displayNoResults();
            }
        } catch (error) {
            console.error('Error en búsqueda:', error);
            displayNoResults();
        }
    }

    function displayAutocompleteResults(results) {
        const autocompleteResults = document.getElementById('autocompleteResults');
        if (!autocompleteResults) return;

        autocompleteResults.innerHTML = results.map(item => {
            const type = item.type === 'movie' ? 'Película' : 'Serie';
            const year = item.release_year || '';
            const poster = item.poster_url || '/streaming-platform/assets/img/default-poster.svg';
            
            return `
                <div class="result-item" onclick="window.location.href='/streaming-platform/content-detail.php?id=${item.id}'">
                    <img src="${poster}" alt="${item.title}" onerror="this.src='/streaming-platform/assets/img/default-poster.svg'">
                    <div class="result-info">
                        <div class="result-title">${escapeHtml(item.title)}</div>
                        <div class="result-meta">
                            <span class="result-type">${type}</span>
                            ${year ? `<span>•</span><span>${year}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        autocompleteResults.classList.add('active');
    }

    function displayNoResults() {
        const autocompleteResults = document.getElementById('autocompleteResults');
        if (!autocompleteResults) return;

        autocompleteResults.innerHTML = `
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>No se encontraron resultados</p>
            </div>
        `;
        autocompleteResults.classList.add('active');
    }

    function closeAutocomplete() {
        const autocompleteResults = document.getElementById('autocompleteResults');
        if (autocompleteResults) {
            autocompleteResults.classList.remove('active');
            autocompleteResults.innerHTML = '';
        }
    }

    function closeSearch() {
        const searchContainer = document.getElementById('searchContainer');
        const searchInput = document.getElementById('searchInput');
        const searchClear = document.getElementById('searchClear');
        
        if (searchContainer) {
            searchContainer.classList.remove('active');
        }
        if (searchInput) {
            searchInput.value = '';
        }
        if (searchClear) {
            searchClear.style.display = 'none';
        }
        closeAutocomplete();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // ROW NAVIGATION
    // ============================================
    function initRowNavigation() {
        const rowContainers = document.querySelectorAll('.row-container');
        
        rowContainers.forEach(container => {
            const rowContent = container.querySelector('.row-content');
            const prevBtn = container.querySelector('.row-nav.prev');
            const nextBtn = container.querySelector('.row-nav.next');
            
            if (!rowContent || !prevBtn || !nextBtn) return;

            // Previous button
            prevBtn.addEventListener('click', () => {
                const scrollAmount = rowContent.clientWidth * 0.8;
                rowContent.scrollBy({
                    left: -scrollAmount,
                    behavior: 'smooth'
                });
            });

            // Next button
            nextBtn.addEventListener('click', () => {
                const scrollAmount = rowContent.clientWidth * 0.8;
                rowContent.scrollBy({
                    left: scrollAmount,
                    behavior: 'smooth'
                });
            });

            // Keyboard navigation
            rowContent.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    prevBtn.click();
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    nextBtn.click();
                }
            });

            // Show/hide nav buttons based on scroll position
            function updateNavButtons() {
                const isAtStart = rowContent.scrollLeft <= 10;
                const isAtEnd = rowContent.scrollLeft >= rowContent.scrollWidth - rowContent.clientWidth - 10;
                
                prevBtn.style.opacity = isAtStart ? '0' : '1';
                prevBtn.style.pointerEvents = isAtStart ? 'none' : 'auto';
                
                nextBtn.style.opacity = isAtEnd ? '0' : '1';
                nextBtn.style.pointerEvents = isAtEnd ? 'none' : 'auto';
            }

            rowContent.addEventListener('scroll', updateNavButtons);
            updateNavButtons();
        });
    }

    // ============================================
    // CONTENT CARD INTERACTIONS
    // ============================================
    function initContentCards() {
        const contentCards = document.querySelectorAll('.content-card');
        
        contentCards.forEach(card => {
            // Play button
            const playBtn = card.querySelector('[data-action="play"]');
            if (playBtn) {
                playBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const contentId = playBtn.dataset.id;
                    const contentType = playBtn.dataset.type || 'movie';
                    handlePlayContent(contentId, contentType);
                });
            }

            // Add to list button
            const addBtn = card.querySelector('[data-action="add"]');
            if (addBtn) {
                addBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const contentId = addBtn.dataset.id;
                    handleAddToList(contentId);
                });
            }

            // Info button
            const infoBtn = card.querySelector('[data-action="info"]');
            if (infoBtn) {
                infoBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const contentId = infoBtn.dataset.id;
                    const contentType = card.dataset.type || 'movie';
                    handleShowInfo(contentId, contentType);
                });
            }

            // Card click
            card.addEventListener('click', () => {
                const contentId = card.dataset.id;
                const contentType = card.dataset.type || 'movie';
                handleShowInfo(contentId, contentType);
            });
        });
    }

    function handlePlayContent(id, type) {
        // Implementar lógica de reproducción
        console.log('Reproducir:', id, type);
        // Aquí puedes llamar a la función playContent del main.js
        if (typeof window.playContent === 'function') {
            window.playContent(id, type);
        } else {
            // Fallback: redirigir a página de reproducción
            window.location.href = `/streaming-platform/watch.php?id=${id}&type=${type}`;
        }
    }

    function handleAddToList(id) {
        fetch('/streaming-platform/api/watchlist/add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ content_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Añadido a Mi lista', 'success');
                } else {
                    showNotification('Añadido a Mi lista', 'success');
                }
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast(data.message || 'Error al añadir', 'error');
                } else {
                    showNotification(data.message || 'Error al añadir', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (typeof window.showToast === 'function') {
                window.showToast('Error al añadir a la lista', 'error');
            } else {
                showNotification('Error al añadir a la lista', 'error');
            }
        });
    }

    function handleShowInfo(id, type) {
        // Redirigir a la página de detalles
        window.location.href = `/streaming-platform/content-detail.php?id=${id}`;
    }

    // ============================================
    // NOTIFICATIONS
    // ============================================
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // ============================================
    // HERO CAROUSEL
    // ============================================
    function initHeroCarousel() {
        const heroSlides = document.querySelectorAll('.hero-slide');
        if (heroSlides.length <= 1) return;

        let currentSlide = 0;
        const totalSlides = heroSlides.length;

        function showSlide(index) {
            heroSlides.forEach((slide, i) => {
                slide.classList.toggle('active', i === index);
            });
        }

        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        // Auto-advance carousel
        setInterval(nextSlide, 8000);

        // Show first slide
        showSlide(0);
    }

    // ============================================
    // MOBILE MENU
    // ============================================
    function setupMobileMenu() {
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navbarNav = document.getElementById('navbarNav');
        
        if (mobileMenuToggle && navbarNav) {
            mobileMenuToggle.addEventListener('click', function() {
                navbarNav.classList.toggle('active');
                this.classList.toggle('active');
                const icon = this.querySelector('i');
                if (navbarNav.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    document.body.style.overflow = 'hidden';
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    document.body.style.overflow = '';
                }
            });
            
            // Cerrar menú al hacer clic en un enlace
            navbarNav.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    navbarNav.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                    const icon = mobileMenuToggle.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                    document.body.style.overflow = '';
                });
            });
            
            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!navbarNav.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                    navbarNav.classList.remove('active');
                    mobileMenuToggle.classList.remove('active');
                    const icon = mobileMenuToggle.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                    document.body.style.overflow = '';
                }
            });
        }
    }

    // ============================================
    // USER MENU DROPDOWN
    // ============================================
    function setupUserMenu() {
        const userMenu = document.getElementById('userMenu');
        if (!userMenu) return;
        
        const userMenuToggle = userMenu.querySelector('.user-menu-toggle');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuToggle && userDropdown) {
            userMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            });
            
            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', (e) => {
                if (!userMenu.contains(e.target)) {
                    userMenu.classList.remove('active');
                }
            });
            
            // Cerrar al hacer clic en un item
            userDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', () => {
                    userMenu.classList.remove('active');
                });
            });
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    function init() {
        initNavbarScroll();
        initSearch();
        initRowNavigation();
        initContentCards();
        initHeroCarousel();
        setupMobileMenu();
        setupUserMenu();
    }

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

})();

