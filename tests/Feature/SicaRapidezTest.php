<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SicaRapidezTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(UsuarioSeeder::class);
    }

    private function datos(array $alumnos = []): array
    {
        $horarios = [];
        foreach (['Matutino', 'Vespertino'] as $t) {
            foreach ([1, 2, 3] as $g) {
                foreach ([1, 2, 3, 4] as $n) {
                    $horarios["$t-$g-$n"] = ['entrada' => '7:10', 'salida' => '14:00', 'orientadorId' => ''];
                }
            }
        }

        return ['orientadores' => [], 'horarios' => $horarios, 'plantillas' => [], 'alumnos' => $alumnos];
    }

    public function test_guardar_los_mismos_horarios_otra_vez_casi_no_gasta_consultas(): void
    {
        $c = $this->actingAs(User::where('rol', 'control')->first());
        $c->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => $this->datos()])->assertOk();
        $this->assertSame(24, Grupo::count());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $c->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => $this->datos()])->assertOk();
        $consultas = count(DB::getQueryLog());
        fwrite(STDERR, "\nCONSULTAS en un guardado sin cambios (24 grupos): $consultas\n");

        $this->assertLessThan(10, $consultas);
        $this->assertSame(24, Grupo::count());
    }

    public function test_un_cambio_de_horario_si_se_guarda_aunque_venga_con_otro_formato(): void
    {
        $c = $this->actingAs(User::where('rol', 'control')->first());
        $c->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => $this->datos()])->assertOk();

        $otroAntes = Grupo::where('grado', 1)->where('nombre', '1° 2')->where('turno', 'Matutino')->value('hora_entrada');

        $d = $this->datos();
        $d['horarios']['Matutino-1-1']['entrada'] = '07:30';
        $c->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => $d])->assertOk();

        $g = Grupo::where('grado', 1)->where('nombre', '1° 1')->where('turno', 'Matutino')->first();
        $this->assertSame('07:30', substr($g->hora_entrada, 0, 5));
        $otro = Grupo::where('grado', 1)->where('nombre', '1° 2')->where('turno', 'Matutino')->first();
        $this->assertSame($otroAntes, $otro->hora_entrada);   // los demás grupos no se tocaron
    }
}
