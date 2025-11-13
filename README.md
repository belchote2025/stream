# 🎬 Streaming Platform - Estilo Netflix

Una plataforma de streaming moderna y elegante inspirada en Netflix, construida con PHP, MySQL y tecnologías web modernas.

🔗 **Repositorio:** [https://github.com/belchote2025/stream](https://github.com/belchote2025/stream)

## ✨ Características Principales

### 🎨 Diseño
- **Diseño estilo Netflix** con colores oscuros y elegantes
- **Navbar transparente** que se vuelve opaco al hacer scroll
- **Hero section** con carrusel automático de contenido destacado
- **Tarjetas de contenido** con efectos hover sofisticados
- **Filas horizontales** con navegación suave
- **Diseño completamente responsive** (móvil, tablet, desktop)

### 🎯 Funcionalidades
- ✅ Sistema de usuarios y autenticación
- ✅ Búsqueda avanzada con filtros (tipo, género, año, calificación)
- ✅ Página de detalles de contenido completa
- ✅ Sistema de "Mi Lista" (favoritos)
- ✅ Historial de reproducción y "Continuar viendo"
- ✅ Sistema de valoraciones
- ✅ Recomendaciones personalizadas
- ✅ Página de reproducción de video
- ✅ Gestión de episodios para series
- ✅ Contenido destacado y trending
- ✅ Sistema de géneros y categorías

### 🚀 Tecnologías
- **Backend:** PHP 7.4+
- **Base de datos:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Frameworks:** Bootstrap 5, Font Awesome
- **Servidor:** Apache (XAMPP)

## 📋 Instalación

### Requisitos
- XAMPP (o servidor Apache + PHP + MySQL)
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Navegador web moderno

### Pasos de Instalación

1. **Clonar/Descargar el proyecto**
   ```bash
   cd C:\xampp\htdocs
   # Coloca el proyecto aquí
   ```

2. **Instalar la base de datos**
   - Visita: `http://localhost/streaming-platform/database/install.php`
   - O ejecuta: `php database/install.php` desde la línea de comandos

3. **Añadir contenido de ejemplo**
   - Visita: `http://localhost/streaming-platform/database/add_sample_content.php`
   - O ejecuta: `php database/add_sample_content_cli.php`

4. **Configurar la base de datos**
   - Edita `includes/config.php` si necesitas cambiar credenciales

5. **Acceder a la aplicación**
   - Visita: `http://localhost/streaming-platform/`
   - Usuario admin: `admin` / Contraseña: `admin123`

## 📁 Estructura del Proyecto

```
streaming-platform/
├── api/                    # Endpoints de API
│   ├── content/           # API de contenido
│   ├── auth/              # Autenticación
│   ├── watchlist/         # Mi lista
│   └── ratings/           # Valoraciones
├── assets/                # Recursos estáticos
│   ├── css/              # Estilos adicionales
│   ├── js/               # Scripts adicionales
│   └── img/              # Imágenes
├── css/                   # Estilos principales
│   ├── styles.css        # Estilos principales estilo Netflix
│   └── animations.css    # Animaciones
├── database/              # Base de datos
│   ├── install.php      # Instalador web
│   ├── install.sql      # Script SQL completo
│   └── migrations/      # Migraciones
├── includes/              # Archivos incluidos
│   ├── config.php       # Configuración
│   ├── header.php       # Header
│   ├── footer.php       # Footer
│   └── gallery-functions.php  # Funciones de galería
├── js/                    # JavaScript principal
│   ├── netflix-enhancements.js  # Mejoras estilo Netflix
│   └── animations.js    # Animaciones
├── dashboard/             # Panel de usuario
├── admin/                 # Panel de administración
├── index.php              # Página principal
├── search.php             # Búsqueda avanzada
├── content-detail.php     # Detalles de contenido
├── my-list.php            # Mi lista
└── watch.php              # Reproductor de video
```

## 🎯 Características Detalladas

### Búsqueda Avanzada
- Búsqueda por título y descripción
- Filtros por tipo (película/serie)
- Filtros por género
- Filtros por año
- Filtros por calificación mínima
- Autocompletado en tiempo real

### Página de Detalles
- Información completa del contenido
- Lista de episodios (para series)
- Contenido similar
- Botones de acción (reproducir, añadir a lista, compartir)
- Géneros clickeables

### Mi Lista
- Ver todo tu contenido guardado
- Filtrar por tipo
- Quitar contenido fácilmente
- Ordenado por fecha de adición

### Reproductor de Video
- Guarda automáticamente el progreso
- Continúa desde donde lo dejaste
- Navegación entre episodios (series)
- Controles completos de video

## 🔐 Credenciales por Defecto

- **Usuario:** admin
- **Email:** admin@streamingplatform.com
- **Contraseña:** admin123

⚠️ **IMPORTANTE:** Cambia la contraseña después de la instalación.

## 🎨 Personalización

### Colores
Edita las variables CSS en `css/styles.css`:
```css
:root {
    --netflix-red: #e50914;
    --netflix-black: #141414;
    /* ... más colores ... */
}
```

### Logo
Añade tu logo en `assets/img/logo.png` o edita el texto en `includes/header.php`.

### Contenido
Añade contenido desde:
- Panel de administración: `/streaming-platform/admin/`
- O directamente en la base de datos

## 📱 Responsive Design

La plataforma está completamente optimizada para:
- 📱 Móviles (320px+)
- 📱 Tablets (768px+)
- 💻 Laptops (1024px+)
- 🖥️ Desktop (1400px+)

## 🚀 Próximas Mejoras Sugeridas

- [ ] Sistema de comentarios
- [ ] Notificaciones push
- [ ] Modo offline
- [ ] Descargas
- [ ] Subtítulos múltiples
- [ ] Audio múltiple
- [ ] Chromecast/AirPlay
- [ ] Perfiles de usuario múltiples
- [ ] Control parental
- [ ] Integración con APIs externas (TMDB, etc.)

## 🐛 Solución de Problemas

### Error 404 en API
- Verifica que los archivos en `api/content/` existan
- Asegúrate de que mod_rewrite esté habilitado en Apache
- O usa las rutas directas a los archivos PHP

### No se muestra contenido
- Ejecuta `database/add_sample_content.php`
- Verifica que la base de datos tenga datos
- Revisa la consola del navegador (F12)

### Errores de conexión a BD
- Verifica que MySQL esté ejecutándose
- Revisa las credenciales en `includes/config.php`
- Asegúrate de que la base de datos exista

## 📞 Soporte

Para problemas o preguntas:
1. Revisa los logs de error de PHP
2. Verifica la consola del navegador (F12)
3. Revisa los logs de MySQL
4. Asegúrate de que todas las dependencias estén instaladas

## 📄 Licencia

Este proyecto es de código abierto y está disponible para uso personal y educativo.

---

**¡Disfruta de tu plataforma de streaming estilo Netflix!** 🎬✨

