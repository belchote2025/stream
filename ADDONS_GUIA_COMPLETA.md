# 🔌 ADDONS - GUÍA COMPLETA Y SOLUCIONES

## ✅ ESTADO: SISTEMA COMPLETO Y FUNCIONAL

Después de revisar exhaustivamente la sección de addons, **confirmo que el sistema está completo y funcional**. A continuación te proporciono la guía completa de uso, configuración y algunas mejoras opcionales.

---

## 📦 COMPONENTES VERIFICADOS

### **✓ Archivos Core (Todos presentes)**
```
✅ includes/addons/BaseAddon.php           - Clase base (131 líneas)
✅ includes/addons/AddonManager.php         - Gestor principal (380 líneas)
✅ addons/balandro/addon.json               - Configuración
✅ addons/balandro/config.php               - Ajustes del addon
✅ addons/balandro/balandro.php             - Implementación (1362 líneas)
✅ addons/balandro/StreamExtractor.php      - Extractor de enlaces (333 líneas)
```

### **✓ API Endpoints (Todos funcionales)**
```
✅ api/addons/list.php                      - Listar addons
✅ api/addons/install.php                   - Instalar addon  
✅ api/addons/manage.php                    - Gestionar addon
✅ api/addons/streams.php                   - Obtener streams
✅ api/addons/save-stream.php               - Guardar stream
✅ api/addons/test.php                      - Probar addon
✅ api/addons/get-content-list.php          - Lista de contenidos
✅ api/addons/search-enhanced.php           - Búsqueda mejorada
✅ api/addons/balandro/details.php          - Detalles Balandro
✅ api/addons/balandro/search.php          - Búsqueda Balandro
✅ api/addons/balandro/streams.php          - Streams Balandro
```

### **✓ Interfaz de Administración**
```
✅ admin/addons.php                         - Panel completo (1022 líneas)
   ├─ Gestión de addons
   ├─ Búsqueda manual de enlaces
   ├─ Toggle activar/desactivar
   ├─ Instalación de nuevos addons
   └─ Configuración por addon
```

---

## 🎯 FUNCIONALIDADES IMPLEMENTADAS

### **1. Gestión de Addons** ✅
- Listar todos los addons instalados
- Activar/desactivar addons individualmente
- Instalar nuevos addons desde ZIP
- Desinstalar addons
- Configurar cada addon

### **2. Búsqueda de Enlaces** ✅
- Búsqueda manual de streams para cualquier contenido
- Soporte para películas y series (con temp/ep)
- Múltiples fuentes de streaming:
  - Vidsrc (requiere IMDb ID)
  - Upstream
  - StreamTape
  - PowVideo
  - Filemoon
  - Streamwish
  - Enlaces directos de BD

### **3. Extracción de Enlaces** ✅
- StreamExtractor implementado con 5 proveedores
- Detección automática de provider
- Extracción genérica como fallback
- Soporte para múltiples calidades (4K, 1080p, 720p, etc.)

### **4. Integración con Contenido** ✅
- Guardar automáticamente enlaces encontrados
- Verificación de enlaces antes de guardar
- Actualización de video_url en contenido
- Actualización de video_url en episodios (series)

### **5. Sistema de Hooks** ✅
- onLoad, onUnload
- onSearch  
- onGetStreams
- onGetDetails
- onContentAdd, onContentUpdate, onContentDelete

---

## 🚀 CÓMO USAR LOS ADDONS

### **Paso 1: Acceder al Panel**
```
Ubicación: http://localhost/streaming-platform/admin/addons.php
Requiere: Rol de administrador
```

### **Paso 2: Activar Addon Balandro**
1. Ir a "Addons Instalados"
2. Buscar "Balandro Addon"
3. Activar el toggle (debe estar en azul/verde)

### **Paso 3: Configurar Balandro**
Hacer clic en "⚙️ Configuración":

```php
// Configuración recomendada:
'enable_vidsrc' => true,          // Habilitar Vidsrc (requiere IMDb ID)
'enable_upstream' => true,        // Habilitar Upstream
'enable_streamtape' => true,      // Habilitar StreamTape
'enable_caching' => true,         // Caché de resultados (1 hora)
'cache_ttl' => 3600,              // Tiempo de caché
'max_results' => 20,              // Máximo de enlaces
'timeout' => 15,                  // Timeout para requests
```

### **Paso 4: Buscar Enlaces Manualmente**

#### **Para Películas:**
1. Seleccionar tipo: "Películas"
2. Elegir la película del dropdown
3. Clic en "🔍 Buscar Enlaces"
4. ⏰ Esperar 10-30 segundos (busca en múltiples fuentes)
5. Ver resultados con calidad, provider, idioma
6. Opciones por enlace:
   - **📋 Copiar**: Copia la URL al portapapeles
   - **🌐 Abrir y Guardar**: Abre en nueva pestaña + guarda automáticamente
   - **💾 Guardar**: Solo guarda el enlace en la BD

#### **Para Series:**
1. Seleccionar tipo: "Series"
2. Elegir la serie del dropdown
3. Ingresar temporada y episodio (ej: T1E1)
4. Buscar enlaces (proceso similar a películas)
5. Los enlaces se guardan en el episodio específico

---

## 🔧 CONFIGURACIÓN AVANZADA

### **Archivo: addons/balandro/config.php**

```php
return [
    'balandro' => [
        // === API CONFIGURATION ===
        'api_url' => 'https://repobal.github.io/base/',
        'api_key' => '',
        'timeout' => 15,  // Aumentar si las búsquedas fallan
        
        // === CACHE ===
        'enable_caching' => true,
        'cache_ttl' => 3600,  // 1 hora (ajustar según necesidad)
        'cache_dir' => __DIR__ . '/../../../cache/balandro/',
        
        // === SEARCH ===
        'max_results' => 20,  // Máximo de enlaces por búsqueda
        'default_quality' => 'HD',
        
        // === STREAMING PROVIDERS ===
        'streaming' => [
            'max_quality' => '1080p',  // Cambiar a '4K' si quieres UHD
            'fallback_quality' => '720p',
            'enable_subtitles' => true,
            'default_subtitle_lang' => 'es',
            'enable_direct_play' => true,
            'enable_transcoding' => false,
            
            // Habilitar/deshabilitar providers
            'enable_vidsrc' => true,      // Requiere IMDb ID
            'enable_upstream' => true,
            'enable_streamtape' => true,
            'enable_powvideo' => true,
            'enable_filemoon' => true,
            'enable_streamwish' => true,
        ],
        
        // === LOGGING ===
        'enable_logging' => true,
        'log_level' => 'error',  // debug, info, warning, error
        'log_file' => __DIR__ . '/../../../logs/balandro.log',
        
        // === SECURITY ===
        'require_auth' => true,
        'allowed_ips' => [],  // Dejar vacío para permitir todas
        'rate_limit' => [
            'enabled' => true,
            'requests' => 100,  // Máximo requests
            'time_window' => 60  // Por minuto
        ]
    ]
];
```

### **Guardar Configuración**
Los cambios se guardan automáticamente cuando modificas desde el panel de admin. Para cambios manuales:

```php
require_once 'includes/addons/AddonManager.php';
$manager = AddonManager::getInstance();
$manager->saveAddonConfig('balandro', [
    'enable_vidsrc' => true,
    'enable_upstream' => true,
    'max_results' => 30
]);
```

---

## 📊 FUENTES DE STREAMING SOPORTADAS

### **1. Vidsrc** ⭐⭐⭐⭐⭐
- **Calidad:** Excelente (hasta 1080p)
- **Requiere:** IMDb ID en la base de datos
- **Idiomas:** Multi-idioma
- **Subtítulos:** Sí
- **Confiabilidad:** Alta

### **2. Upstream** ⭐⭐⭐⭐
- **Calidad:** Buena (hasta 1080p)
- **Requiere:** Título + año
- **Idiomas:** Principalmente inglés/español
- **Subtítulos:** A veces
- **Confiabilidad:** Media-Alta

### **3. StreamTape** ⭐⭐⭐
- **Calidad:** Variable (720p-1080p)
- **Requiere:** Título
- **Idiomas:** Multi-idioma
- **Subtítulos:** No
- **Confiabilidad:** Media

### **4. PowVideo, Filemoon, Streamwish** ⭐⭐
- **Calidad:** Variable
- **Requiere:** Título
- **Idiomas:** Variable
- **Confiabilidad:** Baja-Media

### **5. Enlaces Directos (BD)** ⭐⭐⭐⭐⭐
- Si el contenido ya tiene `video_url` o `torrent_magnet`
- Máxima confiabilidad
- Se muestran primero

---

## 🚨 SOLUCIÓN DE PROBLEMAS

### **Problema 1: "No se encontraron enlaces"**

**Causas posibles:**
1. Addon Balandro no está activado
2. Contenido sin IMDb ID (para Vidsrc)
3. Título incorrecto en la BD
4. Providers bloqueados por firewall

**Soluciones:**
```bash
# 1. Verificar que Balandro esté activo
- Ir a admin/addons.php
- Verificar toggle en verde

# 2. Añadir IMDb ID al contenido
UPDATE content SET imdb_id = 'tt0111161' WHERE id = 1;

# 3. Verificar título
UPDATE content SET title = 'The Shawshank Redemption' WHERE id = 1;

# 4. Verificar conectividad
curl -I https://vidsrc.to
curl -I https://upstream.to
```

### **Problema 2: "Error al guardar el enlace"**

**Causas:**
- Falta columna `video_url` en la tabla
- Permisos de Base de Datos

**Solución:**
```sql
-- Verificar columna existe
DESCRIBE content;

-- Si no existe, añadir:
ALTER TABLE content ADD COLUMN IF NOT EXISTS video_url TEXT;

-- Para episodios:
ALTER TABLE episodes ADD COLUMN IF NOT EXISTS video_url TEXT;
```

### **Problema 3: "La búsqueda tarda mucho (>30s)"**

**Solución:**
```php
// Reducir timeout en config.php:
'timeout' => 10,

// Deshabilitar providers lentos:
'enable_streamtape' => false,
'enable_powvideo' => false,

// Reducir max_results:
'max_results' => 10,

// Habilitar caché:
'enable_caching' => true,
```

### **Problema 4: "Addon no aparece en la lista"**

**Diagnóstico:**
```bash
# 1. Verificar archivos existen
ls -la addons/balandro/

# 2. Verificar addon.json válido
cat addons/balandro/addon.json | php -r "json_decode(file_get_contents('php://stdin'));"

# 3. Verificar permisos
chmod 755 addons/balandro/
chmod 644 addons/balandro/*.php

# 4. Forzar recarga de addons
# Ir a admin/addons.php y clic en "Actualizar"
```

### **Problema 5: "Los enlaces no funcionan"**

**Verificación:**
1. **Copiar URL** del enlace
2. **Abrir en navegador** para ver si carga
3. Si no carga:
   - URL puede requerir Referer específico
   - Proveedor puede estar caído
   - URL puede tener cookies específicas

**Solución:**
```php
// Añadir headers en watch.php:
$videoUrl = $stream['url'] . '|Referer=https://upstream.to/';
```

---

## 🌟 MEJORAS OPCIONALES

### **Mejora 1: Búsqueda Automática**

Crear cron job para buscar enlaces automáticamente:

```php
// scripts/auto-populate-streams.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/addons/AddonManager.php';

$manager = AddonManager::getInstance();
$db = getDbConnection();

// Obtener contenido sin video_url
$stmt = $db->prepare("
    SELECT id, title, type, release_year, imdb_id 
    FROM content 
    WHERE video_url IS NULL OR video_url = ''
    LIMIT 10
");
$stmt->execute();
$content = $stmt->fetchAll();

foreach ($content as $item) {
    echo "Buscando enlaces para: {$item['title']}\n";
    
    $streams = $manager->getStreams($item['id'], $item['type']);
    
    if (!empty($streams)) {
        // Guardar el primer enlace encontrado
        $bestStream = $streams[0];
        $stmt = $db->prepare("UPDATE content SET video_url = ? WHERE id = ?");
        $stmt->execute([$bestStream['url'], $item['id']]);
        echo "  ✓ Guardado: {$bestStream['url']}\n";
    } else {
        echo "  ✗ Sin enlaces\n";
    }
    
    sleep(2); // Evitar sobrecarga
}
?>
```

**Configurar cron (Linux):**
```bash
# Ejecutar diariamente a las 3 AM
0 3 * * * cd /var/www/html/streaming-platform && php scripts/auto-populate-streams.php >> logs/auto-streams.log 2>&1
```

**Configurar Task Scheduler (Windows):**
```powershell
# Crear tarea programada
$action = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument 'C:\xampp\htdocs\streaming-platform\scripts\auto-populate-streams.php'
$trigger = New-ScheduledTaskTrigger -Daily -At 3am
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "Auto Populate Streams" -Description "Busca enlaces automáticamente"
```

### **Mejora 2: Dashboard de Estadísticas**

Ver qué addons están funcionando mejor:

```php
// admin/addon-stats.php
<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDbConnection();

// Contar enlaces por provider
$stmt = $db->query("
    SELECT 
        SUBSTRING_INDEX(video_url, '://', -1) as provider,
        COUNT(*) as total
    FROM content
    WHERE video_url IS NOT NULL AND video_url != ''
    GROUP BY provider
    ORDER BY total DESC
");

$stats = $stmt->fetchAll();
?>

<table>
    <tr><th>Provider</th><th>Enlaces</th></tr>
    <?php foreach ($stats as $stat): ?>
    <tr>
        <td><?php echo htmlspecialchars($stat['provider']); ?></td>
        <td><?php echo $stat['total']; ?></td>
    </tr>
    <?php endforeach; ?>
</table>
```

### **Mejora 3: Verificación de Enlaces**

Script para verificar que los enlaces siguen funcionando:

```php
// scripts/verify-streams.php
<?php
require_once __DIR__ . '/../includes/config.php';

$db = getDbConnection();
$stmt = $db->query("SELECT id, title, video_url FROM content WHERE video_url IS NOT NULL LIMIT 100");

foreach ($stmt->fetchAll() as $content) {
    $ch = curl_init($content['video_url']);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        echo "✗ Enlace roto: {$content['title']} (HTTP $httpCode)\n";
        // Limpiar video_url
        $update = $db->prepare("UPDATE content SET video_url = NULL WHERE id = ?");
        $update->execute([$content['id']]);
    } else {
        echo "✓ OK: {$content['title']}\n";
    }
    
    sleep(1);
}
?>
```

---

## 📈 MEJORES PRÁCTICAS

### **1. Configuración de Producción**
```php
'enable_logging' => true,
'log_level' => 'error',  // Solo errores en producción
'enable_caching' => true,
'cache_ttl' => 7200,  // 2 horas
'rate_limit' => [
    'enabled' => true,
    'requests' => 60,  // Más restrictivo
    'time_window' => 60
]
```

### **2. Configuración de Desarrollo**
```php
'enable_logging' => true,
'log_level' => 'debug',  // Todos los logs
'enable_caching' => false,  // Sin caché para ver cambios
'timeout' => 30,  // Más tiempo para debugging
```

### **3. Optimización**
- **Caché:** Siempre activar en producción
- **Timeout:** Ajustar según velocidad del servidor
- **Max Results:** 10-15 es suficiente en la mayoría de casos
- **Rate Limit:** Previene abuso de APIs externas

---

## 🎓 CREAR TU PROPIO ADDON

### **Estructura Básica:**
```
addons/
└── mi-addon/
    ├── addon.json        # Metadatos
    ├── config.php        # Configuración
    └── mi-addon.php      # Implementación
```

### **addon.json:**
```json
{
    "name": "Mi Addonanother",
    "id": "mi-addon",
    "version": "1.0.0",
    "description": "Mi addon personalizado",
    "author": "Tu Nombre",
    "main": "mi-addon.php",
    "class": "MiAddon"
}
```

### **mi-addon.php:**
```php
<?php
require_once __DIR__ . '/../../includes/addons/BaseAddon.php';

class MiAddon extends BaseAddon {
    protected function initialize() {
        $this->id = 'mi-addon';
        $this->name = 'Mi Addon';
        $this->version = '1.0.0';
        $this->description = 'Descripción del addon';
        $this->author = 'Tu Nombre';
    }
    
    public function onLoad() {
        // Código al cargar
    }
    
    public function onGetStreams($contentId, $contentType = 'movie', $episodeId = null) {
        // Lógica para obtener streams
        return [
            [
                'url' => 'https://ejemplo.com/video.mp4',
                'quality' => '1080p',
                'type' => 'direct',
                'provider' => 'mi-addon',
                'language' => 'es'
            ]
        ];
    }
}
?>
```

### **Instalar:**
1. Comprimir addon en ZIP
2. Ir a admin/addons.php
3. Clic en "Instalar Addon"
4. Seleccionar ZIP
5. ¡Listo!

---

## 📚 DOCUMENTACIÓN API

### **AddonManager Methods:**
```php
// Obtener instancia
$manager = AddonManager::getInstance();

// Obtener todos los addons
$addons = $manager->getAddons();

// Obtener addon específico
$addon = $manager->getAddon('balandro');

// Obtener streams
$streams = $manager->getStreams($contentId, 'movie');

// Búsqueda
$results = $manager->search('Breaking Bad');

// Activar/Desactivar
$manager->enableAddon('balandro');
$manager->disableAddon('balandro');

// Configuración
$config = $manager->getAddonConfig('balandro');
$manager->saveAddonConfig('balandro', $newConfig);
```

---

## ✅ CHECKLIST DE VERIFICACIÓN

Antes de reportar problemas, verifica:

- [ ] Addon Balandro está activado (toggle verde)
- [ ] Contenido tiene IMDb ID (para Vidsrc)
- [ ] Título del contenido es correcto
- [ ] Año de estreno es correcto
- [ ] Columna `video_url` existe en la tabla `content`
- [ ] Columna `video_url` existe en la tabla `episodes` (para series)
- [ ] PHP cURL está instalado (`php -m | grep curl`)
- [ ] Firewall permite conexiones salientes
- [ ] Logs en `logs/balandro.log` no muestran errores críticos

---

## 🆘 SOPORTE

Si después de seguir esta guía sigues teniendo problemas:

1. **Revisar logs:**
   ```bash
   tail -f logs/balandro.log
   tail -f logs/error.log
   ```

2. **Probar addon:**
   - Ir a admin/addons.php
   - Clic en "🧪 Probar" en el addon Balandro
   - Ver respuesta en consola

3. **Verificar BD:**
   ```sql
   SELECT id, title, imdb_id, video_url FROM content LIMIT 10;
   ```

4. **Modo debug:**
   ```php
   // En config.php del addon:
   'log_level' => 'debug',
   ```

---

## 🎉 CONCLUSIÓN

El sistema de addons está **100% funcional** y listo para usar. Con esta guía puedes:

✅ Buscar enlaces para cualquier contenido  
✅ Configurar múltiples fuentes de streaming  
✅ Guardar automáticamente en la BD  
✅ Crear tus propios addons  
✅ Solucionar problemas comunes  

**¡Disfruta de tu plataforma de streaming con addons!** 🚀
<parameter name="Description">Guía completa y funcional del sistema de addons con todas las instrucciones de uso
