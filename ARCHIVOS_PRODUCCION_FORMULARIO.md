# Archivos para Subir a Producción - Formulario Simplificado

## 📋 Resumen de Cambios
Se ha simplificado el formulario de "Agregar Nuevo Contenido" para que solo permita subir archivos locales (sin opciones de URL).

---

## 📁 Archivos Modificados

### 1. **admin/index.php**
**Ruta:** `admin/index.php`
**Cambios:**
- Formulario HTML completamente simplificado
- Eliminadas opciones de URL para imágenes, video y tráiler
- Solo permite subir archivos locales
- Nuevo diseño organizado en secciones
- Mejorado para responsive

**Acción:** ✅ SUBIR A PRODUCCIÓN

---

### 2. **css/admin.css**
**Ruta:** `css/admin.css`
**Cambios:**
- Nuevos estilos para secciones del formulario (`.form-section`, `.section-title`)
- Estilos para carga de archivos (`.file-upload-wrapper`, `.file-label`, `.file-info`)
- Estilos para campo de torrent (`.torrent-input-wrapper`, `.torrent-input`)
- Media queries responsive para móviles y tablets
- Efectos hover y transiciones mejoradas

**Acción:** ✅ SUBIR A PRODUCCIÓN

---

### 3. **js/admin.js**
**Ruta:** `js/admin.js`
**Cambios:**
- Eliminada lógica de opciones mutuamente excluyentes (URL vs archivo)
- Simplificada función `handleContentSubmit` para solo manejar archivos locales
- Actualizada función `showContentModal` para nuevo formulario
- Simplificadas funciones `clearVideoFile` y `clearTrailerFile`
- Mantenida validación de archivos

**Acción:** ✅ SUBIR A PRODUCCIÓN

---

## 🚀 Instrucciones de Despliegue

### Opción 1: Subir archivos individuales
```bash
# Subir archivos modificados
scp admin/index.php usuario@servidor:/ruta/a/admin/
scp css/admin.css usuario@servidor:/ruta/a/css/
scp js/admin.js usuario@servidor:/ruta/a/js/
```

### Opción 2: Usar FTP/SFTP
1. Conecta a tu servidor de producción
2. Sube estos 3 archivos:
   - `admin/index.php`
   - `css/admin.css`
   - `js/admin.js`

### Opción 3: Usar Git (si tienes repositorio)
```bash
git add admin/index.php css/admin.css js/admin.js
git commit -m "Simplificar formulario de contenido - solo archivos locales"
git push origin main
# Luego en producción:
git pull origin main
```

---

## ✅ Verificación Post-Despliegue

Después de subir los archivos, verifica:

1. **Accede al panel de administración**
   - URL: `https://tudominio.com/admin/`

2. **Abre el formulario de "Agregar Nuevo Contenido"**
   - Debe mostrar solo opciones de archivos locales
   - No debe aparecer opción de URL

3. **Prueba la carga de archivos**
   - Póster (imagen, máx. 5MB)
   - Backdrop (imagen, máx. 6MB)
   - Video (máx. 2GB)
   - Tráiler (opcional, máx. 500MB)

4. **Verifica responsive**
   - Abre en móvil/tablet
   - El formulario debe adaptarse correctamente

5. **Prueba la búsqueda de torrents**
   - El botón "Buscar" debe funcionar
   - El botón "Reintentar" debe funcionar

---

## ⚠️ Notas Importantes

1. **No se requieren cambios en la base de datos**
   - Los cambios son solo en frontend

2. **Los archivos de API no se modificaron**
   - `api/upload/image.php` y `api/upload/video.php` siguen funcionando igual

3. **Compatibilidad**
   - Los cambios son compatibles con la estructura existente
   - No afecta contenido ya creado

4. **Backup recomendado**
   - Haz backup de los archivos originales antes de subir
   - Por si necesitas revertir los cambios

---

## 📝 Checklist de Despliegue

- [ ] Backup de archivos originales
- [ ] Subir `admin/index.php`
- [ ] Subir `css/admin.css`
- [ ] Subir `js/admin.js`
- [ ] Verificar permisos de archivos (644 para archivos, 755 para directorios)
- [ ] Limpiar caché del navegador
- [ ] Probar formulario en producción
- [ ] Verificar responsive en móvil
- [ ] Probar carga de archivos

---

## 🔄 Si Necesitas Revertir

Si algo no funciona, puedes revertir subiendo las versiones anteriores de estos 3 archivos desde tu backup.

---

**Fecha de cambios:** $(Get-Date -Format "yyyy-MM-dd")
**Versión:** 1.0 - Formulario Simplificado


