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

<!-- Sección de Búsqueda Manual de Enlaces -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-search me-2"></i>Buscar Enlaces Manualmente</span>
    </div>
    <div class="card-body">
        <form id="searchStreamsForm">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="contentType" class="form-label">Tipo de Contenido</label>
                    <select class="form-select" id="contentType" required>
                        <option value="all">Todos</option>
                        <option value="movie">Películas</option>
                        <option value="series">Series</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="contentSelect" class="form-label">Contenido</label>
                    <select class="form-select" id="contentSelect" required>
                        <option value="">Cargando contenidos...</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-secondary w-100" id="refreshContentList">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            
            <div class="row" id="seriesFields" style="display: none;">
                <div class="col-md-6 mb-3">
                    <label for="seasonNumber" class="form-label">Temporada</label>
                    <input type="number" class="form-control" id="seasonNumber" min="1" value="1">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="episodeNumber" class="form-label">Episodio</label>
                    <input type="number" class="form-control" id="episodeNumber" min="1" value="1">
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Buscar Enlaces
                </button>
            </div>
        </form>
        
        <div id="streamsResults" class="mt-4" style="display: none;">
            <hr>
            <h5><i class="fas fa-link me-2"></i>Resultados</h5>
            <div id="streamsList" class="mt-3"></div>
        </div>
    </div>
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
            
            // Cargar lista de contenidos al iniciar
            loadContentList();
            
            // Manejar cambio de tipo de contenido
            $('#contentType').on('change', function() {
                loadContentList();
            });
            
            // Manejar cambio de contenido seleccionado
            $('#contentSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const contentType = selectedOption.data('type');
                
                if (contentType === 'series') {
                    $('#seriesFields').show();
                } else {
                    $('#seriesFields').hide();
                }
            });
            
            // Manejar actualización de lista de contenidos
            $('#refreshContentList').on('click', function() {
                loadContentList();
            });
            
            // Manejar búsqueda de enlaces
            $('#searchStreamsForm').on('submit', function(e) {
                e.preventDefault();
                searchStreams();
            });
            
            // Función para cargar lista de contenidos
            function loadContentList() {
                const type = $('#contentType').val();
                const $select = $('#contentSelect');
                
                $select.html('<option value="">Cargando...</option>');
                
                $.ajax({
                    url: '../api/addons/get-content-list.php',
                    method: 'GET',
                    data: { type: type, limit: 200 },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            $select.html('<option value="">Selecciona un contenido</option>');
                            response.data.forEach(function(content) {
                                $select.append(
                                    $('<option></option>')
                                        .attr('value', content.id)
                                        .attr('data-type', content.type)
                                        .text(content.display)
                                );
                            });
                        } else {
                            $select.html('<option value="">No hay contenidos disponibles</option>');
                        }
                    },
                    error: function() {
                        $select.html('<option value="">Error al cargar contenidos</option>');
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron cargar los contenidos'
                        });
                    }
                });
            }
            
            // Función para buscar enlaces
            function searchStreams() {
                const contentId = $('#contentSelect').val();
                const selectedOption = $('#contentSelect').find('option:selected');
                const contentType = selectedOption.data('type') === 'series' ? 'series' : 'movie';
                const season = contentType === 'series' ? $('#seasonNumber').val() : null;
                const episode = contentType === 'series' ? $('#episodeNumber').val() : null;
                
                if (!contentId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'Por favor, selecciona un contenido'
                    });
                    return;
                }
                
                // Mostrar loading
                Swal.fire({
                    title: 'Buscando enlaces...',
                    html: 'Por favor espera mientras buscamos enlaces en los addons',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Construir URL
                let url = `../api/addons/streams.php?content_id=${contentId}&content_type=${contentType}`;
                if (contentType === 'series' && season && episode) {
                    url += `&season=${season}&episode=${episode}`;
                }
                
                $.ajax({
                    url: url,
                    method: 'GET',
                    dataType: 'json',
                    timeout: 30000, // 30 segundos
                    success: function(response) {
                        Swal.close();
                        
                        if (response.success && response.data.streams && response.data.streams.length > 0) {
                            displayStreams(response.data);
                        } else {
                            // Mostrar información más detallada sobre por qué no se encontraron enlaces
                            let message = 'No se encontraron enlaces para este contenido.\n\n';
                            
                            if (response.data && response.data.addon_details) {
                                const details = response.data.addon_details;
                                if (details._info) {
                                    message += 'Addons activos: ' + details._info.total_active + '\n';
                                    if (details._info.active_addons && details._info.active_addons.length > 0) {
                                        message += 'Addons verificados:\n';
                                        details._info.active_addons.forEach(function(addon) {
                                            message += '  - ' + addon.name + (addon.hasGetStreams ? ' ✓' : ' ✗ (sin método onGetStreams)') + '\n';
                                        });
                                    }
                                }
                                
                                // Verificar configuración del addon
                                message += '\nSugerencias:\n';
                                message += '1. Verifica que el addon Balandro esté activo\n';
                                message += '2. Configura el addon (enable_vidsrc, enable_upstream)\n';
                                message += '3. El contenido necesita tener IMDb ID para Vidsrc\n';
                                message += '4. Verifica que el contenido tenga video_url o torrent_magnet';
                            }
                            
                            Swal.fire({
                                icon: 'info',
                                title: 'Sin resultados',
                                text: message,
                                width: '600px'
                            });
                            $('#streamsResults').hide();
                        }
                    },
                    error: function(xhr) {
                        Swal.close();
                        
                        let errorMsg = 'Error al buscar enlaces';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMsg = response.error;
                            }
                        } catch (e) {
                            errorMsg = xhr.responseText || errorMsg;
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMsg
                        });
                        $('#streamsResults').hide();
                    }
                });
            }
            
            // Función para mostrar los enlaces encontrados
            function displayStreams(data) {
                const $results = $('#streamsResults');
                const $list = $('#streamsList');
                
                const contentId = data.content_id;
                const contentType = data.content_type;
                const season = data.season || null;
                const episode = data.episode || null;
                
                let html = `<div class="alert alert-info">
                    <strong>Contenido:</strong> ${$('#contentSelect option:selected').text()}<br>
                    <strong>Enlaces encontrados:</strong> ${data.total}
                </div>`;
                
                html += '<div class="list-group">';
                
                data.streams.forEach(function(stream, index) {
                    const qualityBadge = stream.quality ? `<span class="badge bg-primary">${stream.quality}</span>` : '';
                    const typeBadge = stream.type ? `<span class="badge bg-secondary">${stream.type}</span>` : '';
                    const providerBadge = stream.addon ? `<span class="badge bg-success">${stream.addon}</span>` : '';
                    const streamId = `stream-${contentId}-${index}`;
                    
                    html += `<div class="list-group-item" id="${streamId}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Enlace ${index + 1}</h6>
                                <p class="mb-1 text-break"><small>${stream.url || 'URL no disponible'}</small></p>
                                <div class="mt-2">
                                    ${qualityBadge} ${typeBadge} ${providerBadge}
                                </div>
                                <div class="mt-2 stream-status" id="status-${streamId}" style="display: none;">
                                    <span class="badge bg-info">Verificando...</span>
                                </div>
                            </div>
                            <div class="ms-3 d-flex flex-column gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('${stream.url || ''}')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                                ${stream.url ? `
                                <a href="${stream.url}" target="_blank" class="btn btn-sm btn-outline-success" 
                                   onclick="verifyAndSaveStream(event, ${contentId}, '${contentType}', '${stream.url.replace(/'/g, "\\'")}', '${stream.type || 'direct'}', ${season || 'null'}, ${episode || 'null'}, '${streamId}')">
                                    <i class="fas fa-external-link-alt"></i> Abrir y Guardar
                                </a>
                                <button class="btn btn-sm btn-outline-warning" 
                                        onclick="saveStream(${contentId}, '${contentType}', '${stream.url.replace(/'/g, "\\'")}', '${stream.type || 'direct'}', ${season || 'null'}, ${episode || 'null'}, '${streamId}')">
                                    <i class="fas fa-save"></i> Guardar
                                </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>`;
                });
                
                html += '</div>';
                
                $list.html(html);
                $results.show();
            }
            
            // Función para verificar y guardar el stream al abrir
            window.verifyAndSaveStream = function(event, contentId, contentType, streamUrl, streamType, season, episode, streamId) {
                event.preventDefault();
                
                // Abrir en nueva pestaña
                window.open(streamUrl, '_blank');
                
                // Guardar automáticamente después de abrir
                saveStream(contentId, contentType, streamUrl, streamType, season, episode, streamId, true);
            };
            
            // Función para guardar el stream en el contenido
            window.saveStream = function(contentId, contentType, streamUrl, streamType, season, episode, streamId, autoSave = false) {
                const $status = $(`#status-${streamId}`);
                $status.show().html('<span class="badge bg-warning"><i class="fas fa-spinner fa-spin"></i> Verificando y guardando...</span>');
                
                const data = {
                    content_id: contentId,
                    content_type: contentType,
                    stream_url: streamUrl,
                    stream_type: streamType,
                    verify_url: true
                };
                
                if (season !== null && episode !== null) {
                    data.season = season;
                    data.episode = episode;
                }
                
                $.ajax({
                    url: '../api/addons/save-stream.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    dataType: 'json',
                    timeout: 15000,
                    success: function(response) {
                        if (response.success) {
                            $status.html(`<span class="badge bg-success"><i class="fas fa-check"></i> ${response.message || 'Guardado correctamente'}</span>`);
                            
                            if (!autoSave) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Guardado!',
                                    text: response.message || 'El enlace se ha guardado correctamente en el contenido.',
                                    timer: 3000,
                                    showConfirmButton: false
                                });
                            } else {
                                // Mostrar notificación toast si es guardado automático
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Enlace guardado',
                                    text: 'El enlace se ha añadido automáticamente al contenido.',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000
                                });
                            }
                        } else {
                            $status.html(`<span class="badge bg-danger"><i class="fas fa-times"></i> Error: ${response.error || 'Error desconocido'}</span>`);
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.error || 'No se pudo guardar el enlace'
                            });
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Error al guardar el enlace';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMsg = response.error;
                            }
                        } catch (e) {
                            errorMsg = xhr.responseText || errorMsg;
                        }
                        
                        $status.html(`<span class="badge bg-danger"><i class="fas fa-times"></i> ${errorMsg}</span>`);
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: errorMsg
                        });
                    }
                });
            };
            
            // Función global para copiar al portapapeles
            window.copyToClipboard = function(text) {
                if (!text) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: 'No hay URL para copiar'
                    });
                    return;
                }
                
                navigator.clipboard.writeText(text).then(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copiado',
                        text: 'URL copiada al portapapeles',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(function() {
                    // Fallback para navegadores antiguos
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Copiado',
                        text: 'URL copiada al portapapeles',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            };
    
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
