/**
 * Sistema de notificaciones mejorado
 */

(function() {
    'use strict';

    const NotificationManager = {
        container: null,
        notifications: [],

        init() {
            // Crear contenedor de notificaciones
            this.container = document.createElement('div');
            this.container.id = 'notification-container';
            this.container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 10000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            `;
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle'
            };

            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${icons[type] || 'info-circle'}"></i>
                    <span>${message}</span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            notification.style.cssText = `
                background: rgba(20, 20, 20, 0.95);
                color: #fff;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
                border-left: 4px solid ${this.getColor(type)};
                transform: translateX(400px);
                opacity: 0;
                transition: all 0.3s ease;
                min-width: 300px;
            `;

            this.container.appendChild(notification);

            // Animar entrada
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
                notification.style.opacity = '1';
            }, 10);

            // Auto-remover
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(notification);
                }, duration);
            }

            return notification;
        },

        remove(notification) {
            notification.style.transform = 'translateX(400px)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        },

        getColor(type) {
            const colors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };
            return colors[type] || colors.info;
        }
    };

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => NotificationManager.init());
    } else {
        NotificationManager.init();
    }

    // Exponer globalmente
    window.NotificationManager = NotificationManager;
    window.showNotification = (message, type, duration) => NotificationManager.show(message, type, duration);

    // Añadir estilos
    const style = document.createElement('style');
    style.textContent = `
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .notification-content i:first-child {
            font-size: 1.2rem;
        }
        
        .notification-content span {
            flex: 1;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.2s ease;
        }
        
        .notification-close:hover {
            color: #fff;
        }
    `;
    document.head.appendChild(style);

})();

