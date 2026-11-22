<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

    protected $fillable = [
        'nombre',
        'turno',
        'grado',
        'orientador_id',
        'hora_entrada',
        'hora_salida',
        'tolerancia_entrada',
        'tolerancia_salida',
        'plantilla_frente',
        'plantilla_reverso',
    ];

    /** Hora límite de entrada más la tolerancia ("HH:MM"): después de esa hora la entrada cuenta como Retardo. Queda vacío si el grupo no tiene horario. */
    public function limiteEntrada(): ?string
    {
        $m = \App\Support\MovimientoAutomatico::aMinutos($this->hora_entrada);
        if ($m === null) {
            return null;
        }
        $m = min($m + (int) ($this->tolerancia_entrada ?? 0), 1439);

        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    // Relación: Un grupo tiene muchos alumnos
    public function alumnos()
    {
        return $this->hasMany(Alumno::class);
    }

    // Relación: Un grupo tiene un orientador(a) asignado
    public function orientador()
    {
        return $this->belongsTo(Orientador::class);
    }
}