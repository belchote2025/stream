/**
 * Script de diagnóstico para reproductor de torrents
 * Detecta y reporta errores específicos de WebTorrent
 */

(function() {
    'use strict';
    
    const TorrentDebugger = {
        errors: [],
        warnings: [],
        info: [],
        startTime: Date.now(),
        
        init: function() {
            console.log('🔍 Iniciando diagnóstico de torrent...');
            this.setupErrorHandlers();
            this.checkWebTorrent();
            this.checkMagnetLink();
            this.checkPlayerContainer();
            this.monitorWebTorrent();
            this.generateReport();
        },
        
        setupErrorHandlers: function() {
            // Capturar errores de WebTorrent
            const originalError = console.error;
            const self = this;
            
            console.error = function(...args) {
                const message = args.join(' ');
                if (message.includes('webtorrent') || 
                    message.includes('torrent') || 
                    message.includes('magnet') ||
                    message.includes('peer') ||
                    message.includes('tracker')) {
                    self.errors.push({
                        type: 'WebTorrent Error',
                        message: message,
                        timestamp: new Date().toISOString(),
                        stack: new Error().stack
                    });
                }
                originalError.apply(console, args);
            };
            
            // Capturar errores no manejados
            window.addEventListener('error', (e) => {
                if (e.message && (
                    e.message.includes('webtorrent') ||
                    e.message.includes('torrent') ||
                    e.message.includes('magnet') ||
                    e.message.includes('peer')
                )) {
                    this.errors.push({
                        type: 'Unhandled Error',
                        message: e.message,
                        filename: e.filename,
                        lineno: e.lineno,
                        colno: e.colno,
                        timestamp: new Date().toISOString()
                    });
                }
            });
            
            // Capturar promesas rechazadas
            window.addEventListener('unhandledrejection', (e) => {
                const reason = e.reason?.toString() || '';
                if (reason.includes('webtorrent') ||
                    reason.includes('torrent') ||
                    reason.includes('magnet') ||
                    reason.includes('peer')) {
                    this.errors.push({
                        type: 'Unhandled Promise Rejection',
                        message: reason,
                        timestamp: new Date().toISOString()
                    });
                }
            });
        },
        
        checkWebTorrent: function() {
            console.log('📦 Verificando WebTorrent...');
            
            if (typeof WebTorrent === 'undefined') {
                this.errors.push({
                    type: 'WebTorrent Missing',
                    message: 'WebTorrent no está cargado',
                    timestamp: new Date().toISOString()
                });
                console.error('❌ WebTorrent no está disponible');
                return false;
            }
            
            this.info.push({
                type: 'WebTorrent Status',
                message: 'WebTorrent está disponible',
                version: WebTorrent.WEBTORRENT_VERSION || 'unknown'
            });
            console.log('✅ WebTorrent disponible');
            return true;
        },
        
        checkMagnetLink: function() {
            console.log('🔗 Verificando enlace magnet...');
            
            const urlParams = new URLSearchParams(window.location.search);
            const magnet = urlParams.get('magnet') || 
                          urlParams.get('torrent') ||
                          (window.torrentMagnet && window.torrentMagnet);
            
            if (!magnet) {
                this.warnings.push({
                    type: 'Magnet Link Missing',
                    message: 'No se encontró enlace magnet en la URL',
                    timestamp: new Date().toISOString()
                });
                console.warn('⚠️ No se encontró enlace magnet');
                return false;
            }
            
            if (!magnet.startsWith('magnet:?')) {
                this.errors.push({
                    type: 'Invalid Magnet Link',
                    message: 'El enlace magnet no tiene el formato correcto',
                    magnet: magnet.substring(0, 100),
                    timestamp: new Date().toISOString()
                });
                console.error('❌ Enlace magnet inválido');
                return false;
            }
            
            this.info.push({
                type: 'Magnet Link',
                message: 'Enlace magnet encontrado',
                magnet: magnet.substring(0, 100) + '...',
                timestamp: new Date().toISOString()
            });
            console.log('✅ Enlace magnet válido');
            return true;
        },
        
        checkPlayerContainer: function() {
            console.log('📺 Verificando contenedor del reproductor...');
            
            const containers = [
                'unifiedVideoContainer',
                'videoPlayer',
                'video-player-container'
            ];
            
            let found = false;
            for (const id of containers) {
                const container = document.getElementById(id);
                if (container) {
                    this.info.push({
                        type: 'Player Container',
                        message: `Contenedor encontrado: ${id}`,
                        timestamp: new Date().toISOString()
                    });
                    console.log(`✅ Contenedor encontrado: ${id}`);
                    found = true;
                    break;
                }
            }
            
            if (!found) {
                this.errors.push({
                    type: 'Player Container Missing',
                    message: 'No se encontró el contenedor del reproductor',
                    searched: containers,
                    timestamp: new Date().toISOString()
                });
                console.error('❌ No se encontró el contenedor del reproductor');
            }
            
            return found;
        },
        
        monitorWebTorrent: function() {
            console.log('👀 Monitoreando actividad de WebTorrent...');
            
            if (typeof WebTorrent === 'undefined') {
                return;
            }
            
            // Rastrear torrents ya monitoreados para evitar listeners duplicados
            const monitoredTorrents = new Set();
            
            // Verificar si hay instancias de WebTorrent activas
            const checkInterval = setInterval(() => {
                const client = window.webtorrentClient || window.client;
                
                if (client && client.torrents) {
                    const torrents = client.torrents;
                    
                    if (torrents.length > 0) {
                        this.info.push({
                            type: 'WebTorrent Activity',
                            message: `Torrents activos: ${torrents.length}`,
                            timestamp: new Date().toISOString()
                        });
                    }
                    
                    torrents.forEach((torrent, index) => {
                        const torrentId = torrent.infoHash || torrent.magnetURI || index;
                        
                        // Solo agregar listeners una vez por torrent
                        if (!monitoredTorrents.has(torrentId)) {
                            monitoredTorrents.add(torrentId);
                            
                            // Configurar max listeners para evitar warnings
                            if (torrent.setMaxListeners) {
                                torrent.setMaxListeners(20);
                            }
                            
                            // Verificar errores del torrent (solo una vez)
                            torrent.once('error', (err) => {
                                this.errors.push({
                                    type: 'Torrent Error',
                                    message: err.message || err.toString(),
                                    torrentName: torrent.name,
                                    timestamp: new Date().toISOString()
                                });
                                console.error('❌ Error en torrent:', err);
                            });
                            
                            // Verificar cuando el torrent está listo (solo una vez)
                            torrent.once('ready', () => {
                                this.info.push({
                                    type: 'Torrent Ready',
                                    message: 'Torrent listo para reproducir',
                                    torrentName: torrent.name,
                                    timestamp: new Date().toISOString()
                                });
                                console.log('✅ Torrent listo:', torrent.name);
                            });
                            
                            // Monitorear progreso del torrent
                            torrent.on('download', () => {
                                const progress = (torrent.progress * 100).toFixed(2);
                                if (progress > 0 && progress % 10 === 0) { // Log cada 10%
                                    console.log(`📥 Progreso del torrent: ${progress}%`);
                                }
                            });
                        }
                        
                        // Mostrar información del torrent (cada vez)
                        const info = {
                            type: 'Torrent Info',
                            index: index,
                            name: torrent.name || 'Sin nombre',
                            progress: (torrent.progress * 100).toFixed(2) + '%',
                            downloadSpeed: this.formatBytes(torrent.downloadSpeed) + '/s',
                            uploadSpeed: this.formatBytes(torrent.uploadSpeed) + '/s',
                            peers: torrent.numPeers || 0,
                            ready: torrent.ready || false,
                            infoHash: torrent.infoHash || 'N/A',
                            timestamp: new Date().toISOString()
                        };
                        
                        // Solo loggear si hay cambios significativos
                        if (torrent.progress > 0 || torrent.numPeers > 0 || torrent.ready) {
                            console.log(`📊 Torrent ${index}:`, info);
                        }
                        
                        // Verificar si hay archivos de video
                        if (torrent.files && torrent.files.length > 0) {
                            torrent.files.forEach((file, fileIndex) => {
                                if (file.name.match(/\.(mp4|mkv|avi|webm|m4v)$/i)) {
                                    console.log(`🎬 Archivo de video encontrado: ${file.name} (${this.formatBytes(file.length)})`);
                                    this.info.push({
                                        type: 'Video File',
                                        name: file.name,
                                        size: this.formatBytes(file.length),
                                        index: fileIndex,
                                        timestamp: new Date().toISOString()
                                    });
                                }
                            });
                        } else if (torrent.ready) {
                            console.warn('⚠️ Torrent listo pero sin archivos detectados');
                        }
                        
                        // Diagnosticar por qué no descarga
                        if (!torrent.ready && torrent.numPeers === 0 && Date.now() - this.startTime > 10000) {
                            console.warn('⚠️ Torrent sin peers después de 10 segundos. Posibles causas:');
                            console.warn('  - No hay seeders disponibles');
                            console.warn('  - Problemas de conexión con trackers');
                            console.warn('  - Firewall bloqueando conexiones P2P');
                        }
                    });
                    
                    // Si hay torrents pero no se está reproduciendo después de 30 segundos
                    if (torrents.length > 0 && Date.now() - this.startTime > 30000) {
                        const hasVideo = torrents.some(t => 
                            t.files && t.files.some(f => 
                                f.name.match(/\.(mp4|mkv|avi|webm|m4v)$/i)
                            )
                        );
                        
                        if (hasVideo) {
                            this.warnings.push({
                                type: 'Playback Delay',
                                message: 'Torrent cargado pero no se está reproduciendo',
                                elapsed: Math.round((Date.now() - this.startTime) / 1000) + 's',
                                timestamp: new Date().toISOString()
                            });
                            console.warn('⚠️ Torrent cargado pero no se está reproduciendo');
                        }
                    }
                } else {
                    // Si no hay cliente después de 10 segundos
                    if (Date.now() - this.startTime > 10000) {
                        this.warnings.push({
                            type: 'No WebTorrent Client',
                            message: 'No se detectó cliente de WebTorrent activo',
                            elapsed: Math.round((Date.now() - this.startTime) / 1000) + 's',
                            timestamp: new Date().toISOString()
                        });
                        console.warn('⚠️ No se detectó cliente de WebTorrent');
                        clearInterval(checkInterval);
                    }
                }
            }, 5000); // Verificar cada 5 segundos
            
            // Limpiar después de 2 minutos
            setTimeout(() => {
                clearInterval(checkInterval);
            }, 120000);
        },
        
        formatBytes: function(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        generateReport: function() {
            // Generar reporte después de 15 segundos
            setTimeout(() => {
                const report = {
                    timestamp: new Date().toISOString(),
                    elapsed: Math.round((Date.now() - this.startTime) / 1000) + 's',
                    errors: this.errors,
                    warnings: this.warnings,
                    info: this.info,
                    summary: {
                        totalErrors: this.errors.length,
                        totalWarnings: this.warnings.length,
                        totalInfo: this.info.length
                    }
                };
                
                console.group('📋 Reporte de Diagnóstico de Torrent');
                console.log('⏱️ Tiempo transcurrido:', report.elapsed);
                console.log('❌ Errores:', report.summary.totalErrors);
                console.log('⚠️ Advertencias:', report.summary.totalWarnings);
                console.log('ℹ️ Información:', report.summary.totalInfo);
                
                if (report.errors.length > 0) {
                    console.group('❌ Errores Detectados');
                    report.errors.forEach((error, index) => {
                        console.error(`${index + 1}. [${error.type}]`, error.message);
                        if (error.stack) {
                            console.trace(error.stack);
                        }
                    });
                    console.groupEnd();
                }
                
                if (report.warnings.length > 0) {
                    console.group('⚠️ Advertencias');
                    report.warnings.forEach((warning, index) => {
                        console.warn(`${index + 1}. [${warning.type}]`, warning.message);
                    });
                    console.groupEnd();
                }
                
                if (report.info.length > 0) {
                    console.group('ℹ️ Información');
                    report.info.forEach((info, index) => {
                        console.log(`${index + 1}. [${info.type}]`, info.message);
                    });
                    console.groupEnd();
                }
                
                console.groupEnd();
                
                // Guardar reporte en window para acceso externo
                window.torrentDebugReport = report;
                
                // Mostrar resumen visual si hay errores
                if (report.errors.length > 0) {
                    this.showVisualReport(report);
                }
            }, 15000);
        },
        
        showVisualReport: function(report) {
            const reportDiv = document.createElement('div');
            reportDiv.id = 'torrent-debug-report';
            reportDiv.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #1a1a1a;
                color: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.5);
                max-width: 400px;
                max-height: 500px;
                overflow-y: auto;
                z-index: 10000;
                font-family: monospace;
                font-size: 12px;
            `;
            
            let html = '<h3 style="margin-top:0;color:#e50914;">🔍 Diagnóstico de Torrent</h3>';
            html += `<p><strong>Errores:</strong> ${report.summary.totalErrors}</p>`;
            html += `<p><strong>Advertencias:</strong> ${report.summary.totalWarnings}</p>`;
            
            if (report.errors.length > 0) {
                html += '<h4 style="color:#ff4444;">Errores:</h4><ul>';
                report.errors.forEach(error => {
                    html += `<li style="margin:5px 0;">[${error.type}] ${error.message}</li>`;
                });
                html += '</ul>';
            }
            
            if (report.warnings.length > 0) {
                html += '<h4 style="color:#ffaa00;">Advertencias:</h4><ul>';
                report.warnings.forEach(warning => {
                    html += `<li style="margin:5px 0;">[${warning.type}] ${warning.message}</li>`;
                });
                html += '</ul>';
            }
            
            html += '<button onclick="document.getElementById(\'torrent-debug-report\').remove()" style="margin-top:10px;padding:5px 10px;background:#e50914;color:#fff;border:none;border-radius:4px;cursor:pointer;">Cerrar</button>';
            
            reportDiv.innerHTML = html;
            document.body.appendChild(reportDiv);
        }
    };
    
    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => TorrentDebugger.init());
    } else {
        TorrentDebugger.init();
    }
    
    // Hacer disponible globalmente
    window.TorrentDebugger = TorrentDebugger;
})();

