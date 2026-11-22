<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea las tablas.
     */
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->enum('estatus', ['Asistencia', 'Retardo', 'Permanencia', 'Salida']);
            $table->timestamp('fecha_hora')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Deshace los cambios (borra las tablas).
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
