// PWA Installation Manager
class PWAInstaller {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.isStandalone = false;

        this.init();
    }

    init() {
        // Detectar si ya está instalada
        this.checkIfInstalled();

        // Registrar Service Worker
        this.registerServiceWorker();

        // Escuchar evento de instalación
        this.setupInstallPrompt();

        // Detectar cuando se instala
        this.detectInstallation();

        // Mostrar banner de instalación si es apropiado
        this.showInstallBanner();
    }

    checkIfInstalled() {
        // Detectar si está en modo standalone (instalada)
        this.isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone ||
            document.referrer.includes('android-app://');

        this.isInstalled = this.isStandalone;

        if (this.isInstalled) {
            console.log('✅ PWA ya está instalada');
            this.hideInstallPrompts();
        }
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/streaming-platform/sw.js', {
                    scope: '/streaming-platform/'
                });

                console.log('✅ Service Worker registrado:', registration.scope);

                // Actualizar SW si hay una nueva versión
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('🔄 Nueva versión del Service Worker encontrada');

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });

                // Verificar actualizaciones cada hora
                setInterval(() => {
                    registration.update();
                }, 60 * 60 * 1000);

            } catch (error) {
                console.error('❌ Error registrando Service Worker:', error);
            }
        }
    }

    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('📱 Evento beforeinstallprompt disparado');

            // Prevenir el mini-infobar automático
            e.preventDefault();

            // Guardar el evento para usarlo después
            this.deferredPrompt = e;

            // Mostrar botón de instalación personalizado
            this.showInstallButton();
        });
    }

    detectInstallation() {
        window.addEventListener('appinstalled', () => {
            console.log('✅ PWA instalada exitosamente');
            this.isInstalled = true;
            this.deferredPrompt = null;
            this.hideInstallPrompts();

            // Analytics
            this.trackInstallation();

            // Mostrar mensaje de éxito
            this.showSuccessMessage();
        });
    }

    showInstallButton() {
        // Crear botón de instalación si no existe
        let installBtn = document.getElementById('pwa-install-btn');

        if (!installBtn) {
            installBtn = document.createElement('button');
            installBtn.id = 'pwa-install-btn';
            installBtn.className = 'pwa-install-button';
            installBtn.innerHTML = `
                <i class="fas fa-download"></i>
                <span>Instalar App</span>
            `;
            installBtn.onclick = () => this.promptInstall();

            // Agregar al navbar o footer
            const navbar = document.querySelector('.navbar-right');
            if (navbar) {
                navbar.insertBefore(installBtn, navbar.firstChild);
            }
        }

        installBtn.style.display = 'flex';
    }

    showInstallBanner() {
        // No mostrar si ya está instalada
        if (this.isInstalled) return;

        // No mostrar si ya se cerró antes
        if (localStorage.getItem('pwa-banner-dismissed')) return;

        // Esperar 30 segundos antes de mostrar
        setTimeout(() => {
            if (!this.isInstalled && this.deferredPrompt) {
                this.createInstallBanner();
            }
        }, 30000);
    }

    createInstallBanner() {
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner';
        banner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div class="pwa-banner-text">
                    <strong>Instala nuestra app</strong>
                    <p>Acceso rápido y funciona sin conexión</p>
                </div>
                <div class="pwa-banner-actions">
                    <button class="pwa-banner-install" onclick="pwaInstaller.promptInstall()">
                        Instalar
                    </button>
                    <button class="pwa-banner-close" onclick="pwaInstaller.dismissBanner()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(banner);

        // Animar entrada
        setTimeout(() => banner.classList.add('show'), 100);
    }

    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('⚠️ No hay prompt de instalación disponible');
            return;
        }

        // Mostrar el prompt de instalación
        this.deferredPrompt.prompt();

        // Esperar la respuesta del usuario
        const { outcome } = await this.deferredPrompt.userChoice;

        console.log(`Usuario ${outcome === 'accepted' ? 'aceptó' : 'rechazó'} la instalación`);

        // Limpiar el prompt
        this.deferredPrompt = null;

        // Ocultar botón de instalación
        this.hideInstallPrompts();

        // Analytics
        this.trackInstallPrompt(outcome);
    }

    dismissBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.remove('show');
            setTimeout(() => banner.remove(), 300);
        }

        // Recordar que se cerró
        localStorage.setItem('pwa-banner-dismissed', 'true');
    }

    hideInstallPrompts() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'none';
        }

        this.dismissBanner();
    }

    showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="pwa-update-content">
                <i class="fas fa-sync-alt"></i>
                <span>Nueva versión disponible</span>
                <button onclick="pwaInstaller.updateApp()">Actualizar</button>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => notification.classList.add('show'), 100);
    }

    updateApp() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then((registration) => {
                if (registration && registration.waiting) {
                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                    window.location.reload();
                }
            });
        }
    }

    showSuccessMessage() {
        if (typeof showNotification === 'function') {
            showNotification('¡App instalada correctamente!', 'success');
        }
    }

    trackInstallation() {
        // Google Analytics o similar
        if (typeof gtag === 'function') {
            gtag('event', 'pwa_installed', {
                'event_category': 'PWA',
                'event_label': 'Installation'
            });
        }
    }

    trackInstallPrompt(outcome) {
        if (typeof gtag === 'function') {
            gtag('event', 'pwa_install_prompt', {
                'event_category': 'PWA',
                'event_label': outcome
            });
        }
    }

    // Métodos útiles para la app
    async cacheContent(urls) {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'CACHE_URLS',
                urls: urls
            });
        }
    }

    async clearCache() {
        if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
            navigator.serviceWorker.controller.postMessage({
                type: 'CLEAR_CACHE'
            });
        }
    }

    isOnline() {
        return navigator.onLine;
    }

    onOnlineStatusChange(callback) {
        window.addEventListener('online', () => callback(true));
        window.addEventListener('offline', () => callback(false));
    }
}

// Inicializar PWA Installer
const pwaInstaller = new PWAInstaller();

// Exponer globalmente
window.pwaInstaller = pwaInstaller;

// Detectar cambios en el estado de conexión
pwaInstaller.onOnlineStatusChange((isOnline) => {
    console.log(isOnline ? '✅ Conexión restaurada' : '⚠️ Sin conexión');

    // Mostrar indicador visual
    const indicator = document.getElementById('connection-status');
    if (indicator) {
        indicator.textContent = isOnline ? 'En línea' : 'Sin conexión';
        indicator.className = isOnline ? 'online' : 'offline';
    }
});

console.log('✅ PWA Installer cargado');
