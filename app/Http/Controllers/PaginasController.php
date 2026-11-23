<?php

namespace App\Http\Controllers;

/**
 * Las pantallas del sistema. Antes eran funciones dentro de routes/web.php; al estar aquí, Laravel puede dejar
 * todas las rutas preparadas de antemano (route:cache) y cada visita arranca más rápido.
 */
class PaginasController extends Controller
{
    public function inicio()
    {
        $u = auth()->user();

        return view('sica.index', [
            // Se incluye dentro de la página para que al abrirla no haga falta pedir esos datos por separado
            'sesion' => $u ? ['rol' => $u->rol, 'nombre' => $u->name, 'email' => $u->email] : null,
            'orientadoresLogin' => ControlEscolarController::orientadoresLogin(),
            // Si ya inició sesión, todos los datos van dentro de la página: al recargar entra sin esperar
            'estado' => $u ? app(ControlEscolarController::class)->paraPagina() : null,
            // Control Escolar: el estado de las contraseñas también viene en la página (el panel abre ya con todo listo)
            'claves' => ($u && $u->rol === 'control')
                ? ['etag' => ClavesController::etag($c = ClavesController::datos()), 'datos' => $c]
                : null,
        ]);
    }

    public function login()
    {
        return view('sica.dashboard');
    }

    public function lector()
    {
        return view('sica.lector');
    }

    public function controlEscolar()
    {
        return view('sica.control_escolar');
    }

    /** Solo nombres de orientadores, para llenar la lista del inicio de sesión (sin alumnos, CURP ni asistencias). */
    public function orientadores()
    {
        return ControlEscolarController::orientadoresLogin();
    }
}
