<?php
// Incluir configuración y funciones
require_once __DIR__ . '/includes/config.php';

// Establecer el título de la página
$pageTitle = 'Películas - ' . SITE_NAME;

// Incluir encabezado
include __DIR__ . '/includes/header.php';
?>

<!-- Agregar el nuevo CSS de la tabla -->
<link rel="stylesheet" href="/streaming-platform/assets/css/beautiful-table.css">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-white">Películas</h1>
        <div>
            <button class="btn btn-primary me-2">
                <i class="fas fa-plus me-2"></i>Agregar Película
            </button>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-filter me-2"></i>Filtrar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#">Todas</a></li>
                    <li><a class="dropdown-item" href="#">Gratis</a></li>
                    <li><a class="dropdown-item" href="#">Premium</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#">Más vistas</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="movie-table-container">
        <div class="table-responsive">
            <table class="data-table" data-type="movies">
                <thead>
                    <tr>
                        <th>Portada</th>
                        <th>Título</th>
                        <th>Año</th>
                        <th>Categoría</th>
                        <th>Duración</th>
                        <th>Tipo</th>
                        <th>Vistas</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr data-id="1">
                        <td data-label="Portada">
                            <div class="thumbnail-container">
                                <img src="/streaming-platform/assets/img/default-poster.svg" alt="El Padrino" class="thumbnail">
                            </div>
                        </td>
                        <td data-label="Título">El Padrino</td>
                        <td data-label="Año">1972</td>
                        <td data-label="Categoría">Drama, Crimen</td>
                        <td data-label="Duración">175 min</td>
                        <td data-label="Tipo"><span class="badge free">Gratis</span></td>
                        <td data-label="Vistas">12,458</td>
                        <td class="actions" data-label="Acciones">
                            <button class="btn btn-view" title="Ver"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-edit" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr data-id="2">
                        <td data-label="Portada">
                            <div class="thumbnail-container">
                                <img src="/streaming-platform/assets/img/default-poster.svg" alt="El Caballero Oscuro" class="thumbnail">
                            </div>
                        </td>
                        <td data-label="Título">El Caballero Oscuro</td>
                        <td data-label="Año">2008</td>
                        <td data-label="Categoría">Acción, Crimen, Drama</td>
                        <td data-label="Duración">152 min</td>
                        <td data-label="Tipo"><span class="badge premium">Premium</span></td>
                        <td data-label="Vistas">24,789</td>
                        <td class="actions" data-label="Acciones">
                            <button class="btn btn-view" title="Ver"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-edit" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                    <tr data-id="3">
                        <td data-label="Portada">
                            <div class="thumbnail-container">
                                <img src="/streaming-platform/assets/img/default-poster.svg" alt="Parásitos" class="thumbnail">
                            </div>
                        </td>
                        <td data-label="Título">Parásitos</td>
                        <td data-label="Año">2019</td>
                        <td data-label="Categoría">Drama, Thriller</td>
                        <td data-label="Duración">132 min</td>
                        <td data-label="Tipo"><span class="badge free">Gratis</span></td>
                        <td data-label="Vistas">18,342</td>
                        <td class="actions" data-label="Acciones">
                            <button class="btn btn-view" title="Ver"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-edit" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <div class="pagination-container">
            <ul class="pagination">
                <li class="page-item disabled">
                    <a class="page-link" href="#">Anterior</a>
                </li>
                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                <li class="page-item"><a class="page-link" href="#">2</a></li>
                <li class="page-item"><a class="page-link" href="#">3</a></li>
                <li class="page-item">
                    <a class="page-link" href="#">Siguiente</a>
                </li>
            </ul>
        </div>
    </div>

<!-- Modal para vista previa de película -->
<div class="modal fade" id="moviePreviewModal" tabindex="-1" aria-labelledby="moviePreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="moviePreviewLabel">Vista Previa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="ratio ratio-16x9 mb-4">
                    <iframe src="https://www.youtube.com/embed/sY1S34973zA" title="Vista previa de la película" allowfullscreen></iframe>
                </div>
                <h4>El Padrino</h4>
                <p class="text-muted">1972 • 175 min • Drama, Crimen</p>
                <p>La historia de la familia Corleone, una de las dinastías criminales más poderosas de Estados Unidos.</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-play-movie" data-movie-id="">
                        <i class="fas fa-play me-2"></i>Reproducir
                    </button>
                    <button class="btn btn-outline-light btn-add-to-list" data-movie-id="">
                        <i class="fas fa-plus me-2"></i><span>Mi lista</span>
                    </button>
                    <span class="premium-badge ms-auto badge bg-warning text-dark align-self-center">
                        <i class="fas fa-crown me-1"></i> Premium
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script para la funcionalidad de la tabla -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Datos de ejemplo para las películas
    const moviesData = {
        '1': {
            title: 'El Padrino',
            year: '1972',
            duration: '175 min',
            genres: 'Drama, Crimen',
            description: 'La historia de la familia Corleone, una de las dinastías criminales más poderosas de Estados Unidos.',
            trailer: 'sY1S34973zA',
            isPremium: false
        },
        '2': {
            title: 'El Caballero Oscuro',
            year: '2008',
            duration: '152 min',
            genres: 'Acción, Crimen, Drama',
            description: 'Batman tiene que mantener el equilibrio entre el héroe, el caballero y el justiciero.',
            trailer: 'EXeTwQWrcwY',
            isPremium: true
        },
        '3': {
            title: 'Parásitos',
            year: '2019',
            duration: '132 min',
            genres: 'Drama, Thriller',
            description: 'Los miembros de una familia sin recursos intentan sobrevivir a través de trabajos precarios.',
            trailer: 't4n4SSpvoFQ',
            isPremium: false
        }
    };

    // Función para actualizar el modal con los datos de la película
    function updateMovieModal(movieId) {
        const movie = moviesData[movieId];
        if (!movie) return;

        const modal = document.getElementById('moviePreviewModal');
        modal.querySelector('.modal-title').textContent = movie.title;
        modal.querySelector('h4').textContent = movie.title;
        modal.querySelector('.text-muted').textContent = `${movie.year} • ${movie.duration} • ${movie.genres}`;
        modal.querySelector('.modal-body p:last-of-type').textContent = movie.description;
        
        // Actualizar iframe del tráiler
        const iframe = modal.querySelector('iframe');
        iframe.src = `https://www.youtube.com/embed/${movie.trailer}?autoplay=0&rel=0&showinfo=0`;
        
        // Actualizar botón de reproducción
        const playButton = modal.querySelector('.btn-play-movie');
        playButton.onclick = function() {
            playMovie(movieId);
        };
        
        // Actualizar botón de lista
        const listButton = modal.querySelector('.btn-add-to-list');
        listButton.onclick = function() {
            toggleMovieInList(movieId);
        };
        
        // Mostrar u ocultar etiqueta premium
        const premiumBadge = modal.querySelector('.premium-badge');
        if (movie.isPremium) {
            premiumBadge.classList.remove('d-none');
        } else {
            premiumBadge.classList.add('d-none');
        }
        
        return new bootstrap.Modal(modal);
    }

    // Función para reproducir película
    function playMovie(movieId) {
        const movie = moviesData[movieId];
        if (!movie) return;
        
        if (movie.isPremium && !confirm('Esta es una película premium. ¿Deseas ver el tráiler o iniciar la reproducción con tu suscripción?')) {
            return;
        }
        
        // Aquí iría la lógica para reproducir la película
        alert(`Iniciando reproducción de: ${movie.title}`);
        // window.location.href = `/watch.php?id=${movieId}`; // Descomentar cuando exista la página de reproducción
    }

    // Función para agregar/quitar de la lista
    function toggleMovieInList(movieId) {
        const movie = moviesData[movieId];
        if (!movie) return;
        
        // Aquí iría la lógica para guardar en la base de datos
        const button = document.querySelector(`.btn-add-to-list[data-movie-id="${movieId}"]`);
        const isInList = button.classList.toggle('active');
        
        const icon = button.querySelector('i');
        const text = button.querySelector('span');
        
        if (isInList) {
            icon.className = 'fas fa-check me-2';
            text.textContent = 'En mi lista';
            showToast('success', `${movie.title} se ha añadido a tu lista`);
        } else {
            icon.className = 'fas fa-plus me-2';
            text.textContent = 'Mi lista';
            showToast('info', `${movie.title} se ha eliminado de tu lista`);
        }
    }

    // Función para mostrar notificaciones toast
    function showToast(type, message) {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml');
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
        toast.show();
        
        // Eliminar el toast después de que se oculte
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }

    // Manejar clic en botones de vista previa
    document.querySelectorAll('.btn-view').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const movieId = this.closest('tr').getAttribute('data-id');
            const modal = updateMovieModal(movieId);
            modal.show();
        });
    });

    // Manejar clic en botones de editar
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const movieId = this.closest('tr').getAttribute('data-id');
            // Redirigir a la página de edición
            window.location.href = `/admin/edit-movie.php?id=${movieId}`;
        });
    });

    // Manejar clic en botones de eliminar
    document.querySelectorAll('.btn-delete').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const movieId = this.closest('tr').getAttribute('data-id');
            const movieTitle = moviesData[movieId]?.title || 'esta película';
            
            if (confirm(`¿Estás seguro de que deseas eliminar "${movieTitle}"? Esta acción no se puede deshacer.`)) {
                // Aquí iría la lógica para eliminar la película de la base de datos
                fetch(`/api/movies/${movieId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Eliminar la fila de la tabla
                        this.closest('tr').remove();
                        showToast('success', `"${movieTitle}" ha sido eliminada correctamente`);
                    } else {
                        throw new Error(data.message || 'Error al eliminar la película');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('danger', `Error al eliminar la película: ${error.message}`);
                });
            }
        });
    });

    // Inicializar tooltips dinámicamente
    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Inicializar tooltips al cargar la página y después de cambios dinámicos
    initTooltips();
    
    // Manejar eventos de los botones del modal
    document.querySelectorAll('.btn-play-movie').forEach(button => {
        button.addEventListener('click', function() {
            const movieId = this.getAttribute('data-movie-id');
            playMovie(movieId);
        });
    });
    
    document.querySelectorAll('.btn-add-to-list').forEach(button => {
        button.addEventListener('click', function() {
            const movieId = this.getAttribute('data-movie-id');
            toggleMovieInList(movieId);
        });
    });
});
</script>

<!-- Contenedor para notificaciones toast -->
<div id="toastContainer" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <!-- Los toasts se agregarán aquí dinámicamente -->
</div>

<?php
// Incluir pie de página
include __DIR__ . '/includes/footer.php';
?>
