<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Dónde se guardan las sesiones
    |--------------------------------------------------------------------------
    |
    | Indica dónde se guarda quién tiene la sesión abierta.
    | Opciones: "file", "cookie", "database", "memcached", "redis", "dynamodb", "array". La base de datos es una buena opción.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Duración de la sesión
    |--------------------------------------------------------------------------
    |
    | Minutos que puede estar la sesión sin usarse antes de cerrarse.
    | Con expire_on_close la sesión se cierra al cerrar el navegador.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Cifrado de la sesión
    |--------------------------------------------------------------------------
    |
    | Si se activa, los datos de la sesión se guardan cifrados.
    | Laravel lo hace solo; no cambia tu forma de usar el sistema.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Carpeta de las sesiones
    |--------------------------------------------------------------------------
    |
    | Si las sesiones se guardan en archivos ("file"), aquí se indica la carpeta.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Conexión de las sesiones
    |--------------------------------------------------------------------------
    |
    | Si las sesiones se guardan en la base de datos o en Redis, aquí se indica qué conexión usar.
    | Debe existir en la configuración de la base de datos.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Tabla de las sesiones
    |--------------------------------------------------------------------------
    |
    | Tabla donde se guardan las sesiones cuando se usa la base de datos. El nombre normal ya viene puesto.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Caché de las sesiones
    |--------------------------------------------------------------------------
    |
    | Si las sesiones usan la caché, aquí se indica cuál. Debe ser una de las definidas en la configuración de caché.
    | Aplica a: "dynamodb", "memcached", "redis".
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Limpieza de sesiones viejas
    |--------------------------------------------------------------------------
    |
    | Algunos tipos de almacenamiento necesitan borrar de vez en cuando las sesiones viejas.
    | Aquí se define la probabilidad de que ocurra en cada visita; por defecto, 2 de cada 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Nombre de la cookie
    |--------------------------------------------------------------------------
    |
    | Nombre de la cookie (un pequeño archivo del navegador) que guarda la sesión. Normalmente no hace falta cambiarlo.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Ruta de la cookie
    |--------------------------------------------------------------------------
    |
    | Parte del sitio donde la cookie está disponible. Normalmente es toda la aplicación (/).
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Dominio de la cookie
    |--------------------------------------------------------------------------
    |
    | Dominio donde la cookie está disponible. Normalmente no se cambia.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Cookies solo con HTTPS
    |--------------------------------------------------------------------------
    |
    | Si es true, la cookie solo se envía en conexiones seguras (HTTPS). En Render debe ir en true.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | Cookies solo para el navegador
    |--------------------------------------------------------------------------
    |
    | Si es true, JavaScript no puede leer la cookie. Casi nunca conviene desactivarlo.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Cookies del mismo sitio (Same-Site)
    |--------------------------------------------------------------------------
    |
    | Controla cómo se comportan las cookies cuando se piden desde otros sitios; ayuda a evitar ataques.
    | Por defecto es "lax". Más información: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    | Opciones: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Cookies particionadas
    |--------------------------------------------------------------------------
    |
    | Si es true, la cookie queda ligada al sitio principal cuando se usa desde otro sitio.
    | El navegador las acepta si son "secure" y Same-Site está en "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
