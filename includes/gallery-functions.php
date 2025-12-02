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
 * Obtiene las películas con rating de IMDb almacenado
 */
function getImdbTopContent($db, $limit = 10) {
    try {
        $query = "
            SELECT 
                c.id,
                c.title,
                c.type,
                c.release_year,
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description,
                c.is_premium,
                c.torrent_magnet
            FROM content c
            WHERE c.type = 'movie'
              AND c.rating IS NOT NULL
              AND c.rating > 0
            ORDER BY c.rating DESC, c.views DESC, c.release_year DESC
            LIMIT :limit
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getImdbTopContent: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtiene videos subidos localmente
 */
function getLocalUploadedVideos($db, $limit = 10) {
    try {
        $query = "
            SELECT 
                c.id,
                c.title,
                c.type,
                c.release_year,
                c.duration,
                c.rating,
                c.poster_url,
                c.backdrop_url,
                c.description,
                c.video_url,
                c.is_premium,
                c.torrent_magnet
            FROM content c
            WHERE c.video_url IS NOT NULL
              AND c.video_url <> ''
              AND (c.video_url LIKE '/uploads/%' OR c.video_url LIKE '%/uploads/%')
            ORDER BY COALESCE(c.updated_at, c.added_date, c.created_at) DESC
            LIMIT :limit
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getLocalUploadedVideos: " . $e->getMessage());
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
    $baseUrl = rtrim(SITE_URL, '/');
    
    $id = isset($item['id']) ? (int)$item['id'] : 0;
    $type = isset($item['type']) ? htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') : 'movie';
    $title = isset($item['title']) ? htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') : 'Sin título';
    $releaseYear = isset($item['release_year']) ? (int)$item['release_year'] : (isset($item['year']) ? (int)$item['year'] : '');
    $duration = isset($item['duration']) ? htmlspecialchars($item['duration'], ENT_QUOTES, 'UTF-8') : '';
    $rating = isset($item['rating']) ? number_format((float)$item['rating'], 1) : '';
    $posterUrl = getImageUrl($item['poster_url'] ?? $item['backdrop_url'] ?? '', '/assets/img/default-poster.svg');
    $isPremium = !empty($item['is_premium']);
    $hasTorrent = !empty($item['torrent_magnet']);
    $slug = isset($item['slug']) ? htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') : '';
    
    $detailUrl = $baseUrl . '/content.php?id=' . $id;
    if ($slug) {
        $detailUrl = $baseUrl . '/content/' . $slug;
    }
    $detailUrlEscaped = htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8');
    
    $durationLabel = $type === 'movie'
        ? ($duration ? $duration . ' min' : 'Película')
        : (!empty($item['seasons']) ? ((int)$item['seasons']) . ' Temporada' . ((int)$item['seasons'] > 1 ? 's' : '') : 'Serie');
    
    $metaParts = [];
    if ($releaseYear) {
        $metaParts[] = '<span>' . $releaseYear . '</span>';
    }
    if ($durationLabel) {
        if (!empty($metaParts)) {
            $metaParts[] = '<span>•</span>';
        }
        $metaParts[] = '<span>' . htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    if ($rating) {
        $metaParts[] = '<span>•</span><span>⭐ ' . $rating . '</span>';
    }
    $metaHtml = '<div class="content-meta">' . implode('', $metaParts) . '</div>';
    
    $premiumBadge = $isPremium ? '<span class="premium-badge">PREMIUM</span>' : '';
    $torrentBadge = $hasTorrent ? '<span class="torrent-badge" title="Disponible por Torrent"><i class="fas fa-magnet"></i></span>' : '';
    
    // Preparar datos para handlers de torrent e IMDb
    $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $yearEscaped = htmlspecialchars($releaseYear, ENT_QUOTES, 'UTF-8');
    $onclickHandler = "if(!event.target.closest('.action-btn')){window.location.href=this.dataset.detailUrl;}";
    
    $trailerUrlEscaped = htmlspecialchars($item['trailer_url'] ?? '', ENT_QUOTES, 'UTF-8');
    $html = '<div class="content-card" data-id="' . $id . '" data-type="' . $type . '" data-detail-url="' . $detailUrlEscaped . '" data-title="' . $titleEscaped . '" data-year="' . $yearEscaped . '" data-trailer-url="' . $trailerUrlEscaped . '" onclick="' . htmlspecialchars($onclickHandler, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<div class="content-card-media">';
    $html .= '<img src="' . htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $titleEscaped . '" loading="lazy" class="content-poster-clickable" style="cursor: pointer;" onclick="event.stopPropagation(); if(typeof showTorrentModal === \'function\'){showTorrentModal(' . $id . ', \'' . addslashes($title) . '\', ' . ($releaseYear ?: 'null') . ', \'' . $type . '\');} else {window.location.href=\'' . $detailUrlEscaped . '\';}">';
    $html .= '<div class="content-trailer-container" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 10;"><div class="content-trailer-wrapper"></div></div>';
    $html .= '</div>';
    if ($premiumBadge || $torrentBadge) {
        $html .= '<div class="content-badges">' . $premiumBadge . $torrentBadge . '</div>';
    }
    $html .= '<div class="content-info">';
    $html .= '<h3 class="content-title">' . $titleEscaped . '</h3>';
    $html .= $metaHtml;
    // Badge de IMDb
    $html .= '<div class="imdb-badge" data-id="' . $id . '" style="display: inline-flex; align-items: center; gap: 0.35rem; margin: 0.4rem 0; padding: 0.2rem 0.6rem; border-radius: 4px; background: rgba(245,197,24,0.15); color: #f5c518; font-weight: 600; font-size: 0.85rem;">';
    $html .= '<i class="fab fa-imdb"></i>';
    $html .= '<span class="imdb-text">IMDb: —</span>';
    $html .= '</div>';
    $html .= '<div class="content-actions">';
    $html .= '<button class="action-btn" data-action="play" data-id="' . $id . '" title="Reproducir"><i class="fas fa-play"></i></button>';
    $html .= '<button class="action-btn" data-action="add" data-id="' . $id . '" title="Añadir a Mi lista"><i class="fas fa-plus"></i></button>';
    $html .= '<button class="action-btn" data-action="info" data-id="' . $id . '" title="Más información"><i class="fas fa-info-circle"></i></button>';
    $html .= '<button class="action-btn torrent-btn" data-action="torrent" data-id="' . $id . '" data-title="' . urlencode($title) . '" data-year="' . $yearEscaped . '" data-type="' . $type . '" title="Buscar torrents"><i class="fas fa-magnet"></i></button>';
    $html .= '</div>'; // content-actions
    $html .= '</div>'; // content-info
    $html .= '</div>'; // content-card
    
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

/**
 * Formatea la duración en un formato legible
 * 
 * @param int|string $duration Duración en minutos o segundos
 * @return string Duración formateada (ej: "120 min", "2h 30min", "45 min")
 */
function formatDuration($duration) {
    if (empty($duration) || $duration == 0) {
        return '';
    }
    
    // Convertir a entero
    $duration = (int)$duration;
    
    // Si la duración es mayor a 120 minutos, probablemente está en segundos
    // Si es menor, probablemente está en minutos
    if ($duration > 120) {
        // Está en segundos, convertir a minutos
        $minutes = floor($duration / 60);
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
    } else {
        // Está en minutos
        $hours = floor($duration / 60);
        $remainingMinutes = $duration % 60;
    }
    
    // Formatear según las horas
    if ($hours > 0) {
        if ($remainingMinutes > 0) {
            return $hours . 'h ' . $remainingMinutes . 'min';
        } else {
            return $hours . 'h';
        }
    } else {
        return $remainingMinutes . ' min';
    }
}
