# Configuración de Base de Datos por Entorno

## 🔄 Detección Automática de Entorno

El sistema detecta automáticamente si está en **local** o **producción** basándose en:

1. **Hostname**: Si es `localhost`, `127.0.0.1`, contiene `.local` o `ngrok` → **LOCAL**
2. **Variable APP_ENV**: Si está definida en `.env` → se usa esa
3. **Por defecto**: Si no se detecta local → **PRODUCCIÓN**

## 📋 Configuración Actual

### ✅ En LOCAL (XAMPP)
El sistema **SIEMPRE** usa estas credenciales (ignora el `.env`):
```
DB_HOST = 127.0.0.1
DB_USER = root
DB_PASS = (vacío)
DB_NAME = streaming_platform
```

**No necesitas cambiar nada en local** - funciona automáticamente.

### ✅ En PRODUCCIÓN
El sistema lee las credenciales del archivo `.env`:
```
APP_ENV=production
DB_HOST=localhost
DB_USER=tu_usuario_produccion
DB_PASS=tu_contraseña_produccion
DB_NAME=tu_base_datos_produccion
```

## 📝 Archivo `.env` Recomendado

### Para LOCAL (opcional, se ignora para BD)
```env
APP_ENV=local
SITE_URL=http://localhost/streaming-platform

# Estas credenciales se IGNORAN en local (usa valores por defecto)
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=
DB_NAME=streaming_platform
```

### Para PRODUCCIÓN (obligatorio)
```env
APP_ENV=production
SITE_URL=https://tu-dominio.com/streaming-platform

# Estas credenciales se USAN en producción
DB_HOST=localhost
DB_USER=tu_usuario_real
DB_PASS=tu_contraseña_real
DB_NAME=tu_base_datos_real
```

## 🚀 ¿Qué Hacer al Subir a Producción?

### Opción 1: Usar el `.env` existente (recomendado)
1. El archivo `.env` ya tiene las credenciales de producción
2. Solo asegúrate de que tenga:
   ```env
   APP_ENV=production
   ```
3. O simplemente **no pongas** `APP_ENV=local` y el sistema detectará producción automáticamente

### Opción 2: Crear `.env` nuevo en producción
1. Copia `config/env.example` a `.env`
2. Edita y pon tus credenciales de producción
3. Asegúrate de que `APP_ENV=production` o elimina esa línea

## ✅ Ventajas de Esta Configuración

1. **En local**: No necesitas configurar nada, funciona automáticamente
2. **En producción**: Solo necesitas el `.env` con las credenciales correctas
3. **Sin cambios manuales**: El código detecta el entorno automáticamente
4. **Seguro**: Las credenciales de producción no interfieren con local

## 🔍 Verificar Configuración

Ejecuta este script para ver qué credenciales está usando:
```
http://localhost/streaming-platform/test-db-connection.php
```

Muestra:
- Entorno detectado (local/producción)
- Credenciales que está usando
- Estado de la conexión
- Tablas disponibles

## ⚠️ Importante

- **No subas el `.env` a Git** (debe estar en `.gitignore`)
- **En producción**: Asegúrate de que el `.env` tenga las credenciales correctas
- **En local**: El `.env` puede tener cualquier cosa, se ignorará para la BD

