<?php
// Verificar si el usuario está autenticado
if (!$auth->isAuthenticated()) {
    redirect('/login.php');
}

$db = getDbConnection();
$userId = $user['id'];
$success = '';
$error = '';
$birthDateFormatted = '';

if (!function_exists('dashboardTableExists')) {
    function dashboardTableExists(PDO $db, string $table): bool {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = ?
            ");
            $stmt->execute([$table]);
            $cache[$table] = $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('dashboardTableExists error: ' . $e->getMessage());
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}

if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime): string {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$timestamp) {
            return '';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) return 'Hace segundos';
        if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
        if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' h';
        if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';
        return date('d/m/Y', $timestamp);
    }
}

// Procesar el formulario de actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $fullName = trim($_POST['full_name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $birthDate = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $gender = $_POST['gender'] ?? null;
        
        // Validar datos
        if (empty($fullName)) {
            throw new Exception('El nombre completo es obligatorio');
        }
        
        // Validar URL del sitio web si se proporciona
        if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
            throw new Exception('La URL del sitio web no es válida');
        }
        
        // Validar fecha de nacimiento si se proporciona
        if (!empty($birthDate)) {
            $birthDateObj = new DateTime($birthDate);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            
            if ($age < 13) {
                throw new Exception('Debes tener al menos 13 años para usar este servicio');
            }
            
            if ($age > 120) {
                throw new Exception('Por favor, ingresa una fecha de nacimiento válida');
            }
            
            $birthDate = $birthDateObj->format('Y-m-d');
        }
        
        // Manejar la carga de la imagen de perfil
        $avatarUrl = $user['avatar_url'] ?? '';
        
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            
            // Crear directorio si no existe
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($fileExtension), $allowedExtensions)) {
                throw new Exception('Formato de archivo no permitido. Solo se permiten JPG, PNG y GIF.');
            }
            
            // Validar tamaño del archivo (máx 5MB)
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($_FILES['avatar']['size'] > $maxFileSize) {
                throw new Exception('El archivo es demasiado grande. El tamaño máximo permitido es 5MB.');
            }
            
            // Generar un nombre de archivo único
            $newFilename = 'user_' . $userId . '_' . time() . '.' . $fileExtension;
            $uploadPath = $uploadDir . $newFilename;
            
            // Mover el archivo cargado
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                // Si se cargó una nueva imagen, eliminar la anterior si existe
                if (!empty($user['avatar_url']) && file_exists(__DIR__ . '/../..' . $user['avatar_url'])) {
                    @unlink(__DIR__ . '/../..' . $user['avatar_url']);
                }
                
                $avatarUrl = '/uploads/avatars/' . $newFilename;
            } else {
                throw new Exception('Error al cargar la imagen. Por favor, inténtalo de nuevo.');
            }
        }
        
        // Actualizar el perfil en la base de datos
        $stmt = $db->prepare("
            UPDATE users 
            SET full_name = ?, 
                bio = ?, 
                location = ?, 
                website = ?, 
                birth_date = ?, 
                gender = ?,
                avatar_url = ?,
                updated_at = NOW()
            WHERE id = ?
        
        ");
        
        $stmt->execute([
            $fullName,
            $bio,
            $location,
            $website,
            $birthDate,
            $gender,
            $avatarUrl,
            $userId
        ]);
        
        // Actualizar la información del usuario en la sesión
        $user = $auth->getCurrentUser();
        
        $success = 'Perfil actualizado correctamente';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener la información actualizada del perfil
try {
    $subFields = [];
    $subFields[] = dashboardTableExists($db, 'watch_history')
        ? "(SELECT COUNT(*) FROM watch_history WHERE user_id = u.id) AS total_watched"
        : "0 AS total_watched";
    $subFields[] = dashboardTableExists($db, 'user_playlists')
        ? "(SELECT COUNT(*) FROM user_playlists WHERE user_id = u.id) AS total_playlists"
        : "0 AS total_playlists";
    $subFields[] = dashboardTableExists($db, 'ratings')
        ? "(SELECT COUNT(*) FROM ratings WHERE user_id = u.id) AS total_ratings"
        : "0 AS total_ratings";
    
    $select = implode(",\n               ", $subFields);
    $sql = "
        SELECT u.*, 
               {$select}
        FROM users u 
        WHERE u.id = ?
    ";
    
    $stmt = $db->prepare($sql);
    
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        throw new Exception('No se pudo cargar la información del perfil');
    }
    
    // Formatear la fecha de nacimiento para el input de fecha
    if (!empty($profile['birth_date'])) {
        $timestamp = strtotime($profile['birth_date']);
        $birthDateFormatted = $timestamp ? date('Y-m-d', $timestamp) : '';
    } else {
        $birthDateFormatted = '';
    }
    
} catch (Exception $e) {
    $error = $error ?: $e->getMessage();
    $profile = array_merge($user, [
        'full_name' => $user['full_name'] ?? '',
        'bio' => $user['bio'] ?? '',
        'location' => $user['location'] ?? '',
        'website' => $user['website'] ?? '',
        'birth_date' => $user['birth_date'] ?? null,
        'gender' => $user['gender'] ?? '',
        'avatar_url' => $user['avatar_url'] ?? '',
        'created_at' => $user['created_at'] ?? date('Y-m-d H:i:s'),
        'total_watched' => 0,
        'total_playlists' => 0,
        'total_ratings' => 0
    ]);
    $birthDateFormatted = !empty($profile['birth_date']) ? date('Y-m-d', strtotime($profile['birth_date'])) : '';
}
?>

<div class="dashboard-profile">
    <!-- Encabezado de la página -->
    <div class="page-header">
        <h1 class="page-title">Mi Perfil</h1>
    </div>
    
    <!-- Mensajes de éxito/error -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Columna izquierda - Formulario de perfil -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información del perfil</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <div class="row mb-4">
                            <div class="col-md-3 text-center">
                                <div class="profile-avatar-container mb-3">
                                    <div class="profile-avatar-preview">
                                        <?php if (!empty($profile['avatar_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($profile['avatar_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($profile['full_name'] ?? $profile['username']); ?>"
                                                 id="avatarPreview" class="img-fluid rounded-circle">
                                        <?php else: ?>
                                            <div class="avatar-placeholder" id="avatarPreview">
                                                <?php 
                                                $initials = '';
                                                if (!empty($profile['full_name'])) {
                                                    $names = explode(' ', $profile['full_name']);
                                                    $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                                                } else {
                                                    $initials = strtoupper(substr($profile['username'], 0, 2));
                                                }
                                                echo htmlspecialchars($initials);
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-3">
                                        <input type="file" name="avatar" id="avatarInput" class="d-none" accept="image/*">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="changeAvatarBtn">
                                            <i class="fas fa-camera me-1"></i> Cambiar foto
                                        </button>
                                        <?php if (!empty($profile['avatar_url'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="removeAvatarBtn">
                                                <i class="fas fa-trash-alt me-1"></i> Eliminar
                                            </button>
                                        <?php endif; ?>
                                        <div class="form-text mt-1">Formatos: JPG, PNG o GIF (máx. 5MB)</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Nombre de usuario</label>
                                    <input type="text" class="form-control" id="username" 
                                           value="<?php echo htmlspecialchars($profile['username']); ?>" disabled>
                                    <div class="form-text">No se puede cambiar el nombre de usuario.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($profile['email']); ?>" disabled>
                                    <div class="form-text">
                                        <a href="?tab=settings#change-email">Cambiar correo electrónico</a>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="birth_date" class="form-label">Fecha de nacimiento</label>
                                            <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                                   value="<?php echo htmlspecialchars($birthDateFormatted); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="gender" class="form-label">Género</label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="">Seleccionar...</option>
                                                <option value="male" <?php echo ($profile['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="female" <?php echo ($profile['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Femenino</option>
                                                <option value="other" <?php echo ($profile['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Otro</option>
                                                <option value="prefer_not_to_say" <?php echo ($profile['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefiero no decirlo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Biografía</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" 
                                      placeholder="Cuéntanos sobre ti..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                            <div class="form-text">Máximo 500 caracteres. <span id="bioCounter">0/500</span></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Ubicación</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>"
                                           placeholder="Ciudad, País">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="website" class="form-label">Sitio web</label>
                                    <div class="input-group">
                                        <span class="input-group-text">https://</span>
                                        <input type="text" class="form-control" id="website" name="website" 
                                               value="<?php echo !empty($profile['website']) ? str_replace(['http://', 'https://'], '', $profile['website']) : ''; ?>"
                                               placeholder="tusitio.com">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Sección de redes sociales -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Redes sociales</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addSocialModal">
                        <i class="fas fa-plus me-1"></i> Agregar red
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Plataforma</th>
                                    <th>Usuario/URL</th>
                                    <th>Visibilidad</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="fas fa-share-alt fa-2x text-muted mb-2"></i>
                                        <p class="mb-0">No has agregado ninguna red social</p>
                                        <button class="btn btn-sm btn-link p-0 mt-2" data-bs-toggle="modal" data-bs-target="#addSocialModal">
                                            Agrega una para comenzar
                                        </button>
                                    </td>
                                </tr>
                                <!-- Ejemplo de red social (comentado) -->
                                <!--
                                <tr>
                                    <td class="align-middle">
                                        <i class="fab fa-twitter text-primary me-2"></i> Twitter
                                    </td>
                                    <td class="align-middle">@usuario</td>
                                    <td class="align-middle">
                                        <span class="badge bg-success">Público</span>
                                    </td>
                                    <td class="text-end align-middle">
                                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="tooltip" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                                -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha - Estadísticas y actividad -->
        <div class="col-lg-4">
            <!-- Tarjeta de estadísticas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Estadísticas</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-film text-primary me-2"></i>
                                <span>Contenido visto</span>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?php echo number_format($profile['total_watched'] ?? 0); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-list-ul text-success me-2"></i>
                                <span>Listas creadas</span>
                            </div>
                            <span class="badge bg-success rounded-pill"><?php echo number_format($profile['total_playlists'] ?? 0); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-star text-warning me-2"></i>
                                <span>Valoraciones</span>
                            </div>
                            <span class="badge bg-warning rounded-pill"><?php echo number_format($profile['total_ratings'] ?? 0); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-calendar-alt text-info me-2"></i>
                                <span>Miembro desde</span>
                            </div>
                            <span class="text-muted">
                                <?php echo date('M Y', strtotime($profile['created_at'])); ?>
                            </span>
                        </li>
                        <?php if (!empty($profile['last_login'])): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <i class="fas fa-sign-in-alt text-secondary me-2"></i>
                                <span>Último acceso</span>
                            </div>
                            <span class="text-muted" data-bs-toggle="tooltip" 
                                  title="<?php echo date('d M Y H:i', strtotime($profile['last_login'])); ?>">
                                <?php echo time_elapsed_string($profile['last_login']); ?>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Tarjeta de insignias -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Insignias</h5>
                    <a href="#" class="text-muted" data-bs-toggle="tooltip" title="Ver todas">
                        <i class="fas fa-ellipsis-h"></i>
                    </a>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img src="/assets/images/badges/beginner.png" alt="Principiante" class="img-fluid mb-2" style="max-height: 80px;">
                        <h6 class="mb-1">Principiante</h6>
                        <p class="small text-muted mb-0">¡Bienvenido a <?php echo htmlspecialchars(SITE_NAME); ?>!</p>
                    </div>
                    <hr>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="position-relative">
                                <img src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/badges/film-lover.png" alt="Amante del cine" class="img-fluid opacity-50" data-bs-toggle="tooltip" title="Amante del cine - Ve 10 películas">
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <i class="fas fa-lock text-muted"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="position-relative">
                                <img src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/badges/binge-watcher.png" alt="Maratonista" class="img-fluid opacity-50" data-bs-toggle="tooltip" title="Maratonista - Ve 5 episodios seguidos">
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <i class="fas fa-lock text-muted"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="position-relative">
                                <img src="<?php echo rtrim(SITE_URL, '/'); ?>/assets/images/badges/critic.png" alt="Crítico" class="img-fluid opacity-50" data-bs-toggle="tooltip" title="Crítico - Escribe 10 reseñas">
                                <div class="position-absolute top-50 start-50 translate-middle">
                                    <i class="fas fa-lock text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="#" class="btn btn-sm btn-outline-primary mt-3">Ver todas las insignias</a>
                </div>
            </div>
            
            <!-- Tarjeta de cuenta -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Cuenta</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?tab=settings" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-cog me-2"></i> Configuración de la cuenta
                        </a>
                        <a href="?tab=subscription" class="btn btn-outline-secondary text-start">
                            <i class="fas fa-crown me-2"></i> Suscripción y facturación
                        </a>
                        <a href="#" class="btn btn-outline-secondary text-start" data-bs-toggle="modal" data-bs-target="#downloadDataModal">
                            <i class="fas fa-download me-2"></i> Descargar mis datos
                        </a>
                        <a href="#" class="btn btn-outline-danger text-start" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash-alt me-2"></i> Eliminar cuenta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para agregar red social -->
<div class="modal fade" id="addSocialModal" tabindex="-1" aria-labelledby="addSocialModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addSocialModalLabel">Agregar red social</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="addSocialForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="socialPlatform" class="form-label">Plataforma</label>
                        <select class="form-select" id="socialPlatform" required>
                            <option value="" selected disabled>Seleccionar plataforma...</option>
                            <option value="twitter">Twitter</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="youtube">YouTube</option>
                            <option value="tiktok">TikTok</option>
                            <option value="twitch">Twitch</option>
                            <option value="discord">Discord</option>
                            <option value="other">Otra</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="socialUrl" class="form-label">URL o nombre de usuario</label>
                        <div class="input-group">
                            <span class="input-group-text" id="socialPrefix">@</span>
                            <input type="text" class="form-control" id="socialUrl" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Visibilidad</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="socialVisibility" id="socialPublic" value="public" checked>
                            <label class="form-check-label" for="socialPublic">
                                Público <small class="text-muted">(cualquiera puede ver este enlace)</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="socialVisibility" id="socialPrivate" value="private">
                            <label class="form-check-label" for="socialPrivate">
                                Privado <small class="text-muted">(solo tú puedes ver este enlace)</small>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar red social</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para descargar datos -->
<div class="modal fade" id="downloadDataModal" tabindex="-1" aria-labelledby="downloadDataModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="downloadDataModalLabel">Descargar mis datos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Puedes solicitar un archivo con todos tus datos personales que tenemos almacenados en nuestra plataforma.</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    El archivo puede tardar hasta 48 horas en generarse. Te notificaremos por correo electrónico cuando esté listo para descargar.
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmDownload">
                    <label class="form-check-label" for="confirmDownload">
                        Entiendo que recibiré un enlace de descarga por correo electrónico
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="requestDataBtn" disabled>
                    <i class="fas fa-download me-1"></i> Solicitar mis datos
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para eliminar cuenta -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteAccountModalLabel">Eliminar cuenta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>¿Estás seguro de que quieres eliminar tu cuenta?</h5>
                </div>
                <p>Esta acción es permanente y no se puede deshacer. Se eliminarán todos tus datos, incluyendo:</p>
                <ul>
                    <li>Tu perfil e información personal</li>
                    <li>Tu historial de visualización</li>
                    <li>Tus listas y favoritos</li>
                    <li>Tus reseñas y calificaciones</li>
                    <li>Tus suscripciones activas</li>
                </ul>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        Entiendo que todos mis datos se eliminarán permanentemente y no podrán recuperarse
                    </label>
                </div>
                <div class="form-group">
                    <label for="deleteReason" class="form-label">¿Por qué decides irte? (Opcional)</label>
                    <select class="form-select" id="deleteReason">
                        <option value="" selected>Seleccionar motivo...</option>
                        <option value="too_expensive">Demasiado caro</option>
                        <option value="missing_content">Falta de contenido que me interese</option>
                        <option value="technical_issues">Problemas técnicos</option>
                        <option value="customer_service">Atención al cliente</option>
                        <option value="found_alternative">Encontré un servicio mejor</option>
                        <option value="other">Otro motivo</option>
                    </select>
                </div>
                <div class="form-group mt-3" id="otherReasonContainer" style="display: none;">
                    <label for="otherReason" class="form-label">Por favor, especifica el motivo</label>
                    <textarea class="form-control" id="otherReason" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                    <i class="fas fa-trash-alt me-1"></i> Eliminar mi cuenta
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Script para el manejo del perfil -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar vista previa de la imagen de perfil
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreview = document.getElementById('avatarPreview');
    const changeAvatarBtn = document.getElementById('changeAvatarBtn');
    const removeAvatarBtn = document.getElementById('removeAvatarBtn');
    
    if (changeAvatarBtn) {
        changeAvatarBtn.addEventListener('click', function() {
            avatarInput.click();
        });
    }
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    if (avatarPreview.tagName === 'IMG') {
                        avatarPreview.src = event.target.result;
                    } else {
                        // Si es un div con iniciales, reemplazarlo por una imagen
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        img.className = 'img-fluid rounded-circle';
                        img.id = 'avatarPreview';
                        avatarPreview.parentNode.replaceChild(img, avatarPreview);
                        
                        // Mostrar el botón de eliminar si no está visible
                        if (removeAvatarBtn) {
                            removeAvatarBtn.style.display = 'inline-block';
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Manejar la eliminación de la imagen de perfil
    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', function() {
            // Crear un div con las iniciales
            const initialsDiv = document.createElement('div');
            initialsDiv.className = 'avatar-placeholder';
            initialsDiv.id = 'avatarPreview';
            
            // Obtener las iniciales del nombre o nombre de usuario
            const fullName = document.getElementById('full_name').value || '<?php echo $profile['username'] ?? 'US'; ?>';
            const nameParts = fullName.trim().split(' ');
            let initials = nameParts[0].charAt(0).toUpperCase();
            
            if (nameParts.length > 1) {
                initials += nameParts[nameParts.length - 1].charAt(0).toUpperCase();
            } else if (fullName.length > 1) {
                initials += fullName.charAt(1).toUpperCase();
            } else {
                initials = fullName.toUpperCase();
            }
            
            initialsDiv.textContent = initials;
            
            // Reemplazar la imagen con el div de iniciales
            avatarPreview.parentNode.replaceChild(initialsDiv, avatarPreview);
            
            // Ocultar el botón de eliminar
            this.style.display = 'none';
            
            // Limpiar el input de archivo
            avatarInput.value = '';
        });
    }
    
    // Contador de caracteres para la biografía
    const bioTextarea = document.getElementById('bio');
    const bioCounter = document.getElementById('bioCounter');
    
    if (bioTextarea && bioCounter) {
        // Actualizar contador al cargar la página
        updateBioCounter();
        
        // Actualizar contador al escribir
        bioTextarea.addEventListener('input', updateBioCounter);
        
        function updateBioCounter() {
            const currentLength = bioTextarea.value.length;
            const maxLength = 500;
            bioCounter.textContent = `${currentLength}/${maxLength}`;
            
            if (currentLength > maxLength) {
                bioCounter.classList.add('text-danger');
                bioTextarea.classList.add('is-invalid');
            } else {
                bioCounter.classList.remove('text-danger');
                bioTextarea.classList.remove('is-invalid');
            }
        }
    }
    
    // Manejar el formulario de perfil
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            // Validar la biografía
            const bioTextarea = document.getElementById('bio');
            if (bioTextarea && bioTextarea.value.length > 500) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La biografía no puede tener más de 500 caracteres.',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
            
            // Validar la fecha de nacimiento
            const birthDateInput = document.getElementById('birth_date');
            if (birthDateInput && birthDateInput.value) {
                const birthDate = new Date(birthDateInput.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 13) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Debes tener al menos 13 años para usar este servicio.',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
            }
            
            // Si todo está bien, el formulario se envía
            return true;
        });
    }
    
    // Manejar el modal de descarga de datos
    const confirmDownload = document.getElementById('confirmDownload');
    const requestDataBtn = document.getElementById('requestDataBtn');
    
    if (confirmDownload && requestDataBtn) {
        confirmDownload.addEventListener('change', function() {
            requestDataBtn.disabled = !this.checked;
        });
        
        requestDataBtn.addEventListener('click', function() {
            // Simular solicitud de datos
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
            
            // En una implementación real, aquí iría una llamada AJAX
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('downloadDataModal'));
                modal.hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Solicitud recibida',
                    html: 'Hemos recibido tu solicitud de datos. Te notificaremos por correo electrónico cuando tu archivo esté listo para descargar.',
                    confirmButtonText: 'Entendido'
                });
                
                // Restaurar el botón
                requestDataBtn.disabled = false;
                requestDataBtn.innerHTML = '<i class="fas fa-download me-1"></i> Solicitar mis datos';
                confirmDownload.checked = false;
            }, 1500);
        });
    }
    
    // Manejar el modal de eliminación de cuenta
    const confirmDelete = document.getElementById('confirmDelete');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteReason = document.getElementById('deleteReason');
    const otherReasonContainer = document.getElementById('otherReasonContainer');
    
    if (confirmDelete && confirmDeleteBtn) {
        confirmDelete.addEventListener('change', function() {
            confirmDeleteBtn.disabled = !this.checked;
        });
        
        // Mostrar/ocultar campo de otro motivo
        if (deleteReason && otherReasonContainer) {
            deleteReason.addEventListener('change', function() {
                otherReasonContainer.style.display = this.value === 'other' ? 'block' : 'none';
            });
        }
        
        confirmDeleteBtn.addEventListener('click', function() {
            // Mostrar confirmación final
            Swal.fire({
                title: '¿Estás absolutamente seguro?',
                text: "¡Esta acción no se puede deshacer! Todos tus datos se eliminarán permanentemente.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar mi cuenta',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar carga
                    Swal.fire({
                        title: 'Eliminando tu cuenta...',
                        text: 'Esto puede tomar unos momentos.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Simular eliminación (en producción, esto sería una llamada AJAX)
                    setTimeout(() => {
                        // Cerrar el modal de eliminación
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteAccountModal'));
                        modal.hide();
                        
                        // Redirigir a la página de despedida
                        window.location.href = '/goodbye';
                    }, 2000);
                }
            });
        });
    }
    
    // Manejar el formulario de redes sociales
    const socialPlatform = document.getElementById('socialPlatform');
    const socialUrl = document.getElementById('socialUrl');
    const socialPrefix = document.getElementById('socialPrefix');
    
    if (socialPlatform && socialUrl && socialPrefix) {
        socialPlatform.addEventListener('change', function() {
            const platform = this.value;
            let prefix = '';
            
            switch (platform) {
                case 'twitter':
                case 'instagram':
                case 'tiktok':
                    prefix = '@';
                    break;
                case 'youtube':
                    prefix = 'youtube.com/';
                    break;
                case 'facebook':
                    prefix = 'facebook.com/';
                    break;
                case 'twitch':
                    prefix = 'twitch.tv/';
                    break;
                case 'discord':
                    prefix = '';
                    break;
                default:
                    prefix = '';
            }
            
            socialPrefix.textContent = prefix;
            socialUrl.placeholder = platform === 'discord' ? 'Usuario#1234' : 'tu-usuario';
        });
        
        document.getElementById('addSocialForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Aquí iría la lógica para guardar la red social
            // Por ahora, solo cerramos el modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addSocialModal'));
            modal.hide();
            
            // Mostrar mensaje de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Red social agregada!',
                text: 'Tu perfil ha sido actualizado con la nueva red social.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            
            // Recargar la página después de un breve retraso
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        });
    }
    
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Función para formatear fechas como "hace X tiempo"
function time_elapsed_string(datetime, full = false) {
    const date = new Date(datetime);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval > 1) return `hace ${interval} años`;
    if (interval === 1) return 'hace un año';
    
    interval = Math.floor(seconds / 2592000);
    if (interval > 1) return `hace ${interval} meses`;
    if (interval === 1) return 'hace un mes';
    
    interval = Math.floor(seconds / 86400);
    if (interval > 1) return `hace ${interval} días`;
    if (interval === 1) return 'ayer';
    
    interval = Math.floor(seconds / 3600);
    if (interval > 1) return `hace ${interval} horas`;
    if (interval === 1) return 'hace una hora';
    
    interval = Math.floor(seconds / 60);
    if (interval > 1) return `hace ${interval} minutos`;
    if (interval === 1) return 'hace un minuto';
    
    return 'hace unos segundos';
}
</script>
