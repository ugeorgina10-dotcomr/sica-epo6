<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Support\MovimientoAutomatico;
use Carbon\Carbon;

class LectorFijoController extends Controller
{
    // Muestra la vista principal del panel de lectura/accesos
    public function index(Request $request)
    {
        $query = Asistencia::with('alumno.grupo');

        if ($request->filled('buscar')) {
            $busqueda = $request->buscar;
            $query->whereHas('alumno', function($q) use ($busqueda) {
                // En Postgres LIKE distingue mayúsculas (en MySQL no): se compara siempre en minúsculas para que busque igual en ambos
                $patron = '%' . mb_strtolower($busqueda) . '%';
                $q->whereRaw('LOWER(matricula) LIKE ?', [$patron])
                  ->orWhereRaw('LOWER(nombre) LIKE ?', [$patron]);
            });
        }

        $asistencias = $query->orderBy('fecha_hora', 'desc')->paginate(15);
        
        // Asegúrate de que la vista apunte a tu carpeta o archivo existente
        return view('sica.asistencias', compact('asistencias'));
    }

    // Registra la entrada o salida mediante el lector fijo o escáner.
    // Sin tipo_movimiento (o con "Auto") el sistema decide solo, según el horario del grupo del alumno.
    public function registrar(Request $request)
    {
        $request->validate([
            'matricula' => 'required|string',
            'tipo_movimiento' => 'nullable|in:Entrada,Salida,Auto'
        ]);

        $alumno = Alumno::with('grupo')->where('matricula', $request->matricula)->first();

        if (!$alumno) {
            return response()->json([
                'success' => false, 
                'message' => 'Matrícula no encontrada en el sistema.'
            ], 404);
        }

        $ahora = Carbon::now();
        $grupo = $alumno->grupo;
        $tipo = $request->input('tipo_movimiento', 'Auto');

        [$minEntrada, $minSalida] = MovimientoAutomatico::horario($grupo->hora_entrada ?? null, $grupo->hora_salida ?? null, $grupo->turno ?? null);

        if ($tipo === 'Auto') {
            $hoy = Asistencia::where('alumno_id', $alumno->id)->whereDate('fecha_hora', $ahora->toDateString())->get();
            $entrada = $hoy->first(fn ($x) => $x->estatus !== 'Salida');
            $salida = $hoy->first(fn ($x) => $x->estatus === 'Salida');

            $tipo = MovimientoAutomatico::resolver((int) $ahora->format('G') * 60 + (int) $ahora->format('i'), $minEntrada, $minSalida, (bool) $entrada, (bool) $salida);

            if ($tipo === 'Completo') {
                return response()->json(['success' => false, 'message' => "{$alumno->nombre}: la entrada y la salida de hoy ya estaban registradas"], 409);
            }
            if ($tipo === 'Salida' && $entrada && MovimientoAutomatico::esRepetida((int) $ahora->format('G') * 60 + (int) $ahora->format('i'), $minEntrada, $minSalida)) {
                return response()->json(['success' => false, 'message' => "{$alumno->nombre}: la entrada ya estaba registrada"], 409);
            }
        }

        $estatus = 'Asistencia';

        // Retardo: se compara contra la hora de entrada del GRUPO del alumno (ya no una hora fija)
        if ($tipo === 'Entrada') {
            if ((int) $ahora->format('G') * 60 + (int) $ahora->format('i') > $minEntrada) {
                $estatus = 'Retardo';
            }
        } else {
            $estatus = 'Salida';
        }

        Asistencia::create([
            'alumno_id' => $alumno->id,
            'estatus' => $estatus,
            'fecha_hora' => $ahora
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$tipo} registrada: {$alumno->nombre} ({$estatus})",
            'alumno' => $alumno->nombre,
            'estatus' => $estatus,
            'tipo' => $tipo
        ]);
    }
}
