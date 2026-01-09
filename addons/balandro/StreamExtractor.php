<?php
/**
 * StreamExtractor - Clase para extraer enlaces de streaming desde páginas web
 * Adaptado del comportamiento de los servidores de Balandro en Kodi
 */

class StreamExtractor {
    private $timeout = 15;
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /**
     * Extrae enlaces de streaming desde una URL (estilo Balandro)
     */
    public function extractFromUrl($url, $provider = null) {
        if (empty($url)) {
            return [];
        }
        
        // Detectar proveedor si no se especifica
        if (!$provider) {
            $provider = $this->detectProvider($url);
        }
        
        // Extraer según el proveedor
        switch ($provider) {
            case 'upstream':
                return $this->extractUpstream($url);
            case 'streamtape':
                return $this->extractStreamtape($url);
            case 'powvideo':
                return $this->extractPowVideo($url);
            case 'filemoon':
                return $this->extractFilemoon($url);
            case 'streamwish':
                return $this->extractStreamwish($url);
            default:
                return $this->extractGeneric($url);
        }
    }
    
    /**
     * Detecta el proveedor por la URL
     */
    private function detectProvider($url) {
        $url = strtolower($url);
        
        if (strpos($url, 'upstream') !== false) return 'upstream';
        if (strpos($url, 'streamtape') !== false) return 'streamtape';
        if (strpos($url, 'powvideo') !== false) return 'powvideo';
        if (strpos($url, 'filemoon') !== false) return 'filemoon';
        if (strpos($url, 'streamwish') !== false) return 'streamwish';
        
        return 'generic';
    }
    
    /**
     * Extrae enlaces desde Upstream (adaptado de upstream.py)
     */
    private function extractUpstream($url) {
        $streams = [];
        
        try {
            // Normalizar URL
            if (strpos($url, '/embed-') === false) {
                $url = str_replace('upstream.to/', 'upstream.to/embed-', $url);
                if (!strpos($url, '.html')) {
                    $url .= '.html';
                }
            }
            
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar sources en el HTML
            if (preg_match('/sources:\s*\[(.*?)\]/s', $html, $matches)) {
                $sourcesBlock = $matches[1];
                
                // Buscar URLs de video
                if (preg_match_all('/\{file:"([^"]+)"([^}]*)\}/', $sourcesBlock, $sourceMatches)) {
                    foreach ($sourceMatches[1] as $index => $videoUrl) {
                        // Obtener calidad del label
                        $quality = 'HD';
                        if (preg_match('/label:"([^"]+)"/', $sourceMatches[2][$index], $labelMatch)) {
                            $quality = $this->parseQuality($labelMatch[1]);
                        }
                        
                        // Normalizar URL
                        if (strpos($videoUrl, 'http') !== 0) {
                            $videoUrl = 'https://upstream.to' . $videoUrl;
                        }
                        
                        $streams[] = [
                            'url' => $videoUrl . '|Referer=https://upstream.to/',
                            'quality' => $quality,
                            'type' => 'direct',
                            'provider' => 'upstream'
                        ];
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo de Upstream: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Extrae enlaces desde Streamtape
     */
    private function extractStreamtape($url) {
        $streams = [];
        
        try {
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar el enlace de video en Streamtape
            if (preg_match('/get_video\?id=([^&"\']+)/', $html, $matches)) {
                $videoId = $matches[1];
                $videoUrl = 'https://streamtape.com/get_video?id=' . $videoId;
                
                $streams[] = [
                    'url' => $videoUrl,
                    'quality' => 'HD',
                    'type' => 'direct',
                    'provider' => 'streamtape'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo de Streamtape: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Extrae enlaces desde PowVideo
     */
    private function extractPowVideo($url) {
        $streams = [];
        
        try {
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar enlaces de video en PowVideo
            if (preg_match('/player\.setVideoUrl\(["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                
                $streams[] = [
                    'url' => $videoUrl,
                    'quality' => 'HD',
                    'type' => 'direct',
                    'provider' => 'powvideo'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo de PowVideo: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Extrae enlaces desde Filemoon
     */
    private function extractFilemoon($url) {
        $streams = [];
        
        try {
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar enlaces de video en Filemoon
            if (preg_match('/player\.src\(["\']([^"\']+)["\']/', $html, $matches)) {
                $videoUrl = $matches[1];
                
                $streams[] = [
                    'url' => $videoUrl,
                    'quality' => 'HD',
                    'type' => 'direct',
                    'provider' => 'filemoon'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo de Filemoon: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Extrae enlaces desde Streamwish
     */
    private function extractStreamwish($url) {
        $streams = [];
        
        try {
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar enlaces de video en Streamwish
            if (preg_match('/sources:\s*\[.*?"file":\s*"([^"]+)"/s', $html, $matches)) {
                $videoUrl = $matches[1];
                
                $streams[] = [
                    'url' => $videoUrl,
                    'quality' => 'HD',
                    'type' => 'direct',
                    'provider' => 'streamwish'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo de Streamwish: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Extracción genérica de enlaces desde una página
     */
    private function extractGeneric($url) {
        $streams = [];
        
        try {
            $html = $this->fetchPage($url);
            
            if (empty($html)) {
                return $streams;
            }
            
            // Buscar enlaces de video comunes
            $patterns = [
                '/["\'](https?:\/\/[^"\']+\.(mp4|m3u8|webm|mkv)[^"\']*)["\']/i',
                '/src=["\'](https?:\/\/[^"\']+\.(mp4|m3u8|webm|mkv)[^"\']*)["\']/i',
                '/file:\s*["\'](https?:\/\/[^"\']+\.(mp4|m3u8|webm|mkv)[^"\']*)["\']/i'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    foreach ($matches[1] as $videoUrl) {
                        $streams[] = [
                            'url' => $videoUrl,
                            'quality' => 'HD',
                            'type' => 'direct',
                            'provider' => 'generic'
                        ];
                    }
                    break; // Si encontramos algo, no buscar más
                }
            }
            
        } catch (Exception $e) {
            error_log("Error extrayendo genérico: " . $e->getMessage());
        }
        
        return $streams;
    }
    
    /**
     * Obtiene el contenido HTML de una página
     */
    private function fetchPage($url) {
        if (!function_exists('curl_init')) {
            return '';
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Referer: ' . $url
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400 || empty($html)) {
            return '';
        }
        
        return $html;
    }
    
    /**
     * Parsea la calidad desde un label
     */
    private function parseQuality($label) {
        $label = strtolower($label);
        
        if (strpos($label, '4k') !== false || strpos($label, '2160') !== false) return '4K';
        if (strpos($label, '1080') !== false || strpos($label, 'full hd') !== false) return '1080p';
        if (strpos($label, '720') !== false || strpos($label, 'hd') !== false) return '720p';
        if (strpos($label, '480') !== false) return '480p';
        if (strpos($label, '360') !== false) return '360p';
        
        return 'HD';
    }
}

