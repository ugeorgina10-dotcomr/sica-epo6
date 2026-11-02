@echo off
cd /d "%~dp0"
title SICA-EPO6 (no cierres esta ventana)
set "PHP=php"
where php >nul 2>nul || set "PHP=C:\xampp\php\php.exe"

rem Deja lista la configuracion y las vistas ya compiladas: el sistema abre y responde mas rapido
"%PHP%" artisan config:cache >nul 2>nul
"%PHP%" artisan view:cache >nul 2>nul

rem Abre el navegador cuando el servidor ya esta encendido (antes se abria primero y salia "no se puede conectar")
start "" /min cmd /c "ping -n 3 127.0.0.1 >nul & start "" http://127.0.0.1:8000"

rem Arranque rapido: PHP directo (sin el paso extra de "artisan serve") y con opcache, que guarda el codigo ya compilado en memoria.
rem Si cambias archivos .php, cierra esta ventana y vuelve a abrir iniciar.bat.
set "SERVIDOR=vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
if exist "%SERVIDOR%" (
  cd public
  "%PHP%" -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -d opcache.memory_consumption=128 -d opcache.max_accelerated_files=10000 -d realpath_cache_size=4096K -d realpath_cache_ttl=600 -S 127.0.0.1:8000 -t . "..\%SERVIDOR%"
) else (
  "%PHP%" artisan serve --host=127.0.0.1 --port=8000
)
pause
