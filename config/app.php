<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nombre del sistema
    |--------------------------------------------------------------------------
    |
    | Es el nombre del sistema. Se muestra en avisos y en otros lugares donde haga falta.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    |
    | Indica si el sistema está en tu computadora (local) o ya publicado (production).
    | Se cambia en el archivo .env.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Modo de depuración (debug)
    |--------------------------------------------------------------------------
    |
    | Si está activo, cuando hay un error se muestran detalles técnicos.
    | Si está apagado, se muestra una página de error sencilla. En el sitio publicado debe estar apagado.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Dirección del sitio
    |--------------------------------------------------------------------------
    |
    | Dirección principal del sistema. Se usa al ejecutar comandos para armar enlaces correctos.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria
    |--------------------------------------------------------------------------
    |
    | Zona horaria del sistema. Se usa en todas las fechas y horas.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Idioma
    |--------------------------------------------------------------------------
    |
    | Idioma que usa el sistema por defecto.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Clave secreta (APP_KEY)
    |--------------------------------------------------------------------------
    |
    | Clave con la que el sistema protege los datos. Debe ser aleatoria y se genera antes de publicar el sistema.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo mantenimiento
    |--------------------------------------------------------------------------
    |
    | Controla cómo se activa el "modo mantenimiento" (el sitio se pausa mientras se hacen cambios).
    | Opciones: "file" (archivo) o "cache" (útil si hay varios servidores).
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
