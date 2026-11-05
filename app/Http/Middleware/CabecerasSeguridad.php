<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CabecerasSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');            // nadie puede incrustar el sistema en otra página
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara solo se permite en este mismo sitio (escaneo de credenciales)
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Nunca guardar en caché del navegador lo que cambia por sesión (páginas y API)
        if ($request->is('vendor/*', 'img/*', 'build/*')) {
            // Archivos estáticos (librerías, imágenes): el navegador los reutiliza 30 días sin volver a pedirlos
            $respuesta->headers->set('Cache-Control', 'public, max-age=2592000, immutable');
        } else {
            $respuesta->headers->set('Cache-Control', 'no-cache, private');
        }

        return $respuesta;
    }
}
