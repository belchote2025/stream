<?php
// Verificar autenticación
if (!$auth->isAuthenticated()) {
    redirect('/login.php');
}

$db = getDbConnection();
$userId = $user['id'];
$success = $error = '';

// Configuraciones por defecto
$defaultSettings = [
    'receive_emails' => 1,
    'receive_notifications' => 1,
    'privacy_profile' => 'public',
    'language' => 'es',
    'video_quality' => 'hd',
    'autoplay' => 1,
    'subtitle_language' => 'es',
    'mature_content' => 0,
    'two_factor_auth' => 0,
    'email_notifications' => 1,
    'push_notifications' => 1,
    'theme' => 'light',
    'auto_play_next' => 1,
    'data_saver' => 0
];

// Obtener configuraciones guardadas
try {
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $savedSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $settings = array_merge($defaultSettings, $savedSettings);
} catch (Exception $e) {
    $error = 'Error al cargar la configuración';
    $settings = $defaultSettings;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar y limpiar datos
        $updateData = [
            'receive_emails' => isset($_POST['receive_emails']) ? 1 : 0,
            'receive_notifications' => isset($_POST['receive_notifications']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'push_notifications' => isset($_POST['push_notifications']) ? 1 : 0,
            'privacy_profile' => in_array($_POST['privacy_profile'] ?? '', ['public', 'friends', 'private']) 
                ? $_POST['privacy_profile'] 
                : 'public',
            'language' => in_array($_POST['language'] ?? '', ['es', 'en', 'fr', 'pt']) 
                ? $_POST['language'] 
                : 'es',
            'video_quality' => in_array($_POST['video_quality'] ?? '', ['sd', 'hd', 'full_hd', '4k']) 
                ? $_POST['video_quality'] 
                : 'hd',
            'autoplay' => isset($_POST['autoplay']) ? 1 : 0,
            'auto_play_next' => isset($_POST['auto_play_next']) ? 1 : 0,
            'subtitle_language' => in_array($_POST['subtitle_language'] ?? '', ['es', 'en', 'fr', 'pt', 'none']) 
                ? $_POST['subtitle_language'] 
                : 'es',
            'mature_content' => isset($_POST['mature_content']) ? 1 : 0,
            'two_factor_auth' => isset($_POST['two_factor_auth']) ? 1 : 0,
            'theme' => in_array($_POST['theme'] ?? '', ['light', 'dark', 'system']) 
                ? $_POST['theme'] 
                : 'light',
            'data_saver' => isset($_POST['data_saver']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Actualizar base de datos
        $fields = [];
        $values = [];
        $updateFields = [];
        
        foreach ($updateData as $key => $value) {
            $fields[] = $key;
            $values[] = $value;
            $updateFields[] = "$key = ?";
        }
        
        $values[] = $userId;
        
        $sql = "INSERT INTO user_settings (user_id, " . implode(', ', $fields) . ") 
                VALUES (?, " . str_repeat('?, ', count($fields) - 1) . "?) 
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);
        
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$userId], $values, $values));
        
        $success = 'Configuración actualizada correctamente';
        $settings = array_merge($settings, $updateData);
        
        // Actualizar tema si cambió
        if (isset($_POST['theme'])) {
            setcookie('theme', $_POST['theme'], time() + (86400 * 30), "/");
        }
        
    } catch (Exception $e) {
        $error = 'Error al actualizar la configuración: ' . $e->getMessage();
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validar campos
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception('Todos los campos son obligatorios');
        }
        
        if ($newPassword !== $confirmPassword) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($newPassword) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres');
        }
        
        // Verificar contraseña actual
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch();
        
        if (!password_verify($currentPassword, $userData['password'])) {
            throw new Exception('La contraseña actual es incorrecta');
        }
        
        // Actualizar contraseña
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        $success = 'Contraseña actualizada correctamente';
        
        // Cerrar sesión en otros dispositivos (opcional)
        // $auth->logoutOtherDevices($userId);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="dashboard-settings">
    <div class="page-header mb-4">
        <h1 class="page-title">Configuración</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab" aria-controls="account" aria-selected="true">
                <i class="fas fa-user-cog me-2"></i>Cuenta
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab" aria-controls="privacy" aria-selected="false">
                <i class="fas fa-lock me-2"></i>Privacidad
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false">
                <i class="fas fa-bell me-2"></i>Notificaciones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="playback-tab" data-bs-toggle="tab" data-bs-target="#playback" type="button" role="tab" aria-controls="playback" aria-selected="false">
                <i class="fas fa-play-circle me-2"></i>Reproducción
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                <i class="fas fa-shield-alt me-2"></i>Seguridad
            </button>
        </li>
    </ul>

    <form method="POST" class="settings-form">
        <div class="tab-content" id="settingsTabsContent">
            <!-- Pestaña de Cuenta -->
            <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Información de la cuenta</h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center mb-4">
                            <div class="col-auto">
                                <div class="avatar-upload">
                                    <div class="avatar-preview">
                                        <div id="imagePreview" style="background-image: url('<?php echo !empty($user['avatar_url']) ? htmlspecialchars($user['avatar_url']) : '/assets/img/default-avatar.png'; ?>');">
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <input type="file" id="avatar" name="avatar" accept="image/*" class="d-none">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('avatar').click();">
                                            <i class="fas fa-camera me-1"></i> Cambiar foto
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                                <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                                <p class="text-muted mb-0">
                                    Miembro desde <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nombre de usuario</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Correo electrónico</label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#changeEmailModal">
                                            Cambiar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nombre completo</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Biografía</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                     maxlength="500"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <div class="form-text text-end"><span id="bioCounter">0</span>/500 caracteres</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language" class="form-label">Idioma</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="es" <?php echo $settings['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="fr" <?php echo $settings['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                        <option value="pt" <?php echo $settings['language'] === 'pt' ? 'selected' : ''; ?>>Português</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="theme" class="form-label">Tema</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="light" <?php echo $settings['theme'] === 'light' ? 'selected' : ''; ?>>Claro</option>
                                        <option value="dark" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Oscuro</option>
                                        <option value="system" <?php echo $settings['theme'] === 'system' ? 'selected' : ''; ?>>Usar configuración del sistema</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" name="update_profile">
                        <i class="fas fa-save me-2"></i>Guardar cambios
                    </button>
                </div>
            </div>
            
            <!-- Pestaña de Privacidad -->
            <div class="tab-pane fade" id="privacy" role="tabpanel" aria-labelledby="privacy-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Configuración de privacidad</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Visibilidad del perfil</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="privacy_profile" id="privacyPublic" 
                                       value="public" <?php echo $settings['privacy_profile'] === 'public' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="privacyPublic">
                                    <strong>Público</strong>
                                    <p class="text-muted small mb-0">Cualquier persona puede ver tu perfil y actividad</p>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="privacy_profile" id="privacyFriends" 
                                       value="friends" <?php echo $settings['privacy_profile'] === 'friends' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="privacyFriends">
                                    <strong>Solo amigos</strong>
                                    <p class="text-muted small mb-0">Solo tus amigos pueden ver tu perfil completo</p>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="privacy_profile" id="privacyPrivate" 
                                       value="private" <?php echo $settings['privacy_profile'] === 'private' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="privacyPrivate">
                                    <strong>Privado</strong>
                                    <p class="text-muted small mb-0">Solo tú puedes ver tu perfil</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Contenido para adultos</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="mature_content" name="mature_content" 
                                       <?php echo $settings['mature_content'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mature_content">
                                    Mostrar contenido para adultos
                                </label>
                                <p class="form-text small">
                                    Habilita esta opción para ver contenido calificado para mayores de 18 años.
                                </p>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Actividad</h6>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="show_activity" name="show_activity" 
                                       <?php echo $settings['show_activity'] ?? 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_activity">
                                    Mostrar mi actividad
                                </label>
                                <p class="form-text small mb-0">
                                    Permite que otros usuarios vean lo que estás viendo y tus valoraciones.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#downloadDataModal">
                        <i class="fas fa-download me-2"></i>Descargar mis datos
                    </button>
                    <button type="submit" class="btn btn-primary" name="update_privacy">
                        <i class="fas fa-save me-2"></i>Guardar cambios
                    </button>
                </div>
            </div>
            
            <!-- Pestaña de Notificaciones -->
            <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Preferencias de notificaciones</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Notificaciones por correo electrónico</h6>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="receive_emails" name="receive_emails" 
                                       <?php echo $settings['receive_emails'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="receive_emails">
                                    Recibir notificaciones por correo electrónico
                                </label>
                                <p class="form-text small">
                                    Recibirás actualizaciones importantes y noticias en tu correo electrónico.
                                </p>
                            </div>
                            
                            <div class="ps-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; 
                                           echo !$settings['receive_emails'] ? ' disabled' : ''; ?>>
                                    <label class="form-check-label" for="email_notifications">
                                        Notificaciones de actividad
                                    </label>
                                    <p class="form-text small">
                                        Recibe notificaciones cuando alguien interactúe con tu contenido.
                                    </p>
                                </div>
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" 
                                           <?php echo $settings['newsletter'] ?? 1 ? 'checked' : ''; 
                                           echo !$settings['receive_emails'] ? ' disabled' : ''; ?>>
                                    <label class="form-check-label" for="newsletter">
                                        Boletín informativo
                                    </label>
                                    <p class="form-text small">
                                        Recibe nuestras últimas noticias y ofertas especiales.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Notificaciones push</h6>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" 
                                       <?php echo $settings['push_notifications'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="push_notifications">
                                    Activar notificaciones push
                                </label>
                                <p class="form-text small">
                                    Recibe notificaciones en tiempo real en tu dispositivo.
                                </p>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Frecuencia de notificaciones</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_frequency" id="freq_instant" 
                                       value="instant" <?php echo ($settings['notification_frequency'] ?? 'instant') === 'instant' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="freq_instant">
                                    En tiempo real
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_frequency" id="freq_daily" 
                                       value="daily" <?php echo ($settings['notification_frequency'] ?? '') === 'daily' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="freq_daily">
                                    Resumen diario
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="notification_frequency" id="freq_weekly" 
                                       value="weekly" <?php echo ($settings['notification_frequency'] ?? '') === 'weekly' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="freq_weekly">
                                    Resumen semanal
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" name="update_notifications">
                        <i class="fas fa-save me-2"></i>Guardar preferencias
                    </button>
                </div>
            </div>
            
            <!-- Pestaña de Reproducción -->
            <div class="tab-pane fade" id="playback" role="tabpanel" aria-labelledby="playback-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Preferencias de reproducción</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="video_quality" class="form-label">Calidad de video</label>
                                    <select class="form-select" id="video_quality" name="video_quality">
                                        <option value="auto" <?php echo ($settings['video_quality'] ?? '') === 'auto' ? 'selected' : ''; ?>>Automática</option>
                                        <option value="4k" <?php echo ($settings['video_quality'] ?? '') === '4k' ? 'selected' : ''; ?>>4K (UHD)</option>
                                        <option value="full_hd" <?php echo ($settings['video_quality'] ?? '') === 'full_hd' ? 'selected' : ''; ?>>Full HD (1080p)</option>
                                        <option value="hd" <?php echo ($settings['video_quality'] ?? 'hd') === 'hd' ? 'selected' : ''; ?>>HD (720p)</option>
                                        <option value="sd" <?php echo ($settings['video_quality'] ?? '') === 'sd' ? 'selected' : ''; ?>>SD (480p)</option>
                                    </select>
                                    <div class="form-text">
                                        La calidad se ajustará automáticamente según tu conexión si seleccionas "Automática"
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subtitle_language" class="form-label">Idioma de subtítulos</label>
                                    <select class="form-select" id="subtitle_language" name="subtitle_language">
                                        <option value="es" <?php echo ($settings['subtitle_language'] ?? 'es') === 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="en" <?php echo ($settings['subtitle_language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="fr" <?php echo ($settings['subtitle_language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                        <option value="pt" <?php echo ($settings['subtitle_language'] ?? '') === 'pt' ? 'selected' : ''; ?>>Português</option>
                                        <option value="none" <?php echo ($settings['subtitle_language'] ?? '') === 'none' ? 'selected' : ''; ?>>Desactivar subtítulos</option>
                                    </select>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="autoplay" name="autoplay" 
                                           <?php echo $settings['autoplay'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="autoplay">
                                        Reproducción automática
                                    </label>
                                    <p class="form-text small">
                                        Reproduce automáticamente el siguiente episodio o video relacionado.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="auto_play_next" name="auto_play_next" 
                                           <?php echo $settings['auto_play_next'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_play_next">
                                        Reproducir siguiente automáticamente
                                    </label>
                                    <p class="form-text small">
                                        Reproduce automáticamente el siguiente episodio en una serie.
                                    </p>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="data_saver" name="data_saver" 
                                           <?php echo $settings['data_saver'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="data_saver">
                                        Modo ahorro de datos
                                    </label>
                                    <p class="form-text small">
                                        Reduce el uso de datos móviles al reproducir videos.
                                    </p>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="auto_resume" name="auto_resume" 
                                           <?php echo $settings['auto_resume'] ?? 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_resume">
                                        Reanudar reproducción automáticamente
                                    </label>
                                    <p class="form-text small">
                                        Recuerda dónde dejaste de ver y continúa desde ahí.
                                    </p>
                                </div>
                                
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="previews" name="previews" 
                                           <?php echo $settings['previews'] ?? 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="previews">
                                        Mostrar avances automáticos
                                    </label>
                                    <p class="form-text small">
                                        Reproduce automáticamente avances de películas y series mientras navegas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="resetPlaybackSettings()">
                        <i class="fas fa-undo me-2"></i>Restablecer valores predeterminados
                    </button>
                    <button type="submit" class="btn btn-primary" name="update_playback">
                        <i class="fas fa-save me-2"></i>Guardar cambios
                    </button>
                </div>
            </div>
            
            <!-- Pestaña de Seguridad -->
            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Seguridad de la cuenta</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Inicio de sesión y seguridad</h6>
                            
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                <div>
                                    <h6 class="mb-1">Contraseña</h6>
                                    <p class="text-muted small mb-0">
                                        Último cambio: 
                                        <?php 
                                        $lastChanged = !empty($user['password_changed_at']) 
                                            ? date('d/m/Y', strtotime($user['password_changed_at'])) 
                                            : 'Nunca';
                                        echo $lastChanged;
                                        ?>
                                    </p>
                                </div>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    Cambiar contraseña
                                </button>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                                <div>
                                    <h6 class="mb-1">Autenticación de dos factores (2FA)</h6>
                                    <p class="text-muted small mb-0">
                                        Añade una capa adicional de seguridad a tu cuenta.
                                    </p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                           <?php echo $settings['two_factor_auth'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="two_factor_auth">
                                        <?php echo $settings['two_factor_auth'] ? 'Activado' : 'Desactivado'; ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Sesiones activas</h6>
                                    <p class="text-muted small mb-0">
                                        Dispositivos donde has iniciado sesión.
                                    </p>
                                </div>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sessionsModal">
                                    Ver sesiones
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Aplicaciones conectadas</h6>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No hay aplicaciones de terceros conectadas a tu cuenta.
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div>
                                    <h6>Zona de peligro</h6>
                                    <p class="mb-2">
                                        Ten cuidado al realizar cambios en esta sección. Algunas acciones no se pueden deshacer.
                                    </p>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                            <i class="fas fa-trash-alt me-1"></i>Eliminar cuenta
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportDataModal">
                                            <i class="fas fa-file-export me-1"></i>Exportar mis datos
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" name="update_security">
                        <i class="fas fa-save me-2"></i>Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal para cambiar contraseña -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="changePasswordForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required 
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números.
                        </div>
                        <div class="password-strength mt-2">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small id="passwordStrengthText" class="form-text">Seguridad de la contraseña</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar nueva contraseña</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback" id="passwordMatchError">
                            Las contraseñas no coinciden.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cambiar correo electrónico -->
<div class="modal fade" id="changeEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar dirección de correo electrónico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="changeEmailForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_email" class="form-label">Correo electrónico actual</label>
                        <input type="email" class="form-control" id="current_email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_email" class="form-label">Nuevo correo electrónico</label>
                        <input type="email" class="form-control" id="new_email" name="new_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_email" class="form-label">Confirmar nuevo correo electrónico</label>
                        <input type="email" class="form-control" id="confirm_email" name="confirm_email" required>
                        <div class="invalid-feedback" id="emailMatchError">
                            Los correos electrónicos no coinciden.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_password" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="email_password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="change_email" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para sesiones activas -->
<div class="modal fade" id="sessionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sesiones activas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Dispositivo</th>
                                <th>Ubicación</th>
                                <th>Última actividad</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-desktop me-2"></i>
                                        <div>
                                            <div class="fw-bold">Windows 10</div>
                                            <div class="text-muted small">Chrome en Windows</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Madrid, España</td>
                                <td>Hace 5 minutos</td>
                                <td><span class="badge bg-success">Activa</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        <div>
                                            <div class="fw-bold">iPhone 13</div>
                                            <div class="text-muted small">Safari en iOS</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Barcelona, España</td>
                                <td>Ayer a las 14:30</td>
                                <td><span class="badge bg-secondary">Inactiva</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Si ves alguna sesión que no reconoces, cierra la sesión de inmediato y cambia tu contraseña.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt me-1"></i> Cerrar todas las demás sesiones
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para eliminar cuenta -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">Eliminar cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="deleteAccountForm" method="POST" action="/api/account/delete">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>¡Atención!</h5>
                        <p class="mb-0">
                            Esta acción es irreversible. Todos tus datos, incluyendo listas de reproducción, historial y configuraciones, 
                            serán eliminados permanentemente.
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delete_reason" class="form-label">¿Por qué te vas? (Opcional)</label>
                        <select class="form-select" id="delete_reason" name="reason">
                            <option value="" selected>Selecciona un motivo...</option>
                            <option value="privacy">Problemas de privacidad</option>
                            <option value="content">Falta de contenido de mi interés</option>
                            <option value="usability">La aplicación es difícil de usar</option>
                            <option value="found_better">Encontré un servicio mejor</option>
                            <option value="other">Otro motivo</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delete_feedback" class="form-label">Comentarios (Opcional)</label>
                        <textarea class="form-control" id="delete_feedback" name="feedback" rows="3" 
                                  placeholder="¿Hay algo que podamos mejorar?"></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirm_delete" required>
                        <label class="form-check-label" for="confirm_delete">
                            Entiendo que esta acción no se puede deshacer y todos mis datos serán eliminados permanentemente.
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delete_password" class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" id="delete_password" name="password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Eliminar mi cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Contador de caracteres para la biografía
    const bioTextarea = document.getElementById('bio');
    const bioCounter = document.getElementById('bioCounter');
    
    if (bioTextarea && bioCounter) {
        // Establecer el contador con el valor inicial
        bioCounter.textContent = bioTextarea.value.length;
        
        // Actualizar el contador al escribir
        bioTextarea.addEventListener('input', function() {
            bioCounter.textContent = this.value.length;
        });
    }
    
    // Validación de contraseña
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatchError = document.getElementById('passwordMatchError');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordStrengthText = document.getElementById('passwordStrengthText');
    
    function validatePassword() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Las contraseñas no coinciden');
            passwordMatchError.style.display = 'block';
            return false;
        } else {
            confirmPassword.setCustomValidity('');
            passwordMatchError.style.display = 'none';
            return true;
        }
    }
    
    function checkPasswordStrength(password) {
        let strength = 0;
        let tips = "";
        
        if (password.length < 8) {
            tips = "La contraseña es demasiado corta";
        } else {
            if (password.match(/[a-z]+/)) {
                strength += 1;
            }
            if (password.match(/[A-Z]+/)) {
                strength += 1;
            }
            if (password.match(/[0-9]+/)) {
                strength += 1;
            }
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) {
                strength += 2;
            }
            
            // Actualizar la barra de fortaleza
            let width = 0;
            let color = '';
            let text = '';
            
            switch(strength) {
                case 0:
                case 1:
                    width = 25;
                    color = 'bg-danger';
                    text = 'Débil';
                    break;
                case 2:
                    width = 50;
                    color = 'bg-warning';
                    text = 'Moderada';
                    break;
                case 3:
                    width = 75;
                    color = 'bg-info';
                    text = 'Fuerte';
                    break;
                case 4:
                case 5:
                    width = 100;
                    color = 'bg-success';
                    text = 'Muy fuerte';
                    break;
            }
            
            passwordStrength.style.width = width + '%';
            passwordStrength.className = 'progress-bar ' + color;
            passwordStrengthText.textContent = text + ' (' + width + '%)';
            passwordStrengthText.className = 'form-text ' + (strength < 2 ? 'text-danger' : 'text-success');
        }
        
        return strength;
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            validatePassword();
        });
        
        confirmPassword.addEventListener('input', validatePassword);
    }
    
    // Validación de correo electrónico
    const newEmail = document.getElementById('new_email');
    const confirmEmail = document.getElementById('confirm_email');
    const emailMatchError = document.getElementById('emailMatchError');
    
    function validateEmail() {
        if (newEmail.value !== confirmEmail.value) {
            confirmEmail.setCustomValidity('Los correos electrónicos no coinciden');
            emailMatchError.style.display = 'block';
            return false;
        } else {
            confirmEmail.setCustomValidity('');
            emailMatchError.style.display = 'none';
            return true;
        }
    }
    
    if (newEmail && confirmEmail) {
        newEmail.addEventListener('input', validateEmail);
        confirmEmail.addEventListener('input', validateEmail);
    }
    
    // Mostrar/ocultar contraseña
    window.togglePassword = function(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    };
    
    // Restablecer configuraciones de reproducción
    window.resetPlaybackSettings = function() {
        if (confirm('¿Estás seguro de que deseas restablecer todas las configuraciones de reproducción a sus valores predeterminados?')) {
            document.getElementById('video_quality').value = 'hd';
            document.getElementById('subtitle_language').value = 'es';
            document.getElementById('autoplay').checked = true;
            document.getElementById('auto_play_next').checked = true;
            document.getElementById('data_saver').checked = false;
            
            // Mostrar mensaje de éxito
            alert('Las configuraciones de reproducción se han restablecido a sus valores predeterminados.');
        }
    };
    
    // Manejar la carga de imágenes de perfil
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('imagePreview');
    
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    avatarPreview.style.backgroundImage = 'url(' + e.target.result + ')';
                    
                    // Aquí podrías agregar lógica para previsualizar la imagen antes de subirla
                    // y validar el tamaño y tipo de archivo
                };
                
                reader.readAsDataURL(file);
                
                // Aquí podrías agregar lógica para subir la imagen al servidor
                // usando FormData y fetch/XMLHttpRequest
            }
        });
    }
    
    // Actualizar estado de los checkboxes dependientes
    const receiveEmails = document.getElementById('receive_emails');
    const emailNotifications = document.getElementById('email_notifications');
    const newsletter = document.getElementById('newsletter');
    
    if (receiveEmails && emailNotifications && newsletter) {
        function updateEmailSettings() {
            const isDisabled = !receiveEmails.checked;
            emailNotifications.disabled = isDisabled;
            newsletter.disabled = isDisabled;
            
            if (isDisabled) {
                emailNotifications.checked = false;
                newsletter.checked = false;
            }
        }
        
        receiveEmails.addEventListener('change', updateEmailSettings);
        updateEmailSettings(); // Estado inicial
    }
});
</script>
