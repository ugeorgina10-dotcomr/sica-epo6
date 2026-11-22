<?php

namespace App\Providers;

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
        //
    }
}
