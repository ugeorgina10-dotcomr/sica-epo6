<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\Orientador;
use App\Models\Permiso;
use App\Support\ArchivoSeguro;
use App\Support\MovimientoAutomatico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ControlEscolarController extends Controller
{
    /**
     * Único punto por donde el sistema envía sus cambios (sincronizarConServidor() en el JS).
     * Recibe { accion, datos } y decide qué hacer según la acción.
     */
    public function sync(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'accion' => 'required|string',
            'datos'  => 'required|array',
        ]);

        $esControl = $request->user()?->rol === 'control';

        // Cualquier cambio actualiza la marca de versión: los demás equipos ven el cambio en su próxima consulta
        $respuesta = $this->ejecutarAccion($validado, $esControl);
        $this->olvidarMarcas();

        return $respuesta;
    }

    private function ejecutarAccion(array $validado, bool $esControl): JsonResponse
    {
        return match ($validado['accion']) {
            'guardar_permisos'     => $this->guardarPermisos($validado['datos']),
            // Solo Control Escolar puede crear o cambiar alumnos, horarios, orientadores y plantillas.
            // Los demás roles con permiso "editar" únicamente pueden guardar asistencias.
            'guardar_todo'         => $this->guardarTodo($validado['datos'], !$esControl),
            'registrar_asistencia' => $this->registrarAsistencia($validado['datos']),
            'registrar_asistencias' => $this->registrarAsistencias($validado['datos']),
            'eliminar_alumno'      => $this->eliminarAlumno($validado['datos']),
            'eliminar_lista_grupo' => $this->eliminarListaGrupo($validado['datos']),
            'eliminar_listas_grupos' => $this->eliminarListasGrupos($validado['datos']),
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
            'archivo' => 'required|file|max:20480|extensions:docx,pdf',
            'turno'   => 'required|in:Matutino,Vespertino,Ambos',   // "Ambos": el reverso sirve para los dos turnos con UNA sola subida
        ], [
            'archivo.extensions' => 'La plantilla debe ser un Word (.docx) o un PDF.',
            'archivo.max'        => 'El archivo pesa demasiado (máximo 20 MB).',
        ]);

        $ruta      = $request->file('archivo')->getRealPath();
        $extension = strtolower($request->file('archivo')->getClientOriginalExtension());
        $contenido = file_get_contents($ruta);
        $nombre    = $request->file('archivo')->getClientOriginalName();

        $problemaNombre = ArchivoSeguro::problemaDeNombre($nombre);
        if ($problemaNombre !== null) {
            return response()->json(['ok' => false, 'message' => $problemaNombre], 422);
        }

        // Se revisa el CONTENIDO real (no solo la extensión): que sea de verdad un PDF o un Word, que coincida con su extensión y que no esté dañado
        $esPdf = str_starts_with($contenido, '%PDF-');
        $esZip = str_starts_with($contenido, "PK\x03\x04");

        if ($esPdf) {
            if ($extension !== 'pdf') {
                return response()->json(['ok' => false, 'message' => 'El archivo es un PDF pero su extensión dice otra cosa. Guárdalo con extensión .pdf.'], 422);
            }
            if (!str_contains(substr($contenido, -2048), '%%EOF')) {
                return response()->json(['ok' => false, 'message' => 'El PDF está dañado o incompleto (se cortó al guardarlo). Vuelve a exportarlo.'], 422);
            }
            $problemaPdf = ArchivoSeguro::problemaDePdf($contenido);
            if ($problemaPdf !== null) {
                return response()->json(['ok' => false, 'message' => $problemaPdf], 422);
            }
        } elseif ($esZip) {
            // Un .docx es un archivo comprimido que contiene word/document.xml: así no se guarda cualquier otro archivo (ni un Excel con el nombre cambiado)
            $ok = false;
            if ($extension === 'docx' && class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                if ($zip->open($ruta) === true) {   // si el archivo comprimido está incompleto o dañado, no se puede abrir y no hay nada que cerrar
                    $ok = $zip->locateName('word/document.xml') !== false;
                    $zip->close();
                }
            }
            if ($extension !== 'docx') {
                return response()->json(['ok' => false, 'message' => 'El archivo es un documento comprimido (zip, Word, Excel...) pero su extensión no es .docx. Sube un Word guardado como .docx o un PDF.'], 422);
            }
            if (!$ok) {
                return response()->json(['ok' => false, 'message' => 'El archivo no es un Word (.docx) válido o está dañado. Guárdalo de nuevo desde Word.'], 422);
            }
            $problemaOffice = ArchivoSeguro::problemaDeOffice($ruta);
            if ($problemaOffice !== null) {
                return response()->json(['ok' => false, 'message' => $problemaOffice], 422);
            }
        } else {
            return response()->json(['ok' => false, 'message' => 'El archivo no es un Word (.docx) ni un PDF válido.'], 422);
        }

        $turnos = $request->input('turno') === 'Ambos' ? ['Matutino', 'Vespertino'] : [$request->input('turno')];
        $valor  = base64_encode($contenido);
        unset($contenido);   // el archivo puede pesar varios MB: no se mantiene dos veces en memoria
        $ahora  = now();

        foreach ($turnos as $turno) {
            // Se guarda o se reemplaza en un solo paso: ya no se lee de la base de datos la plantilla anterior (varios MB) solo para reemplazarla.
            // (Un turno por vez: así cada guardado pesa lo mismo que antes y no se pasa del límite de tamaño del servidor)
            DB::table('ajustes')->upsert(
                [['clave' => self::clavePlantilla($turno, $lado), 'valor' => $valor, 'created_at' => $ahora, 'updated_at' => $ahora]],
                ['clave'],
                ['valor', 'updated_at']
            );
        }
        // Las plantillas anteriores eran imágenes (.png): ya no se usan
        Ajuste::whereIn('clave', array_map(fn ($t) => self::clavePlantillaImagenVieja($t, $lado), $turnos))->delete();

        return response()->json(['ok' => true]);
    }

    /** Entrega la plantilla (Word o PDF) guardada en la base de datos; si no hay, responde 404 (no encontrada) y la página usa la que viene en public/plantillas */
    public function verPlantillaOficial(string $turno, string $lado)
    {
        abort_unless(in_array($turno, ['Matutino', 'Vespertino'], true) && in_array($lado, ['frente', 'reverso'], true), 404);

        // Primero se revisa solo la fecha de la plantilla (sin traer el archivo): si el equipo ya tiene esta versión, responde "sin cambios" (304) al instante
        $clave = self::clavePlantilla($turno, $lado);
        $meta  = Ajuste::select('clave', 'updated_at')->find($clave);
        abort_unless($meta, 404);

        $etag = '"' . md5($clave . '|' . ($meta->updated_at?->getTimestamp() ?? 0)) . '"';
        if (in_array(request()->header('If-None-Match'), [$etag, 'W/' . $etag], true)) {
            return response('', 304)->header('ETag', $etag)->header('Cache-Control', 'no-cache');
        }

        $a = Ajuste::find($clave);
        abort_unless($a && $a->valor, 404);

        $bytes = base64_decode($a->valor);
        $tipo  = str_starts_with($bytes, '%PDF-')
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        $respuesta = response($bytes, 200, [
            'Content-Type'  => $tipo,
            'Cache-Control' => 'no-cache',
        ]);
        $respuesta->headers->set('ETag', $etag);

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

    /** Nombre con el que se guardaban las plantillas en imagen (ya eliminadas) */
    private static function clavePlantillaImagenVieja(string $turno, string $lado): string
    {
        return 'plantilla_' . strtolower($turno) . '_' . $lado;
    }

    /**
     * Lo que manda el teléfono (o la computadora) en cada escaneo.
     *
     * El alumno se busca por CURP en TODA la base, así que cualquier orientador con permiso puede
     * escanear a cualquier alumno: el registro queda en el grupo del alumno, no en el de quien escanea.
     *
     * La fecha y la hora las pone el SERVIDOR (zona America/Mexico_City): así nadie puede falsear
     * una entrada cambiando la hora de su teléfono. ÚNICA excepción: un escaneo "diferido" (hecho sin
     * internet y enviado después) trae la hora en que se escaneó; solo se acepta si no es del futuro
     * (con 2 minutos de margen) y no tiene más de 3 días. Si no cumple, se usa la hora del servidor.
     */
    /**
     * Escaneos de UN mismo alumno al mismo tiempo (dos lectores, doble toque): se atienden de uno en uno,
     * así el segundo ya ve el primero y nunca se duplica una entrada o una salida. Los de alumnos distintos no se esperan entre sí.
     * (Dentro de un lote ya se resuelve todo en memoria y en una sola transacción, ahí no hace falta.)
     */
    private function registrarAsistencia(array $d): JsonResponse
    {
        if ($this->enLote) {
            return $this->registrarAsistenciaInterno($d);
        }

        $clave = 'sica.reg.' . md5((string) ($d['curp'] ?? $d['qr'] ?? ''));
        try {
            return Cache::lock($clave, 10)->block(4, fn () => $this->registrarAsistenciaInterno($d));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return $this->registrarAsistenciaInterno($d);   // si el seguro tarda demasiado, se atiende igual (mejor atender que dejar al alumno esperando)
        }
    }

    private function registrarAsistenciaInterno(array $d): JsonResponse
    {
        $v = validator($d, [
            'curp'     => 'required_without:qr|nullable|string|max:30',
            'qr'       => 'required_without:curp|nullable|string|max:40',   // código del QR que no revela la CURP
            'modo'     => 'nullable|in:Entrada,Salida,Auto',   // sin modo = Automático (lo decide el horario del grupo)
            'diferido' => 'nullable|boolean',
            'fecha'    => 'nullable|date_format:Y-m-d',
            'hora'     => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'mensaje' => 'Datos de escaneo inválidos'], 422);
        }

        $alumno = $this->buscarAlumno($d);
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
        $grupo     = $this->gruposLote[$alumno->grupo_id] ?? $this->grupoCacheado((int) $alumno->grupo_id);

        $modo = $d['modo'] ?? 'Auto';

        // La PRIMERA hora en que se muestra la credencial es la que vale: mostrarla otra vez no cambia ni agrega nada
        // (ni la entrada ni la salida), en modo automático y también en modo manual.
        if ($this->enLote) {
            // En un lote todo se resuelve en memoria (los registros de estos alumnos se leyeron UNA sola vez al inicio)
            $dia = $ahora->toDateString();
            $hoy = collect($this->asistenciasDeLote($alumno->id))
                ->filter(fn ($x) => substr($x->fecha_hora, 0, 10) === $dia)->sortBy('fecha_hora')->values();
        } else {
            // Rango de fechas (en vez de whereDate): así la base de datos usa el índice (alumno_id, fecha_hora) y no recorre toda la tabla
            $dia = $ahora->toDateString();
            $hoy = collect(DB::table('asistencias')->where('alumno_id', $alumno->id)
                ->where('fecha_hora', '>=', $dia . ' 00:00:00')
                ->where('fecha_hora', '<', $ahora->copy()->addDay()->toDateString() . ' 00:00:00')
                ->orderBy('fecha_hora')->get(['fecha_hora', 'estatus']));
        }
        $entrada = $hoy->first(fn ($x) => $x->estatus !== 'Salida');
        $salida  = $hoy->first(fn ($x) => $x->estatus === 'Salida');
        $horaDe  = fn ($x) => Carbon::parse($x->fecha_hora)->format('H:i');

        if ($modo === 'Auto') {
            // Lector automático: Entrada o Salida según el HORARIO DEL GRUPO del alumno y lo que ya tiene hoy
            // (ver App\Support\MovimientoAutomatico).
            [$minEntrada, $minSalida] = MovimientoAutomatico::horario($grupo->hora_entrada ?? null, $grupo->hora_salida ?? null, $grupo->turno ?? null);
            $minAhora = (int) $ahora->format('G') * 60 + (int) $ahora->format('i');
            $modo = MovimientoAutomatico::resolver($minAhora, $minEntrada, $minSalida, (bool) $entrada, (bool) $salida);

            if ($modo === 'Completo') {
                return response()->json(['ok' => false, 'mensaje' => $alumno->nombre . ': ya tiene entrada (' . $horaDe($entrada) . ') y salida (' . $horaDe($salida) . ') de hoy'], 409);
            }
            if ($modo === 'Salida' && $entrada && MovimientoAutomatico::esRepetida($minAhora, $minEntrada, $minSalida)) {
                return response()->json(['ok' => false, 'mensaje' => $alumno->nombre . ': la entrada ya estaba registrada (' . $horaDe($entrada) . ')'], 409);
            }
        } elseif ($modo === 'Entrada' && $entrada) {
            return response()->json(['ok' => false, 'mensaje' => $alumno->nombre . ': la entrada ya estaba registrada (' . $horaDe($entrada) . ')'], 409);
        } elseif ($modo === 'Salida' && $salida) {
            return response()->json(['ok' => false, 'mensaje' => $alumno->nombre . ': la salida ya estaba registrada (' . $horaDe($salida) . ')'], 409);
        }

        if ($modo === 'Salida') {
            $estatus = 'Salida';
        } else {
            // El retardo se calcula con el horario del grupo DEL ALUMNO
            // (hora límite de entrada + tolerancia de entrada del grupo)
            $limite  = $grupo ? $grupo->limiteEntrada() : null;
            $estatus = ($limite && $hora > $limite) ? 'Retardo' : 'Asistencia';
        }

        // Una salida en el mismo minuto que la entrada no debe pisarla: se anota un minuto después
        // (sin otra consulta: los registros de hoy ya están leídos arriba)
        $mismoMinutoEntrada = $hoy->contains(fn ($x) => substr((string) $x->fecha_hora, 0, 19) === $fechaHora && $x->estatus !== 'Salida');
        if ($estatus === 'Salida' && $mismoMinutoEntrada) {
            $fechaHora = $ahora->copy()->addMinute()->format('Y-m-d H:i:00');
        }

        if ($this->enLote) {
            // Se anota en memoria y se guarda todo junto al final del lote (un solo guardado)
            $existente = null;
            foreach ($this->asisLote[$alumno->id] as $x) {
                if ($x->fecha_hora === $fechaHora) { $existente = $x; break; }
            }
            if ($existente) {
                if ($existente->estatus !== $estatus) {
                    $existente->estatus = $estatus;
                    $this->actualizarLote[] = [$alumno->id, $fechaHora, $estatus];
                }
            } else {
                $this->asisLote[$alumno->id][] = (object) ['fecha_hora' => $fechaHora, 'estatus' => $estatus];
                $this->nuevasLote[] = ['alumno_id' => $alumno->id, 'estatus' => $estatus, 'fecha_hora' => $fechaHora, 'created_at' => now(), 'updated_at' => now()];
            }
        } else {
            // Si el teléfono reintenta el mismo escaneo no se duplica el registro (se actualiza el que ya existe).
            // Antes eran 2 consultas (buscar + guardar); ahora el registro de hoy ya está leído y solo se hace UNA.
            $existente = $hoy->first(fn ($x) => substr((string) $x->fecha_hora, 0, 19) === $fechaHora);
            if (!$existente) {
                DB::table('asistencias')->insert(['alumno_id' => $alumno->id, 'estatus' => $estatus, 'fecha_hora' => $fechaHora, 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($existente->estatus !== $estatus) {
                DB::table('asistencias')->where('alumno_id', $alumno->id)->where('fecha_hora', $fechaHora)->update(['estatus' => $estatus, 'updated_at' => now()]);
            }
        }

        $partes = $grupo ? explode(' ', $grupo->nombre) : [];

        // Retardo ya pasada la tolerancia del grupo: de entrada (al entrar) o de salida (al salir). 0 minutos = a tiempo.
        // El aviso de retardo sale SOLO al final, cuando ya hay entrada y salida: al escanear la entrada no se avisa nada; al escanear la salida se
        // avisa el retardo de entrada (si lo hubo) y el de salida (si lo hubo) en el mismo mensaje.
        $minutosRetardo = 0;
        $minutosRetardoEntrada = 0;
        if ($grupo && $modo === 'Salida') {
            $minutosRetardo = $grupo->minutosRetardo('Salida', (int) $ahora->format('G') * 60 + (int) $ahora->format('i'));
            if ($entrada) {
                $horaEntrada = Carbon::parse($entrada->fecha_hora);
                $minutosRetardoEntrada = $grupo->minutosRetardo('Entrada', (int) $horaEntrada->format('G') * 60 + (int) $horaEntrada->format('i'));
            }
        }

        return response()->json([
            'ok'      => true,
            'fecha'   => $ahora->format('Y-m-d'),
            'hora'    => $hora,
            'estatus' => $estatus,
            'modo'    => $modo,
            'retardo' => $minutosRetardo > 0 ? ['tipo' => 'salida', 'minutos' => $minutosRetardo] : null,
            'retardo_entrada' => $minutosRetardoEntrada > 0 ? ['tipo' => 'entrada', 'minutos' => $minutosRetardoEntrada] : null,
            // A qué grupo se mandó el registro (para mostrarlo en el teléfono)
            'alumno'  => [
                'nombre' => $alumno->nombre,
                'curp'   => (string) $alumno->matricula,
                'grado'  => $grupo ? (int) $grupo->grado : null,
                'grupo'  => $grupo ? (int) end($partes) : null,
                'turno'  => $grupo ? ($grupo->turno ?: 'Matutino') : null,
            ],
        ]);
    }

    /**
     * Registra VARIOS escaneos de una sola vez (los que se acumularon en espera en el equipo).
     * Se procesan en orden (así una entrada y su salida del mismo alumno quedan bien) y se responde el resultado de cada uno.
     */
    /** Alumnos y grupos cargados de antemano durante un lote de escaneos (vacío en un escaneo suelto) */
    private array $alumnosLote = [];
    private array $gruposLote = [];
    private bool $enLote = false;
    private array $asisLote = [];       // alumno_id => registros de asistencia (con fecha_hora y estatus)
    private array $nuevasLote = [];     // filas por guardar al final del lote
    private array $actualizarLote = []; // [alumno_id, fecha_hora, estatus] por actualizar al final del lote

    /** Registros de un alumno dentro de un lote (si no se cargaron de antemano, se leen una sola vez) */
    private function asistenciasDeLote(int $alumnoId): array
    {
        if (!isset($this->asisLote[$alumnoId])) {
            $this->asisLote[$alumnoId] = Asistencia::where('alumno_id', $alumnoId)->where('fecha_hora', '>=', now()->subDays(4)->startOfDay())
                ->get(['fecha_hora', 'estatus'])->map(fn ($x) => (object) ['fecha_hora' => substr((string) $x->fecha_hora, 0, 19), 'estatus' => $x->estatus])->all();
        }
        return $this->asisLote[$alumnoId];
    }

    private function registrarAsistencias(array $d): JsonResponse
    {
        $items = $d['items'] ?? null;
        if (!is_array($items) || !$items || count($items) > 200) {
            return response()->json(['ok' => false, 'mensaje' => 'Lote de escaneos inválido'], 422);
        }

        // Los alumnos y grupos del lote se consultan UNA vez (no una por escaneo) y todo se guarda en un solo paso (todo o nada)
        $curps = [];
        foreach ($items as $item) {
            if (is_array($item) && !empty($item['curp']) && is_string($item['curp'])) {
                $curps[] = $item['curp'];
            }
        }
        $this->alumnosLote = $curps
            ? Alumno::select('id', 'nombre', 'grupo_id', 'matricula')->whereIn('matricula', array_values(array_unique($curps)))->get()->keyBy('matricula')->all()
            : [];
        $this->gruposLote = Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'hora_salida', 'tolerancia_entrada', 'tolerancia_salida')->get()->keyBy('id')->all();

        // Los registros de asistencia de esos alumnos se leen de una vez (antes: varias consultas por cada escaneo)
        $this->enLote = true;
        $this->asisLote = [];
        $this->nuevasLote = [];
        $this->actualizarLote = [];
        $ids = array_values(array_map(fn ($a) => (int) $a->id, $this->alumnosLote));
        foreach ($ids as $id) {
            $this->asisLote[$id] = [];
        }
        foreach (array_chunk($ids, 500) as $bloque) {
            foreach (DB::table('asistencias')->whereIn('alumno_id', $bloque)->where('fecha_hora', '>=', now()->subDays(4)->startOfDay())->select('alumno_id', 'fecha_hora', 'estatus')->cursor() as $x) {
                $this->asisLote[(int) $x->alumno_id][] = (object) ['fecha_hora' => substr((string) $x->fecha_hora, 0, 19), 'estatus' => $x->estatus];
            }
        }

        $resultados = [];
        try {
            DB::transaction(function () use ($items, &$resultados) {
                foreach (array_values($items) as $item) {
                    if (!is_array($item)) {
                        $resultados[] = ['status' => 422, 'mensaje' => 'Datos de escaneo inválidos'];
                        continue;
                    }
                    $r = $this->registrarAsistencia($item);
                    $cuerpo = $r->getData(true);
                    $resultados[] = ['status' => $r->getStatusCode(), 'mensaje' => $cuerpo['mensaje'] ?? null];
                }
                // Todo lo nuevo se guarda junto
                foreach (array_chunk($this->nuevasLote, 500) as $bloque) {
                    DB::table('asistencias')->insert($bloque);
                }
                foreach ($this->actualizarLote as [$aid, $fh, $est]) {
                    Asistencia::where('alumno_id', $aid)->where('fecha_hora', $fh)->update(['estatus' => $est]);
                }
            });
        } finally {
            $this->alumnosLote = [];
            $this->gruposLote = [];
            $this->enLote = false;
            $this->asisLote = [];
            $this->nuevasLote = [];
            $this->actualizarLote = [];
        }

        return response()->json(['ok' => true, 'resultados' => $resultados]);
    }

    /**
     * Código que va al final del texto del QR de la credencial: no se puede sacar la CURP a partir de él.
     * Se calcula con la CURP y la APP_KEY (si cambias la APP_KEY, hay que volver a imprimir las credenciales).
     */
    public static function codigoQr(string $curp): string
    {
        return substr(hash_hmac('sha256', strtoupper($curp), self::claveQr()), 0, 16);
    }

    /** Llave sacada de la APP_KEY (la APP_KEY nunca se manda). El sistema la recibe ya con sesión iniciada y calcula el código del QR de cualquier CURP */
    public static function claveQr(): string
    {
        return hash_hmac('sha256', 'sica-qr-v1', (string) config('app.key'));
    }

    /** Llaves de caché que cambian con cualquier guardado (se borran en sync()) */
    private const K_HUELLAS  = 'sica.huellas.v2';
    private const K_HALU     = 'sica.halu.v1';
    private const K_ORI_HASH = 'sica.orih.v1';
    private const K_GRUPOS   = 'sica.grupos.v2';

    /**
     * Borra las marcas guardadas en este servidor. Las demás copias del sistema (por ejemplo la de Render, que comparte base de datos)
     * las renuevan solas en pocos segundos, porque todas estas marcas duran muy poco.
     */
    private function olvidarMarcas(): void
    {
        foreach ([self::K_HUELLAS, self::K_HALU, self::K_ORI_HASH, self::K_GRUPOS] as $k) {
            Cache::forget($k);
        }
    }

    /** Marca de versión de la tabla de alumnos (se guarda 5 s: así un escaneo no la consulta cada vez) */
    private function huellaAlumnos(bool $fresca = false): string
    {
        if (!$fresca && is_string($v = Cache::get(self::K_HALU))) {
            return $v;
        }
        $h = DB::table('alumnos')->selectRaw('COUNT(*) c, MAX(id) i, MAX(updated_at) u')->first();
        $v = md5(json_encode($h));
        Cache::put(self::K_HALU, $v, 5);

        return $v;
    }

    /**
     * Índice de alumnos para escanear sin tocar la base de datos: código QR => id, CURP => id e id => [nombre, grupo, CURP].
     * Se arma una vez y se renueva solo si la tabla de alumnos cambió (solo cuenta la CURP de cada alumno).
     */
    private function indiceAlumnos(bool $fresca = false): array
    {
        $h = $this->huellaAlumnos($fresca);
        $c = Cache::get('sica.indice.v1');
        if (is_array($c) && ($c['h'] ?? null) === $h) {
            return $c;
        }

        $qr = $curp = $info = [];
        foreach (DB::table('alumnos')->whereNotNull('matricula')->where('matricula', '!=', '')->select('id', 'matricula', 'nombre', 'grupo_id')->cursor() as $a) {
            $id = (int) $a->id;
            $qr[self::codigoQr($a->matricula)] = $id;
            $curp[strtoupper($a->matricula)]   = $id;
            $info[$id] = [$a->nombre, (int) $a->grupo_id, $a->matricula];
        }
        $c = ['h' => $h, 'qr' => $qr, 'curp' => $curp, 'info' => $info];
        Cache::put('sica.indice.v1', $c, 1800);

        return $c;
    }

    /**
     * Alumno de un escaneo (por CURP o por código del QR) sin consultar la base de datos: sale del índice.
     * Si no aparece (p. ej. se dio de alta hace unos segundos) se renueva el índice una vez antes de decir "no encontrado".
     */
    private function buscarAlumno(array $d): ?object
    {
        $porCurp = !empty($d['curp']);
        if ($porCurp && isset($this->alumnosLote[$d['curp']])) {
            return $this->alumnosLote[$d['curp']];
        }

        $buscar = function (array $indice) use ($d, $porCurp): ?int {
            return $porCurp
                ? ($indice['curp'][strtoupper((string) $d['curp'])] ?? null)
                : ($indice['qr'][substr(strtolower((string) $d['qr']), 0, 16)] ?? null);   // acepta también los códigos largos (20) de la versión anterior
        };

        $id = $buscar($this->indiceAlumnos()) ?? null;
        $indice = null;
        if ($id === null) {
            $indice = $this->indiceAlumnos(true);
            $id = $buscar($indice);
        }
        if ($id === null) {
            return null;
        }

        $info = ($indice ?? $this->indiceAlumnos())['info'][$id] ?? null;

        return $info ? (object) ['id' => $id, 'nombre' => $info[0], 'grupo_id' => $info[1], 'matricula' => $info[2]] : null;
    }

    /** Grupos (con horarios) guardados 5 s: un escaneo no tiene que volver a pedirlos a la base de datos */
    private function gruposCacheados(): array
    {
        return Cache::remember(self::K_GRUPOS, 5, fn () => Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'hora_salida', 'tolerancia_entrada', 'tolerancia_salida')->get()->keyBy('id')->all());
    }

    private function grupoCacheado(int $id): ?Grupo
    {
        return $this->gruposCacheados()[$id]
            ?? Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'hora_salida', 'tolerancia_entrada', 'tolerancia_salida')->find($id);
    }

    /** Nombres de orientadores para la pantalla de inicio de sesión (se recalcula solo si la tabla cambió) */
    public static function orientadoresLogin(): array
    {
        $h = Cache::remember(self::K_ORI_HASH, 5, fn () => DB::table('orientadores')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first());

        return Cache::remember('sica.orientadores.' . md5(json_encode($h)), 600, fn () => DB::table('orientadores')
            ->select('id', 'nombre')->orderBy('nombre')->get()
            ->map(fn ($o) => ['id' => (string) $o->id, 'nombre' => $o->nombre])->values()->all());
    }

    /**
     * Marcas de versión del estado: 'total' cambia con cualquier escaneo o cambio; 'base' solo cuando cambian alumnos, grupos, orientadores o permisos.
     * Si no cambió nada, no se vuelve a calcular ni a armar la respuesta. UNA sola consulta (con la base de datos en la nube cada ida y vuelta pesa).
     */
    private function huellas(): array
    {
        return Cache::remember(self::K_HUELLAS, 1, fn () => $this->calcularHuellas());
    }

    private function huellaEstado(): string
    {
        return $this->huellas()['total'];
    }

    private function calcularHuellas(): array
    {
        // En asistencias NO se cuenta (contar toda la tabla se vuelve lento al crecer): basta el último id y la última modificación.
        // Las bajas de asistencias solo ocurren al borrar alumnos, y eso sí cambia el conteo de alumnos.
        $filas = DB::select(
            "select 'asistencias' as t, 0 as c, max(id) as i, max(updated_at) as u from asistencias
             union all select 'alumnos', count(*), 0, max(updated_at) from alumnos
             union all select 'grupos', count(*), 0, max(updated_at) from grupos
             union all select 'orientadores', count(*), 0, max(updated_at) from orientadores
             union all select 'permisos', count(*), 0, max(updated_at) from permisos"
        );

        return [
            'total' => md5(json_encode($filas)),
            'base'  => md5(json_encode(array_slice($filas, 1))),
        ];
    }

    /** Todos los datos para que computadoras y teléfonos los descarguen */
    public function estado(Request $request): JsonResponse
    {
        // "-q2": si cambia la forma de los datos (llave del QR), los equipos con la versión anterior descargan todo de nuevo
        $huellas = $this->huellas();
        $huella  = $huellas['total'] . '-q2';
        $etag    = '"' . $huella . '"';

        // Responde al instante "sin cambios" (304): el navegador ya tiene la versión actual (sin leer asistencias ni armar la respuesta)
        if ($request->header('If-None-Match') === $etag || $request->header('If-None-Match') === 'W/' . $etag) {
            return response()->json(null, 304)->setEtag($huella);
        }

        $gz = $this->estadoComprimido($huellas['base'], $huella);

        // Los equipos que aceptan gzip (todos los navegadores) reciben el estado YA comprimido: antes el servidor lo comprimía
        // una vez por cada equipo y por cada cambio. Ahora se comprime una sola vez para todos.
        if (str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            @ini_set('zlib.output_compression', '0');   // evita comprimir dos veces
            $respuesta = response($gz, 200, ['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip', 'Vary' => 'Accept-Encoding']);
        } else {
            $respuesta = response((string) gzdecode($gz), 200, ['Content-Type' => 'application/json']);
        }
        $respuesta->setEtag($huella);

        return $respuesta;
    }

    /**
     * Estado completo YA armado para esta versión (huella). Si cambia algo y muchos equipos lo piden a la vez, solo UNO lo arma y los
     * demás esperan ese resultado (antes cada uno lo armaba por su cuenta y el servidor se saturaba justo cuando más equipos escanean).
     * Se guarda en una sola llave que se reescribe (antes se creaba un archivo nuevo por cada cambio).
     */
    private function estadoActual(string $huellaBase, string $huella): array
    {
        $leer = function () use ($huella) {
            $g = Cache::get('sica.estado.cur.a');

            return (is_array($g) && ($g['h'] ?? null) === $huella) ? $g['datos'] : null;
        };

        if (($d = $leer()) !== null) {
            return $d;
        }

        $armar = function () use ($leer, $huellaBase, $huella) {
            if (($d = $leer()) !== null) {   // otro equipo lo armó mientras esperábamos
                return $d;
            }
            $d = $this->construirEstado($huellaBase);
            Cache::put('sica.estado.cur.a', ['h' => $huella, 'datos' => $d], 600);
            Cache::put('sica.estado.cur.g', ['h' => $huella, 'gz' => gzencode((string) json_encode($d, JSON_INVALID_UTF8_SUBSTITUTE), 4)], 600);

            return $d;
        };

        try {
            return Cache::lock('sica.estado.armar', 20)->block(8, $armar);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return $armar();
        }
    }

    private function estadoComprimido(string $huellaBase, string $huella): string
    {
        $g = Cache::get('sica.estado.cur.g');
        if (is_array($g) && ($g['h'] ?? null) === $huella) {
            return $g['gz'];
        }
        $d = $this->estadoActual($huellaBase, $huella);
        $g = Cache::get('sica.estado.cur.g');

        return (is_array($g) && ($g['h'] ?? null) === $huella) ? $g['gz'] : gzencode((string) json_encode($d, JSON_INVALID_UTF8_SUBSTITUTE), 4);
    }

    /** Lo mismo que /api/sica/estado, pero incluido en la página al abrirla con sesión iniciada (misma versión guardada y misma marca) */
    public function paraPagina(): array
    {
        $huellas = $this->huellas();
        $huella  = $huellas['total'] . '-q2';

        return [
            'etag'  => '"' . $huella . '"',
            'datos' => $this->estadoActual($huellas['base'], $huella),
        ];
    }

    /** Deja listo todo lo que pide el primer visitante (al encender el servidor): despierta la base de datos y llena las cachés */
    public function calentar(): void
    {
        DB::select('select 1');
        $this->indiceAlumnos();
        $this->gruposCacheados();
        self::orientadoresLogin();
        $this->paraPagina();
    }

    /**
     * Entrada (la más temprana) y salida (la más tardía) por alumno y día, de las asistencias que cumplan la condición de fecha.
     * El agrupado lo hace la base de datos: viaja una fila por alumno y día en vez de una por cada escaneo.
     *
     * @return array<int, array<string, array{entrada?: string, salida?: string}>>
     */
    private function agruparRegistros(string $operador, string $corte): array
    {
        $regs = [];
        $filas = DB::table('asistencias')
            ->where('fecha_hora', $operador, $corte)
            ->selectRaw("alumno_id, DATE(fecha_hora) as dia, MIN(CASE WHEN estatus <> 'Salida' THEN fecha_hora END) as ent, MAX(CASE WHEN estatus = 'Salida' THEN fecha_hora END) as sal")
            ->groupByRaw('alumno_id, DATE(fecha_hora)')
            ->cursor();

        foreach ($filas as $x) {
            $r = [];
            if ($x->ent !== null) {
                $r['entrada'] = substr((string) $x->ent, 11, 5);
            }
            if ($x->sal !== null) {
                $r['salida'] = substr((string) $x->sal, 11, 5);
            }
            $regs[(int) $x->alumno_id][substr((string) $x->dia, 0, 10)] = $r;
        }

        return $regs;
    }

    /**
     * Registros de todos los alumnos. Los días ya cerrados (más de 5 días atrás) casi nunca cambian: se guardan armados y solo se
     * vuelven a leer si cambiaron (se revisa con una consulta de conteo). Así cada escaneo solo obliga a leer los últimos días,
     * y no todo el historial como antes.
     */
    private function registrosPorAlumno(): array
    {
        $corte = now()->subDays(5)->startOfDay()->format('Y-m-d H:i:s');
        $h     = DB::selectOne('select count(*) as c, max(id) as i, max(updated_at) as u from asistencias where fecha_hora < ?', [$corte]);
        $firma = md5($corte . '|' . json_encode($h));

        $guardado = Cache::get('sica.estado.hist.v1');
        if (is_array($guardado) && ($guardado['firma'] ?? null) === $firma) {
            $regs = $guardado['datos'];
        } else {
            $regs = $this->agruparRegistros('<', $corte);
            Cache::put('sica.estado.hist.v1', ['firma' => $firma, 'datos' => $regs], 172800);
        }

        foreach ($this->agruparRegistros('>=', $corte) as $alumnoId => $dias) {
            foreach ($dias as $dia => $r) {
                $regs[$alumnoId][$dia] = $r;
            }
        }

        return $regs;
    }

    /** Lo que solo cambia si cambian alumnos, grupos, orientadores o permisos (sin asistencias) */
    private function construirBase(): array
    {
        $grupos = DB::table('grupos')
            ->select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'hora_salida', 'tolerancia_entrada', 'tolerancia_salida', 'orientador_id')
            ->get()->keyBy('id');

        $horarios = [];
        $info     = [];   // por grupo: [grado, grupo, turno] se calcula una sola vez
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
                'tolEntrada'   => (int) $g->tolerancia_entrada,
                'tolSalida'    => (int) $g->tolerancia_salida,
            ];
        }

        $alumnos = [];
        foreach (DB::table('alumnos')->select('id', 'matricula', 'nombre', 'grupo_id', 'client_key')->cursor() as $a) {
            if (!isset($info[$a->grupo_id])) {
                continue;
            }
            [$grado, $grupo, $turno] = $info[$a->grupo_id];

            $alumnos[] = [
                'dbid'   => (int) $a->id,   // solo para unir sus registros; no se envía
                // Alumno sin CURP: su id es el que le puso el navegador (así todos los equipos lo reconocen)
                'id'     => ($a->matricula !== null && $a->matricula !== '') ? (string) $a->id : (string) ($a->client_key ?: 'srv-' . $a->id),
                'curp'   => (string) ($a->matricula ?? ''),
                'qr'     => ($a->matricula !== null && $a->matricula !== '') ? self::codigoQr($a->matricula) : '',
                'nombre' => $a->nombre,
                'grado'  => $grado,
                'grupo'  => $grupo,
                'turno'  => $turno,
            ];
        }

        $permisos = ['orientador' => 'editar', 'director' => 'ver', 'subdirector' => 'ver'];
        foreach (DB::table('permisos')->select('rol', 'tipo')->get() as $p) {
            $permisos[$p->rol] = $p->tipo;
        }
        $permisos['orientador'] = 'editar';   // el orientador siempre descarga listas y usa la cámara (ya no se configura en Permisos y Roles)

        return [
            'qrClave'      => self::claveQr(),
            'alumnos'      => $alumnos,
            'horarios'     => (object) $horarios,
            'orientadores' => DB::table('orientadores')->select('id', 'nombre')->get()
                ->map(fn ($o) => ['id' => (string) $o->id, 'nombre' => $o->nombre])->values()->all(),
            'permisos'     => $permisos,
        ];
    }

    private function construirEstado(string $huellaBase): array
    {
        // La base (alumnos, horarios...) se guarda con su marca y solo se rehace si esas tablas cambiaron
        $guardada = Cache::get('sica.estado.base.v1');
        if (is_array($guardada) && ($guardada['firma'] ?? null) === $huellaBase) {
            $base = $guardada['datos'];
        } else {
            $base = $this->construirBase();
            Cache::put('sica.estado.base.v1', ['firma' => $huellaBase, 'datos' => $base], 172800);
        }

        $regs = $this->registrosPorAlumno();

        foreach ($base['alumnos'] as &$a) {
            $a['reg'] = (object) ($regs[$a['dbid']] ?? []);
            unset($a['dbid']);
        }
        unset($a);

        return $base;
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
     * Borra TODOS los alumnos de un grupo y turno junto con sus asistencias.
     * Si el grupo se queda sin horario, sin orientador y sin alumnos, también se borra su registro (no queda basura en la base de datos).
     */
    private function eliminarListaGrupo(array $d): JsonResponse
    {
        $grado = (int) ($d['grado'] ?? 0);
        $n     = (int) ($d['grupo'] ?? 0);
        $turno = (($d['turno'] ?? '') === 'Vespertino') ? 'Vespertino' : 'Matutino';

        if ($grado < 1 || $grado > 3 || $n < 1 || $n > 4) {
            return response()->json(['ok' => false, 'mensaje' => 'Grado o grupo no válido'], 422);
        }

        $borrados = 0;
        $grupo = Grupo::where('grado', $grado)->where('nombre', $grado . '° ' . $n)->where('turno', $turno)
            ->first(['id', 'hora_entrada', 'hora_salida', 'orientador_id']);   // sin las plantillas (son archivos pesados)

        if ($grupo) {
            DB::transaction(function () use ($grupo, &$borrados) {
                // Dos consultas sin importar cuántos alumnos haya (antes: una por cada 500 alumnos, más traer todos los ids)
                Asistencia::whereIn('alumno_id', Alumno::where('grupo_id', $grupo->id)->select('id'))->delete();
                $borrados = Alumno::where('grupo_id', $grupo->id)->delete();
                $this->borrarGrupoSiEstaVacio($grupo);
            });
        }

        return response()->json(['ok' => true, 'borrados' => $borrados]);
    }

    /**
     * Borra de una sola vez los alumnos (y sus asistencias) de VARIOS grupos: ['grupos' => [['grado','grupo','turno'], ...], 'configuracion' => bool].
     * Con configuracion = true también se quitan el horario y el orientador de esos grupos (y el grupo se borra si queda vacío).
     * Todo en un solo paso: o se borra todo o no se borra nada.
     */
    private function eliminarListasGrupos(array $d): JsonResponse
    {
        $objetivos = [];
        foreach (array_slice((array) ($d['grupos'] ?? []), 0, 24) as $g) {   // 3 grados x 4 grupos x 2 turnos = 24 como máximo
            $grado = (int) ($g['grado'] ?? 0);
            $n     = (int) ($g['grupo'] ?? 0);
            $turno = (($g['turno'] ?? '') === 'Vespertino') ? 'Vespertino' : 'Matutino';
            if ($grado >= 1 && $grado <= 3 && $n >= 1 && $n <= 4) {
                $objetivos[$grado . '|' . $n . '|' . $turno] = [$grado, $n, $turno];
            }
        }

        if (!$objetivos) {
            return response()->json(['ok' => false, 'mensaje' => 'No se indicó ningún grupo válido'], 422);
        }

        $quitarConfig = !empty($d['configuracion']);
        $borrados = 0;
        $gruposBorrados = 0;

        DB::transaction(function () use ($objetivos, $quitarConfig, &$borrados, &$gruposBorrados) {
            foreach ($objetivos as [$grado, $n, $turno]) {
                $grupo = Grupo::where('grado', $grado)->where('nombre', $grado . '° ' . $n)->where('turno', $turno)
                    ->first(['id', 'hora_entrada', 'hora_salida', 'orientador_id']);
                if (!$grupo) {
                    continue;
                }

                Asistencia::whereIn('alumno_id', Alumno::where('grupo_id', $grupo->id)->select('id'))->delete();
                $borrados += Alumno::where('grupo_id', $grupo->id)->delete();

                if ($quitarConfig) {
                    Grupo::whereKey($grupo->id)->update(['hora_entrada' => null, 'hora_salida' => null, 'orientador_id' => null]);
                    $grupo->hora_entrada = $grupo->hora_salida = $grupo->orientador_id = null;
                }
                if ($this->borrarGrupoSiEstaVacio($grupo)) {
                    $gruposBorrados++;
                }
            }
        });

        return response()->json(['ok' => true, 'borrados' => $borrados, 'grupos' => $gruposBorrados]);
    }

    /** Un grupo sin horario, sin orientador, sin plantilla propia y sin alumnos no tiene por qué seguir en la base de datos */
    private function borrarGrupoSiEstaVacio(Grupo $grupo): bool
    {
        if ($grupo->hora_entrada || $grupo->hora_salida || $grupo->orientador_id) {
            return false;
        }
        // Las plantillas no siempre vienen cargadas en el modelo: se revisan directo en la base de datos
        if (Grupo::whereKey($grupo->id)->where(fn ($q) => $q->whereNotNull('plantilla_frente')->orWhereNotNull('plantilla_reverso'))->exists()) {
            return false;
        }
        if (Alumno::where('grupo_id', $grupo->id)->exists()) {
            return false;
        }
        $grupo->delete();

        return true;
    }

    /**
     * Elimina un orientador de forma permanente: su registro (con contraseña, bloqueo, etc.) desaparece de la base de datos
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
            // Solo estos roles y tipos son válidos; Control Escolar siempre puede hacer todo
            if (!in_array($rol, ['orientador', 'director', 'subdirector'], true) || !in_array($tipo, ['ver', 'editar'], true)) {
                return response()->json(['ok' => false, 'mensaje' => 'Permiso inválido'], 422);
            }
        }

        foreach ($permisos as $rol => $tipo) {
            if ($rol === 'orientador') {
                continue;   // siempre 'editar': no se guarda ni se puede cambiar
            }
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
        // Orientadores y grupos se guardan por separado: un problema con un alumno no impide que se guarden.
        // Los siguientes lotes de una lista grande llegan vacíos: así no se hace ninguna consulta ni guardado de más.
        if (!$soloAsistencias && (!empty($db['orientadores']) || !empty($db['horarios']))) {
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

    /** Guarda la lista de orientadores y devuelve un mapa: id-del-navegador -> id-real-en-la-base-de-datos */
    private function guardarOrientadores(array $orientadores): array
    {
        $mapa = [];
        // Una sola consulta con los que ya existen (antes era una consulta por cada orientador)
        $existentes = Orientador::pluck('id', 'nombre');

        foreach ($orientadores as $o) {
            if (empty($o['nombre'])) {
                continue;
            }

            $id = $existentes[$o['nombre']] ?? null;
            if ($id === null) {
                // No se encuentra igual: se busca o crea como antes (respeta mayúsculas y acentos de la base de datos)
                $id = Orientador::updateOrCreate(['nombre' => $o['nombre']], [])->id;
                $existentes[$o['nombre']] = $id;
            }
            $mapa[$o['id']] = $id;
        }

        return $mapa;
    }

    /** Horario límite de entrada y salida, y orientador asignado, por grupo Y turno */
    private function guardarHorarios(array $horarios, array $mapaOrientadores): void
    {
        // Todos los grupos en una sola consulta, sin las plantillas (son archivos pesados)
        $existentes = Grupo::select('id', 'grado', 'nombre', 'turno', 'hora_entrada', 'hora_salida', 'tolerancia_entrada', 'tolerancia_salida', 'orientador_id')->get()->keyBy(fn ($g) => $g->grado . '|' . $g->nombre . '|' . $g->turno);

        foreach ($horarios as $clave => $h) {
            [$grado, , $nombre, $turno] = $this->datosDeClave($clave);

            $grupo = $existentes[$grado . '|' . $nombre . '|' . $turno]
                ?? Grupo::firstOrNew(['grado' => $grado, 'nombre' => $nombre, 'turno' => $turno]);
            // Un grupo puede tener orientador sin horario todavía: la hora vacía se guarda en blanco
            $grupo->hora_entrada = $this->horaValida($h['entrada'] ?? null);
            $grupo->hora_salida = $this->horaValida($h['salida'] ?? null);
            // Solo si el navegador las manda (una copia vieja guardada en el equipo no debe borrar la tolerancia ya configurada)
            if (array_key_exists('tolEntrada', $h)) {
                $grupo->tolerancia_entrada = $this->toleranciaValida($h['tolEntrada'], 0);
            }
            if (array_key_exists('tolSalida', $h)) {
                $grupo->tolerancia_salida = $this->toleranciaValida($h['tolSalida'], 30);
            }
            $grupo->orientador_id = (!empty($h['orientadorId']) && isset($mapaOrientadores[$h['orientadorId']]))
                ? $mapaOrientadores[$h['orientadorId']]
                : null;
            // Grupo sin nada configurado y sin alumnos: no se guarda (o se borra si ya existía)
            if ($grupo->exists ? $this->borrarGrupoSiEstaVacio($grupo) : (!$grupo->hora_entrada && !$grupo->hora_salida && !$grupo->orientador_id)) {
                continue;
            }

            $grupo->save();
        }
    }

    /** Solo acepta horas con formato HH:MM (o HH:MM:SS); cualquier otra cosa se guarda en blanco en vez de arruinar todo el guardado */
    private function horaValida(mixed $hora): ?string
    {
        if (!is_string($hora) || !preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', trim($hora))) {
            return null;
        }

        return trim($hora);
    }

    /** Tolerancia en minutos: número entero de 0 a 180; si viene vacía o inválida se usa el valor normal */
    private function toleranciaValida(mixed $min, int $defecto): int
    {
        if ($min === null || $min === '' || !is_numeric($min)) {
            return $defecto;
        }

        return max(0, min(180, (int) $min));
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

        $pares      = [];   // [alumno, grupo, registro] para guardar los registros en lote
        $nuevas     = [];   // alumnos que no existen: se guardan TODOS juntos al final (antes: un guardado por alumno)
        $indice     = [];   // 'c:CURP' | 'k:id-del-navegador' => posición en $nuevas (para no duplicar dentro del mismo lote)
        $paresNuevos = [];  // [posición en $nuevas, grupo, registro]
        $ahora      = now();

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
            $gruposCache[$k] ??= Grupo::select('id', 'nombre', 'grado', 'turno', 'hora_entrada', 'tolerancia_entrada')
                ->firstOrCreate(['grado' => $grado, 'nombre' => $nombreGrupo, 'turno' => $turno]);
            $grupo  = $gruposCache[$k];
            $nombre = mb_substr((string) ($a['nombre'] ?? ''), 0, 150);
            $claveNav = ($clave !== null && !str_starts_with($clave, 'srv-')) ? $clave : null;   // id que puso el navegador

            // Primero por CURP; si no, por el id del navegador (alumno de lista sin CURP, o al que se le acaba de asignar)
            $alumno = $curp !== '' ? $existentes->get($curp) : null;
            $alumno ??= $clave !== null ? $porClave->get($clave) : null;
            $alumno ??= ($clave !== null && str_starts_with($clave, 'srv-')) ? Alumno::find((int) substr($clave, 4)) : null;

            if (!$alumno) {
                // ¿Ya viene en este mismo lote? Entonces solo se actualizan sus datos (gana el último, igual que antes)
                $i = ($curp !== '' ? ($indice['c:' . $curp] ?? null) : null)
                    ?? ($claveNav !== null ? ($indice['k:' . $claveNav] ?? null) : null);

                if ($i !== null) {
                    $nuevas[$i]['nombre']   = $nombre;
                    $nuevas[$i]['grupo_id'] = $grupo->id;
                    if ($curp !== '') {
                        $nuevas[$i]['matricula'] = $curp;
                        $indice['c:' . $curp] = $i;
                    }
                    $paresNuevos[] = [$i, $grupo, $a['reg'] ?? []];
                    continue;
                }

                if ($curp === '' && $claveNav === null) {
                    // Caso raro (id "srv-N" que ya no existe y sin CURP): no habría forma de encontrarlo después de guardarlo en lote
                    $alumno = Alumno::create(['matricula' => null, 'nombre' => $nombre, 'grupo_id' => $grupo->id, 'client_key' => null]);
                } else {
                    $i = count($nuevas);
                    $nuevas[$i] = [
                        'matricula'  => $curp !== '' ? $curp : null,
                        'nombre'     => $nombre,
                        'grupo_id'   => $grupo->id,
                        'client_key' => $claveNav,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                    if ($curp !== '') {
                        $indice['c:' . $curp] = $i;
                    }
                    if ($claveNav !== null) {
                        $indice['k:' . $claveNav] = $i;
                    }
                    $paresNuevos[] = [$i, $grupo, $a['reg'] ?? []];
                    continue;
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

        // Alumnos nuevos: se guardan de 200 en 200 (con la base de datos en la nube cada consulta tarda decenas de milisegundos)
        // y una consulta para recuperar sus ids, que hacen falta para guardar sus entradas y salidas.
        if ($nuevas) {
            foreach (array_chunk($nuevas, 200) as $bloque) {
                DB::table('alumnos')->insert($bloque);
            }

            $porCurpNueva  = collect();
            $porClaveNueva = collect();
            $curpsNuevas   = array_values(array_filter(array_column($nuevas, 'matricula')));
            $clavesNuevas  = array_values(array_filter(array_column($nuevas, 'client_key')));

            foreach (array_chunk($curpsNuevas, 500) as $bloque) {
                $porCurpNueva = $porCurpNueva->concat(Alumno::whereIn('matricula', $bloque)->get(['id', 'matricula', 'nombre', 'grupo_id', 'client_key']));
            }
            foreach (array_chunk($clavesNuevas, 500) as $bloque) {
                $porClaveNueva = $porClaveNueva->concat(Alumno::whereIn('client_key', $bloque)->get(['id', 'matricula', 'nombre', 'grupo_id', 'client_key']));
            }
            $porCurpNueva  = $porCurpNueva->keyBy('matricula');
            $porClaveNueva = $porClaveNueva->keyBy('client_key');

            foreach ($paresNuevos as [$i, $grupo, $reg]) {
                $fila   = $nuevas[$i];
                $alumno = $fila['matricula'] !== null
                    ? $porCurpNueva->get($fila['matricula'])
                    : $porClaveNueva->get($fila['client_key']);

                if ($alumno) {
                    $pares[] = [$alumno, $grupo, $reg];
                }
            }
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
        $grupos     = Grupo::select('id', 'hora_entrada', 'tolerancia_entrada')
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
     * Compara contra lo que ya hay en la base de datos (UNA consulta) y solo escribe lo nuevo o lo que cambió de estatus:
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
            $horaLimite = $grupo ? $grupo->limiteEntrada() : null;   // hora límite + tolerancia de entrada

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
        // La primera hora registrada de cada día (entrada y salida) es la que vale: no se agrega una segunda
        $tiene = [];
        foreach ($hay as $k => $est) {
            [$aid, $fh] = explode('|', $k, 2);
            $tiene[$aid . '|' . substr($fh, 0, 10) . '|' . ($est === 'Salida' ? 'S' : 'E')] = true;
        }
        foreach ($deseado as $clave => $estatus) {
            [$alumnoId, $fechaHora] = explode('|', $clave, 2);

            if (!array_key_exists($clave, $hay)) {
                $tipoDia = $alumnoId . '|' . substr($fechaHora, 0, 10) . '|' . ($estatus === 'Salida' ? 'S' : 'E');
                if (isset($tiene[$tipoDia])) {
                    continue;
                }
                $tiene[$tipoDia] = true;
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
