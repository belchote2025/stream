/**
 * Watch Party Manager
 * Gestiona la sincronización en tiempo real del reproductor y el chat
 */

class WatchPartyManager {
    constructor(partyCode, baseUrl) {
        this.partyCode = partyCode;
        this.baseUrl = baseUrl;
        this.partyId = null;
        this.isHost = false;
        this.player = null;
        this.syncInterval = null;
        this.statusInterval = null;
        this.lastSyncTime = 0;
        this.lastSyncEvent = null;
        this.isSyncing = false;
        this.participants = [];
        this.messages = [];
        this.lastMessageId = 0;
        
        this.init();
    }
    
    async init() {
        try {
            // Unirse al party
            await this.joinParty();
            
            // Inicializar reproductor
            await this.initPlayer();
            
            // Iniciar sincronización
            this.startSync();
            
            // Ocultar loading
            const loadingOverlay = document.getElementById('loadingOverlay');
            const container = document.getElementById('watchPartyContainer');
            if (loadingOverlay) loadingOverlay.style.display = 'none';
            if (container) container.style.display = 'block';
            
        } catch (error) {
            console.error('Error al inicializar Watch Party:', error);
            this.showError('Error al conectar al Watch Party: ' + (error.message || 'Error desconocido'));
        }
    }
    
    async joinParty() {
        try {
            const response = await fetch(`${this.baseUrl}/api/watch-party/join.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    party_code: this.partyCode
                })
            });
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Error al unirse al party');
            }
            
            this.partyId = data.data.party_id;
            this.isHost = data.data.is_host;
            
            // Actualizar UI
            this.updatePartyInfo(data.data);
            this.updateParticipants(data.data.participants);
            this.messages = data.data.messages || [];
            this.updateChat();
            
            return data.data;
        } catch (error) {
            console.error('Error al unirse al party:', error);
            throw error;
        }
    }
    
    async initPlayer() {
        try {
            // Obtener estado actual del party
            const status = await this.getStatus();
            
            if (!status || !status.data) {
                throw new Error('No se pudo obtener el estado del party');
            }
            
            const partyData = status.data;
            
            // Determinar URL del video
            let videoUrl = partyData.video_url || null;
            let torrentMagnet = partyData.torrent_magnet || null;
            
            if (!videoUrl && !torrentMagnet) {
                throw new Error('No hay video disponible para este contenido');
            }
            
            // Crear reproductor
            const container = document.getElementById('unifiedVideoContainer');
            if (!container) {
                throw new Error('Contenedor de video no encontrado');
            }
            
            this.player = new UnifiedVideoPlayer('unifiedVideoContainer', {
                autoplay: false,
                controls: true,
                startTime: partyData.current_time || 0,
                onProgress: (currentTime, totalDuration) => {
                    if (this.isHost && !this.isSyncing) {
                        this.syncPlayerState(currentTime, totalDuration, this.player.isPlaying);
                    }
                },
                onPlay: () => {
                    if (this.isHost) {
                        this.syncEvent('play', this.player.currentTime || 0);
                    }
                },
                onPause: () => {
                    if (this.isHost) {
                        this.syncEvent('pause', this.player.currentTime || 0);
                    }
                },
                onError: (error) => {
                    console.error('Error en el reproductor:', error);
                    this.showError('Error en el reproductor: ' + (error.message || 'Error desconocido'));
                }
            });
            
            // Cargar video
            const urlToLoad = torrentMagnet || videoUrl;
            await this.player.loadVideo(urlToLoad, torrentMagnet ? 'torrent' : null);
            
            // Si no es el host, sincronizar con el estado del host
            if (!this.isHost) {
                this.syncToHostState(partyData.current_time, partyData.is_playing);
            } else {
                // Si es el host, establecer el tiempo inicial
                if (partyData.current_time > 0) {
                    setTimeout(() => {
                        if (this.player && this.player.seek) {
                            this.player.seek(partyData.current_time);
                        }
                    }, 1000);
                }
            }
            
        } catch (error) {
            console.error('Error al inicializar reproductor:', error);
            throw error;
        }
    }
    
    async getStatus() {
        try {
            const response = await fetch(`${this.baseUrl}/api/watch-party/status.php?party_id=${this.partyId}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error al obtener estado:', error);
            return null;
        }
    }
    
    startSync() {
        // Sincronizar estado cada 2 segundos
        this.statusInterval = setInterval(async () => {
            try {
                const status = await this.getStatus();
                if (status && status.success) {
                    this.updateParticipants(status.data.participants);
                    this.updateChat(status.data.messages);
                    
                    // Si no es el host, sincronizar con el estado del host
                    if (!this.isHost && status.data) {
                        const hostState = {
                            current_time: status.data.current_time,
                            is_playing: status.data.is_playing
                        };
                        
                        // Solo sincronizar si el estado cambió significativamente
                        const timeDiff = Math.abs((this.player?.currentTime || 0) - hostState.current_time);
                        if (timeDiff > 2) {
                            this.syncToHostState(hostState.current_time, hostState.is_playing);
                        }
                        
                        // Sincronizar play/pause
                        if (this.player) {
                            if (hostState.is_playing && !this.player.isPlaying) {
                                this.player.play();
                            } else if (!hostState.is_playing && this.player.isPlaying) {
                                this.player.pause();
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Error en sincronización:', error);
            }
        }, 2000);
    }
    
    async syncEvent(eventType, currentTime) {
        if (!this.isHost || !this.partyId) return;
        
        try {
            this.isSyncing = true;
            
            await fetch(`${this.baseUrl}/api/watch-party/sync.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    party_id: this.partyId,
                    event_type: eventType,
                    current_time: currentTime,
                    is_playing: this.player?.isPlaying || false
                })
            });
            
            this.lastSyncTime = currentTime;
            this.lastSyncEvent = eventType;
            this.updateSyncStatus('synced');
            
        } catch (error) {
            console.error('Error al sincronizar evento:', error);
            this.updateSyncStatus('error');
        } finally {
            setTimeout(() => {
                this.isSyncing = false;
            }, 500);
        }
    }
    
    async syncPlayerState(currentTime, totalDuration, isPlaying) {
        if (!this.isHost || !this.partyId) return;
        
        // Solo sincronizar cada 5 segundos para no saturar
        const now = Date.now();
        if (now - this.lastSyncTime < 5000) return;
        
        try {
            await fetch(`${this.baseUrl}/api/watch-party/sync.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    party_id: this.partyId,
                    event_type: 'seek',
                    current_time: currentTime,
                    is_playing: isPlaying
                })
            });
            
            this.lastSyncTime = now;
            this.updateSyncStatus('synced');
            
        } catch (error) {
            console.error('Error al sincronizar estado:', error);
            this.updateSyncStatus('error');
        }
    }
    
    syncToHostState(currentTime, isPlaying) {
        if (!this.player || this.isHost) return;
        
        try {
            this.isSyncing = true;
            this.updateSyncStatus('syncing');
            
            // Sincronizar tiempo
            if (Math.abs((this.player.currentTime || 0) - currentTime) > 2) {
                if (this.player.seek) {
                    this.player.seek(currentTime);
                }
            }
            
            // Sincronizar play/pause
            if (isPlaying && !this.player.isPlaying) {
                this.player.play();
            } else if (!isPlaying && this.player.isPlaying) {
                this.player.pause();
            }
            
            setTimeout(() => {
                this.isSyncing = false;
                this.updateSyncStatus('synced');
            }, 1000);
            
        } catch (error) {
            console.error('Error al sincronizar con host:', error);
            this.updateSyncStatus('error');
        }
    }
    
    async sendChatMessage(message) {
        if (!this.partyId || !message.trim()) return;
        
        try {
            const response = await fetch(`${this.baseUrl}/api/watch-party/chat.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    party_id: this.partyId,
                    message: message.trim()
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // El mensaje se añadirá en la próxima actualización de estado
                // Por ahora, añadirlo localmente para feedback inmediato
                this.messages.push(data.data);
                this.updateChat();
            } else {
                console.error('Error al enviar mensaje:', data.error);
            }
            
        } catch (error) {
            console.error('Error al enviar mensaje:', error);
        }
    }
    
    updatePartyInfo(data) {
        const partyNameEl = document.getElementById('partyName');
        const contentTitleEl = document.getElementById('contentTitle');
        const partyCodeEl = document.getElementById('partyCode');
        
        if (partyNameEl) partyNameEl.textContent = data.party_name || 'Watch Party';
        if (contentTitleEl) {
            let title = data.content_title || '';
            if (data.episode_title) {
                title += ' - ' + data.episode_title;
            }
            contentTitleEl.textContent = title;
        }
        if (partyCodeEl) partyCodeEl.textContent = data.party_code || this.partyCode;
    }
    
    updateParticipants(participants) {
        this.participants = participants || [];
        
        const listEl = document.getElementById('participantsList');
        const countEl = document.getElementById('participantsCount');
        
        if (countEl) {
            countEl.textContent = `${this.participants.length} participante${this.participants.length !== 1 ? 's' : ''}`;
        }
        
        if (listEl) {
            listEl.innerHTML = '';
            this.participants.forEach(participant => {
                const avatar = document.createElement('img');
                avatar.className = 'participant-avatar' + (participant.is_host ? ' host' : '');
                avatar.src = participant.avatar_url || `${this.baseUrl}/assets/img/default-avatar.png`;
                avatar.alt = participant.username;
                avatar.title = participant.username + (participant.is_host ? ' (Host)' : '');
                listEl.appendChild(avatar);
            });
        }
    }
    
    updateChat(messages) {
        if (messages) {
            // Actualizar solo si hay mensajes nuevos
            const lastId = this.messages.length > 0 ? this.messages[this.messages.length - 1].id : 0;
            const newMessages = messages.filter(m => m.id > lastId);
            if (newMessages.length > 0) {
                this.messages = messages;
            }
        }
        
        const messagesEl = document.getElementById('chatMessages');
        if (!messagesEl) return;
        
        messagesEl.innerHTML = '';
        
        this.messages.forEach(message => {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            const header = document.createElement('div');
            header.className = 'chat-message-header';
            
            const avatar = document.createElement('img');
            avatar.className = 'chat-message-avatar';
            avatar.src = message.avatar_url || `${this.baseUrl}/assets/img/default-avatar.png`;
            avatar.alt = message.username;
            
            const username = document.createElement('span');
            username.className = 'chat-message-username';
            username.textContent = message.username;
            
            const time = document.createElement('span');
            time.className = 'chat-message-time';
            time.textContent = this.formatTime(message.created_at);
            
            header.appendChild(avatar);
            header.appendChild(username);
            header.appendChild(time);
            
            const text = document.createElement('div');
            text.className = 'chat-message-text';
            text.textContent = message.message;
            
            messageDiv.appendChild(header);
            messageDiv.appendChild(text);
            messagesEl.appendChild(messageDiv);
        });
        
        // Scroll al final
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    
    updateSyncStatus(status) {
        const statusEl = document.getElementById('syncStatus');
        if (!statusEl) return;
        
        statusEl.className = 'sync-status';
        
        switch (status) {
            case 'synced':
                statusEl.innerHTML = '<i class="fas fa-circle" style="color: #0f0;"></i><span>Sincronizado</span>';
                break;
            case 'syncing':
                statusEl.className += ' syncing';
                statusEl.innerHTML = '<i class="fas fa-circle" style="color: #ff0;"></i><span>Sincronizando...</span>';
                break;
            case 'error':
                statusEl.className += ' error';
                statusEl.innerHTML = '<i class="fas fa-circle" style="color: #f00;"></i><span>Error de sincronización</span>';
                break;
        }
    }
    
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Ahora';
        if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
        return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
    }
    
    showError(message) {
        const errorEl = document.getElementById('errorMessage');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
        
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.querySelector('p').textContent = message;
        }
    }
    
    async leave() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
        }
        
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
        }
        
        try {
            if (this.partyId) {
                await fetch(`${this.baseUrl}/api/watch-party/leave.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        party_id: this.partyId
                    })
                });
            }
        } catch (error) {
            console.error('Error al abandonar party:', error);
        }
        
        window.location.href = this.baseUrl + '/';
    }
    
    destroy() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
        }
        
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
        }
        
        if (this.player && typeof this.player.destroy === 'function') {
            this.player.destroy();
        }
    }
}

// Limpiar al cerrar la página
window.addEventListener('beforeunload', function() {
    if (window.watchParty) {
        window.watchParty.destroy();
    }
});
