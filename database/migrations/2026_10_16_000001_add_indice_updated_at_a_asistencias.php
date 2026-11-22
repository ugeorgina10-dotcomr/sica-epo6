<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** La fecha de última modificación que usa /api/sica/estado ahora se busca con este atajo (índice) en vez de recorrer toda la tabla. */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->index('updated_at', 'asistencias_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropIndex('asistencias_updated_idx');
        });
    }
};
