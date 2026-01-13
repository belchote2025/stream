/**
 * Sistema de Logging Condicional
 * Uso: Reemplaza console.log/error/warn en toda la aplicación
 * 
 * Beneficios:
 * - Logging controlado en producción
 * - Reporting automático de errores
 * - Mejor debugging
 */

const Logger = (function () {
    'use strict';

    // Detectar si estamos en desarrollo
    const isDevelopment = window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1' ||
        window.location.hostname.includes('.local');

    // Cola para errores que necesitan ser reportados
    const errorQueue = [];
    let isReporting = false;

    /**
     * Reportar errores al servidor
     */
    function reportToServer(level, message, error) {
        if (isReporting) return;

        const errorData = {
            level,
            message,
            error: error ? {
                message: error.message,
                stack: error.stack,
                name: error.name
            } : null,
            url: window.location.href,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };

        errorQueue.push(errorData);

        // Enviar errores en lote (debounced)
        if (!isReporting) {
            isReporting = true;
            setTimeout(() => {
                sendErrors();
            }, 2000);
        }
    }

    /**
     * Enviar errores acumulados al servidor
     */
    function sendErrors() {
        if (errorQueue.length === 0) {
            isReporting = false;
            return;
        }

        const errors = [...errorQueue];
        errorQueue.length = 0;

        fetch('/api/log-error.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ errors })
        })
            .catch(() => {
                // Silenciar errores del logging para evitar loops
            })
            .finally(() => {
                isReporting = false;
            });
    }

    return {
        /**
         * Log general - solo en desarrollo
         */
        log(...args) {
            if (isDevelopment) {
                console.log(...args);
            }
        },

        /**
         * Advertencias - solo en desarrollo
         */
        warn(...args) {
            if (isDevelopment) {
                console.warn(...args);
            }
        },

        /**
         * Errores - siempre logea, reporta en producción
         */
        error(...args) {
            // Siempre mostrar en consola
            console.error(...args);

            // En producción, reportar al servidor
            if (!isDevelopment) {
                const message = args.map(arg => {
                    if (typeof arg === 'object') {
                        try {
                            return JSON.stringify(arg);
                        } catch (e) {
                            return String(arg);
                        }
                    }
                    return String(arg);
                }).join(' ');

                const errorObj = args.find(arg => arg instanceof Error);
                reportToServer('error', message, errorObj);
            }
        },

        /**
         * Info - solo en desarrollo
         */
        info(...args) {
            if (isDevelopment) {
                console.info(...args);
            }
        },

        /**
         * Debug - solo en desarrollo
         */
        debug(...args) {
            if (isDevelopment) {
                console.debug(...args);
            }
        },

        /**
         * Tabla - solo en desarrollo
         */
        table(data) {
            if (isDevelopment) {
                console.table(data);
            }
        },

        /**
         * Tiempo - solo en desarrollo
         */
        time(label) {
            if (isDevelopment) {
                console.time(label);
            }
        },

        timeEnd(label) {
            if (isDevelopment) {
                console.timeEnd(label);
            }
        },

        /**
         * Información de estado
         */
        getStatus() {
            return {
                isDevelopment,
                queuedErrors: errorQueue.length,
                isReporting
            };
        }
    };
})();

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.Logger = Logger;
}

// Capturar errores no manejados
window.addEventListener('error', (event) => {
    Logger.error('Unhandled error:', event.error || event.message);
});

// Capturar promesas rechazadas no manejadas
window.addEventListener('unhandledrejection', (event) => {
    Logger.error('Unhandled promise rejection:', event.reason);
});
