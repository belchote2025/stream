<?php
/**
 * BaseAddon - Abstract class for all addons
 */
abstract class BaseAddon {
    protected $id;
    protected $name;
    protected $version;
    protected $description;
    protected $author;
    protected $enabled = true;
    
    public function __construct() {
        $this->initialize();
    }
    
    abstract protected function initialize();
    
    public function getId() {
        return $this->id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getVersion() {
        return $this->version;
    }
    
    public function getDescription() {
        return $this->description;
    }
    
    public function getAuthor() {
        return $this->author;
    }
    
    public function isEnabled() {
        return $this->enabled;
    }
    
    public function enable() {
        $this->enabled = true;
    }
    
    public function disable() {
        $this->enabled = false;
    }
    
    public function setEnabled($enabled) {
        $this->enabled = (bool)$enabled;
    }
    
    // Methods that can be overridden by specific addons
    public function onLoad() {}
    public function onUnload() {}
    public function onSearch($query, $filters = []) { return []; }
    public function onContentUpdate($contentId, $data) { return false; }
    public function onContentDelete($contentId) { return false; }
    public function onContentAdd($data) { return false; }
    
    /**
     * Obtener streams de contenido (para reproductor)
     * @param int $contentId ID del contenido
     * @param string $contentType Tipo: 'movie' o 'series'
     * @param int|null $episodeId ID del episodio (solo para series)
     * @return array Lista de streams disponibles
     */
    public function onGetStreams($contentId, $contentType = 'movie', $episodeId = null) {
        return [];
    }
    
    /**
     * Obtener detalles adicionales de contenido
     * @param int $contentId ID del contenido
     * @param string $contentType Tipo: 'movie' o 'series'
     * @return array Detalles adicionales
     */
    public function onGetDetails($contentId, $contentType = 'movie') {
        return [];
    }
    
    /**
     * Ejecutar hook personalizado
     * @param string $hookName Nombre del hook
     * @param array $params Parámetros del hook
     * @return mixed Resultado del hook
     */
    public function onHook($hookName, $params = []) {
        return null;
    }
    
    // Métodos auxiliares para addons más complejos
    protected function registerHook($hookName, $methodName) {
        // Placeholder para sistema de hooks futuro
        // Por ahora, los addons pueden implementar directamente los métodos
    }
    
    protected function loadConfig() {
        // Placeholder para cargar configuración
        // Los addons pueden implementar su propia lógica de configuración
        if (property_exists($this, 'defaultConfig')) {
            $this->config = $this->defaultConfig ?? [];
        }
    }
    
    protected $config = [];
    
    public function getConfig($key = null, $default = null) {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }
    
    public function setConfig($key, $value) {
        $this->config[$key] = $value;
    }
    
    public function saveConfig($config) {
        // Guardar toda la configuración
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }
        
        // Los addons pueden sobrescribir este método para guardar en archivo/BD
        return true;
    }
}
