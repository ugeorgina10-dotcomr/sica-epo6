<?php

return [

    /*
    | Algoritmo de las contraseñas. Con rounds=10 guardar y revisar una contraseña tarda ~4 veces menos que con 12
    | (más rápido al entrar). Las contraseñas ya guardadas con otro costo se actualizan solas la próxima vez que
    | esa persona inicia sesión (ver AuthController::usuarioValido). Para volver al costo anterior: BCRYPT_ROUNDS=12 en .env
    */
    'driver' => 'bcrypt',

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 10),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
