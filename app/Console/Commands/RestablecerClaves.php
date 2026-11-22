<?php

namespace App\Console\Commands;

use App\Http\Controllers\ClavesController;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RestablecerClaves extends Command
{
    protected $signature = 'sica:restablecer-claves {--clave= : Misma contraseña para las 4 cuentas (si no, se pide una por rol)}';

    protected $description = 'Pone contraseña nueva (con hash) a las cuentas de los roles, por ejemplo después de usar "Borrar todas las contraseñas"';

    public function handle(): int
    {
        $usuarios = User::orderBy('id')->get();

        if ($usuarios->isEmpty()) {
            $this->error('No hay usuarios. Ejecuta:  php artisan db:seed --class=UsuarioSeeder');

            return self::FAILURE;
        }

        $unica = $this->option('clave');
        $nuevas = [];

        foreach ($usuarios as $u) {
            $clave = $unica ?: $this->secret("Contraseña nueva para {$u->rol} ({$u->email})");

            $v = Validator::make(
                ['password' => $clave, 'password_confirmation' => $clave],
                ['password' => ClavesController::reglasClave()],
                ClavesController::MENSAJES
            );

            if ($v->fails()) {
                $this->error($v->errors()->first('password') . ' No se cambió ninguna cuenta.');

                return self::FAILURE;
            }

            $nuevas[$u->id] = $clave;
        }

        foreach ($usuarios as $u) {
            $u->password = $nuevas[$u->id];   // el modelo la guarda protegida (cifrada)
            $u->save();
            $this->info("Lista: {$u->rol} | {$u->email}");
        }

        $this->warn('Entra como Control Escolar y asigna una contraseña distinta a cada rol y a cada orientador.');

        return self::SUCCESS;
    }
}
