<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Al iniciar sesión el panel ya recibe todo lo necesario (estado y, en Control Escolar, contraseñas). */
class SicaPrecargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(UsuarioSeeder::class);
    }

    public function test_el_login_de_control_trae_estado_y_claves(): void
    {
        $u = User::where('rol', 'control')->first();
        $r = $this->postJson('/auth/login', ['email' => $u->email, 'password' => 'prepa06']);

        $r->assertOk()->assertJsonPath('rol', 'control')
            ->assertJsonStructure(['estado' => ['etag', 'datos' => ['alumnos', 'horarios', 'permisos']], 'claves' => ['etag', 'datos' => ['roles', 'orientadores']]]);
    }

    public function test_el_login_de_director_trae_estado_pero_no_claves(): void
    {
        $u = User::where('rol', 'director')->first();
        $r = $this->postJson('/auth/login', ['email' => $u->email, 'password' => 'prepa06']);

        $r->assertOk()->assertJsonStructure(['estado' => ['etag', 'datos']])->assertJsonMissingPath('claves');
    }

    public function test_el_etag_del_login_coincide_con_el_de_las_consultas_y_responde_304(): void
    {
        $c = $this->actingAs(User::where('rol', 'control')->first());
        $login = $c->getJson('/auth/me?estado=1')->assertOk();

        $c->getJson('/api/sica/estado', ['If-None-Match' => $login->json('estado.etag')])->assertStatus(304);
        $c->getJson('/api/sica/claves', ['If-None-Match' => $login->json('claves.etag')])->assertStatus(304);
    }

    public function test_auth_me_sin_estado_sigue_siendo_ligero(): void
    {
        $this->actingAs(User::where('rol', 'control')->first())
            ->getJson('/auth/me')->assertOk()->assertJsonMissingPath('estado')->assertJsonMissingPath('claves');
    }

    public function test_la_pagina_de_inicio_con_sesion_de_control_incluye_las_claves(): void
    {
        $this->actingAs(User::where('rol', 'control')->first())
            ->get('/')->assertOk()->assertSee('window.SICA_CLAVES', false);
    }
}
