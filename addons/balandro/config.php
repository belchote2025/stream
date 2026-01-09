<?php
/**
 * Configuración del Addon Balandro
 * 
 * Este archivo contiene la configuración por defecto del addon Balandro.
 * Los valores aquí definidos pueden ser sobrescritos desde el panel de administración.
 */

return [
    'balandro' => [
        // Configuración de la API
        'api_url' => 'https://repobal.github.io/base/',
        'api_key' => '',
        'timeout' => 15,
        
        // Configuración de caché
        'enable_caching' => true,
        'cache_ttl' => 3600, // 1 hora en segundos
        'cache_dir' => __DIR__ . '/../../../cache/balandro/',
        
        // Configuración de búsqueda
        'max_results' => 20,
        'default_quality' => 'HD',
        
        // Configuración de streaming
        'streaming' => [
            'max_quality' => '1080p',
            'fallback_quality' => '720p',
            'enable_subtitles' => true,
            'default_subtitle_lang' => 'es',
            'enable_direct_play' => true,
            'enable_transcoding' => false
        ],
        
        // Configuración de la interfaz
        'show_in_menu' => true,
        'menu_position' => 10,
        'menu_icon' => 'puzzle-piece',
        'menu_title' => 'Balandro',
        
        // Configuración de logs
        'enable_logging' => true,
        'log_level' => 'error', // debug, info, warning, error
        'log_file' => __DIR__ . '/../../../logs/balandro.log',
        
        // Configuración de actualizaciones
        'check_updates' => true,
        'auto_update' => false,
        'update_channel' => 'stable', // stable, beta, alpha
        
        // Configuración de seguridad
        'require_auth' => true,
        'allowed_ips' => [],
        'rate_limit' => [
            'enabled' => true,
            'requests' => 100,
            'time_window' => 60 // segundos
        ]
    ]
];
