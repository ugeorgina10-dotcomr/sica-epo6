<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los Word nuevos (public/plantillas) son los oficiales. Si antes se subió una plantilla desde el sistema,
     * quedó guardada en la base de datos y tendría prioridad sobre los archivos: se borra para que se usen los nuevos.
     */
    public function up(): void
    {
        if (Schema::hasTable('ajustes')) {
            DB::table('ajustes')->where('clave', 'like', 'plantilla\_docx\_%')->delete();
        }
    }

    public function down(): void
    {
        // Lo borrado no se puede recuperar
    }
};
