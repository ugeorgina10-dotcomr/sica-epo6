<?php

namespace Tests\Feature;

use App\Models\Orientador;
use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SicaClavesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();   // reinicia los límites de peticiones entre pruebas
        $this->seed(UsuarioSeeder::class);
    }

    private function como(string $rol): static
    {
        return $this->actingAs(User::where('rol', $rol)->firstOrFail());
    }

    public function test_control_cambia_la_clave_del_director_y_se_guarda_con_hash(): void
    {
        $this->como('control')->postJson('/api/sica/claves/rol', [
            'rol' => 'director', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026',
        ])->assertOk();

        $hash = User::where('rol', 'director')->value('password');
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::check('NuevaClave2026', $hash));

        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertStatus(422);
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'NuevaClave2026'])->assertOk();
    }

    public function test_los_demas_roles_no_pueden_cambiar_claves(): void
    {
        foreach (['director', 'subdirector', 'orientador'] as $rol) {
            $this->como($rol)->postJson('/api/sica/claves/rol', [
                'rol' => 'control', 'password' => 'Hackeada2026', 'password_confirmation' => 'Hackeada2026',
            ])->assertStatus(403);
            $this->como($rol)->getJson('/api/sica/claves')->assertStatus(403);
        }
        $this->assertTrue(Hash::check('prepa06', User::where('rol', 'control')->value('password')));
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])
            ->assertStatus(401);
    }

    public function test_valida_longitud_confirmacion_y_rol(): void
    {
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'corta', 'password_confirmation' => 'corta'])->assertStatus(422);
        $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'NuevaClave2026', 'password_confirmation' => 'otra'])->assertStatus(422);
        $c->postJson('/api/sica/claves/rol', ['rol' => 'admin', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026'])->assertStatus(422);
    }

    public function test_control_necesita_su_clave_actual_para_cambiar_la_suya(): void
    {
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/rol', ['rol' => 'control', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026', 'password_actual' => 'mala'])
            ->assertStatus(422);
        $c->postJson('/api/sica/claves/rol', ['rol' => 'control', 'password' => 'NuevaClave2026', 'password_confirmation' => 'NuevaClave2026', 'password_actual' => 'prepa06'])
            ->assertOk();
    }

    public function test_no_acepta_una_clave_que_ya_usa_otro_rol(): void
    {
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'ClaveDirector1', 'password_confirmation' => 'ClaveDirector1'])->assertOk();
        $c->postJson('/api/sica/claves/rol', ['rol' => 'subdirector', 'password' => 'ClaveDirector1', 'password_confirmation' => 'ClaveDirector1'])->assertStatus(422);
        $o = Orientador::create(['nombre' => 'Ana']);
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $o->id, 'password' => 'ClaveDirector1', 'password_confirmation' => 'ClaveDirector1'])->assertStatus(422);
    }

    public function test_cada_orientador_entra_con_su_clave_propia(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $luis = Orientador::create(['nombre' => 'Luis']);

        $this->como('control')->postJson('/api/sica/claves/orientador', [
            'orientador_id' => $ana->id, 'password' => 'ClaveDeAna2026', 'password_confirmation' => 'ClaveDeAna2026',
        ])->assertOk();
        $this->assertStringStartsWith('$2y$', Orientador::find($ana->id)->password);

        $correo = 'orientador1@epo6.edu.mx';
        // Ana: solo vale la suya
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'ClaveDeAna2026', 'orientador' => 'Ana'])->assertOk()->assertJsonPath('rol', 'orientador');
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Ana'])->assertStatus(422);
        // Luis no tiene propia: vale la general del rol
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Luis'])->assertOk();
        // La clave de Ana no sirve para entrar como Luis
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'ClaveDeAna2026', 'orientador' => 'Luis'])->assertStatus(422);
    }

    public function test_el_rol_orientador_exige_elegir_un_nombre_valido(): void
    {
        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'prepa06'])->assertStatus(422);
        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'prepa06', 'orientador' => 'No existe'])->assertStatus(422);
    }

    public function test_quitar_la_clave_propia_regresa_a_la_general(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'ClaveDeAna2026', 'password_confirmation' => 'ClaveDeAna2026'])->assertOk();
        $c->deleteJson('/api/sica/claves/orientador/' . $ana->id)->assertOk();
        $this->assertNull(Orientador::find($ana->id)->password);
        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'prepa06', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_la_clave_nunca_sale_en_las_respuestas(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'ClaveDeAna2026', 'password_confirmation' => 'ClaveDeAna2026'])->assertOk();

        $lista = $c->getJson('/api/sica/claves')->assertOk()->assertJsonPath('orientadores.0.estado', 'propia')->getContent();
        $this->assertStringNotContainsString('$2y$', $lista);
        $this->assertStringNotContainsString('ClaveDeAna2026', $lista);
        $this->assertStringNotContainsString('$2y$', $c->getJson('/api/sica/estado')->getContent());
        $this->assertArrayNotHasKey('password', Orientador::find($ana->id)->toArray());
    }

    public function test_rechaza_claves_que_no_cumplen_las_indicaciones(): void
    {
        $c = $this->como('control');
        foreach (['todominuscula1' => 'mayúscula', 'TODOMAYUSCULA1' => 'mayúscula', 'SinNumeroAlguno' => 'número',
                  'Con Espacio 12' => 'espacios', 'Segura#Clave12' => 'especiales', 'Contraseña2026X' => 'especiales', 'Clave_Segura12' => 'especiales', 'MiPrepa06Nueva' => 'escuela', 'Epo6Segura123' => 'escuela'] as $clave => $pista) {
            $r = $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => $clave, 'password_confirmation' => $clave]);
            $r->assertStatus(422);
            $this->assertStringContainsStringIgnoringCase($pista, $r->json('errors.password.0'), $clave);
        }
        $this->assertTrue(Hash::check('prepa06', User::where('rol', 'director')->value('password')));
    }

    public function test_control_borra_la_clave_de_un_rol_y_ese_rol_no_entra_hasta_tener_una_nueva(): void
    {
        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/director')->assertOk();

        $this->assertSame('', DB::table('users')->where('rol', 'director')->value('password'));   // no queda ni un hash
        $r = $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertStatus(422);
        $this->assertStringContainsString('Pide a Control Escolar', $r->json('mensaje'));
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => ''])->assertStatus(422);

        // Control genera una nueva y vuelve a entrar
        $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'DirectorNueva26', 'password_confirmation' => 'DirectorNueva26'])->assertOk();
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'DirectorNueva26'])->assertOk();
    }

    public function test_no_se_puede_borrar_la_clave_de_control_ni_de_un_rol_inventado(): void
    {
        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/control')->assertStatus(422);
        $c->deleteJson('/api/sica/claves/rol/admin')->assertStatus(422);
        $this->assertTrue(Hash::check('prepa06', User::where('rol', 'control')->value('password')));
    }

    public function test_solo_control_puede_borrar_claves(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        foreach (['director', 'subdirector', 'orientador'] as $rol) {
            $this->como($rol)->deleteJson('/api/sica/claves/rol/director')->assertStatus(403);
            $this->como($rol)->postJson("/api/sica/claves/orientador/{$ana->id}/borrar")->assertStatus(403);
        }
        $this->assertSame(0, DB::table('users')->where('password', '')->count());
        $this->assertNull(Orientador::find($ana->id)->password);
    }

    public function test_borrar_la_clave_de_un_orientador_no_lo_deja_entrar_ni_con_la_general(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        Orientador::create(['nombre' => 'Luis']);
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'ClaveDeAna2026', 'password_confirmation' => 'ClaveDeAna2026'])->assertOk();

        $c->postJson("/api/sica/claves/orientador/{$ana->id}/borrar")->assertOk();
        $this->assertSame('', DB::table('orientadores')->where('id', $ana->id)->value('password'));

        $correo = 'orientador1@epo6.edu.mx';
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'ClaveDeAna2026', 'orientador' => 'Ana'])->assertStatus(422);
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Ana'])->assertStatus(422);
        // Luis no se ve afectado
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Luis'])->assertOk();

        // Control le genera una nueva y entra (el login de Luis cambió la sesión, así que Control vuelve a entrar)
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'AnaNueva2026x', 'password_confirmation' => 'AnaNueva2026x'])->assertOk();
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'AnaNueva2026x', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_si_se_borra_la_general_los_orientadores_con_clave_propia_siguen_entrando(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'ClaveDeAna2026', 'password_confirmation' => 'ClaveDeAna2026'])->assertOk();
        $c->deleteJson('/api/sica/claves/rol/orientador')->assertOk();

        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'ClaveDeAna2026', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_el_estado_de_las_claves_no_incluye_hashes_y_marca_las_borradas(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/subdirector')->assertOk();
        $c->postJson("/api/sica/claves/orientador/{$ana->id}/borrar")->assertOk();

        $r = $c->getJson('/api/sica/claves')->assertOk();
        $this->assertStringNotContainsString('$2y$', $r->getContent());
        $this->assertSame('borrada', $r->json('orientadores.0.estado'));
        $this->assertSame(['subdirector'], collect($r->json('roles'))->where('borrada', true)->pluck('rol')->values()->all());
    }

    public function test_borrar_una_clave_no_toca_ningun_dato(): void
    {
        $g = \App\Models\Grupo::create(['grado' => 1, 'nombre' => '1° 1', 'turno' => 'Matutino']);
        \App\Models\Alumno::create(['matricula' => 'AAAA000101HMCXXX01', 'nombre' => 'Alumno Uno', 'grupo_id' => $g->id]);
        $ana = Orientador::create(['nombre' => 'Ana']);
        \App\Models\Permiso::updateOrCreate(['rol' => 'director'], ['tipo' => 'editar']);
        $cuentas = fn () => [User::count(), DB::table('grupos')->count(), DB::table('alumnos')->count(), DB::table('orientadores')->count(), DB::table('permisos')->count(),
            User::orderBy('id')->pluck('email', 'rol')->all(), DB::table('permisos')->where('rol', 'director')->value('tipo')];
        $antes = $cuentas();

        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/director')->assertOk();
        $c->postJson("/api/sica/claves/orientador/{$ana->id}/borrar")->assertOk();

        $this->assertSame($antes, $cuentas());
    }

    public function test_el_despliegue_no_restaura_prepa06_en_cuentas_borradas(): void
    {
        $this->como('control')->deleteJson('/api/sica/claves/rol/director')->assertOk();

        $this->artisan('sica:crear-usuarios')->assertSuccessful();   // se puede ejecutar las veces que sea necesario

        $this->assertSame('', DB::table('users')->where('rol', 'director')->value('password'));
        $this->assertTrue(Hash::check('prepa06', User::where('rol', 'subdirector')->value('password')));   // las demás no cambian
    }

    public function test_restablecer_claves_da_acceso_con_hash_cuando_control_olvido_la_suya(): void
    {
        DB::table('users')->where('rol', 'control')->update(['password' => '']);

        $this->artisan('sica:restablecer-claves', ['--clave' => 'Restablecida2026'])->assertSuccessful();

        foreach (User::all() as $u) {
            $this->assertStringStartsWith('$2y$', $u->getAuthPassword());
        }
        $this->postJson('/auth/login', ['email' => 'control.escolar@epo6.edu.mx', 'password' => 'Restablecida2026'])->assertOk();
        $this->artisan('sica:restablecer-claves', ['--clave' => 'debil'])->assertFailed();
    }

    // ===== "¿Olvidaste tu contraseña?": bloqueo hasta que Control Escolar genere una nueva =====

    public function test_olvide_bloquea_la_cuenta_aunque_despues_escriba_la_contrasena_correcta(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])
            ->assertOk()->assertJsonPath('bloqueada', true);

        $this->assertNotNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));

        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])
            ->assertStatus(422)->assertJsonPath('bloqueada', true)->assertJsonPath('motivo', 'olvido');
        // Con una contraseña incorrecta responde igual: a una cuenta bloqueada nunca se le revisa la contraseña
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'cualquiera'])
            ->assertStatus(422)->assertJsonPath('motivo', 'olvido');
        $this->assertGuest();

        // Las demás cuentas no se afectan
        $this->postJson('/auth/login', ['email' => 'subdirector@epo6.edu.mx', 'password' => 'prepa06'])->assertOk();
    }

    public function test_olvide_de_un_orientador_bloquea_solo_a_ese_orientador(): void
    {
        Orientador::create(['nombre' => 'Ana']);
        Orientador::create(['nombre' => 'Luis']);
        $correo = 'orientador1@epo6.edu.mx';

        $this->postJson('/auth/olvide', ['email' => $correo, 'orientador' => 'Ana'])->assertOk()->assertJsonPath('bloqueada', true);

        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Ana'])
            ->assertStatus(422)->assertJsonPath('motivo', 'olvido');
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'prepa06', 'orientador' => 'Luis'])->assertOk();
        $this->assertNull(DB::table('users')->where('rol', 'orientador')->value('olvido_at'));
    }

    public function test_olvide_pide_el_nombre_del_orientador_y_no_revela_que_cuentas_existen(): void
    {
        Orientador::create(['nombre' => 'Ana']);

        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx'])->assertStatus(422);
        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'No existe'])
            ->assertOk()->assertJsonPath('bloqueada', true);
        $this->postJson('/auth/olvide', ['email' => 'nadie@epo6.edu.mx'])
            ->assertOk()->assertJsonPath('bloqueada', true);

        $this->assertSame(0, DB::table('users')->whereNotNull('olvido_at')->count());
        $this->assertSame(0, DB::table('orientadores')->whereNotNull('olvido_at')->count());
    }

    public function test_la_cuenta_de_control_no_se_bloquea_con_olvide(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'control.escolar@epo6.edu.mx'])
            ->assertOk()->assertJsonPath('bloqueada', false);

        $this->assertNull(DB::table('users')->where('rol', 'control')->value('olvido_at'));
        $this->postJson('/auth/login', ['email' => 'control.escolar@epo6.edu.mx', 'password' => 'prepa06'])->assertOk();
    }

    public function test_olvide_tiene_limite_de_peticiones(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/olvide', ['email' => 'nadie@epo6.edu.mx'])->assertOk();
        }
        $this->postJson('/auth/olvide', ['email' => 'nadie@epo6.edu.mx'])->assertStatus(429);
    }

    public function test_olvide_cierra_las_sesiones_abiertas_de_esa_cuenta(): void
    {
        $dir = User::where('rol', 'director')->first();
        DB::table('sessions')->insert([
            'id' => 'sesion-de-prueba', 'user_id' => $dir->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'prueba', 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $dir->id)->count());
    }

    public function test_control_borra_y_genera_nueva_y_el_rol_recupera_el_acceso(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/director')->assertOk();   // Control borra la contraseña anterior
        $this->assertNotNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));   // sigue bloqueada
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertStatus(422);

        $c = $this->como('control');
        $c->postJson('/api/sica/claves/rol', ['rol' => 'director', 'password' => 'DirectorNueva26', 'password_confirmation' => 'DirectorNueva26'])->assertOk();

        $this->assertNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'DirectorNueva26'])->assertOk();
    }

    public function test_control_borra_y_genera_nueva_y_el_orientador_recupera_el_acceso(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $correo = 'orientador1@epo6.edu.mx';
        $this->postJson('/auth/olvide', ['email' => $correo, 'orientador' => 'Ana'])->assertOk();

        $c = $this->como('control');
        $c->postJson("/api/sica/claves/orientador/{$ana->id}/borrar")->assertOk();
        $this->assertNotNull(DB::table('orientadores')->where('id', $ana->id)->value('olvido_at'));

        $c = $this->como('control');
        $c->postJson('/api/sica/claves/orientador', ['orientador_id' => $ana->id, 'password' => 'AnaNueva2026x', 'password_confirmation' => 'AnaNueva2026x'])->assertOk();

        $this->assertNull(DB::table('orientadores')->where('id', $ana->id)->value('olvido_at'));
        $this->postJson('/auth/login', ['email' => $correo, 'password' => 'AnaNueva2026x', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_quitar_la_contrasena_personal_desbloquea_al_orientador(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'Ana'])->assertOk();

        $this->como('control')->deleteJson("/api/sica/claves/orientador/{$ana->id}")->assertOk();

        $this->assertNull(DB::table('orientadores')->where('id', $ana->id)->value('olvido_at'));
        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'prepa06', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_control_puede_desbloquear_sin_cambiar_la_contrasena(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();
        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'Ana'])->assertOk();

        $c = $this->como('control');
        $c->postJson('/api/sica/claves/rol/director/desbloquear')->assertOk();
        $c->postJson("/api/sica/claves/orientador/{$ana->id}/desbloquear")->assertOk();
        $c->postJson('/api/sica/claves/rol/control/desbloquear')->assertStatus(422);

        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertOk();
        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'prepa06', 'orientador' => 'Ana'])->assertOk();
    }

    public function test_desbloquear_no_da_acceso_si_la_contrasena_esta_borrada(): void
    {
        $c = $this->como('control');
        $c->deleteJson('/api/sica/claves/rol/director')->assertOk();
        $c->postJson('/api/sica/claves/rol/director/desbloquear')->assertOk();

        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])
            ->assertStatus(422)->assertJsonPath('bloqueada', true)->assertJsonPath('motivo', 'borrada');
    }

    public function test_solo_control_puede_desbloquear(): void
    {
        $ana = Orientador::create(['nombre' => 'Ana']);
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        foreach (['director', 'subdirector', 'orientador'] as $rol) {
            $this->como($rol)->postJson('/api/sica/claves/rol/director/desbloquear')->assertStatus(403);
            $this->como($rol)->postJson("/api/sica/claves/orientador/{$ana->id}/desbloquear")->assertStatus(403);
        }
        $this->assertNotNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));
    }

    public function test_el_estado_marca_las_cuentas_bloqueadas_por_olvido_y_no_incluye_hashes(): void
    {
        Orientador::create(['nombre' => 'Ana']);
        $this->postJson('/auth/olvide', ['email' => 'subdirector@epo6.edu.mx'])->assertOk();
        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'Ana'])->assertOk();

        $r = $this->como('control')->getJson('/api/sica/claves')->assertOk();

        $this->assertStringNotContainsString('$2y$', $r->getContent());
        $this->assertSame(['subdirector'], collect($r->json('roles'))->where('bloqueada', true)->pluck('rol')->values()->all());
        $this->assertTrue($r->json('orientadores.0.bloqueada'));
    }

    public function test_olvide_no_necesita_token_csrf_ni_sesion(): void
    {
        $r = $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk()->assertJsonPath('bloqueada', true);

        $this->assertNotNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));
        $this->assertNull($r->headers->get('Set-Cookie'));   // no abre sesión: no escribe nada en la tabla sessions
    }

    public function test_al_entrar_una_contrasena_con_costo_distinto_se_vuelve_a_guardar_con_el_actual(): void
    {
        $viejo = password_hash('prepa06', PASSWORD_BCRYPT, ['cost' => 5]);   // el costo de pruebas es 4
        DB::table('users')->where('rol', 'director')->update(['password' => $viejo]);
        $ana = Orientador::create(['nombre' => 'Ana']);
        DB::table('orientadores')->where('id', $ana->id)->update(['password' => password_hash('ClaveDeAna2026', PASSWORD_BCRYPT, ['cost' => 5])]);

        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'prepa06'])->assertOk();
        $nuevo = DB::table('users')->where('rol', 'director')->value('password');
        $this->assertNotSame($viejo, $nuevo);
        $this->assertTrue(Hash::check('prepa06', $nuevo));
        $this->assertFalse(Hash::needsRehash($nuevo));

        $this->postJson('/auth/login', ['email' => 'orientador1@epo6.edu.mx', 'password' => 'ClaveDeAna2026', 'orientador' => 'Ana'])->assertOk();
        $this->assertFalse(Hash::needsRehash(DB::table('orientadores')->where('id', $ana->id)->value('password')));
        $this->assertTrue(Hash::check('ClaveDeAna2026', DB::table('orientadores')->where('id', $ana->id)->value('password')));
        // La contraseña general del rol no se tocó por entrar con la propia
        $this->assertTrue(Hash::check('prepa06', DB::table('users')->where('rol', 'orientador')->value('password')));
    }

    public function test_olvide_repetido_no_cambia_la_hora_de_la_solicitud(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();
        DB::table('users')->where('rol', 'director')->update(['olvido_at' => '2026-01-01 08:00:00']);

        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        $this->assertSame('2026-01-01 08:00:00', DB::table('users')->where('rol', 'director')->value('olvido_at'));
    }

    public function test_la_lista_de_claves_responde_304_si_nada_cambio(): void
    {
        $c = $this->como('control');
        $r = $c->getJson('/api/sica/claves')->assertOk();
        $etag = $r->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->como('control')->getJson('/api/sica/claves', ['If-None-Match' => $etag])->assertStatus(304);

        // Cuando alguien pulsa "¿Olvidaste tu contraseña?" el ETag cambia y vuelve a llegar el cuerpo completo
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();
        $this->como('control')->getJson('/api/sica/claves', ['If-None-Match' => $etag])->assertOk()
            ->assertJsonPath('roles.0.bloqueada', true);
    }

    public function test_guardar_una_contrasena_nueva_sin_borrar_la_anterior_tambien_desbloquea(): void
    {
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();

        $this->como('control')->postJson('/api/sica/claves/rol', [
            'rol' => 'director', 'password' => 'DirectorRapida26', 'password_confirmation' => 'DirectorRapida26',
        ])->assertOk();

        $this->assertNull(DB::table('users')->where('rol', 'director')->value('olvido_at'));
        $this->postJson('/auth/login', ['email' => 'director@epo6.edu.mx', 'password' => 'DirectorRapida26'])->assertOk();
    }

    public function test_el_estado_trae_la_hora_de_la_solicitud_para_las_burbujas_de_control(): void
    {
        Orientador::create(['nombre' => 'Ana']);
        $this->postJson('/auth/olvide', ['email' => 'director@epo6.edu.mx'])->assertOk();
        $this->postJson('/auth/olvide', ['email' => 'orientador1@epo6.edu.mx', 'orientador' => 'Ana'])->assertOk();

        $r = $this->como('control')->getJson('/api/sica/claves')->assertOk();

        $this->assertNotNull(collect($r->json('roles'))->firstWhere('rol', 'director')['olvido_at']);
        $this->assertNull(collect($r->json('roles'))->firstWhere('rol', 'subdirector')['olvido_at']);
        $this->assertNotNull($r->json('orientadores.0.olvido_at'));
    }
}
