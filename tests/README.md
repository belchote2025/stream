# 🧪 Guía de Testing - UrresTv

Esta guía explica cómo configurar y ejecutar los tests unitarios para el proyecto UrresTv.

## 📋 Prerrequisitos

- **PHP 7.4+** con extensiones `pdo` y `mysqli`
- **MySQL 5.7+** o **MariaDB 10.0+**
- **Composer** (gestor de dependencias PHP)

### Instalar Composer

Si no tienes Composer instalado, descárgalo desde [getcomposer.org](https://getcomposer.org/) o usa:

```bash
# Windows (PowerShell como Administrador)
# Descarga e instala Composer desde https://getcomposer.org/Composer-Setup.exe
```

## 🚀 Configuración Inicial

### 1. Instalar Dependencias

```bash
# Instalar PHPUnit y otras dependencias de desarrollo
composer install
```

### 2. Configurar Base de Datos de Pruebas

```bash
# Crear la base de datos de pruebas
php tests/bootstrap.php

# Opcional: Limpiar y recrear la base de datos
php tests/bootstrap.php clean
```

Esto creará una base de datos llamada `streaming_platform_test` con todas las tablas necesarias.

## 🏃 Ejecutar Tests

### Tests Básicos

```bash
# Ejecutar todos los tests
composer test

# O directamente con PHPUnit
./vendor/bin/phpunit
```

### Tests con Reporte de Cobertura

```bash
# Generar reporte HTML de cobertura
composer test:coverage

# Ver el reporte en el navegador
# Abrir: coverage/html/index.html
```

### Tests Específicos

```bash
# Ejecutar solo tests de configuración
./vendor/bin/phpunit tests/Unit/ConfigTest.php

# Ejecutar solo tests de autenticación
./vendor/bin/phpunit tests/Unit/AuthTest.php

# Ejecutar con verbose output
./vendor/bin/phpunit --verbose
```

## 📁 Estructura de Tests

```
tests/
├── bootstrap.php          # Configuración de entorno de pruebas
├── README.md             # Esta guía
├── Unit/                 # Tests unitarios
│   ├── ConfigTest.php    # Tests de configuración
│   └── AuthTest.php      # Tests de autenticación
├── Integration/          # Tests de integración (futuro)
└── Feature/              # Tests funcionales (futuro)
```

## 🔧 Scripts Disponibles

Los siguientes scripts están definidos en `composer.json`:

- `composer test` - Ejecuta todos los tests
- `composer test:coverage` - Ejecuta tests con reporte de cobertura
- `composer lint` - Verifica estilo de código (PSR-12)
- `composer lint:fix` - Corrige automáticamente estilo de código
- `composer analyze` - Análisis estático de código

## 📊 Tipos de Tests Implementados

### Unit Tests (ConfigTest)

- ✅ Verificación de constantes definidas
- ✅ Configuración de base de datos por defecto
- ✅ Override de variables de entorno
- ✅ Construcción de URLs del sitio
- ✅ Sanitización de entrada
- ✅ Generación y verificación de tokens CSRF
- ✅ Constantes de hash de contraseña

### Unit Tests (AuthTest)

- ✅ Registro de usuarios con datos válidos
- ✅ Validación de campos obligatorios
- ✅ Validación de formato de email
- ✅ Validación de longitud de contraseña
- ✅ Prevención de usuarios duplicados
- ✅ Login con credenciales válidas/inválidas
- ✅ Estado de autenticación
- ✅ Actualización de perfil
- ✅ Cambio de contraseña
- ✅ Logout
- ✅ Reset de contraseña con tokens

## 🐛 Troubleshooting

### Error: "No se puede conectar a la base de datos de prueba"

**Solución:**
1. Verifica que MySQL esté ejecutándose
2. Revisa las credenciales en `phpunit.xml`
3. Ejecuta: `php tests/bootstrap.php`

### Error: "Clase PHPUnit_Framework_TestCase no encontrada"

**Solución:**
```bash
composer install
```

### Error: "Permiso denegado" en logs/coverage

**Solución:**
```bash
# Crear directorios necesarios
mkdir -p logs coverage/html
chmod 755 logs coverage
```

### Tests se saltan con "No se puede conectar a la base de datos"

**Solución:**
- Asegúrate de que MySQL esté ejecutándose en `localhost:3306`
- Verifica que el usuario `root` tenga permisos para crear bases de datos
- O modifica las credenciales en `tests/bootstrap.php`

## 📈 Mejores Prácticas

### Escribir Nuevos Tests

1. **Coloca tests en la carpeta apropiada:**
   - `tests/Unit/` - Tests unitarios (sin BD)
   - `tests/Integration/` - Tests de integración (con BD)
   - `tests/Feature/` - Tests funcionales (end-to-end)

2. **Nombra los archivos de test:** `NombreClaseTest.php`

3. **Estructura de test:**
   ```php
   public function testNombreDescriptivo()
   {
       // Arrange
       // Act
       // Assert
   }
   ```

4. **Usa data providers** para tests con múltiples casos:
   ```php
   /**
    * @dataProvider provideTestData
    */
   public function testSomething($input, $expected)
   ```

### Cobertura de Código

- Apunta al **80% mínimo** de cobertura
- Enfócate en **código de negocio** (excluye config.php)
- Revisa el reporte HTML para identificar código no testeado

## 🎯 Próximos Tests a Implementar

### Integration Tests
- Tests de API endpoints
- Tests de interacción con base de datos
- Tests de formularios web

### Feature Tests
- Tests de flujos completos de usuario
- Tests de interfaz de usuario
- Tests de rendimiento

### Mejoras
- Tests para JavaScript (Jest)
- Tests de carga ( Artillery)
- Tests de seguridad automatizados

## 📞 Soporte

Si encuentras problemas con los tests:

1. Verifica que todas las dependencias estén instaladas
2. Revisa los logs de error de PHPUnit
3. Verifica la configuración de base de datos
4. Consulta la documentación de PHPUnit

---

**¡Mantén tus tests actualizados y ejecutándolos regularmente!** 🧪✨
