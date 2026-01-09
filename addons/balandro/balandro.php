<?php
require_once __DIR__ . '/../../includes/addons/BaseAddon.php';
require_once __DIR__ . '/../../includes/imdb-helper.php';

/**
 * BalandroAddon - Addon adaptado de Kodi para la plataforma de streaming
 * Funciona como navegador de páginas web para extraer enlaces de streaming
 * Basado en el addon plugin.video.balandro de Kodi
 */
class BalandroAddon extends BaseAddon {
    private $apiUrl = 'https://repobal.github.io/base/';
    private $cacheDir;
    private $cacheTtl = 3600; // 1 hora
    
    // Fuentes web populares para extraer contenido (como en Kodi)
    private $webSources = [
        'vidsrc' => 'https://vidsrc.to',
        'upstream' => 'https://upstream.to',
        'powvideo' => 'https://powvideo.net',
        'filemoon' => 'https://filemoon.sx',
        'streamtape' => 'https://streamtape.com',
        'streamwish' => 'https://streamwish.to'
    ];
    
    protected function initialize() {
        $this->id = 'balandro';
        $this->name = 'Balandro Addon';
        $this->version = '1.0.0';
        $this->description = 'Addon adaptado de Kodi - Navegador de páginas web para extraer contenido multimedia';
        $this->author = 'Team Balandro';
        
        // Inicializar configuración por defecto
        $this->config = [
            'api_url' => 'https://repobal.github.io/base/',
            'api_key' => '',
            'max_results' => 20,
            'enable_caching' => true,
            'default_quality' => 'HD',
            'timeout' => 15,
            'debug_mode' => false,
            'enable_vidsrc' => true,
            'enable_upstream' => true,
            'enable_torrents' => false,
            'enable_web_scraping' => true
        ];
        
        // Crear directorio de caché
        $this->cacheDir = __DIR__ . '/../../cache/balandro/';
        if (!file_exists($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0755, true)) {
                $cacheBaseDir = dirname($this->cacheDir);
                if (!file_exists($cacheBaseDir)) {
                    @mkdir($cacheBaseDir, 0755, true);
                }
                @mkdir($this->cacheDir, 0755, true);
            }
        }
    }
    
    public function onLoad() {
        // Cargar configuración guardada si existe
        $this->loadConfig();
        
        // Asegurar que el directorio de caché existe
        if (!is_dir($this->cacheDir)) {
            $cacheBaseDir = dirname($this->cacheDir);
            if (!is_dir($cacheBaseDir)) {
                @mkdir($cacheBaseDir, 0755, true);
            }
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    protected function loadConfig() {
        // Cargar configuración desde la base de datos
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("SELECT settings FROM addons WHERE id = ?");
            $stmt->execute([$this->id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['settings'])) {
                $savedConfig = json_decode($result['settings'], true);
                if (is_array($savedConfig)) {
                    $this->config = array_merge($this->config, $savedConfig);
                    // Actualizar URL de API si está en la configuración
                    if (!empty($savedConfig['api_url'])) {
                        $this->apiUrl = rtrim($savedConfig['api_url'], '/') . '/';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error cargando configuración del addon: " . $e->getMessage());
        }
    }
    
    public function saveConfig($config) {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
            
            // Actualizar URL de API
            if (!empty($config['api_url'])) {
                $this->apiUrl = rtrim($config['api_url'], '/') . '/';
            }
            
            // Guardar en base de datos
            try {
                require_once __DIR__ . '/../../includes/config.php';
                $db = getDbConnection();
                
                $stmt = $db->prepare("
                    INSERT INTO addons (id, enabled, settings) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE settings = ?, updated_at = CURRENT_TIMESTAMP
                ");
                
                $settingsJson = json_encode($this->config);
                $enabled = $this->isEnabled() ? 1 : 0;
                
                $stmt->execute([$this->id, $enabled, $settingsJson, $settingsJson]);
                return true;
            } catch (Exception $e) {
                error_log("Error guardando configuración del addon: " . $e->getMessage());
                return true;
            }
        }
        return false;
    }
    
    public function clearCache() {
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '*.cache');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            return count($files);
        }
        return 0;
    }
    
    /**
     * Ejecutar hook personalizado
     */
    public function onHook($hookName, $params = []) {
        if ($hookName === 'get_new_content') {
            // Hook para obtener contenido nuevo/trending
            return $this->getNewContent($params);
        }
        return null;
    }
    
    /**
     * Obtener contenido nuevo/trending desde fuentes externas
     * Busca contenido reciente que pueda necesitar actualización de streams
     */
    private function getNewContent($params = []) {
        $type = $params['type'] ?? 'movie';
        $limit = $params['limit'] ?? 20;
        $sinceDays = $params['since_days'] ?? 7;
        
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            // Buscar contenido reciente que:
            // 1. Fue creado o actualizado recientemente
            // 2. No tiene video_url ni torrent_magnet (necesita streams)
            // 3. O tiene rating alto (contenido popular que puede necesitar actualización)
            $contentType = $type === 'tv' ? 'series' : 'movie';
            $sinceDate = date('Y-m-d', strtotime("-{$sinceDays} days"));
            
            // Priorizar contenido sin streams que necesita actualización
            $sql = "SELECT * FROM content 
                    WHERE type = :type 
                    AND (
                        (created_at >= :sinceDate OR updated_at >= :sinceDate)
                        OR (rating >= 7.0 AND (video_url IS NULL OR video_url = '' OR torrent_magnet IS NULL OR torrent_magnet = ''))
                    )
                    ORDER BY 
                        CASE 
                            WHEN (video_url IS NULL OR video_url = '') AND (torrent_magnet IS NULL OR torrent_magnet = '') THEN 1
                            ELSE 2
                        END,
                        created_at DESC, 
                        rating DESC
                    LIMIT :limit";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':type', $contentType, PDO::PARAM_STR);
            $stmt->bindValue(':sinceDate', $sinceDate, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = [];
            
            foreach ($items as $item) {
                $formatted = $this->formatContentItem($item);
                // Marcar que viene del addon para que se busquen streams
                $formatted['needs_streams'] = empty($item['video_url']) && empty($item['torrent_magnet']);
                $results[] = $formatted;
            }
            
            return [
                'results' => $results,
                'total' => count($results),
                'source' => 'local_recent_needs_update'
            ];
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error obteniendo contenido nuevo: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Realiza una búsqueda de contenido (estilo Balandro/Kodi)
     * Busca primero en la base de datos local, luego en fuentes web
     */
    public function onSearch($query, $filters = []) {
        // Si la query es "trending", "popular", "new" o similar, buscar contenido reciente
        $specialQueries = ['trending', 'popular', 'new', 'latest', 'recent', 'nuevo', 'popular', 'tendencia'];
        $queryLower = strtolower(trim($query));
        
        if (in_array($queryLower, $specialQueries)) {
            // Buscar contenido nuevo/trending
            $params = array_merge($filters, ['limit' => $filters['limit'] ?? 20]);
            return $this->getNewContent($params);
        }
        
        if (empty($query)) {
            return [];
        }
        
        $cacheKey = 'search_' . md5($query . json_encode($filters));
        
        // Intentar obtener de caché
        if ($this->config['enable_caching']) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            // Primero buscar en la base de datos local
            $localResults = $this->searchLocalContent($query, $filters);
            
            $results = [
                'results' => $localResults,
                'total' => count($localResults),
                'page' => $filters['page'] ?? 1,
                'total_pages' => 1,
                'source' => 'local'
            ];
            
            // Guardar en caché
            if ($this->config['enable_caching'] && !empty($localResults)) {
                $this->saveToCache($cacheKey, $results);
            }
            
            return $results;
            
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error en búsqueda Balandro: " . $e->getMessage());
            }
            return [
                'results' => [],
                'total' => 0,
                'page' => $filters['page'] ?? 1,
                'total_pages' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca contenido en la base de datos local
     */
    private function searchLocalContent($query, $filters = []) {
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $type = $filters['type'] ?? null;
            $year = $filters['year'] ?? null;
            $limit = min($this->config['max_results'], 50);
            
            // Limpiar y normalizar la consulta
            $query = trim($query);
            $query = preg_replace('/\s+/', ' ', $query); // Normalizar espacios múltiples
            
            // Búsqueda más flexible: múltiples estrategias
            $searchConditions = [];
            $params = [];
            
            // Estrategia 1: Búsqueda exacta (mayor prioridad)
            $searchConditions[] = "(title LIKE :query_exact OR description LIKE :query_exact)";
            $params[':query_exact'] = '%' . $query . '%';
            
            // Estrategia 2: Búsqueda sin caracteres especiales
            $queryClean = preg_replace('/[^\w\s]/', '', $query);
            if ($queryClean !== $query) {
                $searchConditions[] = "(title LIKE :query_clean OR description LIKE :query_clean)";
                $params[':query_clean'] = '%' . $queryClean . '%';
            }
            
            // Estrategia 3: Búsqueda por palabras individuales (más flexible)
            $searchTerms = explode(' ', $query);
            $validTerms = [];
            foreach ($searchTerms as $term) {
                $term = trim($term);
                // Ignorar palabras muy cortas y palabras comunes
                if (strlen($term) > 2 && !in_array(strtolower($term), ['the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'of', 'with'])) {
                    $validTerms[] = $term;
                }
            }
            
            if (!empty($validTerms)) {
                $termConditions = [];
                foreach ($validTerms as $index => $term) {
                    $key = ':term' . $index;
                    $termConditions[] = "(title LIKE $key OR description LIKE $key)";
                    $params[$key] = '%' . $term . '%';
                }
                if (!empty($termConditions)) {
                    $searchConditions[] = "(" . implode(' AND ', $termConditions) . ")";
                }
            }
            
            // Estrategia 4: Búsqueda con FULLTEXT si está disponible
            // Intentar usar FULLTEXT search si la tabla lo soporta
            try {
                $sqlFulltext = "SELECT * FROM content WHERE MATCH(title, description) AGAINST(:query_ft IN BOOLEAN MODE)";
                $stmtTest = $db->prepare($sqlFulltext);
                $stmtTest->bindValue(':query_ft', $query, PDO::PARAM_STR);
                $stmtTest->execute();
                // Si funciona, añadir esta condición
                $searchConditions[] = "MATCH(title, description) AGAINST(:query_ft IN BOOLEAN MODE)";
                $params[':query_ft'] = $query;
            } catch (Exception $e) {
                // FULLTEXT no disponible, continuar sin ella
            }
            
            if (empty($searchConditions)) {
                return [];
            }
            
            $sql = "SELECT * FROM content WHERE (" . implode(' OR ', $searchConditions) . ")";
            
            if ($type && $type !== 'all') {
                $sql .= " AND type = :type";
                $params[':type'] = $type === 'tv' ? 'series' : 'movie';
            }
            
            if ($year) {
                $sql .= " AND release_year = :year";
                $params[':year'] = $year;
            }
            
            // Ordenar por relevancia: primero los que coinciden exactamente, luego por rating
            if (isset($params[':query_exact'])) {
                $sql .= " ORDER BY 
                    CASE 
                        WHEN title LIKE :query_exact THEN 1
                        " . (isset($params[':query_clean']) ? "WHEN title LIKE :query_clean THEN 2" : "") . "
                        ELSE 3
                    END,
                    rating DESC, 
                    views DESC";
            } else {
                $sql .= " ORDER BY rating DESC, views DESC";
            }
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = [];
            
            foreach ($items as $item) {
                $results[] = $this->formatContentItem($item);
            }
            
            return $results;
            
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error en búsqueda local: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Obtiene los detalles de un contenido específico
     */
    public function onGetDetails($contentId, $type = 'movie') {
        if (empty($contentId)) {
            return null;
        }
        
        $cacheKey = 'details_' . $type . '_' . md5($contentId);
        
        // Intentar obtener de caché
        if ($this->config['enable_caching']) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            // Buscar en la base de datos local primero
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("SELECT * FROM content WHERE id = ?");
            $stmt->execute([$contentId]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($content) {
                $details = $this->formatContentDetails($content, $type);
                
                // Guardar en caché
                if ($this->config['enable_caching']) {
                    $this->saveToCache($cacheKey, $details);
                }
                
                return $details;
            }
            
            return null;
            
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error obteniendo detalles Balandro: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Obtiene las fuentes de streaming para un contenido (estilo Balandro/Kodi)
     * Navega por páginas web para extraer enlaces, como hace el addon original
     */
    public function onGetStreams($contentId, $type = 'movie', $season = null, $episode = null) {
        if (empty($contentId)) {
            return [];
        }
        
        $cacheKey = 'streams_' . $type . '_' . md5($contentId . '_' . $season . '_' . $episode);
        
        // Intentar obtener de caché
        if ($this->config['enable_caching']) {
            $cached = $this->getFromCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            // Obtener información del contenido
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("SELECT * FROM content WHERE id = ?");
            $stmt->execute([$contentId]);
            $content = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$content) {
                return [];
            }
            
            $title = $content['title'];
            $year = $content['release_year'] ?? null;
            // Verificar si existe la columna imdb_id (puede no existir en todas las instalaciones)
            $imdbId = null;
            if (isset($content['imdb_id']) && !empty($content['imdb_id'])) {
                $imdbId = $content['imdb_id'];
            } elseif (isset($content['imdbId']) && !empty($content['imdbId'])) {
                $imdbId = $content['imdbId'];
            }
            $contentType = $content['type'] === 'series' ? 'tv' : 'movie';
            
            $streams = [];
            
            // 1. Si el contenido tiene video_url, usarlo (prioridad máxima)
            if (!empty($content['video_url'])) {
                $streams[] = [
                    'quality' => $this->config['default_quality'],
                    'type' => 'direct',
                    'url' => $content['video_url'],
                    'provider' => 'local',
                    'format' => 'mp4',
                    'name' => 'Local'
                ];
            }
            
            // 2. Si es serie y hay episodio específico, buscar video_url del episodio
            if ($contentType === 'tv' && $season !== null && $episode !== null) {
                try {
                    $episodeStmt = $db->prepare("
                        SELECT video_url 
                        FROM episodes 
                        WHERE series_id = ? AND season_number = ? AND episode_number = ?
                        LIMIT 1
                    ");
                    $episodeStmt->execute([$contentId, $season, $episode]);
                    $episodeData = $episodeStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($episodeData && !empty($episodeData['video_url'])) {
                        $streams[] = [
                            'quality' => $this->config['default_quality'],
                            'type' => 'direct',
                            'url' => $episodeData['video_url'],
                            'provider' => 'local',
                            'format' => 'mp4',
                            'name' => 'Local (Episodio)'
                        ];
                    }
                } catch (Exception $e) {
                    if ($this->config['debug_mode']) {
                        error_log("Error obteniendo video del episodio: " . $e->getMessage());
                    }
                }
            }
            
            // 3. Usar vidsrc si está habilitado (como en Kodi/Balandro)
            // Vidsrc es la fuente principal que usa Balandro
            if ($this->config['enable_vidsrc']) {
                // Intentar obtener IMDb ID si no lo tenemos
                if (empty($imdbId)) {
                    try {
                        // Intentar buscar IMDb ID usando scraping web
                        $imdbId = $this->scrapeImdbId($title, $year, $contentType);
                    } catch (Exception $e) {
                        if ($this->config['debug_mode']) {
                            error_log("Error obteniendo IMDb ID: " . $e->getMessage());
                        }
                    }
                }
                
                // Si tenemos IMDb ID, construir URLs de Vidsrc
                if (!empty($imdbId)) {
                    try {
                        // Construir URLs de Vidsrc manualmente
                        $vidsrcUrls = [];
                        if ($contentType === 'tv' && $season !== null && $episode !== null) {
                            $vidsrcUrls = [
                                "https://vidsrc.to/embed/tv/{$imdbId}/{$season}-{$episode}",
                                "https://vidsrc.cc/v2/embed/tv/{$imdbId}/{$season}-{$episode}",
                                "https://embed.smashystream.com/play/{$imdbId}/{$season}-{$episode}"
                            ];
                        } elseif ($contentType === 'tv') {
                            $vidsrcUrls = [
                                "https://vidsrc.to/embed/tv/{$imdbId}",
                                "https://vidsrc.cc/v2/embed/tv/{$imdbId}",
                                "https://embed.smashystream.com/play/{$imdbId}"
                            ];
                        } else {
                            $vidsrcUrls = [
                                "https://vidsrc.to/embed/movie/{$imdbId}",
                                "https://vidsrc.cc/v2/embed/movie/{$imdbId}",
                                "https://embed.smashystream.com/play/{$imdbId}"
                            ];
                        }
                        
                        foreach ($vidsrcUrls as $index => $url) {
                            $streams[] = [
                                'quality' => $this->config['default_quality'],
                                'type' => 'embed',
                                'url' => $url,
                                'provider' => 'vidsrc',
                                'format' => 'iframe',
                                'name' => 'Vidsrc' . ($index > 0 ? ' ' . ($index + 1) : '')
                            ];
                        }
                    } catch (Exception $e) {
                        if ($this->config['debug_mode']) {
                            error_log("Error construyendo URLs de Vidsrc: " . $e->getMessage());
                        }
                    }
                } elseif ($this->config['debug_mode']) {
                    error_log("Vidsrc: No se pudo obtener IMDb ID para: {$title}");
                }
            }
            
            // 4. Buscar en fuentes de streaming web (upstream, powvideo, etc.)
            // Esto simula el comportamiento de navegación web de Balandro
            if ($this->config['enable_upstream'] && $this->config['enable_web_scraping']) {
                try {
                    // Buscar en múltiples sitios web directamente
                    $streamingSources = $this->searchStreamingSources($title, $contentType, $year, $imdbId);
                    
                    if (!empty($streamingSources) && is_array($streamingSources)) {
                        foreach ($streamingSources as $sourceUrl) {
                            if (empty($sourceUrl)) {
                                continue;
                            }
                            
                            // Usar StreamExtractor para extraer enlaces reales (como en Kodi)
                            $extractedStreams = [];
                            if (file_exists(__DIR__ . '/StreamExtractor.php')) {
                                require_once __DIR__ . '/StreamExtractor.php';
                                if (class_exists('StreamExtractor')) {
                                    $extractor = new StreamExtractor();
                                    $extractedStreams = $extractor->extractFromUrl($sourceUrl);
                                }
                            }
                            
                            if (!empty($extractedStreams)) {
                                foreach ($extractedStreams as $extracted) {
                                    if (!empty($extracted['url'])) {
                                        $streams[] = [
                                            'quality' => $extracted['quality'] ?? $this->config['default_quality'],
                                            'type' => $extracted['type'] ?? 'direct',
                                            'url' => $extracted['url'],
                                            'provider' => $extracted['provider'] ?? 'unknown',
                                            'format' => 'mp4',
                                            'name' => ucfirst($extracted['provider'] ?? 'Unknown')
                                        ];
                                    }
                                }
                            } else {
                                // Si no se puede extraer, usar la URL directamente como embed
                                $provider = $this->detectProviderFromUrl($sourceUrl);
                                $streams[] = [
                                    'quality' => $this->config['default_quality'],
                                    'type' => 'embed',
                                    'url' => $sourceUrl,
                                    'provider' => $provider,
                                    'format' => 'iframe',
                                    'name' => ucfirst($provider)
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    if ($this->config['debug_mode']) {
                        error_log("Error buscando en fuentes web: " . $e->getMessage());
                    }
                }
            }
            
            // 5. Si no hay streams y tenemos torrent, usarlo como último recurso
            if (empty($streams) && !empty($content['torrent_magnet'])) {
                $streams[] = [
                    'quality' => $this->config['default_quality'],
                    'type' => 'torrent',
                    'url' => $content['torrent_magnet'],
                    'provider' => 'torrent',
                    'format' => 'magnet',
                    'name' => 'Torrent'
                ];
            }
            
            
            // Guardar en caché
            if ($this->config['enable_caching'] && !empty($streams)) {
                $this->saveToCache($cacheKey, $streams);
            }
            
            return $streams;
            
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error obteniendo streams Balandro: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Formatea un elemento de contenido
     */
    private function formatContentItem($item) {
        return [
            'id' => $item['id'] ?? '',
            'title' => $item['title'] ?? 'Sin título',
            'original_title' => $item['original_title'] ?? null,
            'type' => $item['type'] === 'series' ? 'tv' : 'movie',
            'year' => $item['release_year'] ?? null,
            'poster' => $item['poster_url'] ?? null,
            'backdrop' => $item['backdrop_url'] ?? null,
            'overview' => $item['description'] ?? '',
            'genres' => $this->getGenres($item['id'] ?? null),
            'rating' => $item['rating'] ?? 0,
            'vote_count' => $item['views'] ?? 0,
            'release_date' => $item['release_date'] ?? null,
            'runtime' => $item['duration'] ?? null,
            'provider' => 'balandro',
            'provider_id' => $item['id'] ?? null,
            'imdb_id' => isset($item['imdb_id']) ? $item['imdb_id'] : (isset($item['imdbId']) ? $item['imdbId'] : null)
        ];
    }
    
    /**
     * Formatea los detalles de un contenido
     */
    private function formatContentDetails($data, $type = 'movie') {
        $details = [
            'id' => $data['id'] ?? '',
            'title' => $data['title'] ?? '',
            'original_title' => $data['original_title'] ?? '',
            'type' => $data['type'] === 'series' ? 'tv' : 'movie',
            'year' => $data['release_year'] ?? null,
            'poster' => $data['poster_url'] ?? null,
            'backdrop' => $data['backdrop_url'] ?? null,
            'overview' => $data['description'] ?? '',
            'genres' => $this->getGenres($data['id'] ?? null),
            'rating' => $data['rating'] ?? 0,
            'vote_count' => $data['views'] ?? 0,
            'release_date' => $data['release_date'] ?? null,
            'runtime' => $data['duration'] ?? null,
            'status' => null,
            'tagline' => '',
            'provider' => 'balandro',
            'provider_id' => $data['id'] ?? null,
            'imdb_id' => isset($data['imdb_id']) ? $data['imdb_id'] : (isset($data['imdbId']) ? $data['imdbId'] : null)
        ];
        
        // Información adicional para series
        if ($data['type'] === 'series') {
            $details['seasons'] = $this->getSeasons($data['id']);
            $details['number_of_seasons'] = count($details['seasons']);
            $details['number_of_episodes'] = $this->getTotalEpisodes($data['id']);
        }
        
        return $details;
    }
    
    /**
     * Obtiene los géneros de un contenido
     */
    private function getGenres($contentId) {
        if (!$contentId) {
            return [];
        }
        
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("
                SELECT g.name 
                FROM genres g
                INNER JOIN content_genres cg ON g.id = cg.genre_id
                WHERE cg.content_id = ?
            ");
            $stmt->execute([$contentId]);
            
            $genres = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $genres[] = $row['name'];
            }
            
            return $genres;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtiene las temporadas de una serie
     */
    private function getSeasons($seriesId) {
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("
                SELECT DISTINCT season_number, COUNT(*) as episode_count
                FROM episodes
                WHERE series_id = ?
                GROUP BY season_number
                ORDER BY season_number
            ");
            $stmt->execute([$seriesId]);
            
            $seasons = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $seasons[] = [
                    'season_number' => (int)$row['season_number'],
                    'episode_count' => (int)$row['episode_count'],
                    'name' => 'Temporada ' . $row['season_number']
                ];
            }
            
            return $seasons;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtiene el total de episodios de una serie
     */
    private function getTotalEpisodes($seriesId) {
        try {
            require_once __DIR__ . '/../../includes/config.php';
            $db = getDbConnection();
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM episodes WHERE series_id = ?");
            $stmt->execute([$seriesId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($result['total'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Obtiene un valor de la caché
     */
    private function getFromCache($key) {
        $cacheFile = $this->cacheDir . md5($key) . '.cache';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->cacheTtl)) {
            $data = file_get_contents($cacheFile);
            $decoded = json_decode($data, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        
        return null;
    }
    
    /**
     * Guarda un valor en la caché
     */
    private function saveToCache($key, $data) {
        $cacheFile = $this->cacheDir . md5($key) . '.cache';
        file_put_contents($cacheFile, json_encode($data));
    }
    
    /**
     * Detecta el proveedor desde una URL
     */
    private function detectProviderFromUrl($url) {
        $url = strtolower($url);
        
        if (strpos($url, 'upstream') !== false) return 'upstream';
        if (strpos($url, 'streamtape') !== false) return 'streamtape';
        if (strpos($url, 'powvideo') !== false) return 'powvideo';
        if (strpos($url, 'filemoon') !== false) return 'filemoon';
        if (strpos($url, 'streamwish') !== false) return 'streamwish';
        
        return 'unknown';
    }
    
    /**
     * Obtiene IMDb ID mediante scraping web
     */
    private function scrapeImdbId($title, $year, $type) {
        $query = urlencode(trim($title) . ' ' . ($year ?: ''));
        $ttype = ($type === 'tv') ? 'tv' : 'ft';
        $url = "https://www.imdb.com/find?q={$query}&s=tt&ttype={$ttype}";
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
                'timeout' => 4,
                'ignore_errors' => true,
            ]
        ]);
        
        try {
            $html = @file_get_contents($url, false, $context);
            if (!$html) {
                return '';
            }
            if (preg_match('/\/title\/(tt\d+)/', $html, $m)) {
                return $m[1];
            }
        } catch (Exception $e) {
            if ($this->config['debug_mode']) {
                error_log("Error en scrapeImdbId: " . $e->getMessage());
            }
        }
        return '';
    }
    
    /**
     * Busca enlaces de streaming en múltiples sitios web
     */
    private function searchStreamingSources($title, $type, $year, $imdbId) {
        $results = [];
        
        // Si tenemos IMDb ID, construir URLs de diferentes servicios
        if (!empty($imdbId)) {
            // 1. Upstream
            try {
                $upstreamUrl = $type === 'tv' 
                    ? "https://upstream.to/embed/tv/{$imdbId}"
                    : "https://upstream.to/embed/movie/{$imdbId}";
                
                // Verificar que el enlace sea accesible
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                        'timeout' => 5,
                        'ignore_errors' => true,
                    ]
                ]);
                
                $headers = @get_headers($upstreamUrl, 1, $context);
                if ($headers && strpos($headers[0], '200') !== false) {
                    $results[] = $upstreamUrl;
                }
            } catch (Exception $e) {
                // Ignorar errores
            }
            
            // 2. Filemoon
            try {
                $filemoonUrl = $type === 'tv'
                    ? "https://filemoon.sx/embed/tv/{$imdbId}"
                    : "https://filemoon.sx/embed/movie/{$imdbId}";
                
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                        'timeout' => 5,
                        'ignore_errors' => true,
                    ]
                ]);
                
                $headers = @get_headers($filemoonUrl, 1, $context);
                if ($headers && strpos($headers[0], '200') !== false) {
                    $results[] = $filemoonUrl;
                }
            } catch (Exception $e) {
                // Ignorar errores
            }
            
            // 3. Streamwish
            try {
                $streamwishUrl = $type === 'tv'
                    ? "https://streamwish.to/embed/tv/{$imdbId}"
                    : "https://streamwish.to/embed/movie/{$imdbId}";
                
                $context = stream_context_create([
                    'http' => [
                        'method' => 'HEAD',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                        'timeout' => 5,
                        'ignore_errors' => true,
                    ]
                ]);
                
                $headers = @get_headers($streamwishUrl, 1, $context);
                if ($headers && strpos($headers[0], '200') !== false) {
                    $results[] = $streamwishUrl;
                }
            } catch (Exception $e) {
                // Ignorar errores
            }
        }
        
        // También buscar por título en sitios que lo permitan
        // (Esto requiere scraping más complejo, por ahora solo con IMDb ID)
        
        return $results;
    }
    
    // Métodos de contenido (para integración futura)
    public function onContentAdd($data) {
        return false;
    }
    
    public function onContentUpdate($contentId, $data) {
        return false;
    }
    
    public function onContentDelete($contentId) {
        return false;
    }
}
