<?php
/*
 * Arranque rápido de SICA-EPO6 (Windows, Linux, Codespaces y VS Code).
 *
 *   php rapido.php          (o:  composer rapido)
 *
 * Qué hace, en orden:
 *  1) Guarda ya compiladas la configuración y las vistas (el sistema abre y responde más rápido).
 *  2) Enciende PHP con opcache (el código queda compilado en memoria) y compresión de las respuestas.
 *  3) En Linux / Codespaces / Mac atiende varias peticiones a la vez (en Windows PHP no lo permite).
 *
 * Si cambias archivos .php o el .env, detén el servidor (Ctrl+C) y vuelve a ejecutarlo: así toma los cambios.
 * No toca tu .env.
 */
chdir(__DIR__);

$php = escapeshellarg(PHP_BINARY);
$puerto = getenv('PUERTO') ?: '8000';
$host = getenv('HOST_SICA') ?: (getenv('CODESPACES') ? '0.0.0.0' : '127.0.0.1');

$router = 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
if (!is_file($router)) {
    fwrite(STDERR, "Falta la carpeta vendor. Ejecuta primero:  composer install\n");
    exit(1);
}

echo "Preparando configuración y vistas...\n";
passthru("$php artisan config:cache", $c1);
passthru("$php artisan view:cache", $c2);
if ($c1 !== 0 || $c2 !== 0) {
    fwrite(STDERR, "No se pudo preparar la configuración (revisa el mensaje de arriba y tu archivo .env).\n");
    exit(1);
}

if (stripos(PHP_OS, 'WIN') !== 0) {
    putenv('PHP_CLI_SERVER_WORKERS=' . (getenv('PHP_CLI_SERVER_WORKERS') ?: '4'));
}

$ajustes = [
    'zlib.output_compression=1', 'upload_max_filesize=25M', 'post_max_size=30M', 'max_file_uploads=50', 'memory_limit=256M',
    'opcache.enable_cli=1', 'opcache.validate_timestamps=0', 'opcache.memory_consumption=128', 'opcache.max_accelerated_files=10000',
    'realpath_cache_size=4096K', 'realpath_cache_ttl=600',
];
$banderas = implode(' ', array_map(fn ($a) => '-d ' . escapeshellarg($a), $ajustes));

echo "SICA-EPO6 en http://localhost:$puerto  (Ctrl+C para detener)\n";
// El servidor debe arrancar dentro de /public: ahí busca los archivos estáticos (imágenes, vendor, sw.js)
chdir(__DIR__ . DIRECTORY_SEPARATOR . 'public');
passthru("$php $banderas -S $host:$puerto -t . " . escapeshellarg('..' . DIRECTORY_SEPARATOR . $router));
