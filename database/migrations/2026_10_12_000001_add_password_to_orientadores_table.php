<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Contraseña propia de cada orientador (guardada cifrada). Vacía = usa la contraseña general del rol Orientador. */
    public function up(): void
    {
        Schema::table('orientadores', function (Blueprint $table) {
            $table->string('password')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('orientadores', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
