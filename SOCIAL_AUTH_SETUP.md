# 🔐 Configuración de Autenticación Social

## ✅ Funcionalidad Implementada

Se ha implementado la estructura completa para autenticación con Google y Facebook:

### Archivos Creados:
1. `api/auth/social/google.php` - Endpoint para autenticación con Google
2. `api/auth/social/facebook.php` - Endpoint para autenticación con Facebook
3. `api/auth/social/callback.php` - Callback para procesar respuestas OAuth
4. `js/social-auth.js` - JavaScript para manejar autenticación social
5. `database/social-auth-tables.sql` - Script SQL para añadir columnas necesarias

### Archivos Modificados:
1. `login.php` - Botones sociales funcionales
2. `register.php` - Botones sociales añadidos

## 📋 Pasos para Configurar

### 1. Base de Datos

Ejecuta el script SQL para añadir las columnas necesarias:

```sql
-- Ejecutar database/social-auth-tables.sql
```

O manualmente:

```sql
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email,
ADD COLUMN facebook_id VARCHAR(255) NULL UNIQUE AFTER google_id;

CREATE INDEX idx_google_id ON users(google_id);
CREATE INDEX idx_facebook_id ON users(facebook_id);
```

### 2. Google OAuth

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Crea un nuevo proyecto o selecciona uno existente
3. Habilita la API de Google+ (o Google Identity)
4. Ve a "Credenciales" → "Crear credenciales" → "ID de cliente OAuth 2.0"
5. Configura:
   - Tipo: Aplicación web
   - URI de redirección autorizados: `https://tudominio.com/api/auth/social/google.php`
6. Copia el **Client ID** y el **Client Secret**

### 3. Facebook OAuth

1. Ve a [Facebook Developers](https://developers.facebook.com/)
2. Crea una nueva aplicación
3. Añade el producto "Facebook Login"
4. Configura:
   - URL de redirección OAuth válida: `https://tudominio.com/api/auth/social/facebook.php`
5. Copia el **App ID** y el **App Secret**

### 4. Variables de Entorno

Añade estas variables a tu archivo `.env`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=tu_google_client_id_aqui
GOOGLE_CLIENT_SECRET=tu_google_client_secret_aqui

# Facebook OAuth
FACEBOOK_APP_ID=tu_facebook_app_id_aqui
FACEBOOK_APP_SECRET=tu_facebook_app_secret_aqui
```

### 5. Actualizar Código (Opcional)

Si quieres usar los SDKs de Google/Facebook en lugar de redirección OAuth:

1. **Google**: Carga el SDK en `login.php` y `register.php`:
```html
<script src="https://apis.google.com/js/platform.js"></script>
```

2. **Facebook**: Carga el SDK:
```html
<script src="https://connect.facebook.net/es_ES/sdk.js"></script>
```

## 🎯 Funcionamiento Actual

### Sin Configuración OAuth:
- Los botones redirigen a los endpoints
- Los endpoints muestran un mensaje informativo
- No se puede autenticar hasta configurar OAuth

### Con Configuración OAuth:
1. Usuario hace clic en "Google" o "Facebook"
2. Se redirige a la página de autorización del proveedor
3. Usuario autoriza la aplicación
4. Proveedor redirige de vuelta con código de autorización
5. El servidor intercambia el código por un token
6. Se obtiene información del usuario
7. Se crea o actualiza el usuario en la base de datos
8. Se inicia sesión automáticamente

## 🔧 Mejoras Futuras

Para una implementación completa, considera:

1. **Verificación de tokens**: Validar tokens con las APIs de Google/Facebook
2. **Refresh tokens**: Manejar renovación de tokens expirados
3. **Desconexión**: Permitir desvincular cuentas sociales
4. **Vínculo de cuentas**: Permitir vincular múltiples proveedores a una cuenta
5. **Avatar automático**: Obtener foto de perfil del proveedor social

## ⚠️ Notas de Seguridad

1. **Nunca expongas** los Client Secrets o App Secrets en el frontend
2. **Usa HTTPS** en producción para OAuth
3. **Valida tokens** en el servidor antes de confiar en ellos
4. **Implementa CSRF protection** para los callbacks
5. **Limpia tokens** expirados periódicamente

## 📝 Estado Actual

✅ **Estructura implementada** - Lista para configurar OAuth
✅ **Botones funcionales** - Redirigen a endpoints
✅ **Base de datos preparada** - Script SQL listo
⏳ **OAuth pendiente** - Requiere credenciales del desarrollador



