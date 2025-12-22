/**
 * Sistema de Accesibilidad
 * Navegación por teclado, modo alto contraste, tamaño de fuente ajustable
 */

(function() {
    'use strict';

    // Configuración
    const config = {
        storageKey: 'urrestv_accessibility',
        defaultSettings: {
            fontSize: 'normal',
            highContrast: false,
            reducedMotion: false
        }
    };

    // Estado
    let settings = { ...config.defaultSettings };

    /**
     * Limpiar controles antiguos si existen
     */
    function cleanupOldControls() {
        // Buscar y eliminar controles antiguos que no estén dentro del nuevo contenedor
        const oldControls = document.getElementById('accessibility-controls');
        if (oldControls && !oldControls.closest('.accessibility-container')) {
            console.log('Eliminando controles antiguos de accesibilidad...');
            oldControls.remove();
        }
        
        // Eliminar cualquier contenedor antiguo sin el toggle
        const oldContainer = document.querySelector('.accessibility-controls:not(.accessibility-container .accessibility-controls)');
        if (oldContainer && !oldContainer.closest('.accessibility-container')) {
            oldContainer.remove();
        }
    }

    /**
     * Inicializar sistema de accesibilidad
     */
    function initAccessibility() {
        // Limpiar controles antiguos primero
        cleanupOldControls();
        
        // Cargar configuración guardada
        loadSettings();

        // Crear controles de accesibilidad
        createAccessibilityControls();

        // Aplicar configuración
        applySettings();

        // Navegación por teclado
        setupKeyboardNavigation();

        // Detectar preferencias del sistema
        detectSystemPreferences();

        console.log('✅ Sistema de accesibilidad inicializado');
    }

    /**
     * Cargar configuración desde localStorage
     */
    function loadSettings() {
        try {
            const saved = localStorage.getItem(config.storageKey);
            if (saved) {
                settings = { ...config.defaultSettings, ...JSON.parse(saved) };
            }
        } catch (e) {
            console.warn('Error al cargar configuración de accesibilidad:', e);
        }
    }

    /**
     * Guardar configuración en localStorage
     */
    function saveSettings() {
        try {
            localStorage.setItem(config.storageKey, JSON.stringify(settings));
        } catch (e) {
            console.warn('Error al guardar configuración de accesibilidad:', e);
        }
    }

    /**
     * Aplicar configuración
     */
    function applySettings() {
        // Tamaño de fuente
        document.body.classList.remove('font-small', 'font-normal', 'font-large', 'font-xlarge', 'font-xxlarge');
        document.body.classList.add(`font-${settings.fontSize}`);

        // Alto contraste
        if (settings.highContrast) {
            document.body.classList.add('high-contrast');
        } else {
            document.body.classList.remove('high-contrast');
        }

        // Movimiento reducido
        if (settings.reducedMotion) {
            document.body.classList.add('reduced-motion');
        } else {
            document.body.classList.remove('reduced-motion');
        }
    }

    /**
     * Crear controles de accesibilidad
     */
    function createAccessibilityControls() {
        // Limpiar cualquier control antiguo primero
        cleanupOldControls();
        
        // Verificar si ya existen los nuevos controles
        if (document.getElementById('accessibility-toggle')) {
            console.log('Controles de accesibilidad ya existen');
            return;
        }
        
        // Eliminar cualquier contenedor antiguo completo si existe
        const existingContainer = document.getElementById('accessibility-container');
        if (existingContainer) {
            existingContainer.remove();
        }

        const container = document.createElement('div');
        container.id = 'accessibility-container';
        container.className = 'accessibility-container';

        container.innerHTML = `
            <button id="accessibility-toggle" class="accessibility-toggle" aria-label="Abrir controles de accesibilidad" aria-expanded="false" title="Accesibilidad">
                <i class="fas fa-universal-access" aria-hidden="true"></i>
            </button>
            <div id="accessibility-controls" class="accessibility-controls" role="toolbar" aria-label="Controles de accesibilidad">
                <div class="accessibility-header">
                    <h3>Accesibilidad</h3>
                    <button id="accessibility-close" class="accessibility-close" aria-label="Cerrar controles de accesibilidad">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="accessibility-group">
                    <label>Tamaño de fuente</label>
                    <div class="accessibility-buttons">
                        <button id="font-decrease" aria-label="Reducir tamaño de fuente" title="Reducir tamaño de fuente">
                            <i class="fas fa-minus" aria-hidden="true"></i>
                            <span>A-</span>
                        </button>
                        <span class="font-size-indicator" id="font-size-indicator">Normal</span>
                        <button id="font-increase" aria-label="Aumentar tamaño de fuente" title="Aumentar tamaño de fuente">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <span>A+</span>
                        </button>
                    </div>
                </div>
                <div class="accessibility-group">
                    <label>Alto contraste</label>
                    <button id="toggle-contrast" class="toggle-button" aria-label="Alternar alto contraste" aria-pressed="false">
                        <i class="fas fa-adjust" aria-hidden="true"></i>
                        <span>Activar</span>
                    </button>
                </div>
                <div class="accessibility-group">
                    <label>Reducir movimiento</label>
                    <button id="toggle-motion" class="toggle-button" aria-label="Reducir movimiento" aria-pressed="false">
                        <i class="fas fa-pause-circle" aria-hidden="true"></i>
                        <span>Activar</span>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(container);
        
        console.log('✅ Controles de accesibilidad creados con nuevo diseño');

        // Event listeners
        const toggle = document.getElementById('accessibility-toggle');
        const close = document.getElementById('accessibility-close');
        const controls = document.getElementById('accessibility-controls');
        
        if (!toggle || !close || !controls) {
            console.error('Error: No se pudieron encontrar los elementos del nuevo diseño');
            return;
        }

        toggle.addEventListener('click', () => {
            const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', !isExpanded);
            controls.classList.toggle('show');
            toggle.classList.toggle('active');
        });

        close.addEventListener('click', () => {
            toggle.setAttribute('aria-expanded', 'false');
            controls.classList.remove('show');
            toggle.classList.remove('active');
        });

        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target) && controls.classList.contains('show')) {
                toggle.setAttribute('aria-expanded', 'false');
                controls.classList.remove('show');
                toggle.classList.remove('active');
            }
        });

        document.getElementById('font-decrease').addEventListener('click', decreaseFontSize);
        document.getElementById('font-increase').addEventListener('click', increaseFontSize);
        document.getElementById('toggle-contrast').addEventListener('click', toggleHighContrast);
        document.getElementById('toggle-motion').addEventListener('click', toggleReducedMotion);

        // Actualizar estado de botones
        updateButtonStates();
    }

    /**
     * Reducir tamaño de fuente
     */
    function decreaseFontSize() {
        const sizes = ['small', 'normal', 'large', 'xlarge', 'xxlarge'];
        const currentIndex = sizes.indexOf(settings.fontSize);
        if (currentIndex > 0) {
            settings.fontSize = sizes[currentIndex - 1];
            applySettings();
            saveSettings();
            updateButtonStates();
        }
    }

    /**
     * Aumentar tamaño de fuente
     */
    function increaseFontSize() {
        const sizes = ['small', 'normal', 'large', 'xlarge', 'xxlarge'];
        const currentIndex = sizes.indexOf(settings.fontSize);
        if (currentIndex < sizes.length - 1) {
            settings.fontSize = sizes[currentIndex + 1];
            applySettings();
            saveSettings();
            updateButtonStates();
        }
    }

    /**
     * Alternar alto contraste
     */
    function toggleHighContrast() {
        settings.highContrast = !settings.highContrast;
        applySettings();
        saveSettings();
        updateButtonStates();
    }

    /**
     * Alternar movimiento reducido
     */
    function toggleReducedMotion() {
        settings.reducedMotion = !settings.reducedMotion;
        applySettings();
        saveSettings();
        updateButtonStates();
    }

    /**
     * Actualizar estado de botones
     */
    function updateButtonStates() {
        const fontDecrease = document.getElementById('font-decrease');
        const fontIncrease = document.getElementById('font-increase');
        const toggleContrast = document.getElementById('toggle-contrast');
        const toggleMotion = document.getElementById('toggle-motion');
        const fontSizeIndicator = document.getElementById('font-size-indicator');

        if (fontDecrease) {
            const sizes = ['small', 'normal', 'large', 'xlarge', 'xxlarge'];
            fontDecrease.disabled = sizes.indexOf(settings.fontSize) === 0;
            fontDecrease.classList.toggle('disabled', sizes.indexOf(settings.fontSize) === 0);
        }

        if (fontIncrease) {
            const sizes = ['small', 'normal', 'large', 'xlarge', 'xxlarge'];
            fontIncrease.disabled = sizes.indexOf(settings.fontSize) === sizes.length - 1;
            fontIncrease.classList.toggle('disabled', sizes.indexOf(settings.fontSize) === sizes.length - 1);
        }

        if (fontSizeIndicator) {
            const sizeLabels = {
                'small': 'Pequeño',
                'normal': 'Normal',
                'large': 'Grande',
                'xlarge': 'Muy Grande',
                'xxlarge': 'Extra Grande'
            };
            fontSizeIndicator.textContent = sizeLabels[settings.fontSize] || 'Normal';
        }

        if (toggleContrast) {
            toggleContrast.classList.toggle('active', settings.highContrast);
            toggleContrast.setAttribute('aria-pressed', settings.highContrast);
            const span = toggleContrast.querySelector('span');
            if (span) {
                span.textContent = settings.highContrast ? 'Desactivar' : 'Activar';
            }
        }

        if (toggleMotion) {
            toggleMotion.classList.toggle('active', settings.reducedMotion);
            toggleMotion.setAttribute('aria-pressed', settings.reducedMotion);
            const span = toggleMotion.querySelector('span');
            if (span) {
                span.textContent = settings.reducedMotion ? 'Desactivar' : 'Activar';
            }
        }
    }

    /**
     * Configurar navegación por teclado
     */
    function setupKeyboardNavigation() {
        // Atajos de teclado globales
        document.addEventListener('keydown', (e) => {
            // Alt + A: Abrir controles de accesibilidad
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                const controls = document.getElementById('accessibility-controls');
                if (controls) {
                    controls.style.display = controls.style.display === 'none' ? 'flex' : 'none';
                }
            }

            // Escape: Cerrar modales y menús
            if (e.key === 'Escape') {
                // Cerrar cualquier modal abierto
                const modals = document.querySelectorAll('.modal.show, .dropdown-menu.show');
                modals.forEach(modal => {
                    modal.classList.remove('show');
                });
            }

            // Tab: Mejorar navegación
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        // Remover clase cuando se usa mouse
        document.addEventListener('mousedown', () => {
            document.body.classList.remove('keyboard-navigation');
        });
    }

    /**
     * Detectar preferencias del sistema
     */
    function detectSystemPreferences() {
        // Detectar preferencia de movimiento reducido
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            settings.reducedMotion = true;
        }

        // Detectar preferencia de alto contraste
        if (window.matchMedia('(prefers-contrast: high)').matches) {
            settings.highContrast = true;
        }

        applySettings();
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccessibility);
    } else {
        initAccessibility();
    }

    // Exportar API pública
    window.Accessibility = {
        init: initAccessibility,
        setFontSize: (size) => {
            settings.fontSize = size;
            applySettings();
            saveSettings();
            updateButtonStates();
        },
        setHighContrast: (enabled) => {
            settings.highContrast = enabled;
            applySettings();
            saveSettings();
            updateButtonStates();
        },
        getSettings: () => ({ ...settings })
    };

})();

