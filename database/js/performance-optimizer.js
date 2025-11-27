/**
 * Optimizador de Rendimiento
 * Gestiona will-change dinámicamente para reducir consumo de memoria
 */

(function() {
    'use strict';

    // Configuración
    const config = {
        willChangeTimeout: 3000, // Remover will-change después de 3 segundos
        maxWillChangeElements: 10 // Máximo de elementos con will-change simultáneos
    };

    let willChangeElements = new Set();
    let timeouts = new Map();

    // Agregar will-change temporalmente a un elemento
    function addWillChange(element, properties = 'transform') {
        // Si ya hay demasiados elementos con will-change, remover el más antiguo
        if (willChangeElements.size >= config.maxWillChangeElements) {
            const oldest = Array.from(willChangeElements)[0];
            removeWillChange(oldest);
        }

        // Agregar will-change
        if (element && !willChangeElements.has(element)) {
            element.style.willChange = properties;
            willChangeElements.add(element);

            // Programar remoción automática
            const timeout = setTimeout(() => {
                removeWillChange(element);
            }, config.willChangeTimeout);

            timeouts.set(element, timeout);
        }
    }

    // Remover will-change de un elemento
    function removeWillChange(element) {
        if (element && willChangeElements.has(element)) {
            element.style.willChange = 'auto';
            willChangeElements.delete(element);

            // Limpiar timeout
            const timeout = timeouts.get(element);
            if (timeout) {
                clearTimeout(timeout);
                timeouts.delete(element);
            }
        }
    }

    // Limpiar todos los will-change
    function cleanupAll() {
        willChangeElements.forEach(element => {
            removeWillChange(element);
        });
    }

    // Track de cards ya optimizadas para evitar duplicados
    const optimizedCards = new WeakSet();
    
    // Optimizar cards en hover
    function optimizeContentCards() {
        const cards = document.querySelectorAll('.content-card');
        
        cards.forEach(card => {
            // Evitar agregar listeners múltiples veces
            if (optimizedCards.has(card)) {
                return;
            }
            optimizedCards.add(card);
            
            card.addEventListener('mouseenter', function() {
                addWillChange(this, 'transform');
            });

            card.addEventListener('mouseleave', function() {
                // Remover después de la transición
                setTimeout(() => {
                    removeWillChange(this);
                }, 300);
            });
        });
    }

    // Optimizar hero slides durante transiciones (DESACTIVADO - causaba bloqueos)
    function optimizeHeroSlides() {
        // DESACTIVADO: MutationObserver en cada slide causaba bloqueos de página
        // Los slides se optimizarán automáticamente cuando sea necesario sin observadores
        return;
    }

    // Limpiar cuando la página se oculta
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cleanupAll();
        }
    });

    // Limpiar al salir
    window.addEventListener('beforeunload', cleanupAll);

    // Inicializar cuando el DOM esté listo
    function init() {
        optimizeContentCards();
        optimizeHeroSlides();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // DESACTIVADO: MutationObserver puede causar bloqueos
    // Re-inicializar si se agregan elementos dinámicamente (con throttling)
    // let mutationTimeout = null;
    // const observer = new MutationObserver(() => {
    //     // Throttle: solo ejecutar después de 500ms sin cambios
    //     if (mutationTimeout) {
    //         clearTimeout(mutationTimeout);
    //     }
    //     mutationTimeout = setTimeout(() => {
    //         optimizeContentCards();
    //     }, 500);
    // });

    // // Solo observar cambios en contenedores de contenido, no todo el body
    // const contentContainer = document.querySelector('.content-rows') || document.body;
    // observer.observe(contentContainer, {
    //     childList: true,
    //     subtree: false // No observar todo el árbol, solo hijos directos
    // });

    // Exportar API pública
    window.PerformanceOptimizer = {
        addWillChange: addWillChange,
        removeWillChange: removeWillChange,
        cleanupAll: cleanupAll
    };

})();

