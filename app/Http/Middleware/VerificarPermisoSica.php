<?php

namespace App\Http\Middleware;

use App\Models\Permiso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermisoSica
{
    /** Acciones de /api/sica/sync que SOLO puede hacer Control Escolar (el servidor manda, no el navegador). */
    private const SOLO_CONTROL = ['guardar_permisos', 'guardar_orientadores', 'eliminar_orientador', 'eliminar_alumno', 'eliminar_lista_grupo'];

    /** Acciones que cualquier rol con permiso "editar" puede hacer. */
    private const ACCIONES_EDITAR = ['guardar_todo', 'registrar_asistencia'];

    /** Permiso por defecto de cada rol si Control Escolar aún no lo cambió. */
    private const PERMISO_DEFAULT = [
        'orientador'  => 'editar',
        'director'    => 'ver',
        'subdirector' => 'ver',
    ];

    /**
     * Uso en rutas:
     *   ->middleware('sica:control')  solo Control Escolar
     *   ->middleware('sica:sync')     según la acción y el permiso del rol
     */
    public function handle(Request $request, Closure $next, string $modo = 'control'): Response
    {
        $usuario = $request->user();

        if (!$usuario) {
            return $this->denegar('Inicia sesión para continuar.', 401);
        }

        // Control Escolar puede todo
        if ($usuario->rol === 'control') {
            return $next($request);
        }

        if ($modo === 'control') {
            return $this->denegar('Solo Control Escolar puede hacer esto.');
        }

        $accion = (string) $request->input('accion');

        if (in_array($accion, self::SOLO_CONTROL, true)) {
            return $this->denegar('Solo Control Escolar puede hacer esto.');
        }

        // Lista blanca: cualquier acción desconocida se rechaza
        if (!in_array($accion, self::ACCIONES_EDITAR, true)) {
            return $this->denegar('Acción no permitida.');
        }

        $tipo = Permiso::where('rol', $usuario->rol)->value('tipo')
            ?? (self::PERMISO_DEFAULT[$usuario->rol] ?? 'ver');

        if ($tipo !== 'editar') {
            return $this->denegar('Tu rol solo tiene permiso de visualización.');
        }

        return $next($request);
    }

    private function denegar(string $mensaje, int $codigo = 403): Response
    {
        return response()->json(['ok' => false, 'mensaje' => $mensaje], $codigo);
    }
}
