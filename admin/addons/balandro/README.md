# Guía de Uso - Addon Balandro

## 📖 Descripción

El addon Balandro es un adaptación del addon de Kodi que permite buscar y reproducir contenido multimedia desde múltiples fuentes de streaming. Funciona como navegador de páginas web para extraer enlaces de streaming.

## 🚀 Configuración Inicial

### 1. Acceder a la Configuración

1. Ve al **Panel de Administración**
2. Haz clic en **Addons** en el menú lateral
3. Busca **Balandro Addon** en la lista
4. Haz clic en **Configurar** o en el nombre del addon

### 2. Configurar Opciones

En la página de configuración puedes ajustar:

- **URL de la API**: URL base de la API (por defecto: `https://repobal.github.io/base/`)
- **Clave de API**: Si la API requiere autenticación (opcional)
- **Habilitar caché**: Activa/desactiva el sistema de caché
- **Tiempo de caché**: Duración en segundos (por defecto: 3600 = 1 hora)
- **Resultados por página**: Número máximo de resultados (por defecto: 20)
- **Calidad predeterminada**: 4K, 1080p, 720p, 480p, 360p
- **Timeout**: Tiempo de espera para peticiones (por defecto: 15 segundos)
- **Modo debug**: Activa logs detallados para depuración

### 3. Fuentes de Streaming

Puedes habilitar/deshabilitar diferentes fuentes:

- ✅ **Vidsrc**: Fuente principal (vidsrc.to, vidsrc.cc, smashystream)
- ✅ **Upstream/PowVideo/Filemoon**: Fuentes alternativas de streaming
- ✅ **Navegación web**: Extracción de enlaces desde páginas web (estilo Kodi)
- ⚠️ **Torrents**: Solo si no hay streaming disponible (opcional)

## 🔍 Uso del Addon

### Búsqueda de Contenido

El addon busca primero en tu base de datos local. Para buscar contenido:

**API Endpoint:**
```
GET /api/addons/balandro/search.php?q=batman&type=movie&page=1
```

**Parámetros:**
- `q`: Término de búsqueda (requerido)
- `type`: Tipo de contenido (`movie`, `tv`, `all`) - opcional
- `year`: Año de lanzamiento - opcional
- `genre`: Género - opcional
- `page`: Número de página - opcional

**Ejemplo de respuesta:**
```json
{
    "status": "success",
    "data": {
        "results": [
            {
                "id": "47",
                "title": "Batman",
                "type": "movie",
                "year": 2022,
                "poster": "...",
                "rating": 8.5
            }
        ],
        "total": 1,
        "page": 1,
        "total_pages": 1
    }
}
```

### Obtener Detalles de Contenido

Para obtener información detallada de una película o serie:

**API Endpoint:**
```
GET /api/addons/balandro/details.php?id=47&type=movie
```

**Parámetros:**
- `id`: ID del contenido (requerido)
- `type`: Tipo (`movie` o `tv`) - opcional, por defecto `movie`

**Ejemplo de respuesta:**
```json
{
    "status": "success",
    "data": {
        "id": "47",
        "title": "Batman",
        "overview": "Descripción...",
        "genres": ["Acción", "Drama"],
        "rating": 8.5,
        "year": 2022
    }
}
```

### Obtener Fuentes de Streaming

Para obtener enlaces de streaming (como en Kodi):

**API Endpoint:**
```
GET /api/addons/balandro/streams.php?id=47&type=movie
```

**Para series con episodio específico:**
```
GET /api/addons/balandro/streams.php?id=123&type=tv&season=1&episode=1
```

**Parámetros:**
- `id`: ID del contenido (requerido)
- `type`: Tipo (`movie` o `tv`) - requerido
- `season`: Número de temporada (solo para series) - opcional
- `episode`: Número de episodio (solo para series) - opcional

**Ejemplo de respuesta:**
```json
{
    "status": "success",
    "data": {
        "id": "47",
        "type": "movie",
        "streams": [
            {
                "quality": "4K",
                "type": "embed",
                "url": "https://vidsrc.to/embed/movie/tt1234567",
                "provider": "vidsrc",
                "format": "iframe",
                "name": "Vidsrc"
            },
            {
                "quality": "HD",
                "type": "direct",
                "url": "https://upstream.to/embed-...",
                "provider": "upstream",
                "format": "mp4",
                "name": "Upstream"
            }
        ]
    }
}
```

## 🎬 Integración en el Frontend

### Ejemplo de Uso con JavaScript

```javascript
// Buscar contenido
async function buscarContenido(query) {
    const response = await fetch(`/api/addons/balandro/search.php?q=${encodeURIComponent(query)}`);
    const data = await response.json();
    return data.data.results;
}

// Obtener streams
async function obtenerStreams(contentId, type, season = null, episode = null) {
    let url = `/api/addons/balandro/streams.php?id=${contentId}&type=${type}`;
    if (season !== null && episode !== null) {
        url += `&season=${season}&episode=${episode}`;
    }
    const response = await fetch(url);
    const data = await response.json();
    return data.data.streams;
}

// Usar en el reproductor
async function reproducirContenido(contentId, type) {
    const streams = await obtenerStreams(contentId, type);
    
    if (streams.length > 0) {
        const stream = streams[0]; // Usar el primer stream disponible
        
        if (stream.type === 'embed') {
            // Reproducir embed (iframe)
            mostrarReproductorIframe(stream.url);
        } else if (stream.type === 'direct') {
            // Reproducir video directo
            mostrarReproductorVideo(stream.url);
        }
    }
}
```

## 🔧 Mantenimiento

### Limpiar Caché

1. Ve a **Configuración de Balandro**
2. En la sección "Información del Addon"
3. Haz clic en **Limpiar caché**

O desde el código:
```php
$addonManager = AddonManager::getInstance();
$balandroAddon = $addonManager->getAddon('balandro');
$archivosEliminados = $balandroAddon->clearCache();
```

### Verificar Estado

Accede a la página de pruebas:
```
/admin/addons/balandro/test.php
```

Esta página ejecuta automáticamente todas las pruebas y muestra el estado del addon.

## 📝 Notas Importantes

1. **Prioridad de Fuentes:**
   - Primero: Video local (si existe en la BD)
   - Segundo: Vidsrc (embeds)
   - Tercero: Upstream/PowVideo/Filemoon (streaming directo)
   - Último: Torrents (solo si está habilitado)

2. **Caché:**
   - Los resultados se cachean por 1 hora (configurable)
   - Limpia la caché si los enlaces no funcionan

3. **IMDb ID:**
   - Si el contenido tiene IMDb ID, se usan más fuentes
   - Sin IMDb ID, solo se usan fuentes que no lo requieren

4. **Series:**
   - Para series, especifica `season` y `episode` para obtener streams específicos
   - Sin especificar, obtiene streams generales de la serie

## 🐛 Solución de Problemas

### No se encuentran streams

1. Verifica que el contenido tenga IMDb ID (si es necesario)
2. Comprueba que las fuentes estén habilitadas en la configuración
3. Limpia la caché y vuelve a intentar
4. Activa el modo debug para ver logs detallados

### Búsqueda no encuentra resultados

1. Verifica que haya contenido en la base de datos
2. Prueba con palabras clave en lugar del título completo
3. Comprueba que el tipo de contenido sea correcto (`movie` o `tv`)

### Errores de conexión

1. Verifica la URL de la API en la configuración
2. Comprueba el timeout (aumenta si es necesario)
3. Revisa los logs de error en modo debug

## 📚 Referencias

- **Repositorio original**: https://github.com/repobal/base
- **Foro**: https://www.mimediacenter.info/foro/
- **Telegram**: t.me/balandro_asesor

