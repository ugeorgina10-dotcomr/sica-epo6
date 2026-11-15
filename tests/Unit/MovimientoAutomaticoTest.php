<?php

namespace Tests\Unit;

use App\Support\MovimientoAutomatico as M;
use PHPUnit\Framework\TestCase;

class MovimientoAutomaticoTest extends TestCase
{
    private function hm(string $h): int
    {
        return M::aMinutos($h);
    }

    public function test_matutino_con_horario_del_grupo(): void
    {
        [$e, $s] = M::horario('07:10', '14:00', 'Matutino');

        $this->assertSame('Entrada', M::resolver($this->hm('06:50'), $e, $s, false, false));   // antes de la hora
        $this->assertSame('Entrada', M::resolver($this->hm('07:40'), $e, $s, false, false));   // con retardo
        $this->assertSame('Entrada', M::resolver($this->hm('10:00'), $e, $s, false, false));   // muy tarde, aún sin entrada
        $this->assertSame('Salida', M::resolver($this->hm('13:30'), $e, $s, false, false));    // bloque de salida sin entrada previa
        $this->assertSame('Salida', M::resolver($this->hm('14:05'), $e, $s, true, false));
        $this->assertSame('Salida', M::resolver($this->hm('10:00'), $e, $s, true, false));     // salida anticipada
        $this->assertSame('Completo', M::resolver($this->hm('14:10'), $e, $s, true, true));
    }

    public function test_vespertino_con_su_propio_horario(): void
    {
        [$e, $s] = M::horario('13:10:00', '20:00:00', 'Vespertino');

        $this->assertSame('Entrada', M::resolver($this->hm('13:00'), $e, $s, false, false));
        $this->assertSame('Salida', M::resolver($this->hm('19:45'), $e, $s, false, false));
        $this->assertSame('Salida', M::resolver($this->hm('20:02'), $e, $s, true, false));
    }

    public function test_sin_horario_usa_el_del_turno(): void
    {
        $this->assertSame([430, 840], M::horario(null, null, 'Matutino'));
        $this->assertSame([790, 1200], M::horario('', '', 'Vespertino'));
        $this->assertSame([430, 840], M::horario('15:00', '08:00', 'Matutino'));   // salida antes que entrada: no tiene sentido
        $this->assertSame([430, 840], M::horario('07:10', null, null));
    }

    public function test_horario_corto_no_manda_todo_a_salida(): void
    {
        [$e, $s] = M::horario('08:00', '09:00', 'Matutino');   // inicio de salida = 08:30 (mitad), no 08:30 - 30
        $this->assertSame('Entrada', M::resolver($this->hm('08:10'), $e, $s, false, false));
        $this->assertSame('Salida', M::resolver($this->hm('08:45'), $e, $s, false, false));
    }

    public function test_aMinutos(): void
    {
        $this->assertSame(430, M::aMinutos('07:10'));
        $this->assertSame(430, M::aMinutos('07:10:00'));
        $this->assertNull(M::aMinutos('25:00'));
        $this->assertNull(M::aMinutos(null));
    }
}
