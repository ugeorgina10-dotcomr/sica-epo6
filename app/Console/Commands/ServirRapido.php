<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        'opcache.jit=tracing',
        'opcache.jit_buffer_size=32M',
        'pcre.jit=1',
        'opcache.interned_strings_buffer=16',
        'xdebug.mode=off',
        'zend.assertions=-1',
        'zlib.output_compression=1',
        'upload_max_filesize=25M',
        'post_max_size=30M',
        'max_file_uploads=50',
        'memory_limit=256M',
    ];

    /**
     * Laravel decide cuántos procesos usar en initialize() (ANTES de handle()) y solo respeta PHP_CLI_SERVER_WORKERS si se usa --no-reload.
     * Antes la variable se ponía en handle(), demasiado tarde: el servidor siempre atendía de UNO en UNO y una petición pesada
     * (un guardado grande, una actualización) hacía esperar a la página y a los demás equipos. Aquí se activa en el momento correcto.
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Windows no permite varios procesos en el servidor de PHP; en Linux, Mac y Codespaces sí
        if (stripos(PHP_OS, 'WIN') !== 0) {
            if (empty($_ENV['PHP_CLI_SERVER_WORKERS']) && !getenv('PHP_CLI_SERVER_WORKERS')) {
                $_ENV['PHP_CLI_SERVER_WORKERS'] = '64';
                putenv('PHP_CLI_SERVER_WORKERS=64');
            }
            // Sin esto Laravel ignora los procesos extra (solo cambia que un cambio en .env pide reiniciar el sistema a mano)
            if ($input->hasOption('no-reload')) {
                $input->setOption('no-reload', true);
            }
        }

        // La conexión a la base de datos se mantiene abierta entre peticiones (con una base de datos en la nube, abrirla en cada
        // visita tardaba cientos de milisegundos). Solo se activa al encender el sistema con este comando.
        $_ENV['DB_PERSISTENT'] = 'true';
        putenv('DB_PERSISTENT=true');
        if (!in_array('DB_PERSISTENT', static::$passthroughVariables, true)) {
            static::$passthroughVariables[] = 'DB_PERSISTENT';
        }

        parent::initialize($input, $output);
    }

    public function handle()
    {
        // Configuración, rutas, pantallas y base de datos en un solo paso (solo repite lo que cambió) + despierta la base de datos
        // y llena las cachés ANTES de abrir: así la primera visita en el navegador ya es rápida
        $this->components->info('Preparando el sistema (despertando la base de datos)...');
        try {
            $this->callSilently('sica:preparar');
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
