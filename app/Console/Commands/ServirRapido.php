<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

/**
 * Sustituye a `php artisan serve` (mismo nombre y mismas opciones: --host, --port...) para que arranque y responda más rápido:
 *  - opcache: el código PHP queda compilado en memoria (se revisan cambios de archivos cada 2 s, así que editar sigue funcionando);
 *  - varias peticiones a la vez (Linux, Mac y Codespaces; en Windows PHP no lo permite);
 *  - compresión de las respuestas y límites de subida de archivos suficientes para listas grandes;
 *  - las vistas ya compiladas.
 * No toca el .env ni guarda la configuración en caché: lo que cambies en .env se aplica al reiniciar el servidor.
 */
class ServirRapido extends ServeCommand
{
    protected $name = 'serve';

    protected $description = 'Enciende el servidor de SICA (rápido: opcache, varias peticiones a la vez y respuestas comprimidas)';

    private const AJUSTES_PHP = [
        'opcache.enable_cli=1',
        'opcache.validate_timestamps=1',
        'opcache.revalidate_freq=2',
        'opcache.memory_consumption=128',
        'opcache.max_accelerated_files=10000',
        'realpath_cache_size=4096K',
        'realpath_cache_ttl=600',
        'zlib.output_compression=1',
        'upload_max_filesize=25M',
        'post_max_size=30M',
        'max_file_uploads=50',
        'memory_limit=256M',
    ];

    public function handle()
    {
        // Varias peticiones a la vez: sin esto, un guardado o una subida grande dejaba en espera las consultas de los demás equipos
        if (stripos(PHP_OS, 'WIN') !== 0 && empty($_ENV['PHP_CLI_SERVER_WORKERS']) && !getenv('PHP_CLI_SERVER_WORKERS')) {
            $_ENV['PHP_CLI_SERVER_WORKERS'] = '4';
        }

        // Vistas ya compiladas (si falla no importa: se compilan al usarlas)
        try {
            $this->callSilently('view:cache');
        } catch (\Throwable $e) {
        }

        return parent::handle();
    }

    protected function serverCommand()
    {
        $comando = parent::serverCommand();
        $php = array_shift($comando);

        $banderas = [];
        foreach (self::AJUSTES_PHP as $ajuste) {
            array_push($banderas, '-d', $ajuste);
        }

        return array_merge([$php], $banderas, $comando);
    }
}
