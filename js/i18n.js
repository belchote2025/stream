/**
 * Sistema de Internacionalización (i18n)
 * Soporte para múltiples idiomas
 */

(function() {
    'use strict';

    // Traducciones
    const translations = {
        es: {
            // Navegación
            'nav.home': 'Inicio',
            'nav.movies': 'Películas',
            'nav.series': 'Series',
            'nav.search': 'Buscar',
            'nav.my-list': 'Mi Lista',
            'nav.login': 'Iniciar Sesión',
            'nav.register': 'Registrarse',
            'nav.logout': 'Cerrar Sesión',
            'nav.profile': 'Perfil',
            'nav.admin': 'Administración',
            
            // Contenido
            'content.featured': 'Destacados',
            'content.popular': 'Populares',
            'content.recent': 'Recientes',
            'content.trending': 'Tendencias',
            'content.continue-watching': 'Continuar Viendo',
            'content.my-list': 'Mi Lista',
            'content.recommendations': 'Recomendaciones',
            
            // Acciones
            'action.play': 'Reproducir',
            'action.add-to-list': 'Añadir a Mi Lista',
            'action.remove-from-list': 'Quitar de Mi Lista',
            'action.more-info': 'Más Información',
            'action.share': 'Compartir',
            'action.download': 'Descargar',
            
            // Mensajes
            'message.loading': 'Cargando...',
            'message.no-content': 'No hay contenido disponible',
            'message.error': 'Ha ocurrido un error',
            'message.success': 'Operación exitosa',
            'message.login-success': '¡Bienvenido!',
            'message.logout-success': 'Has cerrado sesión correctamente',
            
            // Formularios
            'form.email': 'Correo Electrónico',
            'form.password': 'Contraseña',
            'form.username': 'Nombre de Usuario',
            'form.full-name': 'Nombre Completo',
            'form.submit': 'Enviar',
            'form.cancel': 'Cancelar',
            'form.save': 'Guardar',
            
            // Accesibilidad
            'accessibility.font-size': 'Tamaño de Fuente',
            'accessibility.high-contrast': 'Alto Contraste',
            'accessibility.reduced-motion': 'Reducir Movimiento',
            'accessibility.language': 'Idioma',
            
            // Fechas
            'date.today': 'Hoy',
            'date.yesterday': 'Ayer',
            'date.this-week': 'Esta Semana',
            'date.this-month': 'Este Mes',
            
            // Géneros comunes
            'genre.action': 'Acción',
            'genre.comedy': 'Comedia',
            'genre.drama': 'Drama',
            'genre.horror': 'Terror',
            'genre.sci-fi': 'Ciencia Ficción',
            'genre.romance': 'Romance',
            'genre.thriller': 'Suspense',
            'genre.animation': 'Animación',
            'genre.documentary': 'Documental'
        },
        en: {
            // Navigation
            'nav.home': 'Home',
            'nav.movies': 'Movies',
            'nav.series': 'Series',
            'nav.search': 'Search',
            'nav.my-list': 'My List',
            'nav.login': 'Login',
            'nav.register': 'Register',
            'nav.logout': 'Logout',
            'nav.profile': 'Profile',
            'nav.admin': 'Admin',
            
            // Content
            'content.featured': 'Featured',
            'content.popular': 'Popular',
            'content.recent': 'Recent',
            'content.trending': 'Trending',
            'content.continue-watching': 'Continue Watching',
            'content.my-list': 'My List',
            'content.recommendations': 'Recommendations',
            
            // Actions
            'action.play': 'Play',
            'action.add-to-list': 'Add to My List',
            'action.remove-from-list': 'Remove from My List',
            'action.more-info': 'More Info',
            'action.share': 'Share',
            'action.download': 'Download',
            
            // Messages
            'message.loading': 'Loading...',
            'message.no-content': 'No content available',
            'message.error': 'An error occurred',
            'message.success': 'Operation successful',
            'message.login-success': 'Welcome!',
            'message.logout-success': 'You have logged out successfully',
            
            // Forms
            'form.email': 'Email',
            'form.password': 'Password',
            'form.username': 'Username',
            'form.full-name': 'Full Name',
            'form.submit': 'Submit',
            'form.cancel': 'Cancel',
            'form.save': 'Save',
            
            // Accessibility
            'accessibility.font-size': 'Font Size',
            'accessibility.high-contrast': 'High Contrast',
            'accessibility.reduced-motion': 'Reduce Motion',
            'accessibility.language': 'Language',
            
            // Dates
            'date.today': 'Today',
            'date.yesterday': 'Yesterday',
            'date.this-week': 'This Week',
            'date.this-month': 'This Month',
            
            // Common genres
            'genre.action': 'Action',
            'genre.comedy': 'Comedy',
            'genre.drama': 'Drama',
            'genre.horror': 'Horror',
            'genre.sci-fi': 'Sci-Fi',
            'genre.romance': 'Romance',
            'genre.thriller': 'Thriller',
            'genre.animation': 'Animation',
            'genre.documentary': 'Documentary'
        }
    };

    // Idioma actual
    let currentLanguage = 'es';

    /**
     * Inicializar sistema i18n
     */
    function initI18n() {
        // Detectar idioma del navegador
        const browserLang = navigator.language || navigator.userLanguage;
        const langCode = browserLang.split('-')[0];
        
        // Cargar idioma guardado
        loadLanguage();
        
        // Aplicar traducciones
        applyTranslations();
        
        console.log('✅ Sistema de internacionalización inicializado');
    }

    /**
     * Cargar idioma desde localStorage
     */
    function loadLanguage() {
        try {
            const saved = localStorage.getItem('urrestv_language');
            if (saved && translations[saved]) {
                currentLanguage = saved;
            } else {
                // Detectar idioma del navegador
                const browserLang = navigator.language || navigator.userLanguage;
                const langCode = browserLang.split('-')[0];
                currentLanguage = translations[langCode] ? langCode : 'es';
            }
        } catch (e) {
            console.warn('Error al cargar idioma:', e);
        }
    }

    /**
     * Guardar idioma en localStorage
     */
    function saveLanguage() {
        try {
            localStorage.setItem('urrestv_language', currentLanguage);
        } catch (e) {
            console.warn('Error al guardar idioma:', e);
        }
    }

    /**
     * Traducir texto
     */
    function t(key, params = {}) {
        const translation = translations[currentLanguage]?.[key] || translations['es'][key] || key;
        
        // Reemplazar parámetros
        return translation.replace(/\{(\w+)\}/g, (match, param) => {
            return params[param] !== undefined ? params[param] : match;
        });
    }

    /**
     * Cambiar idioma
     */
    function setLanguage(lang) {
        if (!translations[lang]) {
            console.warn(`Idioma no soportado: ${lang}`);
            return;
        }
        
        currentLanguage = lang;
        document.documentElement.lang = lang;
        saveLanguage();
        applyTranslations();
        
        // Disparar evento personalizado
        window.dispatchEvent(new CustomEvent('languageChanged', { detail: { language: lang } }));
    }

    /**
     * Aplicar traducciones a elementos con atributo data-i18n
     */
    function applyTranslations() {
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            const translation = t(key);
            
            if (element.tagName === 'INPUT' && element.type === 'submit') {
                element.value = translation;
            } else if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                element.placeholder = translation;
            } else {
                element.textContent = translation;
            }
        });
        
        // Actualizar atributo lang del HTML
        document.documentElement.lang = currentLanguage;
    }

    /**
     * Formatear fecha según idioma
     */
    function formatDate(date, options = {}) {
        const dateObj = date instanceof Date ? date : new Date(date);
        return new Intl.DateTimeFormat(currentLanguage, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            ...options
        }).format(dateObj);
    }

    /**
     * Formatear número según idioma
     */
    function formatNumber(number, options = {}) {
        return new Intl.NumberFormat(currentLanguage, options).format(number);
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initI18n);
    } else {
        initI18n();
    }

    // Observar nuevos elementos añadidos dinámicamente
    const observer = new MutationObserver(() => {
        applyTranslations();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Exportar API pública
    window.i18n = {
        t,
        setLanguage,
        getLanguage: () => currentLanguage,
        formatDate,
        formatNumber,
        getAvailableLanguages: () => Object.keys(translations)
    };

})();








