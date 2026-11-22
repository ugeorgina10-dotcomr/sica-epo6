<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Las cuentas creadas antes de agregar la columna `rol` quedaron todas como 'orientador'
     * (valor normal). Aquí se asigna a cada cuenta oficial su rol correcto según su correo.
     * No toca contraseñas ni otros datos. Es seguro ejecutarla varias veces.
     */
    public function up(): void
    {
        $roles = [
            'control.escolar@epo6.edu.mx' => 'control',
            'director@epo6.edu.mx'        => 'director',
            'subdirector@epo6.edu.mx'     => 'subdirector',
            'orientador1@epo6.edu.mx'     => 'orientador',
        ];

        foreach ($roles as $correo => $rol) {
            DB::table('users')->where('email', $correo)->update(['rol' => $rol]);
        }
    }

    public function down(): void
    {
        // Sin reversa: no hay forma de saber qué rol tenía cada cuenta antes.
    }
};
