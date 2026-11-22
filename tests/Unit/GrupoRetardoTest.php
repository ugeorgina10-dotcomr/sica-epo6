<?php

namespace Tests\Unit;

use App\Models\Grupo;
use PHPUnit\Framework\TestCase;

/**
 * Retardo de entrada y de salida con la tolerancia de cada grupo (lo que se muestra en rojo al escanear).
 */
class GrupoRetardoTest extends TestCase
{
    private function hm(string $hora): int
    {
        [$h, $m] = explode(':', $hora);

        return (int) $h * 60 + (int) $m;
    }

    public function test_salida_pasada_la_tolerancia_es_retardo_de_salida(): void
    {
        $g = new Grupo(['turno' => 'Vespertino', 'hora_entrada' => '13:10', 'hora_salida' => '20:00', 'tolerancia_entrada' => 0, 'tolerancia_salida' => 30]);

        $this->assertSame(0, $g->minutosRetardo('Salida', $this->hm('20:05')));   // dentro de la tolerancia
        $this->assertSame(0, $g->minutosRetardo('Salida', $this->hm('20:30')));   // justo en el límite
        $this->assertSame(4, $g->minutosRetardo('Salida', $this->hm('20:34')));   // 8:34 p. m. -> 4 min tarde
    }

    public function test_entrada_pasada_la_tolerancia_es_retardo_de_entrada(): void
    {
        $g = new Grupo(['turno' => 'Matutino', 'hora_entrada' => '07:10', 'hora_salida' => '14:00', 'tolerancia_entrada' => 10, 'tolerancia_salida' => 0]);

        $this->assertSame(0, $g->minutosRetardo('Entrada', $this->hm('07:20')));
        $this->assertSame(1, $g->minutosRetardo('Entrada', $this->hm('07:21')));
        $this->assertSame(0, $g->minutosRetardo('Salida', $this->hm('14:00')));
        $this->assertSame(1, $g->minutosRetardo('Salida', $this->hm('14:01')));
    }

    public function test_sin_horario_propio_usa_el_horario_del_turno(): void
    {
        $g = new Grupo(['turno' => 'Vespertino', 'hora_entrada' => null, 'hora_salida' => null, 'tolerancia_entrada' => 0, 'tolerancia_salida' => 30]);

        $this->assertSame(4, $g->minutosRetardo('Salida', $this->hm('20:34')));
        $this->assertSame(0, $g->minutosRetardo('Entrada', $this->hm('13:10')));
    }
}
