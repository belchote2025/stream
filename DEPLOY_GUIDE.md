# Guía de Despliegue en Servidor

Para desplegar UrresTV en un servidor (ej. Hostinger, cPanel), sigue estos pasos:

## 1. Archivos
1. Sube todos los archivos del proyecto a la carpeta `public_html` (o la carpeta de tu subdominio).
2. Asegúrate de incluir el archivo `.htaccess` oculto.

## 2. Base de Datos
1. Crea una nueva base de datos MySQL en tu panel de control.
2. Crea un usuario y contraseña para esa base de datos.
3. Importa el archivo SQL de la base de datos (normalmente en `database/schema.sql` o exportado de tu local) usando phpMyAdmin.

## 3. Configuración
1. En el servidor, renombra el archivo `config/env.production` a `.env` y muévelo a la raíz (junto a `index.php`).
2. Edita el archivo `.env` con tus datos:
   - `SITE_URL`: La URL completa de tu sitio (ej. `https://miweb.com` o `https://miweb.com/app`).
   - `DB_HOST`: Normalmente `localhost`.
   - `DB_USER`: El usuario de base de datos que creaste.
   - `DB_PASS`: La contraseña del usuario.
   - `DB_NAME`: El nombre de la base de datos.

## 4. Permisos
Asegúrate de que las siguientes carpetas tengan permisos de escritura (755 o 777 dependiendo del hosting):
- `uploads/`
- `logs/` (si existe)

## 5. PWA (Aplicación Móvil)
La configuración de la PWA (`manifest.json` y `sw.js`) ha sido actualizada para usar rutas relativas. Esto significa que **funcionarà automáticamente** independientemente de si instalas la app en la raíz o en una subcarpeta.

¡Listo! Tu aplicación debería estar funcionando.
