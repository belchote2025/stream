# 📘 Guía Rápida de Uso - Addon Balandro

## 🎯 ¿Qué hace este addon?

El addon Balandro permite:
- ✅ Buscar contenido en tu base de datos
- ✅ Obtener detalles de películas y series
- ✅ Obtener enlaces de streaming desde múltiples fuentes (Vidsrc, Upstream, etc.)
- ✅ Funciona como el addon de Kodi pero en tu plataforma web

## 🚀 Inicio Rápido

### Paso 1: Acceder a la Configuración

1. Ve al **Panel de Administración** → **Addons**
2. Busca **"Balandro Addon"** en la lista
3. Asegúrate de que esté **Habilitado** (interruptor activado)
4. Haz clic en **"Configuración"**

### Paso 2: Configurar (Opcional)

En la página de configuración puedes ajustar:
- **Fuentes de Streaming**: Activa/desactiva Vidsrc, Upstream, etc.
- **Calidad predeterminada**: 4K, 1080p, 720p, etc.
- **Timeout**: Tiempo de espera para peticiones
- **Modo debug**: Para ver logs detallados

**Nota**: La configuración por defecto funciona bien para la mayoría de casos.

### Paso 3: Usar el Addon

El addon se usa principalmente a través de **APIs** desde tu código o frontend.

## 📡 Uso desde el Código (PHP)

### Ejemplo 1: Buscar Contenido

```php
require_once __DIR__ . '/includes/addons/AddonManager.php';

$addonManager = AddonManager::getInstance();
$balandroAddon = $addonManager->getAddon('balandro');

// Buscar contenido
$resultados = $balandroAddon->onSearch('batman', ['type' => 'movie']);

foreach ($resultados['results'] as $item) {
    echo $item['title'] . " (" . $item['year'] . ")\n";
}
```

### Ejemplo 2: Obtener Detalles

```php
$detalles = $balandroAddon->onGetDetails(47, 'movie');

if ($detalles) {
    echo "Título: " . $detalles['title'] . "\n";
    echo "Descripción: " . $detalles['overview'] . "\n";
    echo "Rating: " . $detalles['rating'] . "\n";
}
```

### Ejemplo 3: Obtener Streams

```php
// Para una película
$streams = $balandroAddon->onGetStreams(47, 'movie');

// Para una serie (episodio específico)
$streams = $balandroAddon->onGetStreams(123, 'tv', 1, 5); // Temporada 1, Episodio 5

foreach ($streams as $stream) {
    echo "Proveedor: " . $stream['provider'] . "\n";
    echo "URL: " . $stream['url'] . "\n";
    echo "Calidad: " . $stream['quality'] . "\n\n";
}
```

## 🌐 Uso desde el Frontend (JavaScript)

### Ejemplo: Buscar y Reproducir

```javascript
// 1. Buscar contenido
async function buscarYReproducir(query) {
    // Buscar
    const searchResponse = await fetch(`/api/addons/balandro/search.php?q=${encodeURIComponent(query)}`);
    const searchData = await searchResponse.json();
    
    if (searchData.data.results.length > 0) {
        const contenido = searchData.data.results[0];
        
        // Obtener streams
        const streamsResponse = await fetch(`/api/addons/balandro/streams.php?id=${contenido.id}&type=${contenido.type}`);
        const streamsData = await streamsResponse.json();
        
        if (streamsData.data.streams.length > 0) {
            const stream = streamsData.data.streams[0];
            
            // Reproducir según el tipo
            if (stream.type === 'embed') {
                // Mostrar iframe para embeds (Vidsrc)
                mostrarIframe(stream.url);
            } else if (stream.type === 'direct') {
                // Reproducir video directo
                reproducirVideo(stream.url);
            }
        }
    }
}

// Usar
buscarYReproducir('batman');
```

## 🔗 Endpoints de la API

### 1. Búsqueda
```
GET /api/addons/balandro/search.php?q=batman&type=movie
```

### 2. Detalles
```
GET /api/addons/balandro/details.php?id=47&type=movie
```

### 3. Streaming
```
GET /api/addons/balandro/streams.php?id=47&type=movie
GET /api/addons/balandro/streams.php?id=123&type=tv&season=1&episode=5
```

**Nota**: Todos los endpoints requieren autenticación (sesión de usuario).

## 🎬 Integración en watch.php

Puedes integrar el addon en tu página de reproducción:

```php
// En watch.php
require_once __DIR__ . '/includes/addons/AddonManager.php';

$addonManager = AddonManager::getInstance();
$balandroAddon = $addonManager->getAddon('balandro');

if ($balandroAddon && $balandroAddon->isEnabled()) {
    // Obtener streams del addon
    $streams = $balandroAddon->onGetStreams($contentId, $contentType, $season, $episode);
    
    // Si hay streams del addon, usarlos
    if (!empty($streams)) {
        // Mostrar opciones de streaming
        foreach ($streams as $stream) {
            echo "<option value='" . htmlspecialchars($stream['url']) . "'>";
            echo $stream['name'] . " (" . $stream['quality'] . ")";
            echo "</option>";
        }
    }
}
```

## 🧪 Probar el Addon

Accede a la página de pruebas:
```
/admin/addons/balandro/test.php
```

Esta página:
- ✅ Verifica que todo funcione correctamente
- ✅ Prueba búsqueda, detalles y streams
- ✅ Muestra información técnica del addon
- ✅ Permite probar búsquedas y detalles en tiempo real

## 💡 Casos de Uso

### Caso 1: Búsqueda desde el Frontend

```javascript
// Cuando el usuario busca en tu plataforma
$('#searchForm').on('submit', async function(e) {
    e.preventDefault();
    const query = $('#searchInput').val();
    
    const response = await fetch(`/api/addons/balandro/search.php?q=${query}`);
    const data = await response.json();
    
    // Mostrar resultados
    mostrarResultados(data.data.results);
});
```

### Caso 2: Obtener Streams Alternativos

```php
// Si el video local no está disponible, usar streams del addon
if (empty($content['video_url'])) {
    $streams = $balandroAddon->onGetStreams($contentId, $contentType);
    
    if (!empty($streams)) {
        // Usar el primer stream disponible
        $videoUrl = $streams[0]['url'];
    }
}
```

### Caso 3: Múltiples Fuentes de Streaming

```php
// Mostrar todas las fuentes disponibles para que el usuario elija
$streams = $balandroAddon->onGetStreams($contentId, $contentType);

echo "<select id='streamSelector'>";
foreach ($streams as $index => $stream) {
    echo "<option value='{$index}'>";
    echo "{$stream['name']} - {$stream['quality']} ({$stream['provider']})";
    echo "</option>";
}
echo "</select>";
```

## ⚙️ Configuración Avanzada

### Habilitar/Deshabilitar Fuentes

En la configuración puedes controlar qué fuentes usar:

- **Vidsrc**: Mejor para contenido con IMDb ID
- **Upstream/PowVideo**: Fuentes alternativas de streaming directo
- **Navegación web**: Extrae enlaces desde páginas web (como en Kodi)
- **Torrents**: Solo como último recurso

### Ajustar Caché

- **Habilitar caché**: Mejora el rendimiento (recomendado)
- **Tiempo de caché**: 3600 segundos = 1 hora (ajustable)
- **Limpiar caché**: Si los enlaces no funcionan, limpia la caché

## 🔍 Ejemplos Prácticos

### Ejemplo Completo: Buscar y Reproducir

```php
<?php
require_once __DIR__ . '/includes/addons/AddonManager.php';

$addonManager = AddonManager::getInstance();
$balandroAddon = $addonManager->getAddon('balandro');

if ($balandroAddon && $balandroAddon->isEnabled()) {
    // Buscar
    $resultados = $balandroAddon->onSearch('matrix', ['type' => 'movie']);
    
    if (!empty($resultados['results'])) {
        $pelicula = $resultados['results'][0];
        
        // Obtener detalles
        $detalles = $balandroAddon->onGetDetails($pelicula['id'], 'movie');
        
        // Obtener streams
        $streams = $balandroAddon->onGetStreams($pelicula['id'], 'movie');
        
        // Mostrar información
        echo "<h1>{$detalles['title']}</h1>";
        echo "<p>{$detalles['overview']}</p>";
        
        // Mostrar opciones de streaming
        echo "<h2>Fuentes de Streaming:</h2>";
        echo "<ul>";
        foreach ($streams as $stream) {
            echo "<li>";
            echo "<strong>{$stream['name']}</strong> - ";
            echo "Calidad: {$stream['quality']} - ";
            echo "<a href='{$stream['url']}' target='_blank'>Reproducir</a>";
            echo "</li>";
        }
        echo "</ul>";
    }
}
?>
```

## 📞 Soporte

Si tienes problemas:
1. Ve a `/admin/addons/balandro/test.php` para verificar el estado
2. Activa el **modo debug** en la configuración
3. Revisa los logs de error de PHP
4. Consulta el README.md completo para más detalles

---

**¡Listo!** Ya puedes usar el addon Balandro en tu plataforma. 🎉

