# Configuración de Jackett para Búsqueda de Torrents

## 🚀 Inicio Rápido

1. **Instalar Jackett** (ver sección de instalación abajo)
2. **Obtener API Key** desde la interfaz web de Jackett
3. **Probar conexión**: Abre `test-jackett-connection.php` en tu navegador
4. **Configurar variables**: Añade a tu archivo `.env`:
   ```env
   JACKETT_URL=http://localhost:9117
   JACKETT_API_KEY=tu_api_key_aqui
   ```

## ¿Qué es Jackett?

Jackett es un servidor proxy que traduce consultas de aplicaciones de torrents (como Sonarr, Radarr, etc.) a sitios de rastreo específicos. Actúa como un agregador de múltiples indexadores de torrents, permitiendo buscar en muchos sitios desde una sola API.

**Referencia:** [Guía de Jackett](https://www.rapidseedbox.com/blog/guide-to-jackett)

## Ventajas de usar Jackett

1. **Múltiples indexadores**: Busca en decenas de sitios de torrents simultáneamente
2. **API unificada**: Una sola API para acceder a todos los indexadores
3. **Actualización automática**: Los indexadores se actualizan automáticamente
4. **Filtrado avanzado**: Soporte para categorías, calidad, etc.

## Instalación de Jackett

### Opción 1: Docker (Recomendado)

```bash
docker run -d \
  --name=jackett \
  -e PUID=1000 \
  -e PGID=1000 \
  -e TZ=Europe/Madrid \
  -p 9117:9117 \
  -v /path/to/config:/config \
  --restart unless-stopped \
  lscr.io/linuxserver/jackett:latest
```

### Opción 2: Instalación directa

1. Descargar desde: https://github.com/Jackett/Jackett/releases
2. Ejecutar el instalador
3. Acceder a http://localhost:9117

## Configuración en la Plataforma de Streaming

### 1. Obtener API Key de Jackett

1. Accede a la interfaz web de Jackett (por defecto: http://localhost:9117)
2. Ve a **Configuration** → **Security**
3. Copia tu **API Key**

### 2. Configurar variables de entorno

Edita el archivo `.env` en la raíz del proyecto:

```env
# Jackett Configuration
JACKETT_URL=http://localhost:9117
JACKETT_API_KEY=tu_api_key_aqui
```

**Nota:** 
- Si Jackett está en otro servidor, cambia `localhost` por la IP o dominio
- Si usas HTTPS, cambia `http://` por `https://`

### 3. Configurar indexadores en Jackett

1. En la interfaz de Jackett, ve a **Indexers**
2. Haz clic en **Add Indexer**
3. Selecciona los indexadores que quieres usar (ej: ThePirateBay, 1337x, RARBG, etc.)
4. Configura cada indexador según sus requisitos (algunos requieren cuenta)

## Cómo funciona la integración

Cuando buscas contenido en la plataforma:

1. **Primero** se buscan enlaces de streaming (upstream, powvideo, filemoon, etc.)
2. **Segundo** se busca en Jackett (si está configurado)
3. **Tercero** se buscan en otras fuentes (Torrentio, YTS, EZTV, etc.)

Los resultados de Jackett aparecen con el nombre del tracker como fuente (ej: "ThePirateBay", "1337x").

## Estructura de respuesta de Jackett

La API de Jackett devuelve resultados con esta estructura:

```json
{
  "Results": [
    {
      "Title": "Movie Title 2024 1080p",
      "MagnetUri": "magnet:?xt=urn:btih:...",
      "Link": "https://...",
      "Tracker": "ThePirateBay",
      "TrackerId": "thepiratebay",
      "Seeders": 50,
      "Peers": 75,
      "Size": 1234567890,
      "Category": [2000],
      "CategoryDesc": "Movies",
      "PublishDate": "2024-01-01T00:00:00Z",
      "Imdb": "tt1234567",
      "TMDb": 12345
    }
  ]
}
```

## Categorías de Jackett

- **2000**: Movies (Películas)
- **5000**: TV Shows (Series)

## 🧪 Probar la Conexión

Después de instalar y configurar Jackett, puedes probar la conexión usando el script incluido:

1. Abre en tu navegador: `http://localhost/streaming-platform/test-jackett-connection.php`
2. El script verificará:
   - ✅ Que Jackett esté corriendo
   - ✅ Que la API Key sea válida
   - ✅ Que tengas indexadores configurados
   - ✅ Que las búsquedas funcionen correctamente

Si no tienes las variables configuradas en `.env`, puedes usar el formulario en la página de prueba para valores temporales.

## Solución de problemas

### Error: "No se pudo conectar a Jackett"

1. Verifica que Jackett esté corriendo: `http://localhost:9117`
2. Verifica la URL en `.env` (debe ser exacta, sin trailing slash)
3. Verifica que el puerto 9117 no esté bloqueado por firewall

### Error: "API Key inválida"

1. Verifica que la API Key en `.env` sea correcta
2. Regenera la API Key en Jackett si es necesario

### No aparecen resultados de Jackett

1. Verifica que tengas indexadores configurados en Jackett
2. Verifica que los indexadores estén funcionando (status verde)
3. Revisa los logs de Jackett para ver errores

## Indexadores recomendados

- **ThePirateBay**: Popular y confiable
- **1337x**: Buena selección de contenido
- **RARBG**: Calidad alta
- **TorrentGalaxy**: Buena para series
- **LimeTorrents**: Alternativa confiable

## Seguridad

- **Nunca** compartas tu API Key públicamente
- Usa HTTPS si Jackett está en un servidor remoto
- Considera usar autenticación adicional si expones Jackett a internet

## Referencias

- [Documentación oficial de Jackett](https://github.com/Jackett/Jackett)
- [Guía de RapidSeedbox sobre Jackett](https://www.rapidseedbox.com/blog/guide-to-jackett)
- [Lista de indexadores soportados](https://github.com/Jackett/Jackett#indexers)

