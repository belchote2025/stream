// Push Notifications Manager for PWA
class PushNotificationManager {
    constructor() {
        this.isSupported = 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
        this.permission = Notification.permission;
        this.subscription = null;

        if (this.isSupported) {
            this.init();
        } else {
            console.warn('⚠️ Push notifications no soportadas en este navegador');
        }
    }

    async init() {
        console.log('🔔 Inicializando Push Notifications...');

        // Verificar si ya hay una suscripción
        await this.checkExistingSubscription();

        // Mostrar prompt de permiso si es apropiado
        this.setupPermissionPrompt();
    }

    async checkExistingSubscription() {
        try {
            const registration = await navigator.serviceWorker.ready;
            this.subscription = await registration.pushManager.getSubscription();

            if (this.subscription) {
                console.log('✅ Suscripción existente encontrada');
                this.sendSubscriptionToServer(this.subscription);
            }
        } catch (error) {
            console.error('Error verificando suscripción:', error);
        }
    }

    setupPermissionPrompt() {
        // No mostrar prompt inmediatamente, esperar acción del usuario
        // Puedes llamar a requestPermission() cuando el usuario haga click en un botón
    }

    async requestPermission() {
        if (!this.isSupported) {
            return { success: false, error: 'No soportado' };
        }

        if (this.permission === 'granted') {
            return { success: true, message: 'Ya tienes permiso' };
        }

        if (this.permission === 'denied') {
            return { success: false, error: 'Permiso denegado previamente' };
        }

        try {
            const permission = await Notification.requestPermission();
            this.permission = permission;

            if (permission === 'granted') {
                console.log('✅ Permiso de notificaciones concedido');
                await this.subscribe();
                return { success: true, message: 'Permiso concedido' };
            } else {
                console.log('❌ Permiso de notificaciones denegado');
                return { success: false, error: 'Permiso denegado' };
            }
        } catch (error) {
            console.error('Error solicitando permiso:', error);
            return { success: false, error: error.message };
        }
    }

    async subscribe() {
        try {
            const registration = await navigator.serviceWorker.ready;

            // Clave pública VAPID (debes generar tu propia clave)
            // Genera tus claves en: https://web-push-codelab.glitch.me/
            const vapidPublicKey = 'TU_CLAVE_PUBLICA_VAPID_AQUI';

            // Convertir la clave a formato correcto
            const convertedVapidKey = this.urlBase64ToUint8Array(vapidPublicKey);

            this.subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });

            console.log('✅ Suscrito a push notifications');

            // Enviar suscripción al servidor
            await this.sendSubscriptionToServer(this.subscription);

            return { success: true, subscription: this.subscription };
        } catch (error) {
            console.error('Error suscribiendo a push:', error);
            return { success: false, error: error.message };
        }
    }

    async unsubscribe() {
        if (!this.subscription) {
            return { success: false, error: 'No hay suscripción activa' };
        }

        try {
            await this.subscription.unsubscribe();
            console.log('✅ Desuscrito de push notifications');

            // Notificar al servidor
            await this.removeSubscriptionFromServer(this.subscription);

            this.subscription = null;
            return { success: true, message: 'Desuscrito correctamente' };
        } catch (error) {
            console.error('Error desuscribiendo:', error);
            return { success: false, error: error.message };
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            const response = await fetch('/streaming-platform/api/push/subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });

            if (response.ok) {
                console.log('✅ Suscripción enviada al servidor');
            } else {
                console.error('❌ Error enviando suscripción al servidor');
            }
        } catch (error) {
            console.error('Error enviando suscripción:', error);
        }
    }

    async removeSubscriptionFromServer(subscription) {
        try {
            await fetch('/streaming-platform/api/push/unsubscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint
                })
            });
        } catch (error) {
            console.error('Error removiendo suscripción:', error);
        }
    }

    // Mostrar notificación local (sin servidor)
    async showLocalNotification(title, options = {}) {
        if (this.permission !== 'granted') {
            console.warn('No hay permiso para mostrar notificaciones');
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;

            const defaultOptions = {
                body: '',
                icon: '/streaming-platform/assets/icons/icon-192x192.png',
                badge: '/streaming-platform/assets/icons/icon-72x72.png',
                vibrate: [200, 100, 200],
                tag: 'streaming-notification',
                requireInteraction: false,
                ...options
            };

            await registration.showNotification(title, defaultOptions);
            console.log('✅ Notificación mostrada');
        } catch (error) {
            console.error('Error mostrando notificación:', error);
        }
    }

    // Utilidad para convertir clave VAPID
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Métodos públicos útiles
    isPermissionGranted() {
        return this.permission === 'granted';
    }

    isSubscribed() {
        return this.subscription !== null;
    }

    getSubscription() {
        return this.subscription;
    }
}

// UI Helper para notificaciones
class NotificationUI {
    constructor(pushManager) {
        this.pushManager = pushManager;
        this.createUI();
    }

    createUI() {
        // Crear botón de notificaciones en el navbar
        const notifBtn = document.createElement('button');
        notifBtn.id = 'notification-toggle';
        notifBtn.className = 'notification-toggle';
        notifBtn.innerHTML = '<i class="fas fa-bell"></i>';
        notifBtn.title = 'Notificaciones';
        notifBtn.onclick = () => this.toggleNotifications();

        // Agregar al navbar
        const navbar = document.querySelector('.navbar-right');
        if (navbar) {
            navbar.insertBefore(notifBtn, navbar.firstChild);
        }

        // Actualizar estado visual
        this.updateButtonState();
    }

    async toggleNotifications() {
        if (this.pushManager.isPermissionGranted()) {
            if (this.pushManager.isSubscribed()) {
                // Desuscribir
                const result = await this.pushManager.unsubscribe();
                if (result.success) {
                    this.showMessage('Notificaciones desactivadas', 'info');
                    this.updateButtonState();
                }
            } else {
                // Suscribir
                const result = await this.pushManager.subscribe();
                if (result.success) {
                    this.showMessage('Notificaciones activadas', 'success');
                    this.updateButtonState();
                }
            }
        } else {
            // Solicitar permiso
            const result = await this.pushManager.requestPermission();
            if (result.success) {
                this.showMessage('¡Notificaciones activadas!', 'success');
                this.updateButtonState();
            } else {
                this.showMessage('Permiso denegado', 'error');
            }
        }
    }

    updateButtonState() {
        const btn = document.getElementById('notification-toggle');
        if (!btn) return;

        if (this.pushManager.isSubscribed()) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-bell"></i>';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="far fa-bell"></i>';
        }
    }

    showMessage(message, type = 'info') {
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else {
            console.log(message);
        }
    }
}

// Ejemplos de uso de notificaciones
class NotificationExamples {
    constructor(pushManager) {
        this.pushManager = pushManager;
    }

    // Notificar nuevo contenido
    notifyNewContent(title, description) {
        this.pushManager.showLocalNotification('Nuevo contenido disponible', {
            body: `${title} - ${description}`,
            icon: '/streaming-platform/assets/icons/icon-192x192.png',
            actions: [
                {
                    action: 'view',
                    title: 'Ver ahora'
                },
                {
                    action: 'later',
                    title: 'Más tarde'
                }
            ]
        });
    }

    // Notificar nuevo episodio
    notifyNewEpisode(seriesTitle, episodeTitle) {
        this.pushManager.showLocalNotification('Nuevo episodio disponible', {
            body: `${seriesTitle}: ${episodeTitle}`,
            tag: 'new-episode',
            actions: [
                {
                    action: 'watch',
                    title: 'Ver ahora'
                }
            ]
        });
    }

    // Recordatorio de contenido
    notifyReminder(title) {
        this.pushManager.showLocalNotification('Recordatorio', {
            body: `No olvides ver: ${title}`,
            tag: 'reminder',
            requireInteraction: true
        });
    }
}

// Inicializar si está soportado
let pushNotificationManager;
let notificationUI;
let notificationExamples;

if ('Notification' in window) {
    pushNotificationManager = new PushNotificationManager();

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            notificationUI = new NotificationUI(pushNotificationManager);
            notificationExamples = new NotificationExamples(pushNotificationManager);
        });
    } else {
        notificationUI = new NotificationUI(pushNotificationManager);
        notificationExamples = new NotificationExamples(pushNotificationManager);
    }

    // Exponer globalmente
    window.pushNotificationManager = pushNotificationManager;
    window.notificationExamples = notificationExamples;
}

console.log('✅ Push Notification Manager cargado');
