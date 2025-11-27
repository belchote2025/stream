/**
 * Optimizador del Hero Backdrop
 * Mejora el rendimiento y la experiencia del carrusel hero
 */

(function() {
    'use strict';

    // Configuración
    const config = {
        preloadNext: true,
        fadeDuration: 800,
        lazyLoadOffset: 100,
        imageQuality: 'high' // 'high', 'medium', 'low'
    };

    // Estado
    let currentIndex = 0;
    let heroSlides = [];
    let preloadedImages = new Map();
    let isTransitioning = false;

    // Inicialización
    function init() {
        heroSlides = Array.from(document.querySelectorAll('.hero-slide'));
        if (heroSlides.length === 0) return;

        // Encontrar el slide activo
        const activeSlide = document.querySelector('.hero-slide.active');
        if (activeSlide) {
            currentIndex = parseInt(activeSlide.dataset.index) || 0;
        }

        // Preload de imágenes
        preloadImages();

        // Optimizar imágenes existentes
        optimizeExistingImages();

        // Configurar observador para lazy loading
        setupIntersectionObserver();

        // Preload de la siguiente imagen
        if (config.preloadNext) {
            preloadNextImage();
        }
    }

    // Preload de todas las imágenes
    function preloadImages() {
        heroSlides.forEach((slide, index) => {
            const backdrop = slide.querySelector('.hero-backdrop');
            if (!backdrop) return;

            const bgImage = backdrop.style.backgroundImage;
            if (!bgImage || bgImage === 'none') return;

            // Extraer URL de la imagen
            const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
            if (!urlMatch) return;

            const imageUrl = urlMatch[1];
            
            // Preload solo si no está ya cargada
            if (!preloadedImages.has(imageUrl)) {
                preloadImage(imageUrl, index === currentIndex);
            }
        });
    }

    // Preload de una imagen individual
    function preloadImage(url, isActive = false) {
        if (preloadedImages.has(url)) return;

        const img = new Image();
        
        img.onload = () => {
            preloadedImages.set(url, true);
            if (isActive) {
                applyImageToActiveSlide(url);
            }
        };

        img.onerror = () => {
            console.warn('Error al cargar imagen:', url);
            preloadedImages.set(url, false);
        };

        // Cargar imagen
        img.src = url;
    }

    // Aplicar imagen al slide activo
    function applyImageToActiveSlide(url) {
        const activeSlide = document.querySelector('.hero-slide.active');
        if (!activeSlide) return;

        const backdrop = activeSlide.querySelector('.hero-backdrop');
        if (!backdrop) return;

        // Usar CSS variable para mejor rendimiento
        backdrop.style.setProperty('--hero-bg-image', `url('${url}')`);
        backdrop.style.backgroundImage = `url('${url}')`;
    }

    // Optimizar imágenes existentes
    function optimizeExistingImages() {
        heroSlides.forEach(slide => {
            const backdrop = slide.querySelector('.hero-backdrop');
            if (!backdrop) return;

            // Agregar atributos de optimización
            backdrop.style.willChange = 'opacity, transform';
            backdrop.style.transform = 'translateZ(0)';
            backdrop.style.backfaceVisibility = 'hidden';
        });
    }

    // Configurar Intersection Observer para lazy loading
    function setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const slide = entry.target;
                    const backdrop = slide.querySelector('.hero-backdrop');
                    
                    if (backdrop) {
                        const bgImage = backdrop.style.backgroundImage;
                        if (bgImage && bgImage !== 'none') {
                            const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
                            if (urlMatch) {
                                preloadImage(urlMatch[1], slide.classList.contains('active'));
                            }
                        }
                    }
                }
            });
        }, {
            rootMargin: `${config.lazyLoadOffset}px`
        });

        heroSlides.forEach(slide => {
            observer.observe(slide);
        });
    }

    // Preload de la siguiente imagen
    function preloadNextImage() {
        const nextIndex = (currentIndex + 1) % heroSlides.length;
        const nextSlide = heroSlides[nextIndex];
        
        if (!nextSlide) return;

        const backdrop = nextSlide.querySelector('.hero-backdrop');
        if (!backdrop) return;

        const bgImage = backdrop.style.backgroundImage;
        if (!bgImage || bgImage === 'none') return;

        const urlMatch = bgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
        if (urlMatch) {
            preloadImage(urlMatch[1], false);
        }
    }

    // Función para cambiar de slide (llamada desde otros scripts)
    function changeSlide(newIndex) {
        if (isTransitioning) return;
        if (newIndex === currentIndex) return;
        if (newIndex < 0 || newIndex >= heroSlides.length) return;

        isTransitioning = true;
        const oldIndex = currentIndex;
        currentIndex = newIndex;

        // Remover clase active del slide anterior
        heroSlides[oldIndex].classList.remove('active');
        
        // Agregar clase next al nuevo slide antes de activarlo
        heroSlides[newIndex].classList.add('next');
        
        // Pequeño delay para la transición
        setTimeout(() => {
            heroSlides[newIndex].classList.remove('next');
            heroSlides[newIndex].classList.add('active');
            
            // Preload de la siguiente imagen
            if (config.preloadNext) {
                preloadNextImage();
            }
            
            isTransitioning = false;
        }, 50);
    }

    // Optimizar cambio de imagen con fade
    function optimizeImageTransition(fromSlide, toSlide) {
        const fromBackdrop = fromSlide.querySelector('.hero-backdrop');
        const toBackdrop = toSlide.querySelector('.hero-backdrop');
        
        if (!fromBackdrop || !toBackdrop) return;

        // Asegurar que la imagen de destino esté cargada
        const toBgImage = toBackdrop.style.backgroundImage;
        if (toBgImage && toBgImage !== 'none') {
            const urlMatch = toBgImage.match(/url\(['"]?([^'"]+)['"]?\)/);
            if (urlMatch && !preloadedImages.has(urlMatch[1])) {
                preloadImage(urlMatch[1], true);
            }
        }
    }

    // Limpiar imágenes no usadas de la memoria
    function cleanupUnusedImages() {
        // Mantener solo las imágenes de los slides actuales y adyacentes
        const keepIndices = [
            currentIndex - 1,
            currentIndex,
            currentIndex + 1
        ].filter(idx => idx >= 0 && idx < heroSlides.length);

        // Nota: En realidad no podemos liberar memoria de imágenes pre-cargadas
        // pero podemos optimizar el DOM
        heroSlides.forEach((slide, index) => {
            if (!keepIndices.includes(index) && !slide.classList.contains('active')) {
                const backdrop = slide.querySelector('.hero-backdrop');
                if (backdrop) {
                    // Limpiar background-image para liberar memoria
                    backdrop.style.backgroundImage = 'none';
                }
            }
        });
    }

    // API pública
    window.HeroOptimizer = {
        init: init,
        changeSlide: changeSlide,
        preloadNext: preloadNextImage,
        cleanup: cleanupUnusedImages
    };

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // DESACTIVADO: MutationObserver puede causar bloqueos de página
    // Re-inicializar si se agregan nuevos slides dinámicamente
    // const observer = new MutationObserver(() => {
    //     const newSlides = Array.from(document.querySelectorAll('.hero-slide'));
    //     if (newSlides.length !== heroSlides.length) {
    //         heroSlides = newSlides;
    //         preloadImages();
    //     }
    // });

    // observer.observe(document.body, {
    //     childList: true,
    //     subtree: true
    // });

})();

