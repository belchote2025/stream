<?php
/**
 * Página de Watch Party - Ver contenido en grupo
 */

require_once __DIR__ . '/includes/config.php';

// Verificar autenticación
if (!isLoggedIn()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$partyCode = $_GET['code'] ?? null;
$partyCode = $partyCode ? strtoupper(trim($partyCode)) : null;

if (!$partyCode) {
    header('Location: /');
    exit;
}

$pageTitle = 'Watch Party - ' . SITE_NAME;
$baseUrl = rtrim(SITE_URL, '/');

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/unified-video-player.css">

<style>
.watch-party-page {
    padding-top: 70px;
    min-height: 100vh;
    background: #000;
    color: #fff;
}

.watch-party-container {
    max-width: 1800px;
    margin: 0 auto;
    padding: 2rem;
}

.watch-party-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.party-info h1 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.party-code {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1rem;
}

.party-code-label {
    color: #999;
    font-size: 0.9rem;
}

.party-code-value {
    font-size: 1.5rem;
    font-weight: bold;
    color: #e50914;
    letter-spacing: 2px;
    padding: 0.5rem 1rem;
    background: rgba(229, 9, 20, 0.1);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.party-code-value:hover {
    background: rgba(229, 9, 20, 0.2);
}

.copy-btn {
    padding: 0.5rem 1rem;
    background: #e50914;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.copy-btn:hover {
    background: #f40612;
}

.participants-section {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.participants-list {
    display: flex;
    gap: 0.5rem;
}

.participant-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #e50914;
    object-fit: cover;
}

.participant-avatar.host {
    border-color: #ffd700;
}

.participants-count {
    color: #999;
    font-size: 0.9rem;
}

.video-container-full {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: #000;
    margin-bottom: 2rem;
}

.video-container-full #unifiedVideoContainer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.chat-sidebar {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 2rem;
    margin-top: 2rem;
}

.chat-container {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 1.5rem;
    height: 500px;
    display: flex;
    flex-direction: column;
}

.chat-header {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 1rem;
    padding-right: 0.5rem;
}

.chat-message {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.chat-message-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.chat-message-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
}

.chat-message-username {
    font-weight: bold;
    color: #e50914;
}

.chat-message-time {
    color: #999;
    font-size: 0.8rem;
    margin-left: auto;
}

.chat-message-text {
    color: #fff;
    word-wrap: break-word;
}

.chat-input-container {
    display: flex;
    gap: 0.5rem;
}

.chat-input {
    flex: 1;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    color: #fff;
    font-size: 0.9rem;
}

.chat-input:focus {
    outline: none;
    border-color: #e50914;
}

.chat-send-btn {
    padding: 0.75rem 1.5rem;
    background: #e50914;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.chat-send-btn:hover {
    background: #f40612;
}

.party-controls {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

.leave-btn {
    padding: 0.75rem 1.5rem;
    background: transparent;
    color: #fff;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.leave-btn:hover {
    border-color: #e50914;
    color: #e50914;
}

.sync-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(0, 255, 0, 0.1);
    border-radius: 4px;
    color: #0f0;
    font-size: 0.9rem;
}

.sync-status.syncing {
    background: rgba(255, 255, 0, 0.1);
    color: #ff0;
}

.sync-status.error {
    background: rgba(255, 0, 0, 0.1);
    color: #f00;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
    color: #fff;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: #e50914;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.error-message {
    padding: 1rem;
    background: rgba(255, 0, 0, 0.1);
    border: 1px solid #f00;
    border-radius: 4px;
    color: #f00;
    margin-bottom: 1rem;
}

@media (max-width: 992px) {
    .chat-sidebar {
        grid-template-columns: 1fr;
    }
    
    .watch-party-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .participants-section {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .watch-party-container {
        padding: 1rem;
    }
    
    .party-code {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="watch-party-page">
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>Conectando al Watch Party...</p>
        </div>
    </div>

    <div class="watch-party-container" id="watchPartyContainer" style="display: none;">
        <div class="watch-party-header">
            <div class="party-info">
                <h1 id="partyName">Watch Party</h1>
                <p id="contentTitle" style="color: #999; margin: 0.5rem 0;">Cargando...</p>
                <div class="party-code">
                    <span class="party-code-label">Código:</span>
                    <span class="party-code-value" id="partyCode"><?php echo htmlspecialchars($partyCode); ?></span>
                    <button class="copy-btn" onclick="copyPartyCode()">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
            </div>
            <div class="participants-section">
                <div class="participants-list" id="participantsList"></div>
                <span class="participants-count" id="participantsCount">0 participantes</span>
            </div>
        </div>

        <div id="errorMessage" class="error-message" style="display: none;"></div>

        <div class="video-container-full">
            <div id="unifiedVideoContainer"></div>
        </div>

        <div class="party-controls">
            <div class="sync-status" id="syncStatus">
                <i class="fas fa-circle"></i>
                <span>Sincronizado</span>
            </div>
            <button class="leave-btn" onclick="leaveParty()">
                <i class="fas fa-sign-out-alt"></i> Abandonar Party
            </button>
        </div>

        <div class="chat-sidebar">
            <div></div>
            <div class="chat-container">
                <div class="chat-header">
                    <h3>Chat</h3>
                </div>
                <div class="chat-messages" id="chatMessages"></div>
                <div class="chat-input-container">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Escribe un mensaje..." maxlength="500">
                    <button class="chat-send-btn" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $baseUrl; ?>/js/player/config.js"></script>
<script src="<?php echo $baseUrl; ?>/js/player/main.js"></script>
<script src="<?php echo $baseUrl; ?>/js/watch-party.js?v=<?php echo time(); ?>"></script>

<script>
const BASE_URL = '<?php echo $baseUrl; ?>';
const PARTY_CODE = '<?php echo htmlspecialchars($partyCode); ?>';

// Inicializar Watch Party cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    if (typeof WatchPartyManager !== 'undefined') {
        window.watchParty = new WatchPartyManager(PARTY_CODE, BASE_URL);
    } else {
        console.error('WatchPartyManager no está disponible');
        document.getElementById('errorMessage').textContent = 'Error al cargar el sistema de Watch Party. Por favor, recarga la página.';
        document.getElementById('errorMessage').style.display = 'block';
    }
});

// Función para copiar código
function copyPartyCode() {
    const code = document.getElementById('partyCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target.closest('.copy-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copiado';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2000);
    });
}

// Permitir enviar mensaje con Enter
document.addEventListener('DOMContentLoaded', function() {
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
});

function sendMessage() {
    if (window.watchParty) {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        if (message) {
            window.watchParty.sendChatMessage(message);
            input.value = '';
        }
    }
}

function leaveParty() {
    if (confirm('¿Estás seguro de que quieres abandonar el Watch Party?')) {
        if (window.watchParty) {
            window.watchParty.leave();
        } else {
            window.location.href = BASE_URL + '/';
        }
    }
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>
