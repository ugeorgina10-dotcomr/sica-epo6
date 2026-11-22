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
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('matricula')->unique(); // CURP o matrícula
            $table->string('nombre');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Deshace los cambios (borra las tablas).
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
