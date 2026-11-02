<?php

namespace App\Http\Controllers;

use App\Models\Orientador;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MENSAJE_OLVIDO = '¿Olvidaste tu contraseña? Avisa a Control Escolar. Ellos borrarán tu contraseña anterior y generarán una nueva. Mientras tanto tu cuenta está bloqueada y no podrás entrar.';

    private const MENSAJE_BORRADA = 'Tu contraseña fue borrada. Pide a Control Escolar que te genere una nueva. Mientras tanto no podrás entrar.';

    /** Inicia sesión con correo y contraseña. Máximo 5 intentos fallidos por minuto (por correo + IP). */
    public function login(Request $request): JsonResponse
    {
        $credenciales = $request->validate([
            'email'    => 'required|email|max:150',
            'password' => 'required|string|max:200',
            'orientador' => 'nullable|string|max:150',   // nombre elegido en la lista (solo rol Orientador)
        ]);

        $nombreOrientador = $credenciales['orientador'] ?? null;
        unset($credenciales['orientador']);

        $credenciales['email'] = Str::lower(trim($credenciales['email']));
        $clave = $credenciales['email'] . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            $segundos = RateLimiter::availableIn($clave);

            return response()->json([
                'ok'      => false,
                'mensaje' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
            ], 429);
        }

        [$usuario, $bloqueo] = $this->usuarioValido($credenciales['email'], $credenciales['password'], $nombreOrientador);

        if ($bloqueo) {
            // Cuenta bloqueada (olvidó su contraseña o Control Escolar se la borró): no entra, ni con la contraseña correcta
            return response()->json([
                'ok'        => false,
                'bloqueada' => true,
                'motivo'    => $bloqueo,
                'mensaje'   => $bloqueo === 'olvido' ? self::MENSAJE_OLVIDO : self::MENSAJE_BORRADA,
            ], 422);
        }

        if (!$usuario) {
            RateLimiter::hit($clave, 60);

            return response()->json([
                'ok'      => false,
                'mensaje' => 'Correo o contraseña incorrectos.',
            ], 422);
        }

        RateLimiter::clear($clave);
        Auth::login($usuario, false);   // false = sin "recordarme": la sesión no queda abierta para siempre
        $request->session()->regenerate();

        return response()->json($this->datosUsuario($request));
    }

    /**
     * "¿Olvidaste tu contraseña?": bloquea la cuenta hasta que Control Escolar le genere una contraseña nueva.
     * - Director y Subdirector: se bloquea la cuenta del rol (y se cierran sus sesiones).
     * - Orientador: se bloquea solo ese orientador (por su nombre); los demás siguen entrando.
     * - Control Escolar nunca se bloquea desde aquí (nadie podría desbloquearlo): lo restablece quien administra el servidor.
     * Responde igual si la cuenta no existe, para no revelar qué cuentas hay. Límite: 5 peticiones por minuto.
     */
    public function olvide(Request $request): JsonResponse
    {
        $d = $request->validate([
            'email'      => 'required|email|max:150',
            'orientador' => 'nullable|string|max:150',
        ]);

        // Solo id y rol: no hace falta traer el hash de la contraseña para bloquear la cuenta
        $usuario = User::select('id', 'rol')->where('email', Str::lower(trim($d['email'])))->first();
        $bloqueada = ['ok' => true, 'bloqueada' => true, 'mensaje' => self::MENSAJE_OLVIDO];

        if (!$usuario) {
            return response()->json($bloqueada);
        }

        if ($usuario->rol === 'control') {
            return response()->json([
                'ok'        => true,
                'bloqueada' => false,
                'mensaje'   => 'La cuenta de Control Escolar no se bloquea desde aquí. Avisa a quien administra el sistema: debe ejecutar en el servidor php artisan sica:restablecer-claves para asignar una contraseña nueva. No se pierde ningún dato.',
            ]);
        }

        if ($usuario->rol === 'orientador') {
            if (empty($d['orientador'])) {
                return response()->json(['ok' => false, 'mensaje' => 'Selecciona tu nombre de la lista.'], 422);
            }

            // Un solo UPDATE (antes: buscar + guardar). Si ya estaba bloqueado no cambia la hora de la solicitud.
            DB::table('orientadores')
                ->where('nombre', $d['orientador'])
                ->whereNull('olvido_at')
                ->update(['olvido_at' => now()]);

            return response()->json($bloqueada);
        }

        DB::table('users')
            ->where('id', $usuario->id)
            ->whereNull('olvido_at')
            ->update(['olvido_at' => now()]);

        DB::table('sessions')->where('user_id', $usuario->id)->delete();

        return response()->json($bloqueada);
    }

    /** Cierra la sesión y descarta la sesión actual. */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'csrf' => csrf_token()]);
    }

    /** Devuelve quién tiene la sesión activa. Sin sesión responde 401 pero entrega un token CSRF nuevo. */
    public function me(Request $request): JsonResponse
    {
        if (!$request->user()) {
            return response()->json(['ok' => false, 'mensaje' => 'Sin sesión.', 'csrf' => csrf_token()], 401);
        }

        return response()->json($this->datosUsuario($request));
    }

    /**
     * Devuelve [cuenta o null, bloqueo].  bloqueo = null | 'olvido' | 'borrada'.
     * Rol Orientador: se pide el nombre y se compara con la clave PERSONAL de ese orientador;
     * si Control Escolar no le puso una, vale la contraseña general del rol.
     * 'olvido'  = la persona marcó "¿Olvidaste tu contraseña?" y Control Escolar aún no le genera una nueva.
     * 'borrada' = Control Escolar eliminó la contraseña (columna vacía) y aún no genera una nueva.
     * En ambos casos no se compara la contraseña escrita: no entra ni con la correcta.
     */
    private function usuarioValido(string $email, string $password, ?string $nombreOrientador): array
    {
        $usuario = User::where('email', $email)->first();

        if (!$usuario) {
            Hash::make($password);   // gasta el mismo tiempo para no revelar qué correos existen

            return [null, null];
        }

        $hash = (string) $usuario->getAuthPassword();
        $olvido = $usuario->rol !== 'control' && $usuario->olvido_at !== null;
        $orientador = null;

        if ($usuario->rol === 'orientador') {
            $orientador = $nombreOrientador ? Orientador::where('nombre', $nombreOrientador)->first() : null;

            if (!$orientador) {
                return [null, null];
            }

            $olvido = $orientador->olvido_at !== null;

            if ($orientador->password !== null) {
                $hash = (string) $orientador->password;   // personal (o '' si se la borraron)
            }
        }

        if ($olvido) {
            return [null, 'olvido'];
        }

        if ($hash === '') {
            return [null, 'borrada'];
        }

        if (!Hash::check($password, $hash)) {
            return [null, null];
        }

        // Si la contraseña se guardó con un costo distinto al actual (config/hashing.php), se vuelve a guardar con el actual:
        // así las siguientes veces que entre será más rápido. Va con DB::table para no pasar por el cast 'hashed' del modelo.
        if (Hash::needsRehash($hash)) {
            $nuevo = Hash::make($password);

            if ($usuario->rol === 'orientador' && $orientador && $orientador->password !== null) {
                DB::table('orientadores')->where('id', $orientador->id)->update(['password' => $nuevo]);
            } else {
                DB::table('users')->where('id', $usuario->id)->update(['password' => $nuevo]);
            }
        }

        return [$usuario, null];
    }

    private function datosUsuario(Request $request): array
    {
        $u = $request->user();

        return [
            'ok'     => true,
            'nombre' => $u->name,
            'email'  => $u->email,
            'rol'    => $u->rol,
            'csrf'   => csrf_token(),
        ];
    }
}