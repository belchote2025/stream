// Performance Optimizer for PWA
class PerformanceOptimizer {
    constructor() {
        this.metrics = {
            fcp: 0,  // First Contentful Paint
            lcp: 0,  // Largest Contentful Paint
            fid: 0,  // First Input Delay
            cls: 0,  // Cumulative Layout Shift
            ttfb: 0  // Time to First Byte
        };

        this.init();
    }

    init() {
        // Medir Core Web Vitals
        this.measureWebVitals();

        // Optimizar imágenes
        this.optimizeImages();

        // Lazy load de recursos
        this.setupLazyLoading();

        // Prefetch de páginas importantes
        this.prefetchImportantPages();

        // Optimizar fuentes
        this.optimizeFonts();

        // Reportar métricas
        this.reportMetrics();
    }

    measureWebVitals() {
        // First Contentful Paint (FCP)
        if ('PerformanceObserver' in window) {
            try {
                const fcpObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (entry.name === 'first-contentful-paint') {
                            this.metrics.fcp = entry.startTime;
                            console.log('✅ FCP:', entry.startTime.toFixed(2), 'ms');
                        }
                    }
                });
                fcpObserver.observe({ entryTypes: ['paint'] });
            } catch (e) {
                console.warn('FCP observer error:', e);
            }

            // Largest Contentful Paint (LCP)
            try {
                const lcpObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    this.metrics.lcp = lastEntry.startTime;
                    console.log('✅ LCP:', lastEntry.startTime.toFixed(2), 'ms');
                });
                lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
            } catch (e) {
                console.warn('LCP observer error:', e);
            }

            // First Input Delay (FID)
            try {
                const fidObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        this.metrics.fid = entry.processingStart - entry.startTime;
                        console.log('✅ FID:', this.metrics.fid.toFixed(2), 'ms');
                    }
                });
                fidObserver.observe({ entryTypes: ['first-input'] });
            } catch (e) {
                console.warn('FID observer error:', e);
            }

            // Cumulative Layout Shift (CLS)
            try {
                let clsValue = 0;
                const clsObserver = new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                            this.metrics.cls = clsValue;
                        }
                    }
                    console.log('✅ CLS:', clsValue.toFixed(4));
                });
                clsObserver.observe({ entryTypes: ['layout-shift'] });
            } catch (e) {
                console.warn('CLS observer error:', e);
            }
        }

        // Time to First Byte (TTFB)
        if (window.performance && window.performance.timing) {
            window.addEventListener('load', () => {
                const timing = window.performance.timing;
                this.metrics.ttfb = timing.responseStart - timing.requestStart;
                console.log('✅ TTFB:', this.metrics.ttfb.toFixed(2), 'ms');
            });
        }
    }

    optimizeImages() {
        // Lazy loading nativo para imágenes
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            });
        } else {
            // Fallback: Intersection Observer
            this.setupIntersectionObserver();
        }

        // Usar WebP cuando esté disponible
        this.useWebPImages();
    }

    setupIntersectionObserver() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }

    useWebPImages() {
        // Detectar soporte WebP
        const supportsWebP = document.createElement('canvas')
            .toDataURL('image/webp')
            .indexOf('data:image/webp') === 0;

        if (supportsWebP) {
            document.documentElement.classList.add('webp');
        }
    }

    setupLazyLoading() {
        // Lazy load de iframes (videos de YouTube, etc.)
        const iframeObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const iframe = entry.target;
                    if (iframe.dataset.src) {
                        iframe.src = iframe.dataset.src;
                        iframe.removeAttribute('data-src');
                        iframeObserver.unobserve(iframe);
                    }
                }
            });
        }, {
            rootMargin: '200px 0px'
        });

        document.querySelectorAll('iframe[data-src]').forEach(iframe => {
            iframeObserver.observe(iframe);
        });
    }

    prefetchImportantPages() {
        // Deshabilitar prefetch en páginas de reproducción y otras páginas críticas
        // para evitar interferencias con el streaming y errores NS_BINDING_ABORTED
        const currentPath = window.location.pathname;
        const skipPrefetchPages = ['watch.php', 'content.php', 'content-detail.php'];
        
        if (skipPrefetchPages.some(page => currentPath.includes(page))) {
            return; // No hacer prefetch en estas páginas
        }
        
        // Solo hacer prefetch si el usuario está inactivo por al menos 5 segundos
        // y solo en la página principal (index.php) o en páginas de listado
        // Detectar página principal de forma dinámica (funciona en local y producción)
        const pathParts = currentPath.split('/').filter(p => p);
        const isRoot = currentPath === '/' || currentPath.endsWith('/');
        const isIndexPage = currentPath.includes('index.php') || 
                           (pathParts.length === 0) ||
                           (pathParts.length === 1 && pathParts[0] === 'streaming-platform');
        
        const isMainPage = isRoot || isIndexPage;
        
        if (!isMainPage) {
            return; // Solo prefetch desde la página principal
        }
        
        // Verificar que los archivos existan antes de hacer prefetch
        // (esto se hace verificando que no estemos en una ruta que ya falló)
        if (sessionStorage.getItem('prefetch_disabled')) {
            return; // Prefetch deshabilitado por errores previos
        }
        
        let idleTimer;
        let prefetchDone = false;
        
        const handleUserActivity = () => {
            clearTimeout(idleTimer);
            if (prefetchDone) return; // Ya se hizo el prefetch
            
            idleTimer = setTimeout(() => {
                // Usuario inactivo por 5 segundos, hacer prefetch de forma inteligente
                if ('requestIdleCallback' in window && !prefetchDone) {
                    requestIdleCallback(() => {
                        // Solo prefetch si no hay peticiones activas y la página está visible
                        if (document.readyState === 'complete' && !document.hidden && !prefetchDone) {
                            // Usar función getBaseUrl() si está disponible, o window.__APP_BASE_URL, o construir desde location
                            let basePath = '';
                            if (typeof window !== 'undefined' && typeof window.getBaseUrl === 'function') {
                                // Usar la función helper global si está disponible
                                basePath = window.getBaseUrl();
                            } else if (typeof window !== 'undefined' && window.__APP_BASE_URL) {
                                basePath = window.__APP_BASE_URL.replace(/\/$/, '');
                            } else {
                                // Construir desde location de forma dinámica
                                const pathParts = currentPath.split('/').filter(p => p);
                                // Detectar el path base automáticamente
                                if (pathParts.length > 0) {
                                    // Si el primer segmento parece ser un directorio del proyecto, usarlo
                                    const firstPart = pathParts[0];
                                    if (firstPart === 'streaming-platform' || firstPart === 'htdocs' || firstPart === 'www') {
                                        basePath = '/' + firstPart;
                                    } else if (pathParts.length > 1) {
                                        // Si hay múltiples partes, el primero podría ser el directorio base
                                        basePath = '/' + firstPart;
                                    } else {
                                        // En la raíz, no usar path base
                                        basePath = '';
                                    }
                                } else {
                                    // En la raíz del servidor
                                    basePath = '';
                                }
                            }
                            
                            // Usar rutas relativas simples para evitar problemas de 404
                            const importantPages = [
                                'movies.php',
                                'series.php',
                                'my-list.php'
                            ];

                            importantPages.forEach(page => {
                                // Construir ruta completa
                                const fullPath = basePath ? `${basePath}/${page}` : page;
                                
                                // Verificar que no exista ya un prefetch para esta página
                                const existingPrefetch = document.querySelector(`link[rel="prefetch"][href="${fullPath}"], link[rel="prefetch"][href="/${fullPath}"], link[rel="prefetch"][href="${page}"]`);
                                if (!existingPrefetch) {
                                    try {
                                        const link = document.createElement('link');
                                        link.rel = 'prefetch';
                                        // Usar ruta relativa para evitar problemas
                                        link.href = fullPath.startsWith('/') ? fullPath : `/${fullPath}`;
                                        // Agregar atributos para mejor manejo
                                        link.as = 'document';
                                        // No usar crossOrigin para rutas del mismo origen
                                        // Silenciar errores de prefetch
                                        link.onerror = () => {
                                            // Si hay errores de prefetch, deshabilitar para esta sesión
                                            const errorCount = parseInt(sessionStorage.getItem('prefetch_errors') || '0') + 1;
                                            sessionStorage.setItem('prefetch_errors', errorCount.toString());
                                            if (errorCount >= 2) {
                                                sessionStorage.setItem('prefetch_disabled', 'true');
                                            }
                                        };
                                        link.onload = () => {
                                            // Prefetch exitoso, resetear contador de errores
                                            sessionStorage.removeItem('prefetch_errors');
                                        };
                                        document.head.appendChild(link);
                                    } catch (e) {
                                        // Ignorar errores de prefetch silenciosamente
                                    }
                                }
                            });
                            
                            prefetchDone = true; // Marcar como hecho
                        }
                    }, { timeout: 3000 }); // Timeout más corto
                }
            }, 5000); // Esperar 5 segundos de inactividad (aumentado)
        };
        
        // Escuchar actividad del usuario
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, handleUserActivity, { passive: true, once: false });
        });
        
        // Iniciar el timer después de que la página se cargue completamente
        if (document.readyState === 'complete') {
            handleUserActivity();
        } else {
            window.addEventListener('load', handleUserActivity, { once: true });
        }
    }

    optimizeFonts() {
        // Usar font-display: swap para evitar FOIT
        if ('fonts' in document) {
            document.fonts.ready.then(() => {
                console.log('✅ Fuentes cargadas');
            });
        }
    }

    reportMetrics() {
        // Reportar métricas después de que la página cargue
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.sendMetricsToAnalytics();
            }, 3000);
        });
    }

    sendMetricsToAnalytics() {
        // Enviar a Google Analytics si está disponible
        if (typeof gtag === 'function') {
            gtag('event', 'web_vitals', {
                event_category: 'Performance',
                fcp: Math.round(this.metrics.fcp),
                lcp: Math.round(this.metrics.lcp),
                fid: Math.round(this.metrics.fid),
                cls: this.metrics.cls.toFixed(4),
                ttfb: Math.round(this.metrics.ttfb)
            });
        }

        // Log en consola
        console.log('📊 Performance Metrics:', {
            FCP: `${this.metrics.fcp.toFixed(2)}ms`,
            LCP: `${this.metrics.lcp.toFixed(2)}ms`,
            FID: `${this.metrics.fid.toFixed(2)}ms`,
            CLS: this.metrics.cls.toFixed(4),
            TTFB: `${this.metrics.ttfb.toFixed(2)}ms`
        });

        // Mostrar advertencias si las métricas son malas
        this.showPerformanceWarnings();
    }

    showPerformanceWarnings() {
        const warnings = [];

        if (this.metrics.fcp > 1800) {
            warnings.push('⚠️ FCP muy lento (>1.8s)');
        }
        if (this.metrics.lcp > 2500) {
            warnings.push('⚠️ LCP muy lento (>2.5s)');
        }
        if (this.metrics.fid > 100) {
            warnings.push('⚠️ FID muy alto (>100ms)');
        }
        if (this.metrics.cls > 0.1) {
            warnings.push('⚠️ CLS muy alto (>0.1)');
        }

        if (warnings.length > 0) {
            console.warn('Performance Issues:', warnings);
        } else {
            console.log('✅ Todas las métricas están en rango óptimo');
        }
    }

    // Método público para obtener métricas
    getMetrics() {
        return this.metrics;
    }
}

// Optimizaciones adicionales
class ResourceOptimizer {
    constructor() {
        this.init();
    }

    init() {
        // Comprimir respuestas con Brotli/Gzip (ya configurado en servidor)
        this.checkCompression();

        // Minimizar reflows y repaints
        this.optimizeDOM();

        // Usar requestAnimationFrame para animaciones
        this.optimizeAnimations();

        // Debounce de eventos costosos
        this.optimizeEvents();
    }

    checkCompression() {
        // Verificar si las respuestas están comprimidas
        fetch(window.location.href, { method: 'HEAD' })
            .then(response => {
                const encoding = response.headers.get('content-encoding');
                if (encoding && (encoding.includes('gzip') || encoding.includes('br'))) {
                    console.log('✅ Compresión activa:', encoding);
                } else {
                    console.warn('⚠️ Compresión no detectada');
                }
            })
            .catch(e => console.warn('No se pudo verificar compresión'));
    }

    optimizeDOM() {
        // Usar DocumentFragment para inserciones múltiples
        window.createOptimizedFragment = function (html) {
            const template = document.createElement('template');
            template.innerHTML = html.trim();
            return template.content;
        };
    }

    optimizeAnimations() {
        // Wrapper para requestAnimationFrame
        window.smoothAnimate = function (callback) {
            let ticking = false;
            return function (...args) {
                if (!ticking) {
                    requestAnimationFrame(() => {
                        callback.apply(this, args);
                        ticking = false;
                    });
                    ticking = true;
                }
            };
        };
    }

    optimizeEvents() {
        // Debounce helper
        window.debounce = function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        };

        // Throttle helper
        window.throttle = function (func, limit) {
            let inThrottle;
            return function (...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        };
    }
}

// Inicializar optimizadores
const performanceOptimizer = new PerformanceOptimizer();
const resourceOptimizer = new ResourceOptimizer();

// Exponer globalmente
window.performanceOptimizer = performanceOptimizer;
window.resourceOptimizer = resourceOptimizer;

console.log('✅ Performance Optimizer cargado');
