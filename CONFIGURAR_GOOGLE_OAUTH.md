# 🔐 Configurar Google OAuth para Login Social

## 📋 Pasos para Configurar Google OAuth

### 1. Crear Proyecto en Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Inicia sesión con tu cuenta de Google
3. Crea un nuevo proyecto o selecciona uno existente:
   - Haz clic en el selector de proyectos (arriba a la izquierda)
   - Haz clic en "NUEVO PROYECTO"
   - Ingresa un nombre (ej: "Streaming Platform")
   - Haz clic en "CREAR"

### 2. Habilitar Google+ API

1. En el menú lateral, ve a **APIs y servicios** → **Biblioteca**
2. Busca "Google+ API" o "Google Identity"
3. Haz clic en "HABILITAR"

### 3. Crear Credenciales OAuth 2.0

1. Ve a **APIs y servicios** → **Credenciales**
2. Haz clic en **+ CREAR CREDENCIALES** → **ID de cliente OAuth 2.0**
3. Si es la primera vez, configura la pantalla de consentimiento:
   - **Tipo de usuario**: Externo
   - **Nombre de la app**: Tu nombre de aplicación
   - **Email de soporte**: Tu email
   - **Dominios autorizados**: Tu dominio (ej: `goldenrod-finch-839887.hostingersite.com`)
   - Haz clic en **GUARDAR Y CONTINUAR**
   - En **Scopes**, haz clic en **GUARDAR Y CONTINUAR**
   - En **Usuarios de prueba**, añade tu email y haz clic en **GUARDAR Y CONTINUAR**
   - Revisa y haz clic en **VOLVER AL PANEL**

4. Crea el ID de cliente:
   - **Tipo de aplicación**: Aplicación web
   - **Nombre**: Streaming Platform Login
   - **URI de redirección autorizados**: 
     ```
     https://tu-dominio.com/api/auth/social/google.php
     ```
     O si estás en desarrollo local:
     ```
     http://localhost/streaming-platform/api/auth/social/google.php
     ```
   - Haz clic en **CREAR**

5. **IMPORTANTE**: Copia y guarda:
   - **ID de cliente** (Client ID) - algo como: `123456789-abcdefg.apps.googleusercontent.com`
   - **Secreto de cliente** (Client Secret) - algo como: `GOCSPX-abcdefghijklmnopqrstuvwxyz`

### 4. Configurar en el Archivo .env

Abre o crea el archivo `.env` en la raíz de tu proyecto y añade:

```env
# Google OAuth
GOOGLE_CLIENT_ID=tu_client_id_aqui
GOOGLE_CLIENT_SECRET=tu_client_secret_aqui
```

**Ejemplo:**
```env
GOOGLE_CLIENT_ID=123456789-abcdefg.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-abcdefghijklmnopqrstuvwxyz
```

### 5. Verificar que el Archivo .env se Está Cargando

El archivo `.env` debe estar en la raíz del proyecto (mismo nivel que `index.php`).

### 6. Probar la Configuración

1. Ve a tu página de login
2. Haz clic en el botón "Google"
3. Deberías ser redirigido a Google para autorizar
4. Después de autorizar, serás redirigido de vuelta y se iniciará sesión automáticamente

## ⚠️ Solución de Problemas

### Error: "Autenticación con Google no está configurada"

**Causa**: Las variables `GOOGLE_CLIENT_ID` o `GOOGLE_CLIENT_SECRET` no están configuradas o no se están leyendo correctamente.

**Solución**:
1. Verifica que el archivo `.env` existe en la raíz del proyecto
2. Verifica que las variables están escritas correctamente (sin espacios, sin comillas)
3. Verifica que el archivo `.env` tiene permisos de lectura (644 o 600)
4. Reinicia el servidor web si es necesario

### Error: "redirect_uri_mismatch"

**Causa**: La URI de redirección en Google Cloud Console no coincide con la URL real.

**Solución**:
1. Ve a Google Cloud Console → Credenciales
2. Edita tu ID de cliente OAuth 2.0
3. Añade la URI exacta que aparece en el error
4. Guarda los cambios

### Error: "access_denied"

**Causa**: El usuario canceló la autorización o la app está en modo de prueba.

**Solución**:
- Si la app está en modo de prueba, añade el email del usuario en "Usuarios de prueba" en Google Cloud Console
- O publica la app para que todos puedan usarla

## 🔒 Seguridad

- **NUNCA** compartas tu `GOOGLE_CLIENT_SECRET`
- **NUNCA** subas el archivo `.env` a Git
- Asegúrate de que `.env` está en `.gitignore`
- Usa HTTPS en producción

## 📝 Notas Adicionales

- El `GOOGLE_CLIENT_ID` puede ser público (se usa en el frontend)
- El `GOOGLE_CLIENT_SECRET` debe mantenerse secreto (solo en el servidor)
- Puedes tener diferentes credenciales para desarrollo y producción



