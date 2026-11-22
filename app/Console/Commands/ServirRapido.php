<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

/**
 * Sustituye a `php artisan serve` (se usa igual: --host, --port...) para que el sistema abra y responda más rápido:
 * - deja el código preparado en memoria (si editas un archivo, el cambio se nota en unos segundos);
 * - atiende a varias personas a la vez (Linux, Mac y Codespaces; en Windows no es posible);
 * - comprime las respuestas y permite subir listas grandes;
 * - deja las pantallas ya preparadas.
 * No cambia tu archivo .env: lo que modifiques ahí se aplica al reiniciar el sistema.
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
        // Atiende a varias personas a la vez: sin esto, un guardado o una subida grande hacía esperar a los demás equipos
        if (stripos(PHP_OS, 'WIN') !== 0 && empty($_ENV['PHP_CLI_SERVER_WORKERS']) && !getenv('PHP_CLI_SERVER_WORKERS')) {
            $_ENV['PHP_CLI_SERVER_WORKERS'] = '4';
        }

        // Deja las pantallas preparadas (si falla no pasa nada: se preparan al usarlas)
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
