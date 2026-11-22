<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\VerificarPermisoSica;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Si el sistema está detrás de otro servidor (como en Render), confía en él para saber si la conexión es segura (HTTPS)
        // y para conocer la IP real de cada persona (se usa para limitar los intentos de inicio de sesión).
        $middleware->trustProxies(at: '*');

        // Protecciones de seguridad en todas las respuestas
        $middleware->append(CabecerasSeguridad::class);

        // Si alguien sin sesión pide una página, se le manda al inicio (ahí está el inicio de sesión)
        $middleware->redirectGuestsTo('/');

        // Revisión de permisos por rol: sica:control / sica:sync
        $middleware->alias([
            'sica' => VerificarPermisoSica::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los datos (/api/*) y el inicio de sesión (/auth/*) siempre responden con mensajes de error y nunca mandan a otra página
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*', 'auth/*') || $request->expectsJson()
        );
    })->create();
