#!/usr/bin/env bash
# Abre SICA-EPO6 de forma rápida en Codespaces o Linux.
# Uso:  bash iniciar-codespaces.sh
cd "$(dirname "$0")" || exit 1

# 1) Permite atender a varias personas al mismo tiempo (antes tenían que esperar su turno).
export PHP_CLI_SERVER_WORKERS=${PHP_CLI_SERVER_WORKERS:-64}

# 2) Ajustes para que funcione más ligero SOLO mientras esté abierto (no cambia tu archivo .env).
export SESSION_DRIVER=file APP_DEBUG=false LOG_LEVEL=warning

export DB_PERSISTENT=true   # mantiene abierta la conexión a la base de datos entre visitas
# 3) Deja todo preparado de antemano para que el sistema abra más rápido.
php artisan config:cache
php artisan view:cache
php artisan migrate --force >/dev/null 2>&1
# Despierta la base de datos (la de la nube se duerme sola) y llena las listas en memoria: la primera visita ya sale rápida
php artisan sica:calentar

echo "SICA-EPO6 en http://localhost:8000 (en Codespaces: pestaña Puertos > puerto 8000)"
# 4) Enciende el sistema. Si cambias algún archivo .php, ciérralo (Ctrl+C) y vuelve a ejecutar este archivo.
SERVIDOR=vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
if [ -f "$SERVIDOR" ]; then
  cd public && exec php -d zlib.output_compression=1 -d upload_max_filesize=25M -d post_max_size=30M -d max_file_uploads=50 -d memory_limit=256M -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -d opcache.memory_consumption=128 \
    -d opcache.max_accelerated_files=10000 -d realpath_cache_size=4096K -d realpath_cache_ttl=600 \
    -S 0.0.0.0:8000 -t . "../$SERVIDOR"
fi
php artisan serve --host=0.0.0.0 --port=8000
