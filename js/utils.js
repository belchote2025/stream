/**
 * Utility functions for the Streaming Platform
 */

/**
 * Helper to get the correct API URL respecting the base path
 * @param {string} endpoint - The API endpoint (e.g., '/api/content/recent.php')
 * @returns {string} The full URL
 */
function getApiUrl(endpoint) {
    // Ensure endpoint starts with /
    if (!endpoint.startsWith('/')) {
        endpoint = '/' + endpoint;
    }

    // Use the global base URL if defined (set in header.php)
    // Usar función getBaseUrl() si está disponible, o window.__APP_BASE_URL, o detectar automáticamente
    let baseUrl = '';
    if (typeof window !== 'undefined' && typeof window.getBaseUrl === 'function') {
        baseUrl = window.getBaseUrl();
    } else if (typeof window !== 'undefined' && window.__APP_BASE_URL) {
        baseUrl = window.__APP_BASE_URL;
    } else {
        // Detectar automáticamente desde location
        const pathname = window.location.pathname;
        if (pathname.includes('/streaming-platform/') || pathname.includes('/streaming-platform')) {
            baseUrl = window.location.origin + '/streaming-platform';
        } else {
            baseUrl = window.location.origin;
        }
    }

    return `${baseUrl}${endpoint}`;
}

/**
 * Helper to get the correct Asset URL
 * @param {string} path - The asset path (e.g., '/assets/img/logo.png')
 * @returns {string} The full URL
 */
function getAssetUrl(path) {
    return getApiUrl(path);
}

// Export functions if using modules, otherwise they are global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { getApiUrl, getAssetUrl };
}
