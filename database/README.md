# Instalación de la Base de Datos

## Método 1: Usando el Instalador Web (Recomendado)

1. Asegúrate de que XAMPP esté ejecutándose (Apache y MySQL)
2. Abre tu navegador y visita:
   ```
   http://localhost/streaming-platform/database/install.php
   ```
3. Haz clic en "Instalar Base de Datos"
4. ¡Listo! La base de datos se creará automáticamente

## Método 2: Usando phpMyAdmin

1. Abre phpMyAdmin: `http://localhost/phpmyadmin`
2. Ve a la pestaña "Importar"
3. Selecciona el archivo `database/install.sql`
4. Haz clic en "Continuar"
5. La base de datos se creará con todas las tablas

## Método 3: Usando Línea de Comandos

```bash
# Conectarse a MySQL
mysql -u root -p

# Ejecutar el script
source C:/xampp/htdocs/streaming-platform/database/install.sql
```

O directamente:

```bash
mysql -u root -p < C:/xampp/htdocs/streaming-platform/database/install.sql
```

## Credenciales por Defecto

Después de la instalación, se crea un usuario administrador:

- **Email:** admin@streamingplatform.com
- **Contraseña:** admin123

⚠️ **IMPORTANTE:** Cambia esta contraseña inmediatamente después de la instalación.

## Estructura de la Base de Datos

La base de datos incluye las siguientes tablas:

- `users` - Usuarios del sistema
- `user_settings` - Configuraciones de usuario
- `genres` - Géneros de contenido
- `content` - Películas y series
- `content_genres` - Relación contenido-géneros
- `episodes` - Episodios de series
- `playback_history` - Historial de reproducción
- `user_playlists` - Listas de reproducción
- `playlist_content` - Contenido en listas
- `user_favorites` - Favoritos de usuarios
- `views` - Estadísticas de visualización

## Solución de Problemas

### Error: "Access denied"
- Verifica que MySQL esté ejecutándose
- Verifica las credenciales en `database/install.php`

### Error: "Database already exists"
- Elimina la base de datos existente desde phpMyAdmin
- O cambia el nombre de la base de datos en `install.php`

### Error: "Table already exists"
- Elimina las tablas existentes
- O elimina la base de datos completa y vuelve a instalar

## Añadir Contenido de Prueba

Después de instalar, puedes añadir contenido de ejemplo ejecutando:

```sql
-- Ejemplo de película
INSERT INTO content (title, slug, type, description, release_year, duration, rating, poster_url, backdrop_url, is_featured) 
VALUES ('Ejemplo Película', 'ejemplo-pelicula', 'movie', 'Descripción de ejemplo', 2024, 120, 8.5, '/assets/img/posters/ejemplo.jpg', '/assets/img/backdrops/ejemplo.jpg', 1);

-- Ejemplo de serie
INSERT INTO content (title, slug, type, description, release_year, duration, rating, poster_url, backdrop_url, is_featured) 
VALUES ('Ejemplo Serie', 'ejemplo-serie', 'series', 'Descripción de ejemplo', 2024, 45, 9.0, '/assets/img/posters/ejemplo-serie.jpg', '/assets/img/backdrops/ejemplo-serie.jpg', 1);
```

## Soporte

Si tienes problemas con la instalación, verifica:
1. Que MySQL/MariaDB esté ejecutándose
2. Que las credenciales sean correctas
3. Que tengas permisos para crear bases de datos
4. Los logs de error de PHP y MySQL

