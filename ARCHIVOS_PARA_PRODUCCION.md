# 📦 Archivos para Subir a Producción

## ✅ Archivos Modificados - OBLIGATORIOS

### 🔧 Service Worker
- `sw.js` - Corregido error de cacheo de respuestas 206 (Partial Content)

### 🎬 Reproductor de Video
- `js/video-player.js` - Mejoras en normalización de URLs y manejo de errores
- `js/player/main.js` - Añadido método `loadYouTubeAPI()` faltante
- `watch.php` - Mejoras en inicialización del reproductor y definición de `__APP_BASE_URL`

### 📱 Responsive Design
- `css/unified-video-player.css` - Mejoras responsive para controles táctiles
- `css/responsive.css` - Ajustes generales responsive
- `css/accessibility.css` - Estilos responsive para controles de accesibilidad

### ♿ Accesibilidad
- `js/accessibility.js` - Nuevo diseño con botón flotante y menú desplegable
- `css/accessibility.css` - Estilos para el nuevo diseño de accesibilidad

## 📋 Lista Completa de Archivos

```
sw.js
js/video-player.js
js/player/main.js
watch.php
css/unified-video-player.css
css/responsive.css
css/accessibility.css
js/accessibility.js
```

## 🚀 Pasos para Subir a Producción

### 1. Preparación
```bash
# Verificar que todos los archivos estén guardados
# Hacer backup de la versión actual en producción
```

### 2. Subir Archivos
Sube los archivos listados arriba manteniendo la estructura de carpetas:
- `/sw.js` (raíz)
- `/js/video-player.js`
- `/js/player/main.js`
- `/watch.php`
- `/css/unified-video-player.css`
- `/css/responsive.css`
- `/css/accessibility.css`
- `/js/accessibility.js`

### 3. Limpiar Caché
Después de subir:
1. **Service Worker**: 
   - Abre DevTools (F12)
   - Application > Service Workers
   - Haz clic en "Unregister" si hay uno activo
   - Recarga la página con `Ctrl + Shift + R`

2. **Caché del Navegador**:
   - `Ctrl + Shift + Delete`
   - Selecciona "Cached images and files"
   - "Clear data"

### 4. Verificar
- ✅ El reproductor de video funciona con videos locales
- ✅ No hay errores en la consola del navegador
- ✅ Los controles de accesibilidad se ven correctamente
- ✅ El diseño responsive funciona en móviles
- ✅ No hay errores del Service Worker (206)

## ⚠️ Archivos NO Modificados (No subir)

Estos archivos NO necesitan ser subidos porque no fueron modificados:
- `index.php`
- `includes/header.php`
- `includes/footer.php`
- Otros archivos PHP
- Otros archivos CSS/JS no mencionados

## 📝 Notas Importantes

1. **Service Worker**: El cambio de versión (`v1.0.1`) forzará la actualización automática
2. **Videos Locales**: Asegúrate de que las rutas de videos en la base de datos sean correctas
3. **Base URL**: Verifica que `SITE_URL` en `.env` esté configurado correctamente
4. **Permisos**: Asegúrate de que los archivos de video en `/uploads/videos/` tengan permisos de lectura

## 🔍 Verificación Post-Deploy

Después de subir, verifica:
- [ ] El reproductor carga videos locales sin errores
- [ ] Los controles del reproductor funcionan en móviles
- [ ] El menú de accesibilidad se despliega correctamente
- [ ] No hay errores en la consola del navegador
- [ ] El Service Worker se actualiza correctamente
- [ ] Las páginas responsive se ven bien en diferentes tamaños

## 🆘 Si Algo Sale Mal

Si encuentras problemas después de subir:
1. Revisa la consola del navegador (F12)
2. Verifica que todos los archivos se subieron correctamente
3. Limpia el caché del navegador y del Service Worker
4. Verifica los permisos de archivos en el servidor
5. Revisa los logs del servidor si hay errores 500



