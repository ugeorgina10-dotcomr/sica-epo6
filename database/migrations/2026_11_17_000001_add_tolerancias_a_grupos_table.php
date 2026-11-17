<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tolerancia (en minutos) de cada grupo:
     *  - tolerancia_entrada: minutos después de la hora límite de entrada en que todavía NO cuenta como "Retardo entrada" (0 = sin tolerancia, como antes).
     *  - tolerancia_salida:  minutos después de la hora de cierre en que todavía NO cuenta como "Retardo salida" (30 por defecto,
     *    porque los alumnos escanean su salida justo después de la hora de cierre).
     */
    public function up(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            if (!Schema::hasColumn('grupos', 'tolerancia_entrada')) {
                $table->unsignedSmallInteger('tolerancia_entrada')->default(0)->after('hora_salida');
            }
            if (!Schema::hasColumn('grupos', 'tolerancia_salida')) {
                $table->unsignedSmallInteger('tolerancia_salida')->default(30)->after('tolerancia_entrada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropColumn(['tolerancia_entrada', 'tolerancia_salida']);
        });
    }
};
