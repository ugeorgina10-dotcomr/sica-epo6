<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Datos sueltos que deben guardarse en la base de datos (por ejemplo, la plantilla oficial de credenciales). */
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->longText('valor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
