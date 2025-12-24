# 🔧 Configurar Base de Datos en Producción

## ✅ PROBLEMA RESUELTO
- Los endpoints ahora devuelven JSON correctamente ✅
- El error de conexión se muestra en formato JSON ✅

## ⚠️ PROBLEMA ACTUAL
- Error de conexión a la base de datos
- Las credenciales no están configuradas correctamente

---

## 📋 PASOS PARA CONFIGURAR LA BASE DE DATOS

### 1. Crear archivo `.env` en la raíz del proyecto (public_html)

Crea un archivo llamado `.env` en la raíz de tu proyecto con este contenido:

```env
APP_ENV=production
SITE_URL=https://goldenrod-finch-839887.hostingersite.com

# Configuración de Base de Datos (Hostinger)
DB_HOST=localhost
DB_USER=u6O0265163_HAggBlS0j_belchote
DB_PASS=Belchote1@
DB_NAME=u6O0265163_HAggBlS0j_streamingplatf

# Seguridad (Opcional - generar claves aleatorias)
API_KEY=genera_una_clave_aleatoria_larga_aqui
SECRET_KEY=genera_otra_clave_secreta_aqui
```

### 2. Verificar credenciales en el panel de Hostinger

1. Accede al panel de Hostinger
2. Ve a **Bases de Datos MySQL**
3. Verifica que:
   - ✅ El nombre de la base de datos es correcto
   - ✅ El usuario tiene permisos sobre la base de datos
   - ✅ La contraseña es correcta
   - ✅ El host es `localhost` (o el que te indique Hostinger)

### 3. Verificar permisos del archivo `.env`

Asegúrate de que el archivo `.env` tenga permisos correctos:
- Permisos: `644` o `600` (solo lectura para el propietario)
- No debe ser accesible públicamente

### 4. Probar la conexión

Después de crear el archivo `.env`, prueba los endpoints:

```
https://goldenrod-finch-839887.hostingersite.com/api/content/recent.php?type=series&limit=12
```

Si todo está correcto, deberías recibir JSON con datos en lugar del error de conexión.

---

## 🔍 VERIFICAR CREDENCIALES

Si no estás seguro de las credenciales, puedes:

1. **Revisar el panel de Hostinger:**
   - Bases de Datos MySQL → Ver detalles de tu base de datos

2. **Verificar el archivo de configuración actual:**
   - Revisa si hay un archivo `.env` existente
   - O verifica las constantes en `includes/config.php`

3. **Contactar con soporte de Hostinger:**
   - Si no puedes acceder a las credenciales

---

## 📝 NOTAS IMPORTANTES

- ⚠️ **NUNCA** subas el archivo `.env` a un repositorio público
- ✅ El archivo `.env` debe estar en `.gitignore`
- ✅ Usa credenciales diferentes para desarrollo y producción
- ✅ Cambia las contraseñas regularmente

---

## ✅ DESPUÉS DE CONFIGURAR

Una vez configurado el `.env`, los endpoints deberían funcionar correctamente y devolver datos JSON válidos.








