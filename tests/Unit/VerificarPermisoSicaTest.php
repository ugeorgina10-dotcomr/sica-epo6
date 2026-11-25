<?php

namespace Tests\Unit;

use App\Http\Middleware\VerificarPermisoSica;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * CB-09: el middleware de permisos, llamado sin usuario en la petición, responde 401 y no deja pasar.
 */
class VerificarPermisoSicaTest extends TestCase
{
    public function test_sin_usuario_responde_401(): void
    {
        $llegoAlControlador = false;
        $siguiente = function () use (&$llegoAlControlador) {
            $llegoAlControlador = true;

            return response()->json(['ok' => true]);
        };

        // Petición sin usuario (nadie inició sesión), en los dos modos de uso de las rutas: sica:control y sica:sync
        foreach (['control', 'sync'] as $modo) {
            $peticion = Request::create('/api/sica/sync', 'POST', ['accion' => 'registrar_asistencia']);

            $respuesta = (new VerificarPermisoSica())->handle($peticion, $siguiente, $modo);

            $this->assertSame(401, $respuesta->getStatusCode());
            $this->assertSame('Inicia sesión para continuar.', $respuesta->getData()->mensaje);
            $this->assertFalse($respuesta->getData()->ok);
        }

        $this->assertFalse($llegoAlControlador, 'Sin usuario, la petición no debe llegar al controlador.');
    }
}
