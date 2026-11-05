<?php

namespace Tests\Feature;

use App\Models\Orientador;
use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SicaOlvidoAvisoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(UsuarioSeeder::class);
    }

    public function test_sin_solicitudes_la_pantalla_de_inicio_no_muestra_nada(): void
    {
        $this->getJson('/api/sica/olvidos')->assertOk()->assertJson(['ok' => true, 'roles' => [], 'orientadores' => []]);
    }

    public function test_al_pulsar_olvide_la_burbuja_aparece_al_instante_sin_iniciar_sesion(): void
    {
        $this->getJson('/api/sica/olvidos')->assertOk();   // deja la respuesta vacía en caché

        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        $r = $this->getJson('/api/sica/olvidos')->assertOk();
        $this->assertSame('director', $r->json('roles.0.rol'));
        $this->assertTrue($r->json('roles.0.bloqueada'));
        $this->assertNotEmpty($r->json('roles.0.olvido_at'));
    }

    public function test_un_orientador_que_olvido_su_clave_aparece_con_su_nombre(): void
    {
        $o = Orientador::create(['nombre' => 'Ana Pérez']);
        $this->getJson('/api/sica/olvidos')->assertOk();

        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'Ana Pérez'])->assertOk();

        $r = $this->getJson('/api/sica/olvidos')->assertOk();
        $this->assertSame('Ana Pérez', $r->json('orientadores.0.nombre'));
        $this->assertSame((string) $o->id, $r->json('orientadores.0.id'));
    }

    public function test_cuando_control_da_acceso_la_burbuja_desaparece(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();
        $this->assertCount(1, $this->getJson('/api/sica/olvidos')->json('roles'));

        $this->actingAs(User::where('rol', 'control')->firstOrFail())
            ->postJson('/api/sica/claves/rol/director/desbloquear')->assertOk();

        $this->assertCount(0, $this->getJson('/api/sica/olvidos')->json('roles'));
    }
}
