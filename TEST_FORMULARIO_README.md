# 🧪 Tests de Funcionalidad - Formulario Netflix

## 📋 Archivos de Prueba

Se han creado 2 archivos para probar el formulario:

### 1. **test-form-functionality.html**
Archivo HTML completo con interfaz visual para probar el formulario.

### 2. **test-form-simple.js**
Script JavaScript simple que se ejecuta en la consola del navegador.

---

## 🚀 Cómo Usar los Tests

### Opción 1: Archivo HTML (Recomendado)

1. **Abre el archivo en tu navegador:**
   ```
   http://localhost/streaming-platform/test-form-functionality.html
   ```

2. **O desde el servidor de producción:**
   ```
   https://tudominio.com/test-form-functionality.html
   ```

3. **El test verificará automáticamente:**
   - ✅ Elementos HTML del formulario
   - ✅ Funcionalidad de eventos
   - ✅ Validación de campos
   - ✅ Estilos CSS aplicados
   - ✅ Responsive design

4. **Funciones disponibles:**
   - **"Ejecutar Todas las Pruebas"**: Ejecuta todos los tests
   - **"Probar Responsive"**: Prueba diferentes anchos de pantalla
   - **"Ver Formulario"**: Abre una vista previa del formulario

---

### Opción 2: Script en Consola (Rápido)

1. **Abre el panel de administración:**
   ```
   http://localhost/streaming-platform/admin/
   ```

2. **Abre la consola del navegador:**
   - `F12` o `Ctrl + Shift + I` (Windows/Linux)
   - `Cmd + Option + I` (Mac)

3. **Copia y pega el contenido de `test-form-simple.js`**

4. **Presiona Enter** para ejecutar

5. **Verás los resultados en la consola:**
   - ✅ Tests exitosos (verde)
   - ❌ Tests fallidos (rojo)
   - ⚠️ Advertencias (amarillo)

---

## 📊 Qué Prueban los Tests

### ✅ Elementos HTML
- Verifica que todos los campos requeridos existan
- Comprueba que los campos opcionales estén presentes
- Valida que los checkboxes estén configurados

### ✅ Funcionalidad
- Prueba eventos de carga de archivos
- Verifica que los botones funcionen
- Comprueba que las funciones globales estén disponibles

### ✅ Validación
- Verifica atributos `required`
- Comprueba tipos de archivo aceptados (`image/*`, `video/*`)
- Valida límites de tamaño de archivos

### ✅ Estilos CSS
- Verifica que las clases Netflix estén aplicadas
- Comprueba que los estilos se rendericen correctamente
- Valida colores y efectos

### ✅ Responsive
- Prueba diferentes anchos de pantalla (1920px, 1024px, 768px, 375px)
- Verifica que el formulario se adapte correctamente
- Comprueba que los elementos se reorganicen en móvil

---

## 🎯 Resultados Esperados

### ✅ Todos los Tests Deben Pasar:
- ✅ Formulario encontrado
- ✅ Todos los campos requeridos presentes
- ✅ Validación de archivos configurada
- ✅ Estilos CSS aplicados
- ✅ Responsive funcionando

### ⚠️ Advertencias Aceptables:
- Campos opcionales (trailer, torrent) pueden no estar presentes si no se usan
- Algunos eventos pueden necesitar interacción del usuario

### ❌ Errores que Requieren Corrección:
- Campos requeridos faltantes
- Funciones JavaScript no disponibles
- Estilos CSS no aplicados
- Problemas de responsive

---

## 🔧 Solución de Problemas

### Si el test HTML no carga el formulario:
1. Asegúrate de que `admin/index.php` esté accesible
2. Verifica que no haya errores de CORS
3. Usa el script de consola en su lugar

### Si faltan elementos:
1. Verifica que hayas subido `admin/index.php` correctamente
2. Limpia la caché del navegador (`Ctrl + Shift + R`)
3. Verifica que no haya errores de JavaScript en la consola

### Si los estilos no se aplican:
1. Verifica que `css/admin.css` esté cargado
2. Comprueba que las clases Netflix estén en el HTML
3. Revisa la consola por errores de CSS

---

## 📱 Prueba Manual de Responsive

Además de los tests automáticos, puedes probar manualmente:

1. **Abre las herramientas de desarrollador** (`F12`)
2. **Activa el modo responsive** (icono de móvil)
3. **Prueba estos anchos:**
   - **Desktop**: 1920px, 1440px, 1280px
   - **Tablet**: 1024px, 768px
   - **Móvil**: 375px, 414px, 360px

4. **Verifica:**
   - ✅ El formulario se adapta al ancho
   - ✅ Los elementos no se salen de la pantalla
   - ✅ Los botones son accesibles
   - ✅ El texto es legible

---

## 🎨 Prueba Visual

### Elementos a Verificar Visualmente:

1. **Fondo oscuro estilo Netflix**
   - Debe ser negro/gris oscuro
   - Con gradientes sutiles

2. **Secciones con iconos**
   - Iconos en cajas rojas
   - Títulos destacados
   - Separadores visibles

3. **Áreas de carga de archivos**
   - Bordes punteados
   - Efectos hover (al pasar el mouse)
   - Iconos grandes

4. **Botones**
   - Color rojo Netflix (#e50914)
   - Efectos hover con elevación
   - Texto en mayúsculas

5. **Inputs**
   - Fondo oscuro
   - Bordes sutiles
   - Focus rojo

---

## 📝 Checklist Completo

- [ ] Ejecutar test HTML
- [ ] Ejecutar script en consola
- [ ] Verificar todos los elementos HTML
- [ ] Probar carga de archivos (póster, backdrop, video)
- [ ] Probar validación de archivos
- [ ] Verificar estilos CSS aplicados
- [ ] Probar responsive en diferentes anchos
- [ ] Probar en navegadores: Chrome, Firefox, Edge
- [ ] Probar en dispositivos móviles reales
- [ ] Verificar que no haya errores en consola

---

## 🚀 Después de los Tests

Si todos los tests pasan:
- ✅ El formulario está funcionando correctamente
- ✅ Puedes proceder con confianza a producción

Si hay errores:
- ❌ Revisa los mensajes de error
- ❌ Corrige los problemas identificados
- ❌ Vuelve a ejecutar los tests

---

**Fecha de creación:** $(Get-Date -Format "yyyy-MM-dd")
**Versión:** 1.0

