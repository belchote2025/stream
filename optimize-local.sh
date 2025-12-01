#!/bin/bash

# Detener los servicios de XAMPP
echo "Deteniendo servicios de XAMPP..."
net stop Apache2.4
net stop MySQL80

# Hacer copia de seguridad de los archivos de configuración
echo "Haciendo copia de seguridad de los archivos de configuración..."
copy "C:\xampp\php\php.ini" "C:\xampp\php\php.ini.bak"
copy "C:\xampp\apache\conf\httpd.conf" "C:\xampp\apache\conf\httpd.conf.bak"

# Aplicar la configuración optimizada de PHP
echo "Aplicando configuración optimizada de PHP..."
copy "C:\xampp\htdocs\streaming-platform\optimized-php.ini" "C:\xampp\php\php.ini" /Y

# Aplicar la configuración optimizada de Apache
echo "Aplicando configuración optimizada de Apache..."
findstr /v "# INCLUDE OPTIMIZED CONFIG" "C:\xampp\apache\conf\httpd.conf" > "C:\xampp\apache\conf\httpd.conf.tmp"
echo. >> "C:\xampp\apache\conf\httpd.conf.tmp"
echo "# INCLUDE OPTIMIZED CONFIG" >> "C:\xampp\apache\conf\httpd.conf.tmp"
type "C:\xampp\htdocs\streaming-platform\optimized-httpd.conf" >> "C:\xampp\apache\conf\httpd.conf.tmp"
move /Y "C:\xampp\apache\conf\httpd.conf.tmp" "C:\xampp\apache\conf\httpd.conf"

# Crear directorio de caché si no existe
if not exist "C:\xampp\htdocs\streaming-platform\cache" (
    mkdir "C:\xampp\htdocs\streaming-platform\cache"
    icacls "C:\xampp\htdocs\streaming-platform\cache" /grant "IUSR:(OI)(CI)F"
)

# Crear directorio de logs si no existe
if not exist "C:\xampp\php\logs" (
    mkdir "C:\xampp\php\logs"
)

# Iniciar los servicios de XAMPP
echo "Iniciando servicios de XAMPP..."
net start MySQL80
net start Apache2.4

echo "¡Optimización completada! Por favor, verifica que todo funcione correctamente."
echo "Puedes revertir los cambios ejecutando el script 'restore-backup.bat'"

# Crear script para restaurar la configuración anterior
echo "@echo off" > "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "echo Restaurando configuración anterior..." >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "net stop Apache2.4" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "net stop MySQL80" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "copy \"C:\xampp\php\php.ini.bak\" \"C:\xampp\php\php.ini\" /Y" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "copy \"C:\xampp\apache\conf\httpd.conf.bak\" \"C:\xampp\apache\conf\httpd.conf\" /Y" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "net start MySQL80" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "net start Apache2.4" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "echo ¡Configuración restaurada!" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"
echo "pause" >> "C:\xampp\htdocs\streaming-platform\restore-backup.bat"

pause
