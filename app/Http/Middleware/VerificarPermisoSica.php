<?php

namespace App\Http\Middleware;

use App\Models\Permiso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermisoSica
{
    /** Acciones que solo puede hacer Control Escolar (lo decide el servidor, no el navegador). */
    private const SOLO_CONTROL = ['guardar_permisos', 'guardar_orientadores', 'eliminar_orientador', 'eliminar_alumno', 'eliminar_lista_grupo', 'eliminar_listas_grupos'];

    /** Acciones que puede hacer cualquier rol que tenga permiso de "editar". */
    private const ACCIONES_EDITAR = ['registrar_asistencia', 'registrar_asistencias'];

    /** Permiso que tiene cada rol mientras Control Escolar no lo cambie. */
    private const PERMISO_DEFAULT = [
        'orientador'  => 'editar',
        'director'    => 'ver',
        'subdirector' => 'ver',
    ];

    /**
     * Cómo se usa en las rutas:
     *   ->middleware('sica:control')  solo Control Escolar
     *   ->middleware('sica:sync')     según la acción y el permiso del rol
     */
    public function handle(Request $request, Closure $next, string $modo = 'control'): Response
    {
        $usuario = $request->user();

        if (!$usuario) {
            return $this->denegar('Inicia sesión para continuar.', 401);
        }

        // Control Escolar puede hacer todo
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

        // Solo se permiten las acciones conocidas; cualquier otra se rechaza
        if (!in_array($accion, self::ACCIONES_EDITAR, true)) {
            return $this->denegar('Acción no permitida.');
        }

        // El orientador siempre puede escanear y descargar listas (ya no se configura en Permisos y Roles):
        // así un permiso 'ver' antiguo guardado en la base de datos no le impide registrar.
        $tipo = $usuario->rol === 'orientador'
            ? 'editar'
            : (Permiso::where('rol', $usuario->rol)->value('tipo') ?? (self::PERMISO_DEFAULT[$usuario->rol] ?? 'ver'));

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
