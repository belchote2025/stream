<?php
/**
 * Funciones para la galería de contenido
 */

/**
 * Obtiene las últimas novedades con trailers para el carrusel
 * 
 * @param PDO $db Conexión a la base de datos
 * @param int $limit Límite de resultados
 * @return array Contenido con trailers
 */
function getLatestWithTrailers($db, $limit = 5) {
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
                c.trailer_url,
                c.video_url,
                c.added_date
            FROM content c
            WHERE c.trailer_url IS NOT NULL 
            AND c.trailer_url != ''
            AND c.trailer_url != 'null'
            ORDER BY c.added_date DESC, c.release_year DESC
            LIMIT :limit
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getLatestWithTrailers: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene el contenido destacado
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
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description
            FROM content c
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($type) {
            $query .= " AND c.type = :type";
            $params[':type'] = $type;
        }
        
        $query .= " ORDER BY c.added_date DESC LIMIT :limit";
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
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
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description,
                c.views
            FROM content c
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($type) {
            $query .= " AND c.type = :type";
            $params[':type'] = $type;
        }
        
        $query .= " ORDER BY c.views DESC LIMIT :limit";
        
        $stmt = $db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getMostViewed: " . $e->getMessage());
        return [];
    }
}

/**
 * Crea una tarjeta de contenido HTML estilo Netflix
 * 
 * @param array $item Array con los datos del contenido
 * @return string HTML de la tarjeta
 */
function createContentCard($item) {
    require_once __DIR__ . '/image-helper.php';
    
    // Asegurar que todos los campos necesarios existan
    $id = isset($item['id']) ? (int)$item['id'] : 0;
    $title = isset($item['title']) ? htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') : 'Sin título';
    $type = isset($item['type']) ? htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') : 'movie';
    $releaseYear = isset($item['release_year']) ? (int)$item['release_year'] : (isset($item['year']) ? (int)$item['year'] : '');
    $duration = isset($item['duration']) ? htmlspecialchars($item['duration'], ENT_QUOTES, 'UTF-8') : '';
    $rating = isset($item['rating']) ? number_format((float)$item['rating'], 1) : '';
    $posterUrl = getImageUrl($item['poster_url'] ?? $item['backdrop_url'] ?? '', '/streaming-platform/assets/img/default-poster.svg');
    $isPremium = isset($item['is_premium']) ? (bool)$item['is_premium'] : false;
    $slug = isset($item['slug']) ? htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') : '';
    
    // Construir URL de detalle
    $detailUrl = '/streaming-platform/content.php?id=' . $id;
    if ($slug) {
        $detailUrl = '/streaming-platform/content/' . $slug;
    }
    
    // Badge premium
    $premiumBadge = $isPremium ? '<span class="premium-badge">PREMIUM</span>' : '';
    
    // Meta información
    $meta = [];
    if ($releaseYear) {
        $meta[] = $releaseYear;
    }
    if ($duration) {
        $meta[] = $type === 'movie' ? $duration . ' min' : $duration;
    }
    if ($rating) {
        $meta[] = '⭐ ' . $rating;
    }
    $metaStr = !empty($meta) ? '<div class="content-meta">' . implode(' • ', $meta) . '</div>' : '';
    
    // Construir HTML de la tarjeta
    $html = '<div class="content-card" data-id="' . $id . '" data-type="' . $type . '">';
    $html .= '<a href="' . htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') . '" class="content-card-link">';
    $html .= '<img src="' . htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $title . '" loading="lazy">';
    
    if ($premiumBadge) {
        $html .= '<div class="content-badges">' . $premiumBadge . '</div>';
    }
    
    $html .= '<div class="content-info">';
    $html .= '<h3>' . $title . '</h3>';
    if ($metaStr) {
        $html .= $metaStr;
    }
    $html .= '<button class="btn btn-sm btn-primary play-btn" data-id="' . $id . '" data-type="' . $type . '" onclick="event.preventDefault(); event.stopPropagation(); if(typeof playContent === \'function\') { playContent(' . $id . ', \'' . $type . '\'); } else { window.location.href=\'/streaming-platform/watch.php?id=' . $id . '\'; }">';
    $html .= '<i class="fas fa-play"></i> Reproducir';
    $html .= '</button>';
    $html .= '</div>'; // .content-info
    $html .= '</a>'; // .content-card-link
    $html .= '</div>'; // .content-card
    
    return $html;
}

/**
 * Obtiene contenido para la galería con filtros
 * 
 * @param PDO $db Conexión a la base de datos
 * @param array $filters Filtros de búsqueda
 * @return array Contenido filtrado
 */
function getGalleryContent($db, $filters = []) {
    try {
        $query = "
            SELECT 
                c.id, 
                c.title, 
                c.type, 
                c.release_year as year,
                c.release_date,
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description,
                c.trailer_url,
                c.video_url,
                c.is_premium,
                c.views,
                c.added_date,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') as genres
            FROM content c
            LEFT JOIN content_genres cg ON c.id = cg.content_id
            LEFT JOIN genres g ON cg.genre_id = g.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro por tipo
        if (!empty($filters['type'])) {
            $query .= " AND c.type = :type";
            $params[':type'] = $filters['type'];
        }
        
        // Filtro por género
        if (!empty($filters['genre'])) {
            $query .= " AND c.id IN (
                SELECT content_id FROM content_genres WHERE genre_id = :genre_id
            )";
            $params[':genre_id'] = (int)$filters['genre'];
        }
        
        // Búsqueda por texto
        if (!empty($filters['search'])) {
            $query .= " AND (c.title LIKE :search OR c.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Filtro por año
        if (!empty($filters['year'])) {
            $query .= " AND (YEAR(c.release_date) = :year OR c.release_year = :year)";
            $params[':year'] = (int)$filters['year'];
        }
        
        // Filtro por rating mínimo
        if (!empty($filters['min_rating'])) {
            $query .= " AND c.rating >= :min_rating";
            $params[':min_rating'] = (float)$filters['min_rating'];
        }
        
        // Agrupar por contenido
        $query .= " GROUP BY c.id";
        
        // Ordenar
        $orderBy = $filters['order_by'] ?? 'c.release_date';
        $orderDir = strtoupper($filters['order_dir'] ?? 'DESC');
        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }
        $query .= " ORDER BY " . $orderBy . " " . $orderDir;
        
        // Límite y paginación
        $limit = (int)($filters['limit'] ?? 24);
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $query .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($query);
        
        // Bind de parámetros
        foreach ($params as $key => $value) {
            if (strpos($key, ':search') !== false) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            } elseif (strpos($key, ':type') !== false) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            } else {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getGalleryContent: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene géneros para usar en filtros
 * 
 * @param PDO $db Conexión a la base de datos
 * @return array Lista de géneros
 */
function getGenresForFilter($db) {
    try {
        $query = "
            SELECT DISTINCT g.id, g.name, COUNT(cg.content_id) as content_count
            FROM genres g
            LEFT JOIN content_genres cg ON g.id = cg.genre_id
            GROUP BY g.id, g.name
            HAVING content_count > 0
            ORDER BY g.name ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error en getGenresForFilter: " . $e->getMessage());
        return [];
    }
}

/**
 * Renderiza la galería de contenido
 * 
 * @param array $content Array de contenido
 * @return string HTML de la galería
 */
function renderGallery($content) {
    if (empty($content)) {
        return '<div class="alert alert-info">No se encontró contenido.</div>';
    }
    
    require_once __DIR__ . '/image-helper.php';
    
    $html = '<div class="row g-4">';
    
    foreach ($content as $item) {
        $html .= '<div class="col-6 col-md-4 col-lg-3 col-xl-2">';
        $html .= createContentCard($item);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
