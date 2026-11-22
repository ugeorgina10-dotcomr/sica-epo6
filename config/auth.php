<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Valores normales de inicio de sesión
    |--------------------------------------------------------------------------
    |
    | Define cómo se revisa quién inicia sesión y cómo se restablecen las contraseñas.
    | Los valores actuales sirven para la mayoría de los sistemas.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Formas de iniciar sesión
    |--------------------------------------------------------------------------
    |
    | Aquí se define cómo se identifica a cada persona. Se usa la sesión del navegador y el modelo de usuarios.
    | Opción disponible: "session".
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Origen de los usuarios
    |--------------------------------------------------------------------------
    |
    | Indica de dónde se obtienen los usuarios (normalmente la base de datos).
    | Si hubiera varias tablas de usuarios, aquí se configuran. Opciones: "database", "eloquent".
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Restablecer contraseñas
    |--------------------------------------------------------------------------
    |
    | Configura cómo funciona el restablecimiento de contraseñas.
    | 'expire' son los minutos que dura cada código antes de caducar; 'throttle' son los segundos de espera antes de pedir otro.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Confirmación de contraseña
    |--------------------------------------------------------------------------
    |
    | Segundos que pasan antes de volver a pedir la contraseña para confirmar acciones importantes.
    | Por defecto son tres horas.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
