@echo off
cd /d "%~dp0"
title Instalar SICA-EPO6

rem --- Buscar PHP (el de XAMPP si no esta en el PATH) ---
set "PHP=php"
where php >nul 2>nul || set "PHP=C:\xampp\php\php.exe"
"%PHP%" -v >nul 2>nul
if errorlevel 1 (
  echo No se encontro PHP. Instala XAMPP o revisa que exista C:\xampp\php\php.exe
  pause & exit /b 1
)

rem --- Dependencias (carpeta vendor) ---
if not exist vendor\autoload.php (
  where composer >nul 2>nul
  if errorlevel 1 (
    echo Falta la carpeta vendor y Composer no esta instalado.
    echo Instala Composer desde https://getcomposer.org/download/ y vuelve a abrir este archivo.
    pause & exit /b 1
  )
  echo Instalando dependencias con Composer...
  call composer install --no-dev --optimize-autoloader --no-interaction
  if errorlevel 1 ( echo Fallo composer install. & pause & exit /b 1 )
)

rem --- Archivo .env y clave de la aplicacion ---
if not exist .env copy .env.example .env >nul
"%PHP%" artisan key:generate --force
if errorlevel 1 ( echo Fallo key:generate. & pause & exit /b 1 )

rem --- Crear la base de datos en el MySQL de XAMPP (debe estar encendido) ---
if exist C:\xampp\mysql\bin\mysql.exe (
  C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS sica_epo6_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  if errorlevel 1 (
    echo No se pudo conectar a MySQL. Abre el Panel de XAMPP, da Start en MySQL y vuelve a ejecutar este archivo.
    pause & exit /b 1
  )
)

rem --- Tablas y cuentas ---
"%PHP%" artisan optimize:clear
if exist storage\framework\sica-preparado.json del storage\framework\sica-preparado.json
"%PHP%" artisan migrate --force
if errorlevel 1 ( echo Fallo migrate. Revisa los datos DB_ en el archivo .env & pause & exit /b 1 )
"%PHP%" artisan sica:crear-usuarios

echo.
echo Listo. Ahora abre "iniciar.bat" para encender el sistema.
pause
