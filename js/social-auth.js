/**
 * Manejo de autenticación social (Google y Facebook)
 */

(function() {
    'use strict';
    
    const baseUrl = window.__APP_BASE_URL || window.location.origin;
    
    /**
     * Inicializar botones de autenticación social
     */
    function initSocialAuth() {
        // Botón de Google
        const googleBtn = document.querySelector('.btn-google');
        if (googleBtn) {
            googleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                handleGoogleAuth();
            });
        }
        
        // Botón de Facebook
        const facebookBtn = document.querySelector('.btn-facebook');
        if (facebookBtn) {
            facebookBtn.addEventListener('click', function(e) {
                e.preventDefault();
                handleFacebookAuth();
            });
        }
    }
    
    /**
     * Manejar autenticación con Google
     */
    function handleGoogleAuth() {
        // Opción 1: Usar Google Sign-In API (recomendado)
        if (window.gapi && window.gapi.auth2) {
            handleGoogleSignIn();
        } else {
            // Opción 2: Redirigir a endpoint OAuth
            window.location.href = baseUrl + '/api/auth/social/google.php';
        }
    }
    
    /**
     * Manejar Google Sign-In con la API
     */
    function handleGoogleSignIn() {
        const auth2 = gapi.auth2.getAuthInstance();
        
        auth2.signIn().then(function(googleUser) {
            const profile = googleUser.getBasicProfile();
            const idToken = googleUser.getAuthResponse().id_token;
            
            // Enviar token al servidor
            fetch(baseUrl + '/api/auth/social/google.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id_token: idToken,
                    email: profile.getEmail(),
                    name: profile.getName(),
                    sub: profile.getId()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect || baseUrl + '/';
                } else {
                    showError(data.error || 'Error al autenticar con Google');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('Error al comunicarse con el servidor');
            });
        }).catch(function(error) {
            console.error('Error en Google Sign-In:', error);
            // Si falla, intentar con redirección OAuth
            window.location.href = baseUrl + '/api/auth/social/google.php';
        });
    }
    
    /**
     * Manejar autenticación con Facebook
     */
    function handleFacebookAuth() {
        // Opción 1: Usar Facebook SDK (recomendado)
        if (window.FB) {
            handleFacebookSDK();
        } else {
            // Opción 2: Redirigir a endpoint OAuth
            window.location.href = baseUrl + '/api/auth/social/facebook.php';
        }
    }
    
    /**
     * Manejar Facebook Login con el SDK
     */
    function handleFacebookSDK() {
        window.FB.login(function(response) {
            if (response.authResponse) {
                // Obtener información del usuario
                window.FB.api('/me', {fields: 'name,email'}, function(userInfo) {
                    // Enviar token al servidor
                    fetch(baseUrl + '/api/auth/social/facebook.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            access_token: response.authResponse.accessToken,
                            id: userInfo.id,
                            email: userInfo.email,
                            name: userInfo.name
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect || baseUrl + '/';
                        } else {
                            showError(data.error || 'Error al autenticar con Facebook');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showError('Error al comunicarse con el servidor');
                    });
                });
            } else {
                console.error('Usuario canceló el login de Facebook');
            }
        }, {scope: 'email,public_profile'});
    }
    
    /**
     * Mostrar mensaje de error
     */
    function showError(message) {
        // Buscar contenedor de errores
        const errorContainer = document.querySelector('.alert-danger') || 
                              document.querySelector('.error-message');
        
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.style.display = 'block';
        } else {
            alert(message);
        }
    }
    
    /**
     * Cargar Google Sign-In API
     */
    function loadGoogleAPI() {
        if (window.gapi) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://apis.google.com/js/platform.js';
            script.onload = () => {
                gapi.load('auth2', () => {
                    // Configurar cliente (necesita GOOGLE_CLIENT_ID)
                    const clientId = getenv('GOOGLE_CLIENT_ID') || '';
                    if (clientId) {
                        gapi.auth2.init({
                            client_id: clientId
                        }).then(resolve).catch(reject);
                    } else {
                        resolve(); // Continuar sin SDK, usar OAuth redirect
                    }
                });
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Cargar Facebook SDK
     */
    function loadFacebookSDK() {
        if (window.FB) {
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://connect.facebook.net/es_ES/sdk.js';
            script.onload = () => {
                const appId = getenv('FACEBOOK_APP_ID') || '';
                if (appId) {
                    window.FB.init({
                        appId: appId,
                        cookie: true,
                        xfbml: true,
                        version: 'v18.0'
                    });
                }
                resolve();
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Obtener variable de entorno (simulado)
     */
    function getenv(key) {
        // En producción, esto vendría del servidor
        return window.__ENV && window.__ENV[key] ? window.__ENV[key] : '';
    }
    
    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initSocialAuth();
            // Cargar APIs de forma asíncrona (no bloqueante)
            loadGoogleAPI().catch(() => console.warn('Google API no disponible'));
            loadFacebookSDK().catch(() => console.warn('Facebook SDK no disponible'));
        });
    } else {
        initSocialAuth();
        loadGoogleAPI().catch(() => console.warn('Google API no disponible'));
        loadFacebookSDK().catch(() => console.warn('Facebook SDK no disponible'));
    }
    
    // Exportar funciones para uso global
    window.SocialAuth = {
        init: initSocialAuth,
        google: handleGoogleAuth,
        facebook: handleFacebookAuth
    };
    
})();


