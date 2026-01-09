# Configuración de qBittorrent para Gestión de Torrents

## 🚀 Inicio Rápido

1. **Instalar qBittorrent** con Web UI habilitado
2. **Habilitar Web UI** en qBittorrent: Tools → Options → Web UI
3. **Probar conexión**: Abre `test-qbittorrent-connection.php` en tu navegador
4. **Configurar variables**: Añade a tu archivo `.env`:
   ```env
   QBITTORRENT_URL=http://localhost:8080
   QBITTORRENT_USERNAME=admin
   QBITTORRENT_PASSWORD=adminadmin
   ```

## ¿Qué es qBittorrent?

qBittorrent es un cliente de torrents gratuito y de código abierto con una interfaz web que permite controlar las descargas remotamente a través de una API REST.

**Referencia:** [Documentación de la API de qBittorrent](https://qbittorrent-api.readthedocs.io/en/v2021.4.20/apidoc/torrents.html)

## Ventajas de usar qBittorrent

1. **Control remoto**: Gestiona torrents desde cualquier lugar
2. **API completa**: Agregar, pausar, reanudar, eliminar torrents
3. **Interfaz web**: No necesitas instalar software adicional
4. **Gestión avanzada**: Categorías, etiquetas, límites de velocidad

## Instalación de qBittorrent

### Windows

1. Descargar desde: https://www.qbittorrent.org/download.php
2. Instalar normalmente
3. Abrir qBittorrent
4. Ir a **Tools → Options → Web UI**
5. Marcar **"Web User Interface (Remote control)"**
6. Configurar usuario y contraseña (por defecto: admin/adminadmin)
7. Guardar y aplicar

### Linux

```bash
# Ubuntu/Debian
sudo apt-get install qbittorrent-nox

# Iniciar con Web UI
qbittorrent-nox --webui-port=8080
```

### Docker

```bash
docker run -d \
  --name=qbittorrent \
  -e PUID=1000 \
  -e PGID=1000 \
  -e TZ=Europe/Madrid \
  -e WEBUI_PORT=8080 \
  -p 8080:8080 \
  -p 6881:6881 \
  -p 6881:6881/udp \
  -v /path/to/config:/config \
  -v /path/to/downloads:/downloads \
  --restart unless-stopped \
  lscr.io/linuxserver/qbittorrent:latest
```

## Configuración en la Plataforma de Streaming

### 1. Habilitar Web UI en qBittorrent

1. Abre qBittorrent
2. Ve a **Tools → Options → Web UI**
3. Marca **"Web User Interface (Remote control)"**
4. Configura:
   - **IP address**: `0.0.0.0` (para aceptar conexiones remotas) o `127.0.0.1` (solo local)
   - **Port**: `8080` (por defecto)
   - **Username**: `admin` (o el que prefieras)
   - **Password**: `adminadmin` (cámbialo por seguridad)

### 2. Configurar variables de entorno

Edita el archivo `.env` en la raíz del proyecto:

```env
# qBittorrent Configuration
QBITTORRENT_URL=http://localhost:8080
QBITTORRENT_USERNAME=admin
QBITTORRENT_PASSWORD=adminadmin
```

**Nota:** 
- Si qBittorrent está en otro servidor, cambia `localhost` por la IP o dominio
- Si usas HTTPS, cambia `http://` por `https://`
- **IMPORTANTE**: Cambia la contraseña por defecto por seguridad

## Funcionalidades Disponibles

La integración permite:

- ✅ **Agregar torrents**: Enviar magnet links o archivos .torrent a qBittorrent
- ✅ **Listar torrents**: Ver todos los torrents activos
- ✅ **Obtener información**: Detalles de un torrent específico
- ✅ **Pausar/Reanudar**: Controlar el estado de las descargas
- ✅ **Eliminar**: Remover torrents (con opción de eliminar archivos)

## Endpoints de la API

### Agregar Torrent

```php
POST /api/qbittorrent/index.php?action=add
{
    "magnet": "magnet:?xt=urn:btih:...",
    "save_path": "/ruta/opcional",
    "category": "peliculas",
    "is_paused": false
}
```

### Listar Torrents

```php
GET /api/qbittorrent/index.php?action=list
```

### Información de Torrent

```php
GET /api/qbittorrent/index.php?action=info&hash=TORRENT_HASH
```

### Pausar Torrent

```php
POST /api/qbittorrent/index.php?action=pause
{
    "hash": "TORRENT_HASH"
}
```

### Reanudar Torrent

```php
POST /api/qbittorrent/index.php?action=resume
{
    "hash": "TORRENT_HASH"
}
```

### Eliminar Torrent

```php
POST /api/qbittorrent/index.php?action=delete
{
    "hash": "TORRENT_HASH",
    "deleteFiles": false
}
```

### Verificar Estado

```php
GET /api/qbittorrent/index.php?action=status
```

## Cómo Funciona la Integración

Cuando seleccionas un torrent en la plataforma:

1. **Opción 1**: Reproducir directamente con WebTorrent (streaming)
2. **Opción 2**: Agregar a qBittorrent para descarga completa (si está configurado)

La plataforma puede usar qBittorrent como alternativa o complemento a WebTorrent para:
- Descargas completas de contenido
- Gestión de biblioteca de torrents
- Control remoto de descargas

## Solución de Problemas

### Error: "No se pudo conectar a qBittorrent"

1. Verifica que qBittorrent esté corriendo
2. Verifica que la Web UI esté habilitada
3. Verifica la URL en `.env` (debe ser exacta, sin trailing slash)
4. Verifica que el puerto 8080 no esté bloqueado por firewall

### Error: "Error de autenticación"

1. Verifica que el usuario y contraseña en `.env` sean correctos
2. Verifica que la Web UI esté habilitada en qBittorrent
3. Prueba acceder manualmente a `http://localhost:8080` en el navegador

### No aparecen torrents

1. Verifica que tengas torrents agregados en qBittorrent
2. Verifica que los torrents no estén pausados
3. Revisa los logs de qBittorrent para ver errores

## Seguridad

- **Nunca** compartas tus credenciales públicamente
- Cambia la contraseña por defecto (`adminadmin`)
- Usa HTTPS si qBittorrent está en un servidor remoto
- Considera usar autenticación adicional si expones qBittorrent a internet
- Limita el acceso por IP si es posible

## Casos de Uso

### 1. Descarga Completa de Contenido

Cuando un usuario selecciona un torrent, puede elegir:
- **Reproducir ahora** (WebTorrent - streaming)
- **Descargar completo** (qBittorrent - descarga completa)

### 2. Gestión de Biblioteca

Los administradores pueden:
- Ver todos los torrents descargados
- Pausar/reanudar descargas
- Organizar por categorías
- Gestionar espacio en disco

### 3. Automatización

La API permite:
- Agregar torrents automáticamente desde búsquedas
- Monitorear el estado de las descargas
- Integrar con otros sistemas

## Referencias

- [Documentación oficial de qBittorrent API](https://qbittorrent-api.readthedocs.io/en/v2021.4.20/apidoc/torrents.html)
- [Web UI API Documentation](https://github.com/qbittorrent/qBittorrent/wiki/Web-UI-API-Documentation)
- [Sitio oficial de qBittorrent](https://www.qbittorrent.org/)



