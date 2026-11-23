<?php

namespace App\Providers;

use App\Support\SesionDbLigera;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Aquí se registran servicios del sistema (por ahora no se usa).
     */
    public function register(): void
    {
        //
    }

    /**
     * Aquí se preparan servicios al arrancar el sistema (por ahora no se usa).
     */
    public function boot(): void
    {
        // Sesión en base de datos que no se reescribe en cada consulta si no cambió (menos viajes a la base de datos en la nube)
        Session::extend('database', function ($app) {
            $c = $app['config']['session'];

            return new SesionDbLigera(
                $app['db']->connection($c['connection']),
                $c['table'],
                $c['lifetime'],
                $app
            );
        });
    }
}
