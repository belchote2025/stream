// Service Worker para PWA - Streaming Platform
const CACHE_NAME = 'streaming-platform-v1.0.1';
const RUNTIME_CACHE = 'streaming-runtime-v1';
const IMAGE_CACHE = 'streaming-images-v1';

// Archivos críticos para cachear durante la instalación
const PRECACHE_URLS = [
    './',
    './index.php',
    './css/styles.css',
    './css/navbar-enhancements.css',
    './js/main.js',
    './js/video-player.js',
    './assets/img/default-poster.svg',
    './assets/img/default-backdrop.svg',
    './manifest.json',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// Instalación del Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Precaching app shell');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activación del Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');

    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME &&
                        cacheName !== RUNTIME_CACHE &&
                        cacheName !== IMAGE_CACHE) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Estrategia de caché
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorar requests que no sean HTTP/HTTPS
    if (!request.url.startsWith('http')) {
        return;
    }

    // EXCLUIR videos grandes - dejar pasar directamente sin interceptar
    // Los videos grandes no deben ser cacheados por el Service Worker
    const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.mkv', '.flv', '.wmv', '.m4v'];
    const isVideoFile = videoExtensions.some(ext => url.pathname.toLowerCase().endsWith(ext)) ||
                       url.pathname.includes('/uploads/videos/') ||
                       url.pathname.includes('/uploads/') && videoExtensions.some(ext => url.pathname.toLowerCase().includes(ext)) ||
                       url.pathname.includes('/videos/') ||
                       request.destination === 'video' ||
                       (request.headers && request.headers.get('accept') && request.headers.get('accept').includes('video/'));
    
    if (isVideoFile) {
        // Dejar pasar directamente sin interceptar - no usar event.respondWith
        // Esto permite que el navegador maneje la petición directamente
        return;
    }

    // EXCLUIR archivos grandes en general (más de 50MB)
    // Verificar Content-Length si está disponible
    if (request.headers && request.headers.get('content-length')) {
        const contentLength = parseInt(request.headers.get('content-length'), 10);
        if (contentLength > 50 * 1024 * 1024) { // 50MB
            return; // Dejar pasar sin interceptar
        }
    }

    // Estrategia para imágenes: Cache First
    if (request.destination === 'image') {
        event.respondWith(cacheFirstStrategy(request, IMAGE_CACHE));
        return;
    }

    // Estrategia para API: Network First
    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirstStrategy(request, RUNTIME_CACHE));
        return;
    }

    // Estrategia para assets estáticos: Cache First
    if (request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'font') {
        event.respondWith(cacheFirstStrategy(request, CACHE_NAME));
        return;
    }

    // Estrategia para páginas: Network First con fallback
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstStrategy(request, RUNTIME_CACHE));
        return;
    }

    // Default: Network First
    event.respondWith(networkFirstStrategy(request, RUNTIME_CACHE));
});

// Estrategia Cache First
async function cacheFirstStrategy(request, cacheName) {
    // No cachear peticiones HEAD - no están soportadas por la Cache API
    if (request.method === 'HEAD') {
        try {
            return await fetch(request);
        } catch (error) {
            console.error('[SW] Fetch failed for HEAD request:', error);
            return new Response('Offline - Content not available', {
                status: 503,
                statusText: 'Service Unavailable'
            });
        }
    }

    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        // Actualizar caché en background
        fetchAndCache(request, cacheName);
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        // Solo cachear respuestas completas (200 OK), no parciales (206)
        if (networkResponse.ok && 
            networkResponse.status === 200 && 
            request.method === 'GET') {
            // Verificar que no sea un video antes de cachear
            const contentType = networkResponse.headers.get('content-type') || '';
            const url = new URL(request.url);
            const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.mkv', '.flv', '.wmv', '.m4v'];
            const isVideoFile = videoExtensions.some(ext => url.pathname.toLowerCase().endsWith(ext)) ||
                               url.pathname.includes('/uploads/videos/') ||
                               url.pathname.includes('/videos/') ||
                               contentType.includes('video/');
            
            if (!isVideoFile) {
                // Solo cachear si no es un video y es una respuesta completa
                try {
                    cache.put(request, networkResponse.clone());
                } catch (cacheError) {
                    // Si falla el cacheo (por ejemplo, respuesta parcial), ignorar silenciosamente
                    console.warn('[SW] Could not cache response:', cacheError.message);
                }
            }
        }
        return networkResponse;
    } catch (error) {
        console.error('[SW] Fetch failed:', error);
        return new Response('Offline - Content not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Estrategia Network First
async function networkFirstStrategy(request, cacheName) {
    const cache = await caches.open(cacheName);

    // No cachear peticiones HEAD - no están soportadas por la Cache API
    if (request.method === 'HEAD') {
        try {
            return await fetch(request);
        } catch (error) {
            console.error('[SW] Fetch failed for HEAD request:', error);
            return new Response('Offline - Content not available', {
                status: 503,
                statusText: 'Service Unavailable'
            });
        }
    }

    try {
        const networkResponse = await fetch(request);
        // Solo cachear respuestas completas (200 OK), no parciales (206)
        if (networkResponse.ok && 
            networkResponse.status === 200 && 
            request.method === 'GET') {
            // Verificar que no sea un video antes de cachear
            const contentType = networkResponse.headers.get('content-type') || '';
            const url = new URL(request.url);
            const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.mkv', '.flv', '.wmv', '.m4v'];
            const isVideoFile = videoExtensions.some(ext => url.pathname.toLowerCase().endsWith(ext)) ||
                               url.pathname.includes('/uploads/videos/') ||
                               url.pathname.includes('/videos/') ||
                               contentType.includes('video/');
            
            if (!isVideoFile) {
                // Solo cachear si no es un video y es una respuesta completa
                try {
                    cache.put(request, networkResponse.clone());
                } catch (cacheError) {
                    // Si falla el cacheo (por ejemplo, respuesta parcial), ignorar silenciosamente
                    console.warn('[SW] Could not cache response:', cacheError.message);
                }
            }
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cachedResponse = await cache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        // Si es una página, devolver página offline
        if (request.mode === 'navigate') {
            const offlinePage = await cache.match('./offline.html');
            if (offlinePage) {
                return offlinePage;
            }
        }

        return new Response('Offline - Content not available', {
            status: 503,
            statusText: 'Service Unavailable',
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// Actualizar caché en background
async function fetchAndCache(request, cacheName) {
    // No cachear peticiones HEAD o que no sean GET
    if (request.method !== 'GET') {
        return;
    }
    
    // No cachear videos
    const url = new URL(request.url);
    const videoExtensions = ['.mp4', '.webm', '.ogg', '.avi', '.mov', '.mkv', '.flv', '.wmv', '.m4v'];
    const isVideoFile = videoExtensions.some(ext => url.pathname.toLowerCase().endsWith(ext)) ||
                       url.pathname.includes('/uploads/videos/') ||
                       url.pathname.includes('/videos/') ||
                       request.destination === 'video';
    
    if (isVideoFile) {
        return; // No cachear videos
    }
    
    try {
        const networkResponse = await fetch(request);
        // Solo cachear respuestas completas (200 OK), no parciales (206)
        if (networkResponse.ok && networkResponse.status === 200) {
            // Verificar Content-Type para excluir videos
            const contentType = networkResponse.headers.get('content-type') || '';
            if (contentType.includes('video/')) {
                return; // No cachear videos
            }
            
            // Verificar tamaño antes de cachear
            const contentLength = networkResponse.headers.get('content-length');
            if (contentLength && parseInt(contentLength, 10) > 10 * 1024 * 1024) {
                return; // No cachear archivos mayores a 10MB
            }
            
            // Verificar que no sea una respuesta parcial (206)
            if (networkResponse.status === 206) {
                return; // No cachear respuestas parciales
            }
            
            try {
                const cache = await caches.open(cacheName);
                cache.put(request, networkResponse.clone());
            } catch (cacheError) {
                // Si falla el cacheo, ignorar silenciosamente
                console.warn('[SW] Could not cache response in background:', cacheError.message);
            }
        }
    } catch (error) {
        // Silently fail
    }
}

// Manejo de mensajes desde la app
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            })
        );
    }

    if (event.data && event.data.type === 'CACHE_URLS') {
        const urls = event.data.urls;
        event.waitUntil(
            caches.open(RUNTIME_CACHE).then((cache) => {
                return cache.addAll(urls);
            })
        );
    }
});

// Sincronización en background
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);

    if (event.tag === 'sync-playback') {
        event.waitUntil(syncPlaybackProgress());
    }
});

// Sincronizar progreso de reproducción
async function syncPlaybackProgress() {
    try {
        // Obtener datos pendientes de sincronización
        const cache = await caches.open(RUNTIME_CACHE);
        const pendingSync = await cache.match('/pending-sync');

        if (pendingSync) {
            const data = await pendingSync.json();
            // Enviar a la API
            await fetch('./api/playback/progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            // Limpiar datos sincronizados
            await cache.delete('/pending-sync');
        }
    } catch (error) {
        console.error('[SW] Sync failed:', error);
    }
}

// Notificaciones Push
self.addEventListener('push', (event) => {
    console.log('[SW] Push received');

    const options = {
        body: event.data ? event.data.text() : 'Nuevo contenido disponible',
        icon: './assets/icons/icon-192x192.png',
        badge: './assets/icons/badge-72x72.png',
        vibrate: [200, 100, 200],
        tag: 'streaming-notification',
        requireInteraction: false,
        actions: [
            {
                action: 'view',
                title: 'Ver ahora',
                icon: './assets/icons/play-icon.png'
            },
            {
                action: 'close',
                title: 'Cerrar',
                icon: './assets/icons/close-icon.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Streaming Platform', options)
    );
});

// Manejo de clicks en notificaciones
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked');
    event.notification.close();

    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow('./')
        );
    }
});

console.log('[SW] Service Worker loaded');
