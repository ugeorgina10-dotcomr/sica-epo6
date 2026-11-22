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

        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');            // nadie puede mostrar el sistema dentro de otra página
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // La cámara solo se permite en este mismo sitio (para escanear credenciales)
        $respuesta->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // No se guarda en el navegador lo que cambia según la persona (páginas y datos), para que nadie vea información vieja o ajena
        if ($request->is('vendor/*', 'img/*', 'build/*')) {
            // Imágenes y librerías: el navegador las guarda 30 días para no descargarlas cada vez
            $respuesta->headers->set('Cache-Control', 'public, max-age=2592000, immutable');
        } else {
            $respuesta->headers->set('Cache-Control', 'no-cache, private');
        }

        return $respuesta;
    }
}
