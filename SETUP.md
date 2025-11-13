# 🎬 Guía de Configuración - Streaming Platform

## ✅ Paso 1: Instalación de Base de Datos (COMPLETADO)

La base de datos ha sido instalada exitosamente. Puedes verificar que todo esté correcto.

## 📋 Paso 2: Verificar Configuración

Abre el archivo `includes/config.php` y verifica que las credenciales sean correctas:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Tu contraseña de MySQL
define('DB_NAME', 'streaming_platform');
```

## 🔐 Paso 3: Cambiar Contraseña del Admin

**IMPORTANTE:** Cambia la contraseña del usuario administrador inmediatamente.

### Opción A: Usando el script web
1. Visita: `http://localhost/streaming-platform/database/create_admin.php`
2. Ingresa una nueva contraseña segura
3. Haz clic en "Crear/Actualizar Admin"

### Opción B: Desde phpMyAdmin
1. Abre phpMyAdmin: `http://localhost/phpmyadmin`
2. Selecciona la base de datos `streaming_platform`
3. Ve a la tabla `users`
4. Edita el usuario `admin`
5. Genera un nuevo hash de contraseña o usa:
   ```sql
   UPDATE users SET password = '$2y$12$TU_HASH_AQUI' WHERE username = 'admin';
   ```

## 🎬 Paso 4: Añadir Contenido de Ejemplo

Para ver la aplicación funcionando con contenido, añade películas y series de ejemplo:

1. Visita: `http://localhost/streaming-platform/database/add_sample_content.php`
2. Haz clic en "Añadir Contenido de Ejemplo"
3. Se añadirán 8 elementos de contenido (películas y series)

## 🚀 Paso 5: Acceder a la Aplicación

1. Abre tu navegador
2. Visita: `http://localhost/streaming-platform/`
3. Inicia sesión con:
   - **Usuario:** admin
   - **Contraseña:** admin123 (o la que hayas configurado)

## 🎨 Características Implementadas

### Diseño Estilo Netflix
- ✅ Navbar transparente que se vuelve opaco al hacer scroll
- ✅ Hero section con carrusel automático
- ✅ Tarjetas de contenido con efectos hover elegantes
- ✅ Filas horizontales con navegación suave
- ✅ Búsqueda mejorada
- ✅ Diseño completamente responsive

### Funcionalidades
- ✅ Sistema de usuarios y autenticación
- ✅ Gestión de contenido (películas y series)
- ✅ Géneros y categorías
- ✅ Historial de reproducción
- ✅ Listas de reproducción
- ✅ Favoritos
- ✅ Sistema de roles (admin, premium, user)

## 📁 Estructura de Archivos Importantes

```
streaming-platform/
├── includes/
│   ├── config.php          # Configuración de BD
│   ├── header.php          # Header mejorado estilo Netflix
│   └── footer.php          # Footer con scripts
├── css/
│   └── styles.css          # Estilos principales estilo Netflix
├── js/
│   └── netflix-enhancements.js  # Funcionalidades JavaScript
├── database/
│   ├── install.php         # Instalador web
│   ├── install.sql         # Script SQL completo
│   ├── create_admin.php    # Crear/resetear admin
│   └── add_sample_content.php  # Añadir contenido de ejemplo
└── index.php               # Página principal
```

## 🔧 Solución de Problemas

### Error: "No se puede conectar a la base de datos"
- Verifica que MySQL esté ejecutándose en XAMPP
- Revisa las credenciales en `includes/config.php`
- Asegúrate de que la base de datos `streaming_platform` exista

### Error: "Usuario no encontrado"
- Ejecuta `database/create_admin.php` para crear el usuario admin
- O verifica que el usuario exista en la tabla `users`

### No se muestra contenido
- Ejecuta `database/add_sample_content.php` para añadir contenido de ejemplo
- Verifica que la tabla `content` tenga registros

### Los estilos no se cargan
- Verifica que la ruta en `includes/header.php` sea correcta
- Asegúrate de que el archivo `css/styles.css` exista
- Revisa la consola del navegador para errores 404

## 📝 Próximos Pasos Sugeridos

1. **Personalizar el logo:**
   - Añade tu logo en `assets/img/logo.png`
   - O edita el texto del logo en `includes/header.php`

2. **Añadir más contenido:**
   - Usa el panel de administración
   - O importa contenido desde una API externa

3. **Configurar el reproductor:**
   - Añade URLs de video reales
   - Configura subtítulos
   - Implementa streaming

4. **Personalizar colores:**
   - Edita las variables CSS en `css/styles.css`
   - Cambia `--netflix-red` por tu color principal

5. **Añadir más funcionalidades:**
   - Sistema de comentarios
   - Valoraciones
   - Recomendaciones personalizadas
   - Notificaciones

## 🎯 URLs Importantes

- **Aplicación principal:** `http://localhost/streaming-platform/`
- **Panel de admin:** `http://localhost/streaming-platform/admin/`
- **Dashboard usuario:** `http://localhost/streaming-platform/dashboard/`
- **Login:** `http://localhost/streaming-platform/login.php`
- **Registro:** `http://localhost/streaming-platform/register.php`

## 📞 Soporte

Si encuentras algún problema:
1. Revisa los logs de error de PHP
2. Verifica la consola del navegador (F12)
3. Revisa los logs de MySQL
4. Asegúrate de que todas las dependencias estén instaladas

---

¡Disfruta de tu plataforma de streaming estilo Netflix! 🎬✨

