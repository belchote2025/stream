# 🔐 Guía de Acceso al Panel de Administración

## ✅ Problema Resuelto

El usuario administrador ha sido verificado y la contraseña ha sido actualizada correctamente.

## 📋 Pasos para Acceder

### Paso 1: Iniciar Sesión

1. Abre tu navegador y visita:
   ```
   http://localhost/streaming-platform/login.php
   ```

2. Ingresa las siguientes credenciales:
   - **Email:** `admin@streamingplatform.com`
   - **Contraseña:** `admin123`

3. Haz clic en "Iniciar Sesión"

### Paso 2: Acceder al Panel de Administración

Después de iniciar sesión, tienes dos opciones:

**Opción A - Acceso Directo:**
```
http://localhost/streaming-platform/admin/
```

**Opción B - Desde el Dashboard:**
1. Después del login, serás redirigido a la página principal
2. Si eres admin, deberías ver un enlace al panel de administración
3. O visita directamente: `http://localhost/streaming-platform/admin/`

## 🔑 Credenciales de Administrador

- **Usuario:** `admin`
- **Email:** `admin@streamingplatform.com`
- **Contraseña:** `admin123`
- **Rol:** `admin`
- **Estado:** `active`

⚠️ **IMPORTANTE:** Cambia la contraseña después de la primera sesión por seguridad.

## 🛠️ Si Tienes Problemas

### Problema: "Credenciales inválidas"
**Solución:**
1. Visita: `http://localhost/streaming-platform/database/create_admin.php`
2. Completa el formulario con:
   - Usuario: `admin`
   - Email: `admin@streamingplatform.com`
   - Contraseña: `admin123` (o la que prefieras)
3. Haz clic en "Crear/Actualizar Admin"
4. Intenta iniciar sesión nuevamente

### Problema: "No tienes permiso para acceder"
**Solución:**
1. Verifica que el usuario tenga `role = 'admin'` en la base de datos
2. Puedes verificar/editarlo desde phpMyAdmin:
   ```sql
   SELECT id, username, email, role, status FROM users WHERE username = 'admin';
   UPDATE users SET role = 'admin' WHERE username = 'admin';
   ```

### Problema: Se redirige a login.php constantemente
**Solución:**
1. Asegúrate de que las sesiones de PHP estén funcionando
2. Verifica que no tengas cookies bloqueadas
3. Limpia las cookies del navegador y vuelve a intentar
4. Verifica que XAMPP esté ejecutándose correctamente

## 📍 URLs Importantes

- **Login:** `http://localhost/streaming-platform/login.php`
- **Panel Admin:** `http://localhost/streaming-platform/admin/`
- **Crear/Resetear Admin:** `http://localhost/streaming-platform/database/create_admin.php`
- **Página Principal:** `http://localhost/streaming-platform/`

## ✨ Funcionalidades del Panel

Una vez dentro del panel podrás:
- ✅ Ver estadísticas en tiempo real
- ✅ Gestionar contenido (películas y series)
- ✅ Agregar nuevo contenido
- ✅ Editar contenido existente
- ✅ Eliminar contenido
- ✅ Gestionar usuarios
- ✅ Ver reportes
- ✅ Configurar el sitio

---

**¡Listo para usar!** 🎉

