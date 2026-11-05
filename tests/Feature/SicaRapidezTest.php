<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Asistencia;
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

    private function enviarAlumnos(array $alumnos)
    {
        return $this->actingAs(User::where('rol', 'control')->first())
            ->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => ['orientadores' => [], 'horarios' => [], 'plantillas' => [], 'alumnos' => $alumnos]]);
    }

    public function test_subir_una_lista_grande_no_hace_una_consulta_por_alumno(): void
    {
        $alumnos = [];
        for ($i = 1; $i <= 60; $i++) {
            $alumnos[] = ['id' => "id_$i", 'curp' => sprintf('PELA05%04dMMCRPN09', $i), 'nombre' => "ALUMNO $i", 'grado' => 1, 'grupo' => 2,
                          'turno' => 'Matutino', 'reg' => ['2026-11-02' => ['entrada' => '07:05']]];
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->enviarAlumnos($alumnos)->assertOk();
        $consultas = count(DB::getQueryLog());
        fwrite(STDERR, "\nCONSULTAS al subir una lista de 60 alumnos nuevos: $consultas\n");

        $this->assertSame(60, Alumno::count());
        $this->assertSame(60, Asistencia::count());
        $this->assertLessThan(25, $consultas);   // antes: más de 60 (un INSERT por alumno)
    }

    public function test_el_mismo_alumno_repetido_en_un_lote_no_se_duplica(): void
    {
        $base = ['id' => 'id_x', 'curp' => '', 'nombre' => 'UNO', 'grado' => 1, 'grupo' => 1, 'turno' => 'Matutino', 'reg' => []];
        $conCurp = ['curp' => 'PELA050101MMCRPN09', 'nombre' => 'UNO CORREGIDO'] + $base;

        $this->enviarAlumnos([$base, $conCurp, $conCurp])->assertOk();

        $this->assertSame(1, Alumno::count());
        $this->assertSame('PELA050101MMCRPN09', Alumno::first()->matricula);
        $this->assertSame('UNO CORREGIDO', Alumno::first()->nombre);

        // y al volver a mandarlo no crea otro
        $this->enviarAlumnos([$conCurp])->assertOk();
        $this->assertSame(1, Alumno::count());
    }

    public function test_borrar_la_lista_de_un_grupo_borra_alumnos_asistencias_y_el_grupo_vacio(): void
    {
        $alumnos = [];
        foreach ([1, 2, 3] as $i) {
            $alumnos[] = ['id' => "id_$i", 'curp' => sprintf('PELA05%04dMMCRPN09', $i), 'nombre' => "ALUMNO $i", 'grado' => 1, 'grupo' => 2,
                          'turno' => 'Matutino', 'reg' => ['2026-11-02' => ['entrada' => '07:05']]];
        }
        $this->enviarAlumnos($alumnos)->assertOk();
        $this->assertSame(3, Asistencia::count());

        $this->actingAs(User::where('rol', 'control')->first())
            ->postJson('/api/sica/sync', ['accion' => 'eliminar_lista_grupo', 'datos' => ['grado' => 1, 'grupo' => 2, 'turno' => 'Matutino']])
            ->assertOk()->assertJson(['ok' => true, 'borrados' => 3]);

        $this->assertSame(0, Alumno::count());
        $this->assertSame(0, Asistencia::count());
        $this->assertSame(0, Grupo::count());
    }
}
