<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Console\Command;

class CrearUsuarios extends Command
{
    protected $signature = 'sica:crear-usuarios';

    protected $description = 'Crea las cuentas que falten (contraseña inicial con hash) sin tocar las que ya existen y funcionan. Seguro para ejecutar en cada despliegue.';

    public function handle(): int
    {
        foreach (UsuarioSeeder::CUENTAS as $cuenta) {
            $usuario = User::where('email', $cuenta['email'])->first();

            if (!$usuario) {
                User::create($cuenta + ['password' => UsuarioSeeder::CLAVE_INICIAL]);
                $this->info("Creada:     {$cuenta['rol']} | {$cuenta['email']}");
                continue;
            }

            // Cada cuenta debe tener siempre su rol correcto (antes todas quedaban como 'orientador' por error)
            if ($usuario->rol !== $cuenta['rol']) {
                $anterior = $usuario->rol;
                $usuario->rol = $cuenta['rol'];
                $usuario->save();
                $this->warn("Rol corregido: {$cuenta['email']} ({$anterior} -> {$cuenta['rol']})");
            }

            // Si Control Escolar borró la contraseña a propósito, no se vuelve a poner la inicial cada vez que se publica el sistema
            if ((string) $usuario->getAuthPassword() === '') {
                $this->warn("Sin contraseña (borrada): {$cuenta['rol']} | {$cuenta['email']}. Usa:  php artisan sica:restablecer-claves");
                continue;
            }

            // Si una cuenta vieja tiene la contraseña sin proteger, se corrige. Si ya está protegida no se toca (su dueño pudo haberla cambiado).
            if (!str_starts_with((string) $usuario->getAuthPassword(), '$2y$')) {
                $usuario->password = UsuarioSeeder::CLAVE_INICIAL;
                $usuario->rol = $cuenta['rol'];
                $usuario->save();
                $this->warn("Corregida:  {$cuenta['rol']} | {$cuenta['email']} (la contraseña no estaba con hash)");
                continue;
            }

            $this->line("Sin cambios: {$cuenta['rol']} | {$cuenta['email']}");
        }

        return self::SUCCESS;
    }
}
