<?php
// Evitar caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Incluir config primero para que configure la sesión correctamente
require_once __DIR__ . '/../includes/config.php';

// Incluir el archivo de autenticación
require_once __DIR__ . '/../includes/auth/AdminAuth.php';
$auth = new AdminAuth();

// Verificar autenticación
if (!$auth->isAuthenticated() || !$auth->hasRole('admin')) {
    // Guardar la URL actual para redirigir después del login
    $_SESSION['redirect_after_login'] = 'addons.php';
    header('Location: login.php');
    exit;
}

$pageTitle = 'Gestión de Addons';
include __DIR__ . '/includes/header.php';
// El header ya abre el contenedor .col-md-10 admin-content en la línea 157
// El contenido de esta página se inserta aquí, dentro del contenedor
?>

<style>
    .addon-card {
        margin-bottom: 20px;
        border: 1px solid rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
        background: white;
    }
    
    .addon-header {
        padding: 15px;
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .addon-title {
        font-size: 1.25rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .addon-version {
        font-size: 0.8rem;
        background-color: #e9ecef;
        padding: 2px 8px;
        border-radius: 10px;
    }
    
    .addon-body {
        padding: 15px;
    }
    
    .addon-description {
        color: #6c757d;
        margin-bottom: 15px;
    }
    
    .addon-footer {
        padding: 10px 15px;
        background-color: #f8f9fa;
        border-top: 1px solid rgba(0,0,0,0.1);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .spinner-border {
        width: 1.5rem;
        height: 1.5rem;
        border-width: 0.2em;
    }
</style>

<div class="d-flex justify-content-end align-items-center mb-4">
    <button class="btn btn-success me-2" id="installAddonBtn" data-bs-toggle="modal" data-bs-target="#installAddonModal">
        <i class="fas fa-plus me-1"></i> Instalar Addon
    </button>
    <button class="btn btn-primary" id="refreshAddons">
        <i class="fas fa-sync-alt me-1"></i> Actualizar
    </button>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plug me-2"></i>Addons Instalados</span>
    </div>
    <div class="card-body">
        <div id="addonsList">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando addons...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal para instalar addon -->
<div class="modal fade" id="installAddonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Instalar Addon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="installAddonForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="addonZip" class="form-label">Archivo ZIP del Addon</label>
                        <input type="file" class="form-control" id="addonZip" name="addon_zip" accept=".zip" required>
                        <small class="form-text text-muted">Selecciona un archivo ZIP que contenga el addon con su addon.json</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="installAddonSubmit">Instalar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Suprimir errores de extensiones del navegador (spoofer.js)
window.addEventListener('error', function(e) {
    if (e.message && (
        e.message.includes('spoofer.js') ||
        e.message.includes('An unexpected error occurred') ||
        (e.filename && e.filename.includes('spoofer.js'))
    )) {
        e.preventDefault();
        return false;
    }
}, true);

// Esperar a que jQuery esté disponible
(function() {
    function initAddons() {
        if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
            setTimeout(initAddons, 50);
            return;
        }
        
        // Usar jQuery directamente
        jQuery(document).ready(function($) {
            // Cargar addons al iniciar
            loadAddons();
    
            // Manejar clic en el botón de actualizar
            $('#refreshAddons').on('click', function() {
                loadAddons();
            });
            
            // Manejar instalación de addon
            $('#installAddonSubmit').on('click', function() {
                const form = $('#installAddonForm')[0];
                const formData = new FormData(form);
                
                if (!formData.get('addon_zip')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Por favor, selecciona un archivo ZIP'
                    });
                    return;
                }
                
                const $btn = $(this);
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Instalando...');
                
                $.ajax({
            url: '../api/addons/install.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Instalado!',
                        text: response.message || 'El addon se ha instalado correctamente',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    
                    $('#installAddonModal').modal('hide');
                    form.reset();
                    loadAddons();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al instalar el addon'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error al instalar el addon';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch (e) {
                    errorMsg = xhr.responseText || errorMsg;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg
                });
            },
                    complete: function() {
                        $btn.prop('disabled', false).html('Instalar');
                    }
                });
            });
            
            // Función para cargar los addons
            function loadAddons() {
                $('#addonsList').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando addons...</p>
                    </div>
                `);
                
                // Cargar addons desde la API
                $.ajax({
                    url: '../api/addons/list.php',
                    method: 'GET',
                    dataType: 'json',
                    timeout: 10000, // 10 segundos de timeout
                    success: function(response) {
                        if (response.status === 'success') {
                            // Añadir settingsUrl a cada addon si no existe
                            const addons = response.data.map(addon => {
                                if (!addon.settingsUrl) {
                                    addon.settingsUrl = `addons/${addon.id}/settings.php`;
                                }
                                return addon;
                            });
                            renderAddons(addons);
                        } else {
                            $('#addonsList').html(`
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error al cargar addons: ${response.message}
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error cargando addons:', error, status);
                        
                        let errorMessage = 'Error al conectar con el servidor. Por favor, recarga la página.';
                        
                        if (status === 'timeout') {
                            errorMessage = 'La petición tardó demasiado. Por favor, verifica tu conexión e intenta de nuevo.';
                        } else if (status === 'abort') {
                            errorMessage = 'La petición fue cancelada.';
                        } else {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    errorMessage = response.message;
                                }
                            } catch (e) {
                                if (xhr.status === 403) {
                                    errorMessage = 'No tienes permisos para acceder a esta sección. Por favor, inicia sesión.';
                                } else if (xhr.status === 401) {
                                    errorMessage = 'Sesión expirada. Por favor, recarga la página.';
                                } else if (xhr.status === 0) {
                                    errorMessage = 'No se pudo conectar con el servidor. Verifica tu conexión.';
                                }
                            }
                        }
                        
                        $('#addonsList').html(`
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${errorMessage}
                                <br><br>
                                <button class="btn btn-sm btn-primary" onclick="location.reload()">
                                    <i class="fas fa-redo me-1"></i> Recargar página
                                </button>
                            </div>
                        `);
                    }
                });
            }
            
            // Función para renderizar la lista de addons
            function renderAddons(addons) {
                if (addons.length === 0) {
                    $('#addonsList').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-puzzle-piece fa-3x text-muted mb-3"></i>
                            <h5>No se encontraron addons instalados</h5>
                            <p class="text-muted">Instala addons para extender la funcionalidad de tu plataforma.</p>
                        </div>
                    `);
                    return;
                }
                
                let html = '';
                
                addons.forEach(addon => {
            html += `
            <div class="addon-card" id="addon-${addon.id}">
                <div class="addon-header">
                    <h3 class="addon-title">
                        <i class="fas fa-puzzle-piece"></i>
                        ${addon.name}
                        <span class="addon-version">v${addon.version}</span>
                    </h3>
                    <div class="form-check form-switch">
                        <input class="form-check-input toggle-addon" 
                               type="checkbox" 
                               role="switch" 
                               data-addon-id="${addon.id}" 
                               ${addon.enabled ? 'checked' : ''}>
                    </div>
                </div>
                <div class="addon-body">
                    <p class="addon-description">${addon.description}</p>
                    <div class="text-muted small">
                        <i class="fas fa-user"></i> ${addon.author}
                    </div>
                </div>
                <div class="addon-footer">
                    ${addon.enabled ? `
                        <a href="${addon.settingsUrl}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                        <button class="btn btn-sm btn-outline-info" onclick="testAddon('${addon.id}')">
                            <i class="fas fa-vial"></i> Probar
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-outline-danger" onclick="uninstallAddon('${addon.id}')">
                        <i class="fas fa-trash"></i> Desinstalar
                    </button>
                </div>
            </div>
            `;
        });
        
                $('#addonsList').html(html);
                
                // Manejar el cambio en el interruptor de activación/desactivación
                $('.toggle-addon').on('change', function() {
                    const addonId = $(this).data('addon-id');
                    const isEnabled = $(this).is(':checked');
                    
                    toggleAddon(addonId, isEnabled);
                });
            }
            
            // Función para activar/desactivar un addon
            function toggleAddon(addonId, enable) {
                const $card = $(`#addon-${addonId}`);
                const $switch = $card.find('.toggle-addon');
                
                // Mostrar indicador de carga
                $switch.prop('disabled', true);
                
                // Realizar petición AJAX al servidor
                $.ajax({
            url: '../api/addons/manage.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                addon_id: addonId,
                action: enable ? 'enable' : 'disable'
            }),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Actualizar interfaz
                    if (enable) {
                        $card.find('.addon-footer').html(`
                            <a href="addons/${addonId}/settings.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-cog"></i> Configuración
                            </a>
                            <button class="btn btn-sm btn-outline-info" onclick="testAddon('${addonId}')">
                                <i class="fas fa-vial"></i> Probar
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="uninstallAddon('${addonId}')">
                                <i class="fas fa-trash"></i> Desinstalar
                            </button>
                        `);
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Listo!',
                            text: response.message || `El addon ${addonId} ha sido activado correctamente`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    } else {
                        $card.find('.addon-footer').html(`
                            <button class="btn btn-sm btn-outline-danger" onclick="uninstallAddon('${addonId}')">
                                <i class="fas fa-trash"></i> Desinstalar
                            </button>
                        `);
                        
                        Swal.fire({
                            icon: 'info',
                            title: 'Addon desactivado',
                            text: response.message || `El addon ${addonId} ha sido desactivado`,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    }
                } else {
                    // Revertir el cambio en caso de error
                    $switch.prop('checked', !enable);
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'No se pudo completar la operación. Inténtalo de nuevo.'
                    });
                }
                
                // Habilitar el interruptor nuevamente
                $switch.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                // Revertir el cambio en caso de error
                $switch.prop('checked', !enable);
                
                let errorMessage = 'No se pudo completar la operación. Inténtalo de nuevo.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMessage
                });
                
                    // Habilitar el interruptor nuevamente
                    $switch.prop('disabled', false);
                }
            });
            }
            
            // Función para probar addon
            window.testAddon = function(addonId) {
        Swal.fire({
            title: 'Probando addon...',
            html: 'Ejecutando pruebas del addon',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: `../api/addons/test.php?addon_id=${addonId}`,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    let html = '<div style="text-align: left;">';
                    if (response.data && response.data.tests) {
                        response.data.tests.forEach(test => {
                            const icon = test.passed ? '✅' : '❌';
                            html += `<p>${icon} <strong>${test.name}</strong>: ${test.message || ''}</p>`;
                        });
                    }
                    html += '</div>';
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Pruebas completadas',
                        html: html,
                        width: '600px'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al probar el addon'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo conectar con el servidor'
                });
            }
            });
            };
            
            // Función para desinstalar addon
            window.uninstallAddon = function(addonId) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: `Esto eliminará el addon "${addonId}" permanentemente`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, desinstalar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '../api/addons/manage.php',
                    method: 'DELETE',
                    contentType: 'application/json',
                    data: JSON.stringify({ addon_id: addonId }),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Desinstalado',
                                text: 'El addon ha sido desinstalado correctamente',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            loadAddons();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al desinstalar'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudo desinstalar el addon'
                        });
                    }
                });
                }
            });
            };
        });
    }
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAddons);
    } else {
        initAddons();
    }
})();
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>
