# 🔧 Actualizar archivo .env en Producción

## 📋 CREDENCIALES CORRECTAS (del panel de Hostinger)

Según el panel de Hostinger, las credenciales correctas son:

- **Base de datos:** `u600265163_HAggBlS0j_urrestv`
- **Usuario:** `u600265163_HAggBlS0j_admin`
- **Host:** `localhost` (o el que indique Hostinger)
- **Contraseña:** (la que configuraste al crear el usuario)

---

## 📝 CONTENIDO DEL ARCHIVO `.env`

Actualiza el archivo `.env` en la raíz de tu proyecto con este contenido:

```env
APP_ENV=production
SITE_URL=https://goldenrod-finch-839887.hostingersite.com

DB_HOST=localhost
DB_USER=u600265163_HAggBlS0j_admin
DB_PASS=tu_contraseña_aqui
DB_NAME=u600265163_HAggBlS0j_urrestv
```

---

## ⚠️ IMPORTANTE

1. **Reemplaza `tu_contraseña_aqui`** con la contraseña real del usuario `u600265163_HAggBlS0j_admin`

2. **Verifica el host:** En algunos casos, Hostinger usa un host diferente a `localhost`. Si `localhost` no funciona, prueba:
   - `127.0.0.1`
   - O el host específico que te muestre Hostinger en el panel

3. **Formato del archivo:**
   - Sin espacios antes o después del `=`
   - Sin comillas alrededor de los valores
   - Sin espacios al final de las líneas

---

## 🔍 CÓMO OBTENER LA CONTRASEÑA

Si no recuerdas la contraseña del usuario:

1. Ve al panel de Hostinger
2. **Bases de datos MySQL** → **MySQL Users**
3. Busca el usuario `u600265163_HAggBlS0j_admin`
4. Haz clic en **"Cambiar contraseña"** o **"Reset Password"**
5. Genera una nueva contraseña segura
6. Copia la contraseña y actualiza el archivo `.env`

---

## ✅ DESPUÉS DE ACTUALIZAR

1. Guarda el archivo `.env`
2. Prueba el diagnóstico:
   ```
   https://goldenrod-finch-839887.hostingersite.com/api/content/test-db.php
   ```
3. Debería mostrar `"status": "SUCCESS"` si todo está correcto

---

## 📝 NOTA SOBRE EL NOMBRE DE LA BASE DE DATOS

El panel muestra:
- Base de datos: `u600265163_HAggBlS0j_urrestv`

Pero el `.env` tenía:
- `u6O0265163_HAggBlS0j_streamingplatf` ❌ (incorrecto)

**Asegúrate de usar el nombre exacto que aparece en el panel.**






