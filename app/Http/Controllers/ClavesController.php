<?php

namespace App\Http\Controllers;

use App\Models\Orientador;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Contraseñas administradas por Control Escolar (todas las rutas pasan por el guardia sica:control).
 *  - clave de cada rol (director, subdirector, orientador general y la propia de Control Escolar)
 *  - clave personal de cada orientador (si no tiene, entra con la general del rol Orientador)
 * Control Escolar es quien genera y entrega las contraseñas; una cuenta bloqueada por "olvidé mi contraseña"
 * se desbloquea al generarle una nueva (o al desbloquearla sin cambiarla).
 * Las claves se guardan siempre con hash (bcrypt); nunca se devuelven ni se guardan en texto plano.
 */
class ClavesController extends Controller
{
    private const ROLES = ['control', 'director', 'subdirector', 'orientador'];

    public const MENSAJES = [
        'password.required'  => 'Escribe la contraseña nueva.',
        'password.min'       => 'La contraseña debe tener al menos 8 caracteres.',
        'password.max'       => 'La contraseña no puede tener más de 72 caracteres.',
        'password.confirmed' => 'Las dos contraseñas no coinciden.',
    ];

    /**
     * Reglas de una contraseña nueva (las mismas indicaciones que muestra la pantalla):
     * 8 a 72 caracteres, mayúscula, minúscula, número, solo letras y números (sin caracteres especiales), sin espacios y sin usar el nombre de la escuela ni la clave inicial.
     */
    public static function reglasClave(): array
    {
        return [
            'required', 'string', 'min:8', 'max:72', 'confirmed',
            function (string $atributo, mixed $valor, \Closure $fallar) {
                $valor = (string) $valor;
                if (preg_match('/\s/u', $valor)) {
                    $fallar('La contraseña no puede llevar espacios.');
                } elseif (!preg_match('/^[A-Za-z0-9]+$/', $valor)) {
                    $fallar('La contraseña solo puede llevar letras (A-Z) y números; no uses caracteres especiales ni acentos.');
                } elseif (!preg_match('/\p{Lu}/u', $valor) || !preg_match('/\p{Ll}/u', $valor)) {
                    $fallar('La contraseña debe llevar al menos una letra mayúscula y una minúscula.');
                } elseif (!preg_match('/\d/', $valor)) {
                    $fallar('La contraseña debe llevar al menos un número.');
                } elseif (preg_match('/prepa0?6|epo0?6/i', $valor)) {
                    $fallar('No uses el nombre de la escuela ni la contraseña inicial (prepa06, epo6).');
                }
            },
        ];
    }

    /**
     * Estado de las contraseñas (nunca la clave ni su hash).
     * Roles: borrada = sin contraseña; bloqueada = la persona marcó "olvidé mi contraseña".
     * Orientadores: estado propia (personal) | general (usa la del rol) | borrada; bloqueada igual que en los roles.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $datos = [
            'ok' => true,
            'roles' => User::select('rol', 'password', 'olvido_at')
                ->whereIn('rol', ['director', 'subdirector', 'orientador'])->get()
                ->map(fn ($u) => [
                    'rol'       => $u->rol,
                    'borrada'   => (string) $u->getAuthPassword() === '',
                    'bloqueada' => $u->olvido_at !== null,
                    'olvido_at' => $u->olvido_at ? (string) $u->olvido_at : null,
                ])
                ->values(),
            'orientadores' => Orientador::select('id', 'nombre', 'password', 'olvido_at')->orderBy('nombre')->get()
                ->map(fn ($o) => [
                    'id'        => (string) $o->id,
                    'nombre'    => $o->nombre,
                    'estado'    => $o->password === null ? 'general' : ($o->password === '' ? 'borrada' : 'propia'),
                    'bloqueada' => $o->olvido_at !== null,
                    'olvido_at' => $o->olvido_at ? (string) $o->olvido_at : null,
                ])
                ->values(),
        ];

        // ETag: Control Escolar consulta esto cada pocos segundos para ver quién pulsó "¿Olvidaste tu contraseña?".
        // Si nada cambió se responde 304 (sin cuerpo) y la pantalla no se vuelve a dibujar.
        $etag = '"' . md5(json_encode($datos)) . '"';

        if (in_array($request->header('If-None-Match'), [$etag, 'W/' . $etag], true)) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json($datos)->header('ETag', $etag);
    }

    /** Cambia la contraseña de la cuenta de un rol. */
    public function rol(Request $request): JsonResponse
    {
        $d = $request->validate([
            'rol'             => ['required', Rule::in(self::ROLES)],
            'password'        => self::reglasClave(),
            'password_actual' => ['nullable', 'string', 'max:200'],
        ], self::MENSAJES);

        $usuario = User::where('rol', $d['rol'])->first();

        if (!$usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No existe la cuenta de ese rol.'], 404);
        }

        // Para cambiar su propia clave, Control Escolar confirma primero la actual
        if ($d['rol'] === 'control' && !Hash::check($d['password_actual'] ?? '', $usuario->getAuthPassword())) {
            return response()->json(['ok' => false, 'mensaje' => 'La contraseña actual no es correcta.'], 422);
        }

        if ($this->laUsaOtraCuenta($d['password'], $usuario->id)) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa contraseña ya la usa otro rol. Elige una distinta.'], 422);
        }

        $usuario->password = $d['password'];   // el modelo la guarda con hash
        $usuario->olvido_at = null;            // con contraseña nueva, la cuenta vuelve a tener acceso
        $usuario->save();

        // Cierra las sesiones abiertas de esa cuenta (en Control Escolar, menos la actual)
        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->when($d['rol'] === 'control', fn ($q) => $q->where('id', '!=', $request->session()->getId()))
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Pone o cambia la clave propia de un orientador. */
    public function orientador(Request $request): JsonResponse
    {
        $d = $request->validate([
            'orientador_id' => ['required', 'integer', 'exists:orientadores,id'],
            'password'      => self::reglasClave(),
        ], self::MENSAJES);

        if ($this->laUsaOtraCuenta($d['password'], null)) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa contraseña ya la usa un rol. Elige una distinta.'], 422);
        }

        $o = Orientador::findOrFail($d['orientador_id']);
        $o->password = $d['password'];
        $o->olvido_at = null;   // con contraseña nueva, vuelve a tener acceso
        $o->save();

        return response()->json(['ok' => true]);
    }

    /** Quita la clave personal: el orientador vuelve a entrar con la contraseña general del rol Orientador (y se desbloquea). */
    public function quitarOrientador(Orientador $orientador): JsonResponse
    {
        $orientador->password = null;
        $orientador->olvido_at = null;
        $orientador->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Control Escolar borra la contraseña guardada de una cuenta (cuando alguien la olvidó).
     * La columna queda vacía: nadie entra con esa cuenta hasta que Control Escolar genere una nueva.
     * Solo se borra la contraseña (y las sesiones de esa cuenta); no se toca ningún otro dato.
     * La de Control Escolar no se puede borrar desde aquí (se quedaría sin acceso): usa  php artisan sica:restablecer-claves
     */
    public function borrarRol(Request $request, string $rol): JsonResponse
    {
        if (!in_array($rol, ['director', 'subdirector', 'orientador'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa cuenta no se puede borrar desde aquí.'], 422);
        }

        $usuario = User::where('rol', $rol)->first();

        if (!$usuario) {
            return response()->json(['ok' => false, 'mensaje' => 'No existe la cuenta de ese rol.'], 404);
        }

        DB::table('users')->where('id', $usuario->id)->update(['password' => '', 'remember_token' => null]);
        DB::table('sessions')->where('user_id', $usuario->id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Borra la contraseña de un orientador: no podrá entrar (ni con la general) hasta que le generen una nueva. */
    public function borrarOrientador(Orientador $orientador): JsonResponse
    {
        DB::table('orientadores')->where('id', $orientador->id)->update(['password' => '']);

        return response()->json(['ok' => true]);
    }

    /**
     * Quita el bloqueo de "olvidé mi contraseña" SIN cambiar la contraseña
     * (por ejemplo, si alguien marcó el bloqueo por error o por broma). Si la contraseña está borrada, sigue sin entrar
     * hasta que se genere una nueva.
     */
    public function desbloquearRol(string $rol): JsonResponse
    {
        if (!in_array($rol, ['director', 'subdirector', 'orientador'], true)) {
            return response()->json(['ok' => false, 'mensaje' => 'Esa cuenta no se bloquea.'], 422);
        }

        DB::table('users')->where('rol', $rol)->update(['olvido_at' => null]);

        return response()->json(['ok' => true]);
    }

    public function desbloquearOrientador(Orientador $orientador): JsonResponse
    {
        DB::table('orientadores')->where('id', $orientador->id)->update(['olvido_at' => null]);

        return response()->json(['ok' => true]);
    }

    /** ¿La contraseña nueva ya es la de otra cuenta de rol? (se compara contra sus hashes) */
    private function laUsaOtraCuenta(string $password, ?int $exceptoUserId): bool
    {
        // Solo id y hash, y sin cuentas vacías (borradas): cada Hash::check cuesta ~0.2 s, así que no se gasta en vano
        return User::when($exceptoUserId, fn ($q) => $q->where('id', '!=', $exceptoUserId))
            ->where('password', '!=', '')
            ->get(['id', 'password'])
            ->contains(fn ($u) => Hash::check($password, $u->getAuthPassword()));
    }
}
