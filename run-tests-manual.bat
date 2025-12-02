@echo off
echo ============================================
echo 🚀 Tests Manuales - UrresTv (Sin Composer)
echo ============================================
echo.

REM Usar PHP de XAMPP directamente
set XAMPP_PHP="C:\xampp\php\php.exe"

if not exist %XAMPP_PHP% (
    echo ❌ PHP no encontrado en: %XAMPP_PHP%
    echo.
    echo 💡 Asegúrate de que XAMPP esté instalado en C:\xampp
    echo.
    pause
    exit /b 1
)

echo ✅ PHP encontrado
echo.

REM Crear directorio de logs si no existe
if not exist "logs" mkdir logs
if not exist "coverage" mkdir coverage

echo 🗄️ Configurando base de datos de pruebas...
echo.

%XAMPP_PHP% tests/bootstrap.php
if %errorlevel% neq 0 (
    echo ❌ Error configurando base de datos
    echo.
    pause
    exit /b 1
)

echo ✅ Base de datos configurada
echo.

REM Crear un phpunit básico si no existe vendor
if not exist "vendor\bin\phpunit.bat" (
    echo ⚠️ PHPUnit no encontrado, intentando ejecutar tests manualmente...
    echo.

    REM Ejecutar tests uno por uno
    echo 🧪 Ejecutando ConfigTest...
    %XAMPP_PHP% -c C:\xampp\php\php.ini %XAMPP_PHP% tests/bootstrap.php && %XAMPP_PHP% -d include_path=".;C:\xampp\php\PEAR" vendor\bin\phpunit.bat tests\Unit\ConfigTest.php --bootstrap=tests/bootstrap.php --colors=always

    if %errorlevel% equ 0 (
        echo ✅ ConfigTest pasó
    ) else (
        echo ❌ ConfigTest falló
    )

    echo.
    echo 🧪 Ejecutando AuthTest...
    %XAMPP_PHP% tests/bootstrap.php && %XAMPP_PHP% vendor\bin\phpunit.bat tests\Unit\AuthTest.php --bootstrap=tests/bootstrap.php --colors=always

    if %errorlevel% equ 0 (
        echo ✅ AuthTest pasó
    ) else (
        echo ❌ AuthTest falló
    )

) else (
    echo 🧪 Ejecutando todos los tests con PHPUnit...
    echo.
    vendor\bin\phpunit.bat --colors=always
)

echo.
echo ============================================
echo ✅ Proceso completado
echo ============================================
echo.
echo 📊 Tests incluidos:
echo    • ConfigTest: 7 tests de configuración
echo    • AuthTest: 18 tests de autenticación
echo    • Total: 25 tests unitarios
echo.
echo 💡 Para ver resultados detallados, instala Composer y usa:
echo    composer test
echo.
pause
