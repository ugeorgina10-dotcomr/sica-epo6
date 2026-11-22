<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class VerificarClaves extends Command
{
    protected $signature = 'sica:verificar-claves {--clave= : Contraseña a comprobar (por defecto la inicial)}';

    protected $description = 'Comprueba que cada cuenta existe, que su contraseña está guardada con hash y que la contraseña inicial funciona';

    public function handle(): int
    {
        $clave = $this->option('clave') ?: UsuarioSeeder::CLAVE_INICIAL;
        $filas = [];
        $todoBien = true;

        foreach (User::orderBy('id')->get() as $u) {
            $hash       = (string) $u->getAuthPassword();
            $esHash     = str_starts_with($hash, '$2y$');          // forma en que se protege la contraseña
            $coincide   = $esHash && Hash::check($clave, $hash);
            $todoBien   = $todoBien && $esHash && $coincide;

            $filas[] = [
                $u->rol,
                $u->email,
                $esHash ? 'Sí (' . substr($hash, 0, 7) . '…)' : 'NO',
                $coincide ? 'Correcta' : 'No coincide',
            ];
        }

        if (!$filas) {
            $this->error('No hay usuarios. Ejecuta:  php artisan db:seed --class=UsuarioSeeder');
            return self::FAILURE;
        }

        $this->table(['Rol', 'Correo', 'Guardada con hash', 'Contraseña'], $filas);
        $todoBien ? $this->info('Todo correcto.') : $this->error('Hay cuentas con problemas.');

        return $todoBien ? self::SUCCESS : self::FAILURE;
    }
}
