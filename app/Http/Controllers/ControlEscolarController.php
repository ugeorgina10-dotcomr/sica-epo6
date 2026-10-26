<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\Orientador;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ControlEscolarController extends Controller
{
    /**
     * Único punto de entrada que ya usa el frontend (sincronizarConServidor() en el JS).
     * Recibe { accion, datos } y decide qué hacer según la acción.
     */
    public function sync(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'accion' => 'required|string',
            'datos'  => 'required|array',
        ]);

        $esControl = $request->user()?->rol === 'control';

        // Cualquier escritura invalida la huella: los demás equipos ven el cambio en su próxima consulta
        $respuesta = $this->ejecutarAccion($validado, $esControl);
        Cache::forget('sica.huella');

        return $respuesta;
    }

    private function ejecutarAccion(array $validado, bool $esControl): JsonResponse
    {
        return match ($validado['accion']) {
            'guardar_permisos'     => $this->guardarPermisos($validado['datos']),
            // Solo Control Escolar puede crear/cambiar alumnos, horarios, orientadores y plantillas.
            // Los demás roles con permiso "editar" únicamente pueden guardar asistencias.
            'guardar_todo'         => $this->guardarTodo($validado['datos'], !$esControl),
            'registrar_asistencia' => $this->registrarAsistencia($validado['datos']),
            'eliminar_alumno'      => $this->eliminarAlumno($validado['datos']),
            'guardar_orientadores' => $this->guardarSoloOrientadores($validado['datos']),
            'eliminar_orientador'  => $this->eliminarOrientador($validado['datos']),
            default                => response()->json(['ok' => false, 'mensaje' => 'Acción no reconocida'], 400),
        };
    }

    /**
     * Guarda la plantilla oficial (frente o reverso) de un turno EN LA BASE DE DATOS.
     * La plantilla es un archivo de Word (.docx) o un PDF; el navegador lo lee y escribe encima los datos del alumno.
     */
    public function plantillaOficial(Request $request, string $lado): JsonResponse
    {
        abort_unless(in_array($lado, ['frente', 'reverso'], true), 404);

        $request->validate([
            'archivo' => 'required|file|max:10240|extensions:docx,pdf',
            'turno'   => 'required|in:Matutino,Vespertino',
        ], [
            'archivo.extensions' => 'La plantilla debe ser un Word (.docx) o un PDF.',
            'archivo.max'        => 'El archivo pesa demasiado (máximo 10 MB).',
        ]);

        $ruta = $request->file('archivo')->getRealPath();

        $contenido = file_get_contents($ruta);

        if (str_starts_with($contenido, '%PDF-')) {
            // PDF: se reconoce por su encabezado
        } elseif (class_exists(\ZipArchive::class)) {
            // Un .docx es un zip que contiene word/document.xml: así no se guarda cualquier otro archivo
            $zip = new \ZipArchive();
            $ok  = $zip->open($ruta) === true && $zip->locateName('word/document.xml') !== false;
            $zip->close();
            if (!$ok) {
                return response()->json(['ok' => false, 'message' => 'El archivo no es un Word (.docx) ni un PDF válido.'], 422);
            }
        }

        $turno = $request->input('turno');
        Ajuste::updateOrCreate(
            ['clave' => self::clavePlantilla($turno, $lado)],
            ['valor' => base64_encode($contenido)]
        );
        // Las plantillas anteriores eran imágenes (.png): ya no se usan
        Ajuste::where('clave', self::clavePlantillaImagenVieja($turno, $lado))->delete();

        return response()->json(['ok' => true]);
    }

    /** Sirve la plantilla (Word o PDF) guardada en la BD; si no hay, 404 y la página usa la que viene en public/plantillas */
    public function verPlantillaOficial(string $turno, string $lado)
    {
        abort_unless(in_array($turno, ['Matutino', 'Vespertino'], true) && in_array($lado, ['frente', 'reverso'], true), 404);

        $a = Ajuste::find(self::clavePlantilla($turno, $lado));
        abort_unless($a && $a->valor, 404);

        $bytes = base64_decode($a->valor);
        $tipo  = str_starts_with($bytes, '%PDF-')
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $respuesta = response($bytes, 200, [
            'Content-Type'  => $tipo,
            'Cache-Control' => 'no-cache',
        ]);
        $respuesta->setEtag(md5($a->valor));
        $respuesta->isNotModified(request());   // 304 si el equipo ya tiene esta plantilla

        return $respuesta;
    }

    /** Vuelve a la plantilla original (la de public/img) */
    public function borrarPlantillaOficial(string $turno, string $lado): JsonResponse
    {
        abort_unless(in_array($turno, ['Matutino', 'Vespertino'], true) && in_array($lado, ['frente', 'reverso'], true), 404);
        Ajuste::whereIn('clave', [self::clavePlantilla($turno, $lado), self::clavePlantillaImagenVieja($turno, $lado)])->delete();

        return response()->json(['ok' => true]);
    }

    /** Archivos de Word originales que vienen en public/plantillas (el reverso es el mismo para los dos turnos) */
    private const PLANTILLAS_ORIGINALES = [
        'Matutino'   => ['frente' => 'FrenteMatutino.docx',   'reverso' => 'Reverso.docx'],
        'Vespertino' => ['frente' => 'FrenteVespertino.docx', 'reverso' => 'Reverso.docx'],
    ];

    /**
     * Elimina PERMANENTEMENTE la plantilla anterior (el Word original de public/plantillas).
     * Solo se permite cuando ya hay una plantilla nueva guardada en el servidor para ese turno y lado,
     * así nunca se queda el sistema sin plantilla. Después ya no se puede "restaurar la original".
     */
    public function eliminarPlantillaAnterior(string $turno, string $lado): JsonResponse
    {
        abort_unless(in_array($turno, ['Matutino', 'Vespertino'], true) && in_array($lado, ['frente', 'reverso'], true), 404);

        if (!Ajuste::where('clave', self::clavePlantilla($turno, $lado))->exists()) {
            return response()->json([
                'ok'      => false,
                'mensaje' => 'Primero sube la plantilla nueva (' . $lado . ' ' . $turno . '). La anterior solo se puede eliminar cuando la nueva ya está en uso.',
            ], 422);
        }

        // El reverso lo usan los dos turnos: solo se borra si los dos ya tienen su reverso nuevo
        if ($lado === 'reverso') {
            foreach (['Matutino', 'Vespertino'] as $t) {
                if (!Ajuste::where('clave', self::clavePlantilla($t, 'reverso'))->exists()) {
                    return response()->json([
                        'ok'      => false,
                        'mensaje' => 'El reverso anterior lo usan los dos turnos. Sube también el reverso nuevo del turno ' . $t . ' (o marca "Usar este reverso en los dos turnos") y vuelve a intentarlo.',
                    ], 422);
                }
            }
        }

        $archivo = self::PLANTILLAS_ORIGINALES[$turno][$lado];
        $ruta    = public_path('plantillas/' . $archivo);
        $borrado = false;

        if (is_file($ruta)) {
            $borrado = @unlink($ruta);
            if (!$borrado) {
                return response()->json([
                    'ok'      => false,
                    'mensaje' => 'No se pudo borrar ' . $archivo . ' (el servidor no tiene permiso sobre la carpeta public/plantillas).',
                ], 500);
            }
        }

        // También se limpia cualquier plantilla en imagen que haya quedado guardada de versiones viejas
        Ajuste::where('clave', self::clavePlantillaImagenVieja($turno, $lado))->delete();

        return response()->json(['ok' => true, 'archivo' => $archivo, 'borrado' => $borrado]);
    }

    private static function clavePlantilla(string $turno, string $lado): string
    {
        return 'plantilla_docx_' . strtolower($turno) . '_' . $lado;
    }

    /** Clave que usaban las plantillas en imagen (ya eliminadas) */
    private static function clavePlantillaImagenVieja(string $turno, string $lado): string
    {
        return 'plantilla_' . strtolower($turno) . '_' . $lado;
    }

    /**
     * Lo que manda el teléfono (o la computadora) en cada escaneo.
     *
     * El alumno se busca por CURP en TODA la base, así que cualquier orientador con permiso puede
     * escanear a cualquier alumno: el registro queda en el grupo del alumno, no en el del que escanea.
     *
     * La fecha y la hora las pone el SERVIDOR (zona America/Mexico_City): así nadie puede falsear
     * una entrada cambiando la hora de su teléfono. ÚNICA excepción: un escaneo "diferido" (hecho sin
     * internet y enviado después) trae la hora en que se escaneó; solo se acepta si no es futura
     * (tolerancia de 2 minutos) y no tiene más de 3 días. Si no cumple, se usa la hora del servidor.
     */
    private function registrarAsistencia(array $d): JsonResponse
    {
        $v = validator($d, [
            'curp'     => 'required|string|max:30',
            'modo'     => 'nullable|in:Entrada,Salida',
            'diferido' => 'nullable|boolean',
            'fecha'    => 'nullable|date_format:Y-m-d',
            'hora'     => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Datos de escaneo inválidos'], 422);
        }

        $alumno = Alumno::select('id', 'nombre', 'grupo_id')->where('matricula', $d['curp'])->first();
        if (!$alumno) {
            return response()->json(['ok' => false, 'mensaje' => 'Alumno no encontrado'], 404);
        }

        $ahora = now();

        if (!empty($d['diferido']) && !empty($d['fecha']) && !empty($d['hora'])) {
            $escaneo = Carbon::createFromFormat('Y-m-d H:i', $d['fecha'] . ' ' . $d['hora'], config('app.timezone'));
            $limiteFuturo = $ahora->copy()->addMinutes(2);
            $limitePasado = $ahora->copy()->subDays(3)->startOfDay();

            if ($escaneo->lessThanOrEqualTo($limiteFuturo) && $escaneo->greaterThanOrEqualTo($limitePasado)) {
                $ahora = $escaneo;
            }
        }

        $hora      = $ahora->format('H:i');
        $fechaHora = $ahora->format('Y-m-d H:i:00');
        $grupo     = Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada')->find($alumno->grupo_id);

        if (($d['modo'] ?? 'Entrada') === 'Salida') {
            $estatus = 'Salida';
        } else {
            // El retardo se calcula con el horario del grupo DEL ALUMNO
            $estatus = ($grupo && $grupo->hora_entrada && $hora > substr($grupo->hora_entrada, 0, 5))
                ? 'Retardo' : 'Asistencia';
        }

        // updateOrCreate: si el teléfono reintenta el mismo escaneo no se duplica el registro
        Asistencia::updateOrCreate(
            ['alumno_id' => $alumno->id, 'fecha_hora' => $fechaHora],
            ['estatus' => $estatus]
        );

        $partes = $grupo ? explode(' ', $grupo->nombre) : [];

        return response()->json([
            'ok'      => true,
            'fecha'   => $ahora->format('Y-m-d'),
            'hora'    => $hora,
            'estatus' => $estatus,
            // A qué grupo se mandó el registro (para mostrarlo en el teléfono)
            'alumno'  => [
                'nombre' => $alumno->nombre,
                'grado'  => $grupo ? (int) $grupo->grado : null,
                'grupo'  => $grupo ? (int) end($partes) : null,
                'turno'  => $grupo ? ($grupo->turno ?: 'Matutino') : null,
            ],
        ]);
    }

    /** Nombres de orientadores para la pantalla de login (se recalcula solo si la tabla cambió) */
    public static function orientadoresLogin(): array
    {
        $h = DB::table('orientadores')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();

        return Cache::remember('sica.orientadores.' . md5(json_encode($h)), 600, fn () => DB::table('orientadores')
            ->select('id', 'nombre')->orderBy('nombre')->get()
            ->map(fn ($o) => ['id' => (string) $o->id, 'nombre' => $o->nombre])->values()->all());
    }

    /**
     * Huella barata del estado: si no cambió nada, no se recalcula ni se arma el JSON.
     * Son 5 consultas de agregado (usan índices); cubre altas, bajas y cambios.
     */
    private function huellaEstado(): string
    {
        return Cache::remember('sica.huella', 2, fn () => $this->calcularHuella());
    }

    private function calcularHuella(): string
    {
        $a  = DB::table('asistencias')->selectRaw('COUNT(*) c, MAX(id) i, MAX(updated_at) u')->first();
        $al = DB::table('alumnos')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();
        $g  = DB::table('grupos')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();
        $o  = DB::table('orientadores')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();
        $p  = DB::table('permisos')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();

        return md5(json_encode([$a, $al, $g, $o, $p]));
    }

    /** Estado completo para que computadoras y teléfonos lo descarguen */
    public function estado(Request $request): JsonResponse
    {
        $huella = $this->huellaEstado();
        $etag   = '"' . $huella . '"';

        // 304 inmediato: el navegador ya tiene la versión actual (sin leer asistencias ni armar JSON)
        if ($request->header('If-None-Match') === $etag || $request->header('If-None-Match') === 'W/' . $etag) {
            return response()->json(null, 304)->setEtag($huella);
        }

        $datos = Cache::remember('sica.estado.' . $huella, 300, fn () => $this->construirEstado());

        $respuesta = response()->json($datos);
        $respuesta->setEtag($huella);

        return $respuesta;
    }

    private function construirEstado(): array
    {
        $grupos = DB::table('grupos')
            ->select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'hora_salida', 'orientador_id')
            ->get()->keyBy('id');

        // Entrada (la más temprana) y salida (la más tardía) por alumno y día.
        // Query builder + cursor: sin crear un modelo Eloquent por cada asistencia y sin cargar todo en memoria.
        $regs = [];
        foreach (DB::table('asistencias')->select('alumno_id', 'estatus', 'fecha_hora')->orderBy('alumno_id')->cursor() as $x) {
            $f = substr((string) $x->fecha_hora, 0, 10);
            $h = substr((string) $x->fecha_hora, 11, 5);
            $r = &$regs[$x->alumno_id][$f];

            if ($x->estatus === 'Salida') {
                if (empty($r['salida']) || $h > $r['salida']) {
                    $r['salida'] = $h;
                }
            } elseif (empty($r['entrada']) || $h < $r['entrada']) {
                $r['entrada'] = $h;
            }
            unset($r);
        }

        $horarios = [];
        $info     = [];   // por grupo: [grado, grupo, turno] calculado una sola vez
        foreach ($grupos as $g) {
            $partes = explode(' ', $g->nombre);
            $turno  = $g->turno ?: 'Matutino';
            $info[$g->id] = [(int) $g->grado, (int) end($partes), $turno];

            if (!$g->hora_entrada && !$g->hora_salida && !$g->orientador_id) {
                continue;
            }
            $horarios[$turno . '-' . $g->grado . '-' . end($partes)] = [
                'entrada'      => $g->hora_entrada ? substr($g->hora_entrada, 0, 5) : '',
                'salida'       => $g->hora_salida ? substr($g->hora_salida, 0, 5) : '',
                'orientadorId' => $g->orientador_id ? (string) $g->orientador_id : '',
            ];
        }

        $alumnos = [];
        foreach (DB::table('alumnos')->select('id', 'matricula', 'nombre', 'grupo_id', 'client_key')->cursor() as $a) {
            if (!isset($info[$a->grupo_id])) {
                continue;
            }
            [$grado, $grupo, $turno] = $info[$a->grupo_id];

            $alumnos[] = [
                // Alumno sin CURP: su id es el que le puso el navegador (así todos los equipos lo reconocen)
                'id'     => ($a->matricula !== null && $a->matricula !== '') ? (string) $a->id : (string) ($a->client_key ?: 'srv-' . $a->id),
                'curp'   => (string) ($a->matricula ?? ''),
                'nombre' => $a->nombre,
                'grado'  => $grado,
                'grupo'  => $grupo,
                'turno'  => $turno,
                'reg'    => (object) ($regs[$a->id] ?? []),
            ];
        }

        $permisos = ['orientador' => 'editar', 'director' => 'ver', 'subdirector' => 'ver'];
        foreach (DB::table('permisos')->select('rol', 'tipo')->get() as $p) {
            $permisos[$p->rol] = $p->tipo;
        }

        return [
            'alumnos'      => $alumnos,
            'horarios'     => (object) $horarios,
            'orientadores' => DB::table('orientadores')->select('id', 'nombre')->get()
                ->map(fn ($o) => ['id' => (string) $o->id, 'nombre' => $o->nombre])->values()->all(),
            'permisos'     => $permisos,
        ];
    }

    /** Elimina un alumno (por CURP, o por su id si todavía no tiene CURP) junto con todos sus registros de asistencia */
    private function eliminarAlumno(array $d): JsonResponse
    {
        $curp  = is_string($d['curp'] ?? null) ? $d['curp'] : '';
        $clave = is_string($d['id'] ?? null) ? $d['id'] : '';
        $alumno = null;

        if ($curp !== '') {
            $alumno = Alumno::where('matricula', $curp)->first();
        }
        if (!$alumno && $clave !== '') {
            $alumno = str_starts_with($clave, 'srv-')
                ? Alumno::find((int) substr($clave, 4))
                : Alumno::where('client_key', $clave)->first();
        }

        if ($alumno) {
            DB::transaction(function () use ($alumno) {
                Asistencia::where('alumno_id', $alumno->id)->delete();
                $alumno->delete();
            });
        }

        // Si no existía en el servidor, también es correcto: el navegador lo quita de su lista
        return response()->json(['ok' => true]);
    }

    /**
     * Elimina un orientador de forma permanente: su registro (con contraseña, bloqueo, etc.) desaparece de la BD
     * y los grupos que tenía quedan sin orientador. Se busca por id y, si no, por nombre.
     */
    private function eliminarOrientador(array $d): JsonResponse
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $orientador = null;

        if (isset($d['id']) && ctype_digit((string) $d['id'])) {
            $orientador = Orientador::find((int) $d['id']);
        }
        if (!$orientador && $nombre !== '') {
            $orientador = Orientador::where('nombre', $nombre)->first()
                ?? Orientador::whereRaw('LOWER(TRIM(nombre)) = ?', [mb_strtolower($nombre)])->first();
        }

        if ($orientador) {
            DB::transaction(function () use ($orientador) {
                Grupo::where('orientador_id', $orientador->id)->update(['orientador_id' => null]);
                DB::table('orientadores')->where('id', $orientador->id)->delete();
            });
        }

        // Si ya no existía en el servidor, también es correcto: el navegador lo quita de su lista
        return response()->json(['ok' => true]);
    }

    private function guardarPermisos(array $permisos): JsonResponse
    {
        foreach ($permisos as $rol => $tipo) {
            // Solo estos roles y estos tipos son válidos; Control Escolar siempre puede todo
            if (!in_array($rol, ['orientador', 'director', 'subdirector'], true) || !in_array($tipo, ['ver', 'editar'], true)) {
                return response()->json(['ok' => false, 'mensaje' => 'Permiso inválido'], 422);
            }
        }

        foreach ($permisos as $rol => $tipo) {
            Permiso::updateOrCreate(['rol' => $rol], ['tipo' => $tipo]);
        }

        return response()->json(['ok' => true]);
    }

    /** Guarda solo orientadores y grupos (rápido): así aparecen en la pantalla de inicio aunque lo demás falle o tarde */
    private function guardarSoloOrientadores(array $db): JsonResponse
    {
        DB::transaction(function () use ($db) {
            $mapa = $this->guardarOrientadores($db['orientadores'] ?? []);
            $this->guardarHorarios($db['horarios'] ?? [], $mapa);
        });

        return response()->json(['ok' => true]);
    }

    private function guardarTodo(array $db, bool $soloAsistencias = false): JsonResponse
    {
        if (!$soloAsistencias) {
            // Orientadores y grupos van en su propia transacción: un problema con un alumno no los deja sin guardar
            $this->guardarSoloOrientadores($db);
        }

        DB::transaction(function () use ($db, $soloAsistencias) {
            if ($soloAsistencias) {
                $this->guardarSoloAsistencias($db['alumnos'] ?? []);
                return;
            }

            $this->guardarPlantillas($db['plantillas'] ?? []);
            $this->guardarAlumnos($db['alumnos'] ?? []);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Clave de grupo -> [grado, grupo, "1° 2", turno]
     * Acepta "Vespertino-1-2" (nueva) y "1-2" (formato anterior, se toma como Matutino).
     */
    private function datosDeClave(string $clave): array
    {
        $p = explode('-', $clave);

        if (count($p) === 2) {
            $turno = 'Matutino';
            [$grado, $grupo] = $p;
        } else {
            [$turno, $grado, $grupo] = $p;
        }

        $turno = ($turno === 'Vespertino') ? 'Vespertino' : 'Matutino';

        return [(int) $grado, (int) $grupo, $grado . '° ' . $grupo, $turno];
    }

    /** Guarda la lista de orientadores y devuelve un mapa id-del-JS -> id-real-de-la-BD */
    private function guardarOrientadores(array $orientadores): array
    {
        $mapa = [];

        foreach ($orientadores as $o) {
            if (empty($o['nombre'])) {
                continue;
            }

            $registro = Orientador::updateOrCreate(['nombre' => $o['nombre']], []);
            $mapa[$o['id']] = $registro->id;
        }

        return $mapa;
    }

    /** Horario límite de entrada/salida y orientador asignado por grupo Y turno */
    private function guardarHorarios(array $horarios, array $mapaOrientadores): void
    {
        foreach ($horarios as $clave => $h) {
            [$grado, , $nombre, $turno] = $this->datosDeClave($clave);

            $grupo = Grupo::firstOrNew(['grado' => $grado, 'nombre' => $nombre, 'turno' => $turno]);
            // Un grupo puede tener orientador sin horario todavía: la hora vacía se guarda como NULL
            $grupo->hora_entrada = !empty($h['entrada']) ? $h['entrada'] : null;
            $grupo->hora_salida = !empty($h['salida']) ? $h['salida'] : null;
            $grupo->orientador_id = (!empty($h['orientadorId']) && isset($mapaOrientadores[$h['orientadorId']]))
                ? $mapaOrientadores[$h['orientadorId']]
                : null;
            $grupo->save();
        }
    }

    /** Plantillas (frente/reverso) propias de cada grupo (opcional, ya casi no se usa) */
    private function guardarPlantillas(array $plantillas): void
    {
        foreach ($plantillas as $clave => $p) {
            [$grado, , $nombre, $turno] = $this->datosDeClave($clave);

            $grupo = Grupo::firstOrNew(['grado' => $grado, 'nombre' => $nombre, 'turno' => $turno]);

            if (!empty($p['frente']['img'])) {
                $grupo->plantilla_frente = $p['frente']['img'];
            }
            if (!empty($p['reverso']['img'])) {
                $grupo->plantilla_reverso = $p['reverso']['img'];
            }

            $grupo->save();
        }
    }

    /**
     * Alumnos y sus registros de entrada/salida por día.
     *
     * Un alumno se identifica por su CURP (guardada en `matricula`). Los alumnos que vienen de una lista
     * SIN CURP todavía se identifican por el id que les da el navegador (columna `client_key`); cuando
     * más tarde se les asigna la CURP, el mismo registro se actualiza (no se duplica).
     */
    private function guardarAlumnos(array $alumnos): void
    {
        $alumnos = array_values(array_filter($alumnos, 'is_array'));

        // Una sola consulta por tabla (antes: varias por cada alumno)
        $gruposCache = [];
        $curps  = collect($alumnos)->pluck('curp')->filter(fn ($c) => is_string($c) && $c !== '')->unique()->all();
        $claves = collect($alumnos)->pluck('id')->filter(fn ($i) => is_string($i) && $i !== '')->unique()->all();

        $existentes = collect();
        foreach (array_chunk($curps, 500) as $bloque) {
            $existentes = $existentes->concat(Alumno::whereIn('matricula', $bloque)->get(['id', 'matricula', 'nombre', 'grupo_id', 'client_key']));
        }
        $existentes = $existentes->keyBy('matricula');

        $porClave = collect();
        foreach (array_chunk($claves, 500) as $bloque) {
            $porClave = $porClave->concat(Alumno::whereIn('client_key', $bloque)->get(['id', 'matricula', 'nombre', 'grupo_id', 'client_key']));
        }
        $porClave = $porClave->keyBy('client_key');

        $pares = [];   // [alumno, grupo, reg] para guardar registros en lote
        foreach ($alumnos as $a) {
            $curp  = (isset($a['curp']) && is_string($a['curp'])) ? $a['curp'] : '';
            $clave = (isset($a['id']) && is_string($a['id']) && $a['id'] !== '') ? mb_substr($a['id'], 0, 255) : null;

            // Sin CURP y sin id no hay forma de reconocerlo después
            if ($curp === '' && $clave === null) {
                continue;
            }

            $grado       = (int) ($a['grado'] ?? 0);
            $nombreGrupo = $grado . '° ' . ((int) ($a['grupo'] ?? 0));
            $turno       = (($a['turno'] ?? '') === 'Vespertino') ? 'Vespertino' : 'Matutino';
            $k           = $turno . '|' . $nombreGrupo;

            // El grupo se identifica por grado + nombre + TURNO; se busca/crea una sola vez por grupo
            $gruposCache[$k] ??= Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada')
                ->firstOrCreate(['grado' => $grado, 'nombre' => $nombreGrupo, 'turno' => $turno]);
            $grupo  = $gruposCache[$k];
            $nombre = mb_substr((string) ($a['nombre'] ?? ''), 0, 150);

            // Primero por CURP; si no, por el id del navegador (alumno de lista sin CURP, o al que se le acaba de asignar)
            $alumno = $curp !== '' ? $existentes->get($curp) : null;
            $alumno ??= $clave !== null ? $porClave->get($clave) : null;
            $alumno ??= ($clave !== null && str_starts_with($clave, 'srv-')) ? Alumno::find((int) substr($clave, 4)) : null;

            if (!$alumno) {
                $alumno = Alumno::create([
                    'matricula'  => $curp !== '' ? $curp : null,
                    'nombre'     => $nombre,
                    'grupo_id'   => $grupo->id,
                    'client_key' => $clave !== null && !str_starts_with($clave, 'srv-') ? $clave : null,
                ]);
                if ($curp !== '') {
                    $existentes->put($curp, $alumno);
                }
                if ($alumno->client_key) {
                    $porClave->put($alumno->client_key, $alumno);
                }
            } else {
                $cambios = [];
                if ($alumno->nombre !== $nombre) {
                    $cambios['nombre'] = $nombre;
                }
                if ((int) $alumno->grupo_id !== (int) $grupo->id) {
                    $cambios['grupo_id'] = $grupo->id;
                }
                if ($curp !== '' && $alumno->matricula !== $curp) {
                    $cambios['matricula'] = $curp;   // se le asignó la CURP a un alumno que venía de una lista sin CURP
                }
                if ($cambios) {
                    $alumno->update($cambios);   // solo si algo cambió
                    if ($curp !== '') {
                        $existentes->put($curp, $alumno);
                    }
                }
            }

            $pares[] = [$alumno, $grupo, $a['reg'] ?? []];
        }

        $this->guardarRegistrosEnLote($pares);
    }

    /**
     * Modo para roles que NO son Control Escolar: solo se guardan entradas/salidas de alumnos que ya existen.
     * No se crean alumnos, grupos, horarios ni orientadores, aunque la petición los traiga.
     */
    private function guardarSoloAsistencias(array $alumnos): void
    {
        $alumnos = array_values(array_filter($alumnos, 'is_array'));
        $curps   = collect($alumnos)->pluck('curp')->filter(fn ($c) => is_string($c) && $c !== '')->all();
        $claves  = collect($alumnos)->pluck('id')->filter(fn ($i) => is_string($i) && $i !== '')->all();

        $existentes = Alumno::whereIn('matricula', $curps)->get(['id', 'matricula', 'grupo_id'])->keyBy('matricula');
        $porClave   = Alumno::whereIn('client_key', $claves)->get(['id', 'client_key', 'grupo_id'])->keyBy('client_key');
        $grupos     = Grupo::select('id', 'hora_entrada')
            ->whereIn('id', $existentes->pluck('grupo_id')->merge($porClave->pluck('grupo_id'))->unique())->get()->keyBy('id');

        $pares = [];
        foreach ($alumnos as $a) {
            $curp   = (isset($a['curp']) && is_string($a['curp'])) ? $a['curp'] : '';
            $alumno = $curp !== '' ? $existentes->get($curp) : null;
            $alumno ??= (isset($a['id']) && is_string($a['id'])) ? $porClave->get($a['id']) : null;

            if ($alumno) {
                $pares[] = [$alumno, $grupos->get($alumno->grupo_id), $a['reg'] ?? []];
            }
        }

        $this->guardarRegistrosEnLote($pares);
    }

    /**
     * Guarda entradas y salidas por fecha, ignorando fechas u horas con formato inválido.
     * Compara contra lo que ya hay en la BD (UNA consulta) y solo escribe lo nuevo o lo que cambió de estatus:
     * el navegador reenvía todo el historial en cada sincronización y casi nunca hay nada distinto.
     *
     * @param array<int, array{0: Alumno, 1: ?Grupo, 2: mixed}> $pares
     */
    private function guardarRegistrosEnLote(array $pares): void
    {
        $esFecha = fn ($x) => is_string($x) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $x) === 1;
        $esHora  = fn ($x) => is_string($x) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $x) === 1;

        // 1) Lo que el navegador quiere guardar: [alumno_id|fecha_hora => estatus]
        $deseado = [];
        foreach ($pares as [$alumno, $grupo, $reg]) {
            if (!is_array($reg)) {
                continue;
            }
            $horaLimite = ($grupo && $grupo->hora_entrada) ? substr($grupo->hora_entrada, 0, 5) : null;

            foreach ($reg as $fecha => $r) {
                if (!$esFecha($fecha) || !is_array($r)) {
                    continue;
                }
                if (!empty($r['entrada']) && $esHora($r['entrada'])) {
                    $deseado[$alumno->id . '|' . $fecha . ' ' . $r['entrada'] . ':00'] =
                        ($horaLimite && $r['entrada'] > $horaLimite) ? 'Retardo' : 'Asistencia';
                }
                if (!empty($r['salida']) && $esHora($r['salida'])) {
                    $deseado[$alumno->id . '|' . $fecha . ' ' . $r['salida'] . ':00'] = 'Salida';
                }
            }
        }

        if (!$deseado) {
            return;
        }

        // 2) Lo que ya existe para esos alumnos (una consulta, por bloques)
        $ids   = array_values(array_unique(array_map(fn ($k) => (int) explode('|', $k)[0], array_keys($deseado))));
        $hay   = [];
        foreach (array_chunk($ids, 500) as $bloque) {
            foreach (DB::table('asistencias')->whereIn('alumno_id', $bloque)->select('alumno_id', 'fecha_hora', 'estatus')->cursor() as $x) {
                $hay[$x->alumno_id . '|' . substr((string) $x->fecha_hora, 0, 19)] = $x->estatus;
            }
        }

        // 3) Solo se inserta lo que falta; solo se actualiza lo que cambió de estatus
        $ahora   = now();
        $nuevos  = [];
        foreach ($deseado as $clave => $estatus) {
            [$alumnoId, $fechaHora] = explode('|', $clave, 2);

            if (!array_key_exists($clave, $hay)) {
                $nuevos[] = ['alumno_id' => (int) $alumnoId, 'estatus' => $estatus, 'fecha_hora' => $fechaHora,
                             'created_at' => $ahora, 'updated_at' => $ahora];
            } elseif ($hay[$clave] !== $estatus) {
                Asistencia::where('alumno_id', $alumnoId)->where('fecha_hora', $fechaHora)
                    ->update(['estatus' => $estatus]);
            }
        }

        foreach (array_chunk($nuevos, 500) as $bloque) {
            DB::table('asistencias')->insert($bloque);
        }
    }
}
