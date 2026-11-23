<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Sesión en base de datos que NO se vuelve a guardar si no cambió nada.
 * Laravel guarda la sesión en CADA petición aunque sea idéntica; el sistema consulta datos varias veces por segundo desde cada
 * equipo, así que con la base de datos en la nube eso eran decenas de escrituras por minuto sin sentido (cada una es un viaje a internet).
 * Aquí solo se escribe si el contenido cambió o si pasó más de 1 minuto (para que la sesión siga contando como activa).
 * Si algo falla se usa el comportamiento normal de Laravel.
 */
class SesionDbLigera extends DatabaseSessionHandler
{
    private const REFRESCO_SEGUNDOS = 60;

    /** @var array{0: string, 1: string, 2: int}|null  [id, contenido tal como está en la base, última actividad] */
    private ?array $leido = null;

    public function read($sessionId): string|false
    {
        $this->leido = null;

        $sesion = (object) $this->getQuery()->find($sessionId);

        if ($this->expired($sesion)) {
            $this->exists = true;

            return '';
        }

        if (isset($sesion->payload)) {
            $this->exists = true;
            $this->leido = [(string) $sessionId, (string) $sesion->payload, (int) ($sesion->last_activity ?? 0)];

            return base64_decode($sesion->payload);
        }

        return '';
    }

    public function write($sessionId, $data): bool
    {
        $l = $this->leido;

        if ($l && $this->exists && $l[0] === (string) $sessionId
            && $l[1] === base64_encode($data)
            && (time() - $l[2]) < self::REFRESCO_SEGUNDOS) {
            return true;
        }

        $this->leido = null;

        return parent::write($sessionId, $data);
    }
}
