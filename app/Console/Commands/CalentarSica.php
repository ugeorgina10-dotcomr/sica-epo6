<?php

namespace App\Console\Commands;

use App\Http\Controllers\ControlEscolarController;
use Illuminate\Console\Command;

/**
 * Deja el sistema "caliente" antes de que llegue la primera persona: despierta la base de datos (la de la nube se duerme sola
 * tras unos minutos sin uso y la primera consulta tardaba segundos) y prepara las listas guardadas en memoria.
 * Se ejecuta solo al encender el sistema; también se puede usar a mano:  php artisan sica:calentar
 */
class CalentarSica extends Command
{
    protected $signature = 'sica:calentar';

    protected $description = 'Despierta la base de datos y deja listas las cachés para que la primera visita sea rápida';

    public function handle(): int
    {
        $inicio = microtime(true);

        try {
            app(ControlEscolarController::class)->calentar();
        } catch (\Throwable $e) {
            // Si la base de datos no responde no pasa nada: el sistema abre igual y se prepara con el primer uso
            $this->warn('No se pudo preparar todo (' . $e->getMessage() . '). El sistema abrirá igual.');

            return self::SUCCESS;
        }

        $this->info('Sistema listo en ' . number_format((microtime(true) - $inicio) * 1000) . ' ms.');

        return self::SUCCESS;
    }
}
