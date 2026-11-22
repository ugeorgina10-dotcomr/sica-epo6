<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Orientador extends Model
{
    use HasFactory;

    protected $table = 'orientadores';

    protected $fillable = [
        'nombre',
    ];

    // La contraseña nunca se muestra hacia afuera y solo se cambia desde ClavesController
    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',   // al asignarla se guarda cifrada
        ];
    }

    // Relación: Un orientador puede tener asignados varios grupos
    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
}