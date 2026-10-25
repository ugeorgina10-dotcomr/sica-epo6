<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Las plantillas de credencial ahora son archivos de Word (.docx): se borran las imágenes que se guardaron antes. */
    public function up(): void
    {
        if (Schema::hasTable('ajustes')) {
            DB::table('ajustes')->whereIn('clave', [
                'plantilla_matutino_frente', 'plantilla_matutino_reverso',
                'plantilla_vespertino_frente', 'plantilla_vespertino_reverso',
            ])->delete();
        }
    }

    public function down(): void
    {
        // Las imágenes borradas no se pueden recuperar
    }
};
