<?php

namespace App\Console\Commands;

use App\Http\Controllers\ControlEscolarController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

/**
 * Deja el sistema listo para encender, TODO en un solo arranque de Laravel.
 * Antes los scripts de inicio lanzaban 4 comandos seguidos (config:cache, view:cache, migrate y sica:calentar) y cada uno
 * volvía a arrancar Laravel. Además ahora solo se repite lo que de verdad cambió:
 *  - configuración: siempre (es lo más barato y así toma los cambios del .env);
 *  - rutas y pantallas: solo si cambiaron sus archivos (compilar las pantallas era lo más lento del arranque);
 *  - base de datos (migrate): solo si hay migraciones nuevas o cambió la base de datos a la que se conecta.
 * Uso:  php artisan sica:preparar   (con --forzar repite todo)
 */
class PrepararSica extends Command
{
    protected $signature = 'sica:preparar {--forzar : Repite todo aunque no haya cambios}';

    protected $description = 'Prepara configuración, rutas, pantallas y base de datos en un solo paso (rápido)';

    public function handle(): int
    {
        $inicio = microtime(true);
        $marca = storage_path('framework/sica-preparado.json');
        $antes = $this->option('forzar') ? [] : (json_decode((string) @file_get_contents($marca), true) ?: []);
        $ahora = $antes;

        // 1) Configuración (siempre: toma los cambios del .env)
        try {
            $this->callSilently('config:cache');
        } catch (\Throwable $e) {
            $this->error('No se pudo preparar la configuración: ' . $e->getMessage());

            return self::FAILURE;
        }

        // 2) Rutas (solo si cambió el archivo de rutas o falta la copia preparada)
        $huellaRutas = $this->huella([base_path('routes')], ['php'], [base_path('bootstrap/app.php')]);
        if (($antes['rutas'] ?? null) !== $huellaRutas || !is_file(base_path('bootstrap/cache/routes-v7.php'))) {
            try {
                $this->callSilently('route:cache');
                $ahora['rutas'] = $huellaRutas;
            } catch (\Throwable $e) {
                $this->callSilently('route:clear');   // si alguna ruta no se puede preparar, el sistema abre igual (más lento)
                unset($ahora['rutas']);
            }
        }

        // 3) Pantallas (solo si cambiaron)
        $huellaVistas = $this->huella([resource_path('views')], ['php']);
        if (($antes['vistas'] ?? null) !== $huellaVistas || !glob(storage_path('framework/views/*.php'))) {
            try {
                $this->callSilently('view:cache');
                $ahora['vistas'] = $huellaVistas;
            } catch (\Throwable $e) {
                unset($ahora['vistas']);
            }
        }

        // 4) Base de datos: mejoras pendientes + despertarla (la de la nube se duerme sola) y llenar las listas en memoria
        $conexion = config('database.default');
        $huellaBd = md5(json_encode([
            $conexion,
            config("database.connections.$conexion.url"),
            config("database.connections.$conexion.host"),
            config("database.connections.$conexion.database"),
            $this->huella([database_path('migrations')], ['php']),
        ]));

        try {
            if (($antes['bd'] ?? null) !== $huellaBd || !Schema::hasTable('grupos')) {
                $this->callSilently('migrate', ['--force' => true]);
                $ahora['bd'] = $huellaBd;
            }
            app(ControlEscolarController::class)->calentar();
        } catch (\Throwable $e) {
            // Si la base de datos no responde no pasa nada: el sistema abre igual y se prepara con el primer uso
            unset($ahora['bd']);
            $this->warn('No se pudo preparar la base de datos (' . $e->getMessage() . '). El sistema abrirá igual.');
        }

        if ($ahora !== $antes) {
            @file_put_contents($marca, json_encode($ahora));
        }

        $this->info('Sistema listo en ' . number_format((microtime(true) - $inicio) * 1000) . ' ms.');

        return self::SUCCESS;
    }

    /** Resumen de nombre, tamaño y fecha de los archivos: cambia si se edita, agrega o quita alguno. */
    private function huella(array $carpetas, array $extensiones, array $extras = []): string
    {
        $partes = [];
        foreach ($extras as $f) {
            if (is_file($f)) {
                $partes[] = $f . filesize($f) . filemtime($f);
            }
        }
        $buscador = Finder::create()->files()->in(array_filter($carpetas, 'is_dir'))->name(array_map(fn ($e) => "*.$e", $extensiones));
        foreach ($buscador as $f) {
            $partes[] = $f->getPathname() . $f->getSize() . $f->getMTime();
        }
        sort($partes);

        return md5(implode('|', $partes));
    }
}
