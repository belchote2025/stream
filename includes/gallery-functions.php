<?php
/**
 * Funciones para la galería de contenido
 */

/**
 * Obtiene el contenido para la galería
 * 
 * @param PDO $db Conexión a la base de datos
 * @param array $filters Filtros de búsqueda
 * @return array Contenido formateado
 */
function getGalleryContent($db, $filters = []) {
    try {
        // Consulta base
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.release_year as year,
                c.duration,
                c.rating,
                c.poster_url,
                c.description,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ',') as genres
            FROM content c
            LEFT JOIN content_genres cg ON c.id = cg.content_id
            LEFT JOIN genres g ON cg.genre_id = g.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if (!empty($filters['type'])) {
            $query .= " AND c.type = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['genre'])) {
            $query .= " AND g.id = :genre_id";
            $params[':genre_id'] = (int)$filters['genre'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (c.title LIKE :search OR c.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['year'])) {
            $query .= " AND c.release_year = :year";
            $params[':year'] = (int)$filters['year'];
        }
        
        if (!empty($filters['min_rating'])) {
            $query .= " AND c.rating >= :min_rating";
            $params[':min_rating'] = (float)$filters['min_rating'];
        }
        
        // Agrupar por contenido
        $query .= " GROUP BY c.id";
        
        // Ordenar
        $orderBy = $filters['order_by'] ?? 'c.release_date';
        $orderDir = isset($filters['order_dir']) && strtoupper($filters['order_dir']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY $orderBy $orderDir";
        
        // Limitar resultados (paginación)
        if (isset($filters['limit'])) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int)$filters['limit'];
            
            if (isset($filters['offset'])) {
                $query .= " OFFSET :offset";
                $params[':offset'] = (int)$filters['offset'];
            }
        }
        
        $stmt = $db->prepare($query);
        
        // Vincular parámetros
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear resultados
        $formattedResults = [];
        
        foreach ($results as $row) {
            // Convertir géneros de cadena a array
            $genres = !empty($row['genres']) ? explode(',', $row['genres']) : [];
            
            $formattedResults[] = [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'type' => $row['type'], // 'movie' o 'series'
                'year' => (int)$row['year'],
                'duration' => (int)$row['duration'],
                'rating' => $row['rating'] ? (float)$row['rating'] : null,
                'poster_url' => $row['poster_url'] ?: '/streaming-platform/assets/img/default-poster.svg',
                'poster' => $row['poster_url'] ?: '/streaming-platform/assets/img/default-poster.svg',
                'backdrop_url' => isset($row['backdrop_url']) ? $row['backdrop_url'] : null,
                'description' => $row['description'],
                'genres' => $genres,
                'url' => "/content/" . $row['type'] . "/" . $row['id']
            ];
        }
        
        return $formattedResults;
        
    } catch (PDOException $e) {
        error_log("Error en getGalleryContent: " . $e->getMessage());
        return [];
    }
}

/**
 * Renderiza la galería de contenido
 * 
 * @param array $content Array de contenido
 * @param bool $showFilters Mostrar filtros
 * @return string HTML de la galería
 */
function renderGallery($content, $showFilters = true) {
    ob_start();
    ?>
    <div class="content-gallery-container">
        <?php if ($showFilters): ?>
        <div class="gallery-filters mb-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="search-box">
                        <input type="text" class="form-control" id="searchContent" placeholder="Buscar películas y series...">
                        <button class="btn btn-search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-filter="all">Todo</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="movie">Películas</button>
                        <button type="button" class="btn btn-outline-secondary" data-filter="series">Series</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="content-gallery">
            <?php if (empty($content)): ?>
                <div class="col-12 text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-film fa-3x mb-3"></i>
                        <h4>No se encontró contenido</h4>
                        <p class="text-muted">Intenta con otros filtros de búsqueda</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($content as $item): ?>
                    <div class="content-card" data-id="<?php echo $item['id']; ?>" data-type="<?php echo $item['type']; ?>">
                        <div class="content-type"><?php echo $item['type'] === 'movie' ? 'Película' : 'Serie'; ?></div>
                        
                        <div class="content-poster">
                            <img 
                                src="<?php echo htmlspecialchars($item['poster']); ?>" 
                                alt="<?php echo htmlspecialchars($item['title']); ?>"
                                loading="lazy"
                                onerror="this.onerror=null; this.src='/streaming-platform/assets/img/default-poster.svg'"
                            >
                            
                            <div class="content-overlay">
                                <div class="content-actions">
                                    <button class="btn-play" data-id="<?php echo $item['id']; ?>" title="Reproducir">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn-add" data-id="<?php echo $item['id']; ?>" title="Añadir a mi lista">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                
                                <div class="content-info">
                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                    
                                    <div class="meta">
                                        <span class="year"><?php echo $item['year']; ?></span>
                                        <?php if ($item['duration']): ?>
                                            <span class="duration">
                                                <?php 
                                                    if ($item['type'] === 'movie') {
                                                        echo floor($item['duration'] / 60) . 'h ' . ($item['duration'] % 60) . 'm';
                                                    } else {
                                                        echo $item['duration'] . ' min';
                                                    }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($item['rating']): ?>
                                            <span class="rating">
                                                ⭐ <?php echo number_format($item['rating'], 1); ?>/10
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($item['genres'])): ?>
                                        <div class="genres">
                                            <?php foreach (array_slice($item['genres'], 0, 3) as $genre): ?>
                                                <span><?php echo htmlspecialchars($genre); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['description'])): ?>
                                        <p class="description">
                                            <?php echo mb_strimwidth(htmlspecialchars($item['description']), 0, 150, '...'); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="content-details">
                            <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                            <span class="year"><?php echo $item['year']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Paginación -->
        <?php if (isset($filters['total_pages']) && $filters['total_pages'] > 1): ?>
            <nav aria-label="Paginación de la galería" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $filters['page'] <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $filters['page'] - 1; ?>">Anterior</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $filters['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i == $filters['page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $filters['page'] >= $filters['total_pages'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $filters['page'] + 1; ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtiene los géneros para los filtros
 * 
 * @param PDO $db Conexión a la base de datos
 * @return array Lista de géneros
 */
function getGenresForFilter($db) {
    try {
        $stmt = $db->query("SELECT id, name FROM genres ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getGenresForFilter: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene el contenido destacado para el carrusel principal
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $limit Límite de resultados
 * @return array Contenido destacado
 */
function getFeaturedContent($db, $limit = 5) {
    try {
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.release_year as year,
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description,
                c.trailer_url
            FROM content c
            WHERE c.featured = 1
            ORDER BY c.release_date DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getFeaturedContent: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene el contenido recientemente agregado
 * 
 * @param PDO $db Conexión a la base de datos
 * @param string $type Tipo de contenido (movie/series)
 * @param int $limit Límite de resultados
 * @return array Contenido reciente
 */
function getRecentlyAdded($db, $type = null, $limit = 10) {
    try {
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.release_year as year,
                c.poster_url,
                c.added_date
            FROM content c
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($type) {
            $query .= " AND c.type = :type";
            $params[':type'] = $type;
        }
        
        $query .= " ORDER BY c.added_date DESC LIMIT :limit";
        $params[':limit'] = (int)$limit;
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getRecentlyAdded: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene el contenido más visto
 * 
 * @param PDO $db Conexión a la base de datos
 * @param string $type Tipo de contenido (movie/series)
 * @param int $limit Límite de resultados
 * @return array Contenido más visto
 */
function getMostViewed($db, $type = null, $limit = 10) {
    try {
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.release_year as year,
                c.poster_url,
                COUNT(v.id) as view_count
            FROM content c
            LEFT JOIN views v ON c.id = v.content_id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($type) {
            $query .= " AND c.type = :type";
            $params[':type'] = $type;
        }
        
        $query .= " GROUP BY c.id ORDER BY view_count DESC, c.title LIMIT :limit";
        $params[':limit'] = (int)$limit;
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getMostViewed: " . $e->getMessage());
        return [];
    }
}

/**
 * Crea una tarjeta de contenido estilo Netflix
 * 
 * @param array $item Datos del contenido
 * @return string HTML de la tarjeta
 */
function createContentCard($item) {
    // Usar helper para obtener URL de imagen
    require_once __DIR__ . '/image-helper.php';
    
    $posterUrl = getImageUrl($item['poster_url'] ?? '', '/streaming-platform/assets/img/default-poster.svg');
    $title = htmlspecialchars($item['title']);
    $year = !empty($item['release_year']) ? $item['release_year'] : (isset($item['year']) ? $item['year'] : '');
    $type = $item['type'] ?? 'movie';
    $id = $item['id'] ?? 0;
    
    $badges = '';
    if (!empty($item['is_premium'])) {
        $badges .= '<span class="premium-badge">PREMIUM</span>';
    }
    if (!empty($item['torrent_magnet'])) {
        $badges .= '<span class="torrent-badge" title="Disponible por Torrent"><i class="fas fa-magnet"></i></span>';
    }
    
    $rating = '';
    if (!empty($item['rating'])) {
        $rating = '<span>⭐ ' . number_format($item['rating'], 1) . '</span>';
    }
    
    $duration = '';
    if (!empty($item['duration'])) {
        if ($type === 'movie') {
            $hours = floor($item['duration'] / 60);
            $minutes = $item['duration'] % 60;
            $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
        } else {
            $duration = $item['duration'] . ' min';
        }
    }
    
    ob_start();
    ?>
    <div class="content-card" data-id="<?php echo $id; ?>" data-type="<?php echo $type; ?>" onclick="window.location.href='/streaming-platform/content-detail.php?id=<?php echo $id; ?>';" style="cursor: pointer;">
        <?php if (!empty($badges)): ?>
            <div class="content-badges">
                <?php echo $badges; ?>
            </div>
        <?php endif; ?>
        
        <img 
            src="<?php echo $posterUrl; ?>" 
            alt="<?php echo $title; ?>"
            loading="lazy"
            onerror="this.onerror=null; this.src='/streaming-platform/assets/img/default-poster.svg'; this.style.background='linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%)';"
            style="background: linear-gradient(135deg, #1f1f1f 0%, #2d2d2d 100%);"
        >
        
        <div class="content-info">
            <h3><?php echo $title; ?></h3>
            <div class="content-meta">
                <?php if ($year): ?>
                    <span><?php echo $year; ?></span>
                <?php endif; ?>
                <?php if ($duration): ?>
                    <?php if ($year): ?><span>•</span><?php endif; ?>
                    <span><?php echo $duration; ?></span>
                <?php endif; ?>
                <?php if ($rating): ?>
                    <?php if ($year || $duration): ?><span>•</span><?php endif; ?>
                    <?php echo $rating; ?>
                <?php endif; ?>
            </div>
            <div class="content-actions">
                <button class="action-btn" data-action="play" data-id="<?php echo $id; ?>" data-type="<?php echo $type; ?>" title="Reproducir" onclick="event.stopPropagation(); window.location.href='/streaming-platform/watch.php?id=<?php echo $id; ?>';">
                    <i class="fas fa-play"></i>
                </button>
                <button class="action-btn" data-action="add" data-id="<?php echo $id; ?>" title="Añadir a Mi lista" onclick="event.stopPropagation(); handleAddToList(<?php echo $id; ?>);">
                    <i class="fas fa-plus"></i>
                </button>
                <button class="action-btn" data-action="info" data-id="<?php echo $id; ?>" title="Más información" onclick="event.stopPropagation(); window.location.href='/streaming-platform/content-detail.php?id=<?php echo $id; ?>';">
                    <i class="fas fa-info-circle"></i>
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
