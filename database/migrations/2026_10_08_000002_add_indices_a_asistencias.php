<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Índices (atajos de búsqueda) para que /api/sica/estado y cada escaneo respondan rápido aunque crezcan las asistencias. */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->index(['alumno_id', 'fecha_hora'], 'asistencias_alumno_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropIndex('asistencias_alumno_fecha_idx');
        });
    }
};
