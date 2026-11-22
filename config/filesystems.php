<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de archivos por defecto
    |--------------------------------------------------------------------------
    |
    | Indica dónde se guardan los archivos que maneja el sistema. "local" es el disco del servidor.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Discos de archivos
    |--------------------------------------------------------------------------
    |
    | Aquí se configuran los lugares donde se pueden guardar archivos (el servidor u otros servicios en la nube).
    | Opciones: "local", "ftp", "sftp", "s3".
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Accesos directos
    |--------------------------------------------------------------------------
    |
    | Enlaces que se crean al ejecutar el comando `storage:link` para que ciertos archivos se puedan ver desde internet.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
