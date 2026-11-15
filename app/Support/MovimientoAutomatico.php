<?php

namespace App\Support;

/**
 * Decide si un escaneo del QR es ENTRADA o SALIDA usando el horario del grupo del alumno
 * (hora_entrada y hora_salida de la tabla grupos) y lo que el alumno ya tiene registrado hoy.
 *
 * Reglas (en este orden):
 *  1. Ya tiene entrada y salida hoy            -> 'Completo' (no se registra nada más).
 *  2. Ya tiene entrada y aún no salida         -> 'Salida'   (también sirve para salidas anticipadas con permiso).
 *  3. Sin entrada hoy y la hora ya está en el bloque de salida del grupo -> 'Salida'.
 *  4. Sin entrada hoy en cualquier otro momento (antes del turno, a tiempo o con retardo) -> 'Entrada'.
 *
 * El bloque de salida empieza SALIDA_ANTES minutos antes de la hora de salida del grupo
 * (nunca antes de la mitad de la jornada, para horarios muy cortos).
 * Un grupo sin horario válido usa el horario por defecto de su turno.
 *
 * La misma lógica está en el JavaScript de index.blade.php (resolverMovimiento) para que el
 * teléfono y el lector fijo decidan igual aunque no haya internet.
 */
class MovimientoAutomatico
{
    /** Minutos ANTES de la hora de salida del grupo en que ya se considera "bloque de salida" */
    public const SALIDA_ANTES = 30;

    /** Un segundo escaneo dentro de estos minutos se toma como lectura repetida de la entrada */
    public const REPETIDA_MIN = 5;

    /** Horario por defecto [entrada, salida] en minutos del día, si el grupo no tiene horario capturado */
    public const DEFECTO = [
        'Matutino'   => [430, 840],    // 07:10 - 14:00
        'Vespertino' => [790, 1200],   // 13:10 - 20:00
    ];

    /** "07:10" o "07:10:00" -> minutos del día (null si no es una hora válida) */
    public static function aMinutos(?string $hora): ?int
    {
        if ($hora === null || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', trim($hora), $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    /** [entrada, salida] del grupo en minutos; si falta alguna o no tienen sentido, el horario por defecto del turno */
    public static function horario(?string $entrada, ?string $salida, ?string $turno): array
    {
        $e = self::aMinutos($entrada);
        $s = self::aMinutos($salida);

        if ($e === null || $s === null || $s <= $e) {
            return self::DEFECTO[$turno ?: 'Matutino'] ?? self::DEFECTO['Matutino'];
        }

        return [$e, $s];
    }

    /** Minuto del día desde el cual un escaneo sin entrada previa cuenta como salida */
    public static function inicioBloqueSalida(int $entrada, int $salida): int
    {
        return max($salida - self::SALIDA_ANTES, intdiv($entrada + $salida, 2));
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

        return $ahoraMin >= self::inicioBloqueSalida($entrada, $salida) ? 'Salida' : 'Entrada';
    }
}
