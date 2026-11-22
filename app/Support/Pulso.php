<?php

namespace App\Support;

/**
 * "Pulso": aviso instantáneo de que algo cambió en el servidor.
 *
 * Cada vez que se guarda, escanea, borra o sube algo, el servidor sube un contador en un archivo pequeño
 * (storage/framework/pulso.json). Los equipos (computadoras y celulares) preguntan por ese contador a
 * public/pulso.php, que NO arranca Laravel ni toca la base de datos (responde en ~1 ms), y en cuanto cambia
 * descargan lo nuevo. Así los cambios se ven casi al instante en todos los equipos.
 *
 * Canales: e = estado (alumnos, asistencias, horarios...), c = contraseñas y avisos de "olvidé mi contraseña",
 *          p = plantillas de credenciales.
 */
class Pulso
{
    private const CANALES = ['e', 'c', 'p'];

    public static function ruta(): string
    {
        return storage_path('framework/pulso.json');
    }

    public static function tocar(string ...$canales): void
    {
        $canales = array_values(array_intersect($canales, self::CANALES));
        if (!$canales) {
            return;
        }

        try {
            $ruta = self::ruta();
            $cerrojo = @fopen($ruta . '.lock', 'c');
            if (!$cerrojo) {
                return;
            }

            if (flock($cerrojo, LOCK_EX)) {
                $actual = json_decode((string) @file_get_contents($ruta), true);
                $actual = is_array($actual) ? $actual : [];
                $ahora  = (int) floor(microtime(true) * 1000);

                foreach (self::CANALES as $c) {
                    $actual[$c] = (int) ($actual[$c] ?? 0);
                }
                foreach ($canales as $c) {
                    $actual[$c] = max($ahora, $actual[$c] + 1);   // siempre sube, aunque dos cambios caigan en el mismo milisegundo
                }

                // Se escribe en un archivo aparte y se renombra: quien lo lee nunca ve un archivo a medias
                $tmp = $ruta . '.' . getmypid() . '.tmp';
                if (@file_put_contents($tmp, json_encode($actual)) !== false) {
                    @rename($tmp, $ruta);
                }
                flock($cerrojo, LOCK_UN);
            }
            fclose($cerrojo);
        } catch (\Throwable $e) {
            // El aviso instantáneo es un extra: si falla, los equipos siguen sincronizando con su consulta normal de respaldo
        }
    }
}
