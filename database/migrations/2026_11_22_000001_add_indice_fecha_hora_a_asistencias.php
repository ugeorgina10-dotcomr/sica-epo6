<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Atajo (índice) por fecha: leer solo los últimos días de asistencias (lo que pide cada escaneo y cada actualización) ya no recorre toda la tabla. */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->index('fecha_hora', 'asistencias_fecha_hora_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropIndex('asistencias_fecha_hora_idx');
        });
    }
};
