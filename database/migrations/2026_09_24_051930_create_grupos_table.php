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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej. "1° 1", "1° 2", etc.
            $table->string('turno'); // Matutino o Vespertino
            $table->timestamps();
        });
    }

    /**
     * Deshace los cambios (borra las tablas).
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
