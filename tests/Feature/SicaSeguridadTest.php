<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\Grupo;
use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SicaSeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        RateLimiter::clear('director@epo6.edu.mx|127.0.0.1');
        $this->seed(UsuarioSeeder::class);
    }

    private function como(string $rol): static
    {
        return $this->actingAs(User::where('rol', $rol)->firstOrFail());
    }

    private function alumnoDePrueba(): Alumno
    {
        $g = Grupo::create(['grado' => 1, 'nombre' => '1° 1', 'turno' => 'Matutino']);

        return Alumno::create(['matricula' => 'AAAA000101HMCXXX01', 'nombre' => 'Alumno Uno', 'grupo_id' => $g->id]);
    }

    public function test_todas_las_cuentas_usan_prepa06_guardada_con_hash(): void
    {
        foreach (User::all() as $u) {
            $this->assertNotSame('prepa06', $u->getAuthPassword());
            $this->assertStringStartsWith('$2y$', $u->getAuthPassword());
            $this->assertTrue(Hash::check('prepa06', $u->getAuthPassword()));
        }
        $this->assertSame(4, User::count());
    }

    public function test_login_correcto_devuelve_el_rol_del_servidor(): void
    {
        $this->postJson('/auth/login', ['email' => 'DIRECTOR@epo6.edu.mx', 'password' => 'prepa06'])
            ->assertOk()->assertJsonPath('rol', 'director');
    }

    public function test_login_con_clave_incorrecta_se_rechaza(): void
    {
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'otra'])->assertStatus(422);
    }

    public function test_login_se_bloquea_tras_5_intentos_fallidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'x'])->assertStatus(422);
        }
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertStatus(429);
    }

    public function test_sin_sesion_no_se_ve_el_estado(): void
    {
        $this->getJson('/api/sica/estado')->assertStatus(401);
    }

    public function test_orientador_no_puede_eliminar_alumnos_ni_cambiar_permisos(): void
    {
        $this->alumnoDePrueba();

        $this->como('orientador')->postJson('/api/sica/sync', ['accion' => 'eliminar_alumno', 'datos' => ['curp' => 'AAAA000101HMCXXX01']])->assertForbidden();
        $this->postJson('/api/sica/sync', ['accion' => 'guardar_permisos', 'datos' => ['director' => 'editar']])->assertForbidden();
        $this->assertSame(1, Alumno::count());
    }

    public function test_orientador_no_puede_crear_alumnos_con_guardar_todo(): void
    {
        $this->como('orientador')->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => [
            'alumnos' => [['curp' => 'HACKER00000000001', 'nombre' => 'Intruso', 'grado' => 1, 'grupo' => 1, 'turno' => 'Matutino', 'reg' => []]],
        ]])->assertForbidden();

        $this->assertSame(0, Alumno::count());
    }

    public function test_director_solo_ve_y_no_puede_guardar(): void
    {
        $this->como('director')->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => ['alumnos' => []]])->assertForbidden();
        $this->getJson('/api/sica/estado')->assertOk();
    }

    public function test_accion_desconocida_se_rechaza(): void
    {
        $this->como('orientador')->postJson('/api/sica/sync', ['accion' => 'borrar_todo', 'datos' => ['x' => 1]])->assertForbidden();
    }

    public function test_control_si_puede_crear_y_eliminar_alumnos(): void
    {
        $this->como('control')->postJson('/api/sica/sync', ['accion' => 'guardar_todo', 'datos' => [
            'alumnos' => [['curp' => 'AAAA000101HMCXXX01', 'nombre' => 'Alumno Uno', 'grado' => 1, 'grupo' => 1, 'turno' => 'Matutino', 'reg' => []]],
        ]])->assertOk();
        $this->assertSame(1, Alumno::count());

        $this->postJson('/api/sica/sync', ['accion' => 'eliminar_alumno', 'datos' => ['curp' => 'AAAA000101HMCXXX01']])->assertOk();
        $this->assertSame(0, Alumno::count());
    }

    public function test_permisos_invalidos_se_rechazan(): void
    {
        $this->como('control')->postJson('/api/sica/sync', ['accion' => 'guardar_permisos', 'datos' => ['hacker' => 'editar']])->assertStatus(422);
        $this->postJson('/api/sica/sync', ['accion' => 'guardar_permisos', 'datos' => ['director' => 'todo']])->assertStatus(422);
    }

    public function test_el_escaneo_usa_la_hora_del_servidor(): void
    {
        $a = $this->alumnoDePrueba();
        $this->travelTo(now()->setTime(8, 30));

        $this->como('orientador')->postJson('/api/sica/sync', ['accion' => 'registrar_asistencia', 'datos' => [
            'curp' => $a->matricula, 'modo' => 'Entrada', 'fecha' => '2001-01-01', 'hora' => '01:00',
        ]])->assertOk()->assertJsonPath('hora', '08:30');

        $this->assertSame(now()->format('Y-m-d') . ' 08:30:00', $a->asistencias()->first()->fecha_hora);
    }

    public function test_cabeceras_de_seguridad_presentes(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
