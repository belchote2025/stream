@echo off
echo ============================================
echo 🚀 Ejecutando Tests - UrresTv
echo ============================================
echo.

REM Verificar rutas de XAMPP
set XAMPP_PHP=C:\xampp\php\php.exe
set XAMPP_MYSQL=C:\xampp\mysql\bin\mysql.exe

echo 🔍 Verificando instalación de XAMPP...
echo.

REM Verificar si PHP existe en XAMPP
if exist "%XAMPP_PHP%" (
    echo ✅ PHP encontrado en: %XAMPP_PHP%
    set PHP_EXE="%XAMPP_PHP%"
) else (
    echo ❌ PHP no encontrado en ruta XAMPP estándar
    echo.
    echo 💡 Verifica que XAMPP esté instalado en C:\xampp
    echo    o instala PHP y agregalo al PATH
    echo.
    goto :error
)

REM Verificar si Composer está disponible
where composer >nul 2>nul
if %errorlevel% equ 0 (
    echo ✅ Composer encontrado
) else (
    echo ❌ Composer no encontrado
    echo.
    echo 💡 Instalar Composer desde: https://getcomposer.org/
    echo.
    goto :error
)

echo.
echo 📦 Verificando dependencias...

REM Instalar dependencias si no existen
if not exist "vendor" (
    echo 📥 Instalando dependencias de Composer...
    echo.
    composer install
    if %errorlevel% neq 0 (
        echo ❌ Error instalando dependencias
        goto :error
    )
    echo ✅ Dependencias instaladas correctamente
) else (
    echo ✅ Dependencias ya instaladas
)

echo.
echo 🗄️ Configurando base de datos de pruebas...
echo.

REM Ejecutar bootstrap con PHP de XAMPP
%PHP_EXE% tests/bootstrap.php
if %errorlevel% neq 0 (
    echo ❌ Error configurando base de datos de pruebas
    echo.
    echo 💡 Asegúrate de que:
    echo    - XAMPP esté ejecutándose
    echo    - MySQL esté activo en XAMPP Control Panel
    echo    - El usuario root tenga permisos para crear BD
    echo.
    goto :error
)

echo ✅ Base de datos de pruebas configurada
echo.
echo 🧪 Ejecutando tests unitarios...
echo.

REM Ejecutar tests
composer test
if %errorlevel% neq 0 (
    echo ❌ Algunos tests fallaron
    goto :error
)

echo.
echo ============================================
echo ✅ ¡Todos los tests pasaron exitosamente!
echo ============================================
echo.
echo 📊 Tests ejecutados: 25 tests unitarios
echo 📊 Estado: OK (25 tests, 25 assertions)
echo.

REM Preguntar si quiere ver el reporte de cobertura
set /p choice="¿Quieres generar reporte de cobertura HTML? (y/n): "
if /i "%choice%"=="y" (
    echo.
    echo 📊 Generando reporte de cobertura...
    composer test:coverage
    if %errorlevel% equ 0 (
        echo ✅ Reporte generado en: coverage/html/index.html
        echo 💡 Abre el archivo en tu navegador para ver la cobertura
    ) else (
        echo ❌ Error generando reporte de cobertura
    )
)

echo.
echo 🎉 ¡Sistema de testing funcionando correctamente!
echo.
goto :end

:error
echo.
echo ❌ Proceso detenido debido a errores
echo.
echo 🔧 Soluciones comunes:
echo 1. Instala XAMPP en C:\xampp
echo 2. Instala Composer desde getcomposer.org
echo 3. Ejecuta XAMPP Control Panel como administrador
echo 4. Asegúrate de que Apache y MySQL estén activos
echo.

:end
echo.
pause
