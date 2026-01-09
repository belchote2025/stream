# Cómo Buscar Enlaces de Películas y Series en el Addon Balandro

## 📋 Resumen

El addon **Balandro** busca automáticamente enlaces de reproducción cuando intentas ver una película o serie. Funciona de forma similar a los addons de Kodi, buscando en múltiples fuentes.

## 🔍 Cómo Funciona la Búsqueda de Enlaces

### 1. **Búsqueda Automática al Reproducir**

Cuando haces clic en "Reproducir" en una película o serie, el sistema automáticamente:

1. **Llama al addon Balandro** para buscar enlaces
2. **Busca en este orden de prioridad:**
   - ✅ Enlaces locales guardados en la base de datos (`video_url`)
   - ✅ Vidsrc (si el contenido tiene IMDb ID)
   - ✅ Fuentes web (Upstream, Streamtape, Filemoon, etc.)
   - ✅ Torrents (si están habilitados)

### 2. **API Endpoint para Obtener Streams**

Puedes obtener enlaces manualmente usando la API:

```
GET /api/addons/streams.php?content_id=123&content_type=movie
GET /api/addons/streams.php?content_id=456&content_type=series&season=1&episode=1
```

**Parámetros:**
- `content_id`: ID del contenido en la base de datos
- `content_type`: `movie` o `series`
- `season`: Número de temporada (solo para series)
- `episode`: Número de episodio (solo para series)

**Ejemplo de respuesta:**
```json
{
  "success": true,
  "data": {
    "content_id": 123,
    "content_type": "movie",
    "streams": [
      {
        "url": "https://vidsrc.to/embed/movie/tt1234567",
        "quality": "HD",
        "type": "embed",
        "provider": "vidsrc",
        "addon": "balandro"
      }
    ],
    "total": 1,
    "sources": ["balandro"]
  }
}
```

### 3. **Búsqueda Específica del Addon Balandro**

También puedes llamar directamente al addon:

```
GET /api/addons/balandro/streams.php?id=123&type=movie
GET /api/addons/balandro/streams.php?id=456&type=tv&season=1&episode=1
```

## 🎯 Fuentes de Enlaces que Busca el Addon

### Fuentes Habilitadas por Defecto:

1. **Vidsrc** (`enable_vidsrc: true`)
   - Busca enlaces usando el IMDb ID del contenido
   - Soporta películas y series
   - Para series, busca enlaces específicos por temporada/episodio

2. **Fuentes Web** (`enable_upstream: true`, `enable_web_scraping: true`)
   - Upstream.to
   - Streamtape.com
   - Filemoon.sx
   - Powvideo.net
   - Streamwish.to

3. **Torrents** (`enable_torrents: false` por defecto)
   - Usa el `torrent_magnet` guardado en la base de datos
   - Solo si no se encontraron otros enlaces

## ⚙️ Configuración del Addon

Puedes configurar qué fuentes usar desde el panel de administración:

1. Ve a **Administración → Gestión de Addons**
2. Haz clic en **Configurar** en el addon Balandro
3. Ajusta las opciones:
   - ✅ `enable_vidsrc`: Habilitar búsqueda en Vidsrc
   - ✅ `enable_upstream`: Habilitar búsqueda en fuentes web
   - ✅ `enable_web_scraping`: Habilitar scraping web
   - ❌ `enable_torrents`: Habilitar torrents (deshabilitado por defecto)
   - ⏱️ `timeout`: Tiempo de espera para búsquedas (15 segundos)
   - 💾 `enable_caching`: Habilitar caché de resultados (1 hora)

## 🔧 Uso Programático

### Desde PHP:

```php
require_once __DIR__ . '/includes/addons/AddonManager.php';

$addonManager = AddonManager::getInstance();
$addonManager->loadAddons();

// Obtener streams para una película
$streams = $addonManager->getStreams($contentId, 'movie');

// Obtener streams para un episodio de serie
$streams = $addonManager->getStreams($contentId, 'series', $episodeId);
```

### Desde JavaScript:

```javascript
// Obtener streams de una película
fetch('/api/addons/streams.php?content_id=123&content_type=movie')
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('Streams encontrados:', data.data.streams);
      // Usar el primer stream disponible
      if (data.data.streams.length > 0) {
        const streamUrl = data.data.streams[0].url;
        // Reproducir el stream
      }
    }
  });
```

## 📝 Notas Importantes

1. **IMDb ID Requerido para Vidsrc**: 
   - El contenido debe tener un `imdb_id` para que Vidsrc funcione
   - Si no tiene IMDb ID, el addon intentará otras fuentes

2. **Caché de Resultados**:
   - Los resultados se guardan en caché por 1 hora
   - Puedes limpiar la caché desde el panel de administración

3. **Orden de Prioridad**:
   - Los enlaces locales tienen máxima prioridad
   - Luego Vidsrc (más confiable)
   - Finalmente fuentes web (pueden ser más lentas)

4. **Series**:
   - Para series, siempre especifica `season` y `episode`
   - El addon buscará enlaces específicos para ese episodio

## 🐛 Solución de Problemas

### No se encuentran enlaces:

1. **Verifica que el addon esté habilitado**:
   - Administración → Gestión de Addons → Activar Balandro

2. **Verifica la configuración**:
   - Asegúrate de que `enable_vidsrc` o `enable_upstream` estén activados

3. **Verifica que el contenido tenga IMDb ID**:
   - Si no tiene IMDb ID, Vidsrc no funcionará
   - El script de actualización debería agregar IMDb IDs automáticamente

4. **Revisa los logs**:
   - Activa `debug_mode: true` en la configuración del addon
   - Revisa los logs del servidor para ver errores

### Los enlaces no funcionan:

1. **Prueba diferentes fuentes**:
   - Algunas fuentes pueden estar caídas
   - El addon intentará múltiples fuentes automáticamente

2. **Limpia la caché**:
   - Los enlaces en caché pueden estar obsoletos
   - Limpia la caché desde el panel de administración

3. **Verifica la conexión a internet**:
   - Las fuentes web requieren conexión a internet
   - Algunas pueden estar bloqueadas por tu ISP

## 📚 Ejemplos de Uso

### Ejemplo 1: Obtener enlaces de una película

```bash
curl "http://localhost/streaming-platform/api/addons/streams.php?content_id=91&content_type=movie"
```

### Ejemplo 2: Obtener enlaces de un episodio

```bash
curl "http://localhost/streaming-platform/api/addons/streams.php?content_id=45&content_type=series&season=1&episode=1"
```

### Ejemplo 3: Buscar contenido en el addon

```bash
curl "http://localhost/streaming-platform/api/addons/balandro/search.php?q=matrix"
```

## 🎬 Integración con el Reproductor

El reproductor de video (`watch.php`) automáticamente:

1. Intenta usar el `video_url` o `torrent_magnet` de la base de datos
2. Si no hay, llama a `api/addons/streams.php` para buscar enlaces
3. Usa el primer enlace disponible para reproducir

No necesitas hacer nada especial, el sistema busca enlaces automáticamente cuando intentas reproducir contenido.
