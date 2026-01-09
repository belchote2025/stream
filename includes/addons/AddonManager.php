<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/BaseAddon.php';

/**
 * AddonManager - Manages loading and interacting with addons
 */
class AddonManager {
    private static $instance = null;
    private $addons = [];
    private $addonsDir;
    private $db;
    
    private function __construct() {
        $this->addonsDir = __DIR__ . '/../../addons';
        $this->db = getDbConnection();
        
        // Create addons directory if it doesn't exist
        if (!file_exists($this->addonsDir)) {
            mkdir($this->addonsDir, 0755, true);
        }
        
        // Crear tabla de addons si no existe
        $this->ensureAddonsTable();
    }
    
    private function ensureAddonsTable() {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS addons (
                    id VARCHAR(100) PRIMARY KEY,
                    enabled BOOLEAN DEFAULT TRUE,
                    settings TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            // La tabla ya existe o hay un error, continuar
            error_log("Error creando tabla addons: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function loadAddons() {
        $this->addons = [];
        
        // Cargar estados de addons desde la base de datos
        $addonStates = $this->loadAddonStates();
        
        // Scan the addons directory for addon files
        $addonFiles = glob($this->addonsDir . '/*/addon.json');
        
        foreach ($addonFiles as $addonFile) {
            try {
                $addonDir = dirname($addonFile);
                $addonConfig = json_decode(file_get_contents($addonFile), true);
                
                if ($addonConfig && isset($addonConfig['main'])) {
                    $mainFile = $addonDir . '/' . $addonConfig['main'];
                    
                    if (file_exists($mainFile)) {
                        require_once $mainFile;
                        
                        $className = $addonConfig['class'] ?? 'Addon_' . basename($addonDir);
                        
                        if (class_exists($className)) {
                            try {
                                $addon = new $className();
                                if ($addon instanceof BaseAddon) {
                                    $addonId = $addon->getId();
                                    
                                    // Aplicar estado guardado desde la base de datos
                                    if (isset($addonStates[$addonId])) {
                                        $addon->setEnabled($addonStates[$addonId]['enabled']);
                                    } else {
                                        // Si no existe en la BD, crear registro con estado por defecto
                                        $this->saveAddonState($addonId, $addon->isEnabled());
                                    }
                                    
                                    $this->addons[$addonId] = $addon;
                                    $addon->onLoad();
                                }
                            } catch (Exception $e) {
                                error_log("Error cargando addon {$className}: " . $e->getMessage());
                                // Continuar con el siguiente addon
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error procesando addon file {$addonFile}: " . $e->getMessage());
                // Continuar con el siguiente addon
            }
        }
        
        return $this->addons;
    }
    
    private function loadAddonStates() {
        $states = [];
        try {
            $stmt = $this->db->query("SELECT id, enabled FROM addons");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $states[$row['id']] = [
                    'enabled' => (bool)$row['enabled']
                ];
            }
        } catch (PDOException $e) {
            error_log("Error cargando estados de addons: " . $e->getMessage());
        }
        return $states;
    }
    
    public function saveAddonState($addonId, $enabled) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO addons (id, enabled) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE enabled = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$addonId, $enabled ? 1 : 0, $enabled ? 1 : 0]);
            return true;
        } catch (PDOException $e) {
            error_log("Error guardando estado de addon: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAddons() {
        return $this->addons;
    }
    
    public function getAddon($id) {
        return $this->addons[$id] ?? null;
    }
    
    public function search($query) {
        $results = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled()) {
                $addonResults = $addon->onSearch($query);
                if (is_array($addonResults)) {
                    $results = array_merge($results, $addonResults);
                }
            }
        }
        
        return $results;
    }
    
    public function updateContent($contentId, $data) {
        $results = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled()) {
                $result = $addon->onContentUpdate($contentId, $data);
                if ($result !== false) {
                    $results[$addon->getId()] = $result;
                }
            }
        }
        
        return $results;
    }
    
    public function deleteContent($contentId) {
        $results = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled()) {
                $result = $addon->onContentDelete($contentId);
                if ($result !== false) {
                    $results[$addon->getId()] = $result;
                }
            }
        }
        
        return $results;
    }
    
    public function addContent($data) {
        $results = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled()) {
                $result = $addon->onContentAdd($data);
                if ($result !== false) {
                    $results[$addon->getId()] = $result;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Obtener streams de contenido desde addons
     */
    public function getStreams($contentId, $contentType = 'movie', $episodeId = null) {
        $streams = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled() && method_exists($addon, 'onGetStreams')) {
                try {
                    $addonStreams = $addon->onGetStreams($contentId, $contentType, $episodeId);
                    if (is_array($addonStreams) && !empty($addonStreams)) {
                        $streams[$addon->getId()] = $addonStreams;
                    }
                } catch (Exception $e) {
                    error_log("Error obteniendo streams del addon {$addon->getId()}: " . $e->getMessage());
                }
            }
        }
        
        return $streams;
    }
    
    /**
     * Obtener detalles de contenido desde addons
     */
    public function getContentDetails($contentId, $contentType = 'movie') {
        $details = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled() && method_exists($addon, 'onGetDetails')) {
                try {
                    $addonDetails = $addon->onGetDetails($contentId, $contentType);
                    if (is_array($addonDetails) && !empty($addonDetails)) {
                        $details[$addon->getId()] = $addonDetails;
                    }
                } catch (Exception $e) {
                    error_log("Error obteniendo detalles del addon {$addon->getId()}: " . $e->getMessage());
                }
            }
        }
        
        return $details;
    }
    
    /**
     * Ejecutar hook personalizado
     */
    public function executeHook($hookName, $params = []) {
        $results = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled() && method_exists($addon, 'onHook')) {
                try {
                    $result = $addon->onHook($hookName, $params);
                    if ($result !== null && $result !== false) {
                        $results[$addon->getId()] = $result;
                    }
                } catch (Exception $e) {
                    error_log("Error ejecutando hook {$hookName} en addon {$addon->getId()}: " . $e->getMessage());
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Búsqueda mejorada que combina resultados de múltiples addons
     */
    public function searchEnhanced($query, $filters = []) {
        $allResults = [];
        $resultsByAddon = [];
        
        foreach ($this->addons as $addon) {
            if ($addon->isEnabled()) {
                try {
                    // Si el addon soporta búsqueda con filtros
                    if (method_exists($addon, 'onSearch')) {
                        $addonResults = $addon->onSearch($query, $filters);
                        
                        if (is_array($addonResults)) {
                            // Normalizar formato de resultados
                            if (isset($addonResults['results'])) {
                                $resultsByAddon[$addon->getId()] = $addonResults['results'];
                                $allResults = array_merge($allResults, $addonResults['results']);
                            } else {
                                $resultsByAddon[$addon->getId()] = $addonResults;
                                $allResults = array_merge($allResults, $addonResults);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error en búsqueda del addon {$addon->getId()}: " . $e->getMessage());
                }
            }
        }
        
        // Eliminar duplicados basados en título y año
        $uniqueResults = [];
        $seen = [];
        foreach ($allResults as $result) {
            $key = strtolower(trim($result['title'] ?? '')) . '_' . ($result['year'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueResults[] = $result;
            }
        }
        
        return [
            'results' => $uniqueResults,
            'total' => count($uniqueResults),
            'by_addon' => $resultsByAddon,
            'query' => $query,
            'filters' => $filters
        ];
    }
    
    /**
     * Guardar configuración de un addon
     */
    public function saveAddonConfig($addonId, $config) {
        try {
            $configJson = json_encode($config);
            $stmt = $this->db->prepare("
                UPDATE addons 
                SET settings = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$configJson, $addonId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error guardando configuración del addon: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener configuración de un addon
     */
    public function getAddonConfig($addonId) {
        try {
            $stmt = $this->db->prepare("SELECT settings FROM addons WHERE id = ?");
            $stmt->execute([$addonId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['settings'])) {
                return json_decode($result['settings'], true);
            }
            return [];
        } catch (PDOException $e) {
            error_log("Error obteniendo configuración del addon: " . $e->getMessage());
            return [];
        }
    }
    
    public function enableAddon($addonId) {
        $addon = $this->getAddon($addonId);
        if ($addon) {
            $addon->enable();
            $this->saveAddonState($addonId, true);
            return true;
        }
        return false;
    }
    
    public function disableAddon($addonId) {
        $addon = $this->getAddon($addonId);
        if ($addon) {
            $addon->disable();
            $this->saveAddonState($addonId, false);
            return true;
        }
        return false;
    }
}
