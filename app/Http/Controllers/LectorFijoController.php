<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Asistencia;
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
                $q->where('matricula', 'like', "%{$busqueda}%")
                  ->orWhere('nombre', 'like', "%{$busqueda}%");
            });
        }

        $asistencias = $query->orderBy('fecha_hora', 'desc')->paginate(15);
        
        // Asegúrate de que la vista apunte a tu carpeta o archivo existente
        return view('sica.asistencias', compact('asistencias'));
    }

    // Registra la entrada o salida mediante el lector fijo o escáner
    public function registrar(Request $request)
    {
        $request->validate([
            'matricula' => 'required|string',
            'tipo_movimiento' => 'required|in:Entrada,Salida'
        ]);

        $alumno = Alumno::where('matricula', $request->matricula)->first();

        if (!$alumno) {
            return response()->json([
                'success' => false, 
                'message' => 'Matrícula no encontrada en el sistema.'
            ], 404);
        }

        $ahora = Carbon::now();
        $estatus = 'Asistencia';

        // Lógica de horarios institucionales
        if ($request->tipo_movimiento == 'Entrada') {
            $horaLimiteMatutino = Carbon::createFromTime(7, 10, 0);
            if ($ahora->gt($horaLimiteMatutino)) {
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
            'message' => "Registro exitoso: {$alumno->nombre} ({$estatus})",
            'alumno' => $alumno->nombre,
            'estatus' => $estatus
        ]);
    }
}