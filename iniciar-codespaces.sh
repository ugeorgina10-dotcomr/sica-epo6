#!/usr/bin/env bash
# Arranque rápido de SICA-EPO6 en Codespaces / Linux.
# Uso:  bash iniciar-codespaces.sh
cd "$(dirname "$0")" || exit 1

# 1) Varias peticiones a la vez: antes, las consultas de actualización (cada 3 s) y los guardados hacían cola.
export PHP_CLI_SERVER_WORKERS=4

# 2) Configuración para producción SOLO durante esta ejecución (no toca tu .env):
#    sesiones en archivo (sin consultar MySQL en cada petición), sin modo debug y con menos escritura de logs.
export SESSION_DRIVER=file APP_DEBUG=false LOG_LEVEL=warning

# 3) Deja lista la configuración y las vistas ya compiladas (arranque y respuestas más rápidas).
#    (No se usa route:cache porque las rutas de este proyecto llevan funciones anónimas.)
php artisan config:cache
php artisan view:cache

echo "SICA-EPO6 en http://localhost:8000 (en Codespaces: pestaña Puertos > puerto 8000)"
# 4) PHP directo (sin el paso extra de "artisan serve") y con opcache: el código queda compilado en memoria.
#    Si cambias archivos .php, detén el servidor (Ctrl+C) y vuelve a ejecutar este script.
SERVIDOR=vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
if [ -f "$SERVIDOR" ]; then
  cd public && exec php -d zlib.output_compression=1 -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -d opcache.memory_consumption=128 \
    -d opcache.max_accelerated_files=10000 -d realpath_cache_size=4096K -d realpath_cache_ttl=600 \
    -S 0.0.0.0:8000 -t . "../$SERVIDOR"
fi
php artisan serve --host=0.0.0.0 --port=8000
