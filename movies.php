<?php
// Incluir configuración y funciones
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/gallery-functions.php';
require_once __DIR__ . '/includes/image-helper.php';

// Establecer el título de la página
$pageTitle = 'Películas - ' . SITE_NAME;

// Obtener conexión a la base de datos
$db = getDbConnection();
$baseUrl = rtrim(SITE_URL, '/');

// Obtener películas
$movies = getRecentlyAdded($db, 'movie', 50);

// Incluir encabezado
include __DIR__ . '/includes/header.php';
?>

<style>
.movies-page {
    padding-top: 100px;
    min-height: 100vh;
    background: linear-gradient(180deg, #141414 0%, #1a1a1a 100%);
}

.page-header {
    padding: 3rem 4% 2rem;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.7) 0%, transparent 100%);
}

.page-title {
    font-size: clamp(2rem, 4vw, 3.5rem);
    font-weight: 900;
    margin-bottom: 1rem;
    background: linear-gradient(135deg, #ffffff 0%, #e50914 50%, #f5f5f5 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 10px rgba(229, 9, 20, 0.3);
    color: #fff;
}

.movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 2rem 4%;
}

.movie-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent;
    position: relative;
}

.movie-card:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #e50914;
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 10px 30px rgba(229, 9, 20, 0.3);
}

.movie-card:hover .movie-overlay {
    display: flex !important;
    opacity: 1 !important;
}

.movie-poster-wrapper {
    position: relative;
    overflow: hidden;
}

.movie-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 2;
}

.movie-actions {
    margin-top: 0.75rem;
    display: flex;
    gap: 0.5rem;
}

.movie-actions .btn {
    transition: all 0.3s ease;
}

.movie-actions .btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(229, 9, 20, 0.4);
}

.movie-poster {
    width: 100%;
    height: 300px;
    object-fit: cover;
    display: block;
}

.movie-info {
    padding: 1rem;
}

.movie-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.movie-meta {
    color: #999;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.movie-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.movie-badge.premium {
    background: rgba(255, 193, 7, 0.9);
    color: #000;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #999;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    display: block;
}
</style>

<div class="movies-page">
    <div class="page-header">
        <h1 class="page-title">Películas</h1>
        <p class="page-subtitle" style="color: #999; font-size: 1.1rem;">Explora nuestra colección de películas</p>
    </div>
    
    <div class="movies-grid">
        <?php if (!empty($movies)): ?>
            <?php foreach ($movies as $movie): 
                $posterUrl = getImageUrl($movie['poster_url'] ?? $movie['backdrop_url'] ?? '', '/assets/img/default-poster.svg');
                $isPremium = isset($movie['is_premium']) && $movie['is_premium'];
                $year = $movie['year'] ?? $movie['release_year'] ?? '';
                $duration = $movie['duration'] ?? '';
                $rating = isset($movie['rating']) && $movie['rating'] > 0 ? number_format($movie['rating'], 1) : '';
            ?>
                <div class="movie-card">
                    <?php if ($isPremium): ?>
                        <span class="movie-badge premium">
                            <i class="fas fa-crown"></i> PREMIUM
                        </span>
                    <?php endif; ?>
                    <div class="movie-poster-wrapper" style="position: relative;">
                        <img src="<?php echo htmlspecialchars($posterUrl); ?>" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                             class="movie-poster" 
                             loading="lazy"
                             style="cursor: pointer; background: linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%);"
                             onerror="this.onerror=null; this.src='<?php echo $baseUrl; ?>/assets/img/default-poster.svg'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
                             onclick="window.location.href='<?php echo $baseUrl; ?>/content-detail.php?id=<?php echo $movie['id']; ?>'">
                        <div class="movie-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; gap: 1rem; opacity: 0; transition: opacity 0.3s;">
                            <button class="btn btn-primary play-movie-btn" 
                                    data-id="<?php echo $movie['id']; ?>" 
                                    data-type="movie"
                                    style="padding: 0.75rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; background: #e50914; color: #fff; transition: all 0.3s ease;">
                                <i class="fas fa-play"></i> Reproducir
                            </button>
                            <button class="btn btn-secondary" onclick="event.stopPropagation(); window.location.href='<?php echo $baseUrl; ?>/content-detail.php?id=<?php echo $movie['id']; ?>'" style="padding: 0.75rem 1.5rem; border-radius: 8px; border: 2px solid rgba(255,255,255,0.5); background: rgba(0,0,0,0.5); color: #fff; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Detalles
                            </button>
                        </div>
                    </div>
                    <div class="movie-info">
                        <div class="movie-title" onclick="window.location.href='<?php echo $baseUrl; ?>/content-detail.php?id=<?php echo $movie['id']; ?>'" style="cursor: pointer;"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="movie-meta">
                            <?php if ($year): ?>
                                <span><?php echo $year; ?></span>
                            <?php endif; ?>
                            <?php if ($duration): ?>
                                <span>•</span>
                                <span><?php echo $duration; ?> min</span>
                            <?php endif; ?>
                            <?php if ($rating): ?>
                                <span>•</span>
                                <span><i class="fas fa-star" style="color: #ffc107;"></i> <?php echo $rating; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="movie-actions" style="margin-top: 0.75rem; display: flex; gap: 0.5rem;">
                            <button class="btn btn-primary play-movie-btn" 
                                    data-id="<?php echo $movie['id']; ?>" 
                                    data-type="movie"
                                    style="flex: 1; padding: 0.5rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: #e50914; color: #fff; transition: all 0.3s ease;">
                                <i class="fas fa-play"></i> Reproducir
                            </button>
                            <button class="btn btn-secondary" 
                                    onclick="event.stopPropagation(); window.location.href='<?php echo $baseUrl; ?>/content-detail.php?id=<?php echo $movie['id']; ?>'" 
                                    style="padding: 0.5rem 1rem; border-radius: 6px; border: 2px solid rgba(255,255,255,0.3); background: transparent; color: #fff; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1 / -1;">
                <i class="fas fa-film"></i>
                <h3>No hay películas disponibles</h3>
                <p>Próximamente agregaremos más contenido</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Función playContent para movies.php - Definida globalmente para evitar conflictos
// Esta función redirige a watch.php o usa el modal si está disponible
(function() {
    'use strict';
    
    const BASE_URL = '<?php echo $baseUrl; ?>';
    
    // Función principal que siempre funciona
    window.playMovieContent = function(id, type) {
        if (!id || !type) {
            console.error('playMovieContent: Se requieren id y type', { id, type });
            alert('Error: No se pudo reproducir el contenido');
            return false;
        }
        
        console.log('playMovieContent llamado:', { id, type, BASE_URL });
        
        // Verificar si hay un modal de video disponible (desde netflix-gallery.js)
        const videoModal = document.getElementById('videoPlayerModal');
        
        // Intentar usar playContentFromGallery primero (modal de netflix-gallery.js)
        if (videoModal && typeof window.playContentFromGallery === 'function') {
            console.log('Usando playContentFromGallery (modal)');
            try {
                window.playContentFromGallery(id, type);
                return true;
            } catch (error) {
                console.error('Error en playContentFromGallery:', error);
            }
        }
        
        // Fallback: redirigir directamente a watch.php (siempre funciona)
        console.log('Redirigiendo a watch.php');
        window.location.href = BASE_URL + '/watch.php?id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type);
        return true;
    };
    
    // Alias para compatibilidad - se sobrescribe después de cargar scripts si es necesario
    window.playContentMovies = window.playMovieContent;
    
    // Función local para esta página
    function playContent(id, type) {
        return window.playMovieContent(id, type);
    }
    
    // Exponer globalmente después de que se carguen los scripts
    window.addEventListener('load', function() {
        // Si no hay otra función playContent, usar la nuestra
        if (typeof window.playContent === 'undefined' || !window.playContent.toString().includes('watch.php')) {
            window.playContent = window.playMovieContent;
        }
    });
    
    // Hacer disponible inmediatamente también
    window.playContent = window.playMovieContent;
})();

// Asegurar que los botones funcionen correctamente
document.addEventListener('DOMContentLoaded', function() {
    console.log('Movies.php: DOMContentLoaded');
    
    // Agregar event listeners a todos los botones de reproducción
    function setupPlayButtons() {
        const BASE_URL = '<?php echo $baseUrl; ?>';
        
        document.querySelectorAll('.play-movie-btn').forEach(button => {
            // Evitar agregar múltiples listeners
            if (button.dataset.listenerAdded === 'true') {
                return;
            }
            
            const id = button.getAttribute('data-id');
            const type = button.getAttribute('data-type') || 'movie';
            
            if (id) {
                // Remover cualquier onclick existente
                button.removeAttribute('onclick');
                
                // Función de click
                const handleClick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Botón clickeado:', { id, type, button: this });
                    
                    // Verificar que la función esté disponible
                    if (typeof window.playMovieContent === 'function') {
                        window.playMovieContent(parseInt(id), type);
                    } else if (typeof window.playContent === 'function') {
                        window.playContent(parseInt(id), type);
                    } else {
                        // Fallback directo - siempre funciona
                        console.log('Usando fallback directo a watch.php');
                        window.location.href = BASE_URL + '/watch.php?id=' + encodeURIComponent(id) + '&type=' + encodeURIComponent(type);
                    }
                };
                
                // Agregar event listener
                button.addEventListener('click', handleClick, { once: false });
                button.dataset.listenerAdded = 'true';
            }
        });
    }
    
    // Ejecutar inmediatamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupPlayButtons);
    } else {
        setupPlayButtons();
    }
    
    // También ejecutar después de que se carguen todos los scripts
    window.addEventListener('load', setupPlayButtons);
    
    // Mejorar el hover de las tarjetas
    document.querySelectorAll('.movie-card').forEach(card => {
        const overlay = card.querySelector('.movie-overlay');
        const posterWrapper = card.querySelector('.movie-poster-wrapper');
        
        if (overlay && posterWrapper) {
            posterWrapper.addEventListener('mouseenter', function() {
                overlay.style.display = 'flex';
                setTimeout(() => {
                    overlay.style.opacity = '1';
                }, 10);
            });
            
            posterWrapper.addEventListener('mouseleave', function() {
                overlay.style.opacity = '0';
                setTimeout(() => {
                    overlay.style.display = 'none';
                }, 300);
            });
        }
    });
    
    // Verificar que playContent esté disponible
    console.log('playMovieContent disponible:', typeof window.playMovieContent === 'function');
    console.log('playContent disponible:', typeof playContent === 'function');
});
</script>

<?php
// Incluir pie de página
include __DIR__ . '/includes/footer.php';
?>
