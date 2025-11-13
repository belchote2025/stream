<?php
// Incluir configuración y funciones
require_once __DIR__ . '/../includes/config.php';

// Obtener conexión a la base de datos
$db = getDbConnection();

// Verificar si el usuario está autenticado (deberías implementar tu propio sistema de autenticación)
// Por ahora, lo dejamos comentado para desarrollo
/*
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
*/

// Establecer el título de la página
$pageTitle = 'Editar Película - ' . SITE_NAME;
$movieId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$movie = null;
$genres = [];
$selectedGenres = [];

try {
    // Obtener la película
    if ($movieId > 0) {
        $stmt = $db->prepare("SELECT * FROM content WHERE id = :id AND type = 'movie'");
        $stmt->execute([':id' => $movieId]);
        $movie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$movie) {
            header('HTTP/1.0 404 Not Found');
            die('Película no encontrada');
        }
        
        // Obtener géneros seleccionados
        $stmt = $db->prepare("SELECT genre_id FROM content_genres WHERE content_id = :content_id");
        $stmt->execute([':content_id' => $movieId]);
        $selectedGenres = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Obtener todos los géneros disponibles
    $stmt = $db->query("SELECT id, name FROM genres ORDER BY name");
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Error al cargar la película: ' . $e->getMessage());
    die('Error al cargar la película. Por favor, inténtalo de nuevo.');
}

// Incluir encabezado
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><?php echo $movieId ? 'Editar Película' : 'Nueva Película'; ?></h4>
                </div>
                <div class="card-body">
                    <form id="movieForm" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo $movieId; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Título</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           value="<?php echo htmlspecialchars($movie['title'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Descripción</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required><?php 
                                        echo htmlspecialchars($movie['description'] ?? ''); 
                                    ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="release_year" class="form-label">Año de lanzamiento</label>
                                            <input type="number" class="form-control" id="release_year" name="release_year" 
                                                   min="1888" max="<?php echo date('Y') + 5; ?>" required
                                                   value="<?php echo $movie['release_year'] ?? date('Y'); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="duration" class="form-label">Duración (minutos)</label>
                                            <input type="number" class="form-control" id="duration" name="duration" 
                                                   min="1" required
                                                   value="<?php echo $movie['duration'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="age_rating" class="form-label">Clasificación por edad</label>
                                            <select class="form-select" id="age_rating" name="age_rating">
                                                <option value="G" <?php echo (isset($movie['age_rating']) && $movie['age_rating'] === 'G') ? 'selected' : ''; ?>>G - Para todos los públicos</option>
                                                <option value="PG" <?php echo (isset($movie['age_rating']) && $movie['age_rating'] === 'PG') ? 'selected' : ''; ?>>PG - Guía paterna sugerida</option>
                                                <option value="PG-13" <?php echo (isset($movie['age_rating']) && $movie['age_rating'] === 'PG-13') ? 'selected' : ''; ?>>PG-13 - Mayores de 13 años</option>
                                                <option value="R" <?php echo (isset($movie['age_rating']) && $movie['age_rating'] === 'R') ? 'selected' : ''; ?>>R - Mayores de 17 años</option>
                                                <option value="NC-17" <?php echo (isset($movie['age_rating']) && $movie['age_rating'] === 'NC-17') ? 'selected' : ''; ?>>NC-17 - Solo adultos</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="rating" class="form-label">Calificación (0-10)</label>
                                            <input type="number" class="form-control" id="rating" name="rating" 
                                                   min="0" max="10" step="0.1"
                                                   value="<?php echo $movie['rating'] ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Géneros</label>
                                    <div class="row">
                                        <?php foreach ($genres as $genre): ?>
                                            <div class="col-md-4 col-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="genres[]" value="<?php echo $genre['id']; ?>"
                                                           id="genre_<?php echo $genre['id']; ?>"
                                                           <?php echo in_array($genre['id'], $selectedGenres) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="genre_<?php echo $genre['id']; ?>">
                                                        <?php echo htmlspecialchars($genre['name']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="poster" class="form-label">Póster</label>
                                    <input type="file" class="form-control" id="poster" name="poster" accept="image/*">
                                    <?php if (!empty($movie['poster_url'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" 
                                                 alt="Póster actual" class="img-thumbnail" style="max-height: 200px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="backdrop" class="form-label">Imagen de fondo</label>
                                    <input type="file" class="form-control" id="backdrop" name="backdrop" accept="image/*">
                                    <?php if (!empty($movie['backdrop_url'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo htmlspecialchars($movie['backdrop_url']); ?>" 
                                                 alt="Fondo actual" class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trailer_url" class="form-label">URL del tráiler (YouTube)</label>
                                    <input type="url" class="form-control" id="trailer_url" name="trailer_url"
                                           value="<?php echo htmlspecialchars($movie['trailer_url'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                           value="1" <?php echo (!empty($movie['is_featured'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">Destacada</label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_trending" name="is_trending"
                                           value="1" <?php echo (!empty($movie['is_trending'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_trending">Tendencia</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/movies.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Volver
                            </a>
                            <div>
                                <button type="button" id="saveBtn" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Guardar
                                </button>
                                <?php if ($movieId): ?>
                                    <button type="button" id="deleteBtn" class="btn btn-danger">
                                        <i class="fas fa-trash me-2"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar eliminación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                ¿Estás seguro de que deseas eliminar esta película? Esta acción no se puede deshacer.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts necesarios -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('movieForm');
    const saveBtn = document.getElementById('saveBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    
    // Manejar el envío del formulario
    saveBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const movieId = formData.get('id') || '';
        const isNew = movieId === '' || movieId === '0';
        
        // Mostrar indicador de carga
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...';
        
        // Enviar datos al servidor
        fetch(`/api/movies/${isNew ? '' : movieId}`, {
            method: isNew ? 'POST' : 'PUT',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensaje de éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: isNew ? 'Película creada correctamente' : 'Cambios guardados correctamente',
                    showConfirmButton: false,
                    timer: 1500
                });
                
                // Redirigir después de guardar si es una película nueva
                if (isNew && data.id) {
                    setTimeout(() => {
                        window.location.href = `/admin/edit-movie.php?id=${data.id}`;
                    }, 1500);
                }
            } else {
                throw new Error(data.message || 'Error al guardar la película');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Ocurrió un error al guardar la película',
                confirmButtonText: 'Aceptar'
            });
        })
        .finally(() => {
            // Restaurar el botón
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Guardar';
        });
    });
    
    // Manejar la eliminación de la película
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            confirmModal.show();
        });
        
        confirmDeleteBtn.addEventListener('click', function() {
            const movieId = form.querySelector('input[name="id"]').value;
            
            // Mostrar indicador de carga
            deleteBtn.disabled = true;
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Eliminando...';
            
            // Enviar solicitud de eliminación
            fetch(`/api/movies/${movieId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito y redirigir
                    Swal.fire({
                        icon: 'success',
                        title: '¡Eliminada!',
                        text: 'La película ha sido eliminada correctamente',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    setTimeout(() => {
                        window.location.href = '/movies.php';
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Error al eliminar la película');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Ocurrió un error al eliminar la película',
                    confirmButtonText: 'Aceptar'
                });
            })
            .finally(() => {
                confirmModal.hide();
                deleteBtn.disabled = false;
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Eliminar';
            });
        });
    }
    
    // Validar el formulario antes de enviar
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        saveBtn.click();
    });
});
</script>

<?php
// Incluir pie de página
include __DIR__ . '/../includes/footer.php';
?>
