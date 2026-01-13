# 🎬 Guía para Activar Sincronización Automática (TMDB)

El sistema de búsqueda automática (`fetch-new-content.php`) requiere una **API Key de TMDB** para funcionar al 100%. Sin ella, TVMaze solo encuentra series viejas y los resultados son limitados.

## 1. Obtener la API Key (Gratis)
1. Ve a [https://www.themoviedb.org/signup](https://www.themoviedb.org/signup) y crea una cuenta gratuita.
2. Una vez logueado, ve a **Settings** (Configuración) -> **API**.
3. Haz clic en **Create** o **Request an API Key**.
4. Elige "Developer" (Desarrollador) y acepta los términos.
5. Rellena el formulario (puedes poner "Personal Project" y URLs locales como `http://localhost`).
6. Copia tu **API Key (v3 auth)**. Será una cadena larga de caracteres alfanuméricos.

## 2. Configurar en tu Proyecto
Tienes dos opciones para configurar la clave:

### Opción A: Archivo .env (Recomendado)
Crea un archivo llamado `.env` en la raíz del proyecto (`c:\xampp\htdocs\streaming-platform\.env`) y añade:

```ini
TMDB_API_KEY=tucodigoapikeyaqui123456
APP_ENV=local
DB_HOST=localhost
DB_NAME=streaming_platform
DB_USER=root
DB_PASS=
```

### Opción B: Editar config.php directo
Si prefieres editar el código, abre `includes/config.php` y busca donde se definen las constantes:

```php
// Añadir esta línea
define('TMDB_API_KEY', 'tucodigoapikeyaqui123456');
```

## 3. Probar la Sincronización
Una vez configurado, ejecuta el script de nuevo:

```bash
php scripts/fetch-new-content.php --type=movie --limit=50
```

¡Ahora verás cómo encuentra cientos de películas y sus carátulas automáticamente! 🚀
