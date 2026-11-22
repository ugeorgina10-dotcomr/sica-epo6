<?php

namespace App\Http\Middleware;

use App\Support\Pulso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Después de cualquier cambio que el servidor aceptó (guardar, escanear, borrar, subir una plantilla, desbloquear una cuenta...)
 * sube el "pulso" para que los demás equipos lo vean al instante (ver App\Support\Pulso).
 *
 * Uso en rutas:  ->middleware('pulso:sync')  (estado, y contraseñas si la acción toca orientadores)
 *                ->middleware('pulso:c')     (contraseñas y avisos)      ->middleware('pulso:p')  (plantillas)
 */
class PulsoTrasCambio
{
    /** Acciones de /api/sica/sync que también cambian la lista de orientadores / contraseñas */
    private const ACCIONES_CLAVES = ['guardar_todo', 'guardar_orientadores', 'eliminar_orientador'];

    public function handle(Request $request, Closure $next, string ...$canales): Response
    {
        $respuesta = $next($request);

        if ($request->isMethod('GET') || $respuesta->getStatusCode() >= 400) {
            return $respuesta;
        }

        if (in_array('sync', $canales, true)) {
            $canales = in_array((string) $request->input('accion'), self::ACCIONES_CLAVES, true) ? ['e', 'c'] : ['e'];
        }

        Pulso::tocar(...$canales);

        return $respuesta;
    }
}
