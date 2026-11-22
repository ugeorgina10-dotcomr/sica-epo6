<?php

namespace App\Support;

/**
 * Decide si un escaneo del QR es ENTRADA o SALIDA, según el horario del grupo del alumno
 * (hora de entrada y de salida del grupo) y lo que el alumno ya tiene registrado hoy.
 *
 * Reglas (en este orden):
 * 1. Ya tiene entrada y salida hoy -> 'Completo' (no se registra nada más).
 * 2. Ya tiene entrada pero no salida -> 'Salida' solo cuando llega el bloque de salida; antes de eso,
 *    mostrar la credencial otra vez NO cambia la hora de entrada (ver esRepetida).
 * 3. No tiene entrada hoy y ya llegó (o pasó) la hora de salida del grupo -> 'Salida' (solo salida: no escaneó al llegar).
 * 4. No tiene entrada hoy y todavía no es la hora de salida -> 'Entrada', SIEMPRE: antes del turno, a tiempo,
 *    dentro de la tolerancia o con retardo. Se guarda la hora real en que escaneó.
 *    La tolerancia solo decide si lleva retardo, nunca si se registra o no.
 *
 * El bloque de salida empieza SALIDA_ANTES minutos antes de la hora de salida del grupo
 * (y nunca antes de la mitad de la jornada, para horarios muy cortos).
 * Si un grupo no tiene un horario válido, se usa el horario normal de su turno.
 *
 * La misma lógica está en el JavaScript de index.blade.php (resolverMovimiento) para que el
 * teléfono y el lector fijo decidan igual aunque no haya internet.
 */
class MovimientoAutomatico
{
    /** Minutos antes de la hora de salida del grupo en que ya empieza el "bloque de salida" */
    public const SALIDA_ANTES = 30;

    /** Horario normal [entrada, salida] en minutos del día, si el grupo no tiene horario */
    public const DEFECTO = [
        'Matutino'   => [430, 840],    // 07:10 - 14:00
        'Vespertino' => [790, 1200],   // 13:10 - 20:00
    ];

    /** "07:10" o "07:10:00" -> minutos del día (vacío si no es una hora válida) */
    public static function aMinutos(?string $hora): ?int
    {
        if ($hora === null || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', trim($hora), $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /** [entrada, salida] del grupo en minutos; si falta alguna o no tienen sentido, se usa el horario normal del turno */
    public static function horario(?string $entrada, ?string $salida, ?string $turno): array
    {
        $e = self::aMinutos($entrada);
        $s = self::aMinutos($salida);

        if ($e === null || $s === null || $s <= $e) {
            return self::DEFECTO[$turno ?: 'Matutino'] ?? self::DEFECTO['Matutino'];
        }

        return [$e, $s];
    }

    /** Minuto del día desde el cual un segundo escaneo (con entrada ya registrada) cuenta como salida */
    public static function inicioBloqueSalida(int $entrada, int $salida): int
    {
        return max($salida - self::SALIDA_ANTES, intdiv($entrada + $salida, 2));
    }

    /**
     * Con la entrada ya registrada, ¿este escaneo es solo la credencial mostrada otra vez? (eso NO cambia la hora de entrada)
     * Lo es mientras no llegue el bloque de salida (la hora de cierre menos SALIDA_ANTES, con o sin tolerancia): desde ahí el escaneo es la SALIDA.
     * Una salida anticipada con permiso se registra con el modo manual Salida o con el botón «Marcar Salida».
     */
    public static function esRepetida(int $ahoraMin, int $entrada, int $salida): bool
    {
        return $ahoraMin < self::inicioBloqueSalida($entrada, $salida);
    }

    /**
     * @return string 'Entrada' | 'Salida' | 'Completo'
     */
    public static function resolver(int $ahoraMin, int $entrada, int $salida, bool $tieneEntrada, bool $tieneSalida): string
    {
        if ($tieneEntrada && $tieneSalida) {
            return 'Completo';
        }
        if ($tieneEntrada) {
            return 'Salida';
        }

        return $ahoraMin >= $salida ? 'Salida' : 'Entrada';
    }
}
