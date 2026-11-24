<?php

namespace Tests\Unit;

use App\Services\ScholarshipStanding;
use PHPUnit\Framework\TestCase;

class ScholarshipStandingTest extends TestCase
{
    public function test_las_cuotas_anteriores_a_octubre_no_cuentan_ni_se_muestran(): void
    {
        $split = ScholarshipStanding::split([
            $this->row(1, '2026-09-15', 7, false, null),
            $this->row(1, '2026-10-13', 8, false, null),
            $this->row(1, '2026-11-10', 9, false, null),
            $this->row(1, '2026-12-10', 10, false, null),
        ], '2026-11-24');

        $this->assertSame([], $split['perdidas']);
        $this->assertSame([8, 9, 10], array_column($split['activas'], 'cuota_n'));
        $this->assertSame(
            [ScholarshipStanding::VENCIDA_SIN_PAGAR, ScholarshipStanding::VENCIDA_SIN_PAGAR, ScholarshipStanding::POR_VENCER],
            array_column($split['activas'], 'situacion')
        );
    }

    public function test_tres_caidas_seguidas_desde_octubre_dan_de_baja_la_beca(): void
    {
        $split = ScholarshipStanding::split([
            $this->row(1, '2026-10-13', 8, true, '2026-10-20', 180000),
            $this->row(1, '2026-11-10', 9, false, null, 15000),
            $this->row(1, '2026-12-10', 10, false, null, 0),
        ], '2026-12-20');

        $this->assertSame([], $split['activas']);
        $this->assertSame([8, 9, 10], array_column($split['perdidas'], 'cuota_n'));
        $this->assertSame(
            [ScholarshipStanding::FUERA_DE_TERMINO, ScholarshipStanding::VENCIDA_SIN_PAGAR, ScholarshipStanding::VENCIDA_SIN_PAGAR],
            array_column($split['perdidas'], 'situacion')
        );
        $this->assertSame([180000.0, 15000.0, 0.0], array_column($split['perdidas'], 'pago'));
        $this->assertSame([false, false, true], array_column($split['perdidas'], 'perdio_la_beca'));
        $this->assertSame('Fuera de término', $split['perdidas'][0]['situacion_label']);
        $this->assertSame('Vencida sin pagar', $split['perdidas'][1]['situacion_label']);
    }

    public function test_un_pago_a_termino_corta_la_racha(): void
    {
        $split = ScholarshipStanding::split([
            $this->row(1, '2026-10-13', 8, true, '2026-10-14', 180000),
            $this->row(1, '2026-11-10', 9, true, '2026-11-10', 120000),
            $this->row(1, '2026-12-10', 10, true, '2026-12-18', 180000),
        ], '2026-12-20');

        $this->assertSame([], $split['perdidas']);
        $this->assertCount(3, $split['activas']);
        $this->assertSame(ScholarshipStanding::A_TERMINO, $split['activas'][1]['situacion']);
        $this->assertFalse($split['activas'][2]['perdio_la_beca']);
    }

    public function test_el_dia_del_vencimiento_no_cae_y_el_dia_siguiente_si(): void
    {
        $elDia = ScholarshipStanding::split([
            $this->row(1, '2026-11-10', 9, false, null),
            $this->row(1, '2026-10-13', 8, true, '2026-10-13', 120000),
        ], '2026-11-10');
        $alDiaSiguiente = ScholarshipStanding::split([
            $this->row(1, '2026-11-10', 9, false, null),
            $this->row(1, '2026-10-13', 8, true, null, 120000),
        ], '2026-11-11');

        $noviembre = $this->cuota($elDia['activas'], 9);
        $octubre = $this->cuota($elDia['activas'], 8);
        $this->assertSame(ScholarshipStanding::POR_VENCER, $noviembre['situacion']);
        $this->assertSame(ScholarshipStanding::A_TERMINO, $octubre['situacion']);
        $this->assertSame(ScholarshipStanding::VENCIDA_SIN_PAGAR, $this->cuota($alDiaSiguiente['activas'], 9)['situacion']);
        $this->assertSame(ScholarshipStanding::A_TERMINO, $this->cuota($alDiaSiguiente['activas'], 8)['situacion']);
    }

    public function test_un_pago_posterior_no_devuelve_la_beca(): void
    {
        $split = ScholarshipStanding::split([
            $this->row(1, '2026-10-13', 8, false, null),
            $this->row(1, '2026-11-10', 9, false, null),
            $this->row(1, '2026-12-10', 10, false, null),
            $this->row(1, '2026-12-20', 11, true, '2026-12-20', 100000),
        ], '2026-12-21');

        $this->assertSame([], $split['activas']);
        $this->assertSame([8, 9, 10], array_column($split['perdidas'], 'cuota_n'));
        $this->assertTrue($split['perdidas'][2]['perdio_la_beca']);
    }

    public function test_la_baja_de_un_alumno_no_arrastra_al_otro(): void
    {
        $split = ScholarshipStanding::split([
            $this->row(1, '2026-10-13', 8, false, null, 0, 'López, Bruno'),
            $this->row(2, '2026-10-13', 8, true, '2026-10-13', 120000, 'García, Ana'),
            $this->row(1, '2026-11-10', 9, false, null, 0, 'López, Bruno'),
            $this->row(1, '2026-12-10', 10, false, null, 0, 'López, Bruno'),
            $this->row(2, '2026-11-10', 9, false, null, 0, 'García, Ana'),
        ], '2026-12-20');

        $this->assertSame(['García, Ana', 'García, Ana'], array_column($split['activas'], 'alumno'));
        $this->assertSame(['López, Bruno', 'López, Bruno', 'López, Bruno'], array_column($split['perdidas'], 'alumno'));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function cuota(array $rows, int $numero): array
    {
        foreach ($rows as $row) {
            if ((int) $row['cuota_n'] === $numero) {
                return $row;
            }
        }

        $this->fail('No está la cuota '.$numero);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $student, string $due, int $cuota, bool $paid, ?string $paidOn, float $pago = 0, string $alumno = 'García, Ana'): array
    {
        return [
            'student_id' => $student,
            'alumno' => $alumno,
            'carrera' => 'Contador',
            'cuota_n' => $cuota,
            'cuota' => 'Cuota '.$cuota,
            'expirated_on' => $due,
            'paid_on' => $paidOn,
            'pagada' => $paid,
            'pago' => $pago,
            'cuota_original' => 200000,
            'porcentaje' => 25,
            'porcentaje_label' => '25',
            'monto_beca' => 50000,
            'saldo_a_favor' => 0,
            'motivo' => 'Reducción arancelaria por razones económicas',
        ];
    }
}
