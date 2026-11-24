<?php

namespace App\Services;

/**
 * Vigencia de la beca desde la cuota de octubre.
 *
 * Una cuota paga después del vencimiento, o vencida y sin pagar, suma una caída
 * de ese mes. El día del vencimiento todavía no cae. Tres caídas seguidas dan
 * de baja la beca completa.
 */
final class ScholarshipStanding
{
    public const DESDE_MES = 10;

    public const A_TERMINO = 'a_termino';

    public const FUERA_DE_TERMINO = 'fuera_de_termino';

    public const VENCIDA_SIN_PAGAR = 'vencida_sin_pagar';

    public const POR_VENCER = 'por_vencer';

    public static function aplica(string $expiratedOn): bool
    {
        if (! preg_match('/^\d{4}-(\d{2})-\d{2}$/', $expiratedOn, $m)) {
            return false;
        }

        return (int) $m[1] >= self::DESDE_MES;
    }

    /**
     * Marca cada cuota del reporte. La pérdida se juzga desde octubre.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function annotate(array $rows, string $todayYmd): array
    {
        $todayYmd = substr($todayYmd, 0, 10);
        $byStudent = [];
        foreach ($rows as $index => $row) {
            $byStudent[(string) ($row['student_id'] ?? '')][] = $index;
        }

        $marks = [];
        foreach ($byStudent as $indexes) {
            $october = array_values(array_filter(
                $indexes,
                fn (int $index) => self::aplica((string) ($rows[$index]['expirated_on'] ?? ''))
            ));
            usort($october, function (int $a, int $b) use ($rows) {
                $byDate = strcmp((string) $rows[$a]['expirated_on'], (string) $rows[$b]['expirated_on']);
                if ($byDate !== 0) {
                    return $byDate;
                }

                return ((int) $rows[$a]['cuota_n']) <=> ((int) $rows[$b]['cuota_n']);
            });

            $streak = 0;
            $alreadyLost = false;
            foreach ($october as $index) {
                $situacion = self::situacion($rows[$index], $todayYmd);
                if ($alreadyLost) {
                    $marks[$index] = self::marca($situacion, 'Baja total', false, true);
                } elseif ($situacion === self::POR_VENCER) {
                    $marks[$index] = self::marca($situacion, 'Vigente', false, false);
                } elseif (self::esCaida($situacion)) {
                    $streak++;
                    if ($streak >= 3) {
                        $alreadyLost = true;
                        $marks[$index] = self::marca($situacion, 'Baja total', true, true);
                    } else {
                        $marks[$index] = self::marca($situacion, 'Perdida este mes', true, false);
                    }
                } else {
                    $streak = 0;
                    $marks[$index] = self::marca($situacion, 'Vigente', false, false);
                }
            }

            foreach ($indexes as $index) {
                if (isset($marks[$index])) {
                    continue;
                }
                $situacion = self::tieneVencimiento($rows[$index])
                    ? self::situacion($rows[$index], $todayYmd)
                    : self::POR_VENCER;
                $marks[$index] = self::marca($situacion, '—', false, false);
            }
        }

        foreach ($rows as $index => $row) {
            $rows[$index] = array_merge($row, $marks[$index]);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{activas: list<array<string, mixed>>, perdidas: list<array<string, mixed>>}
     */
    public static function split(array $rows, string $todayYmd): array
    {
        $todayYmd = substr($todayYmd, 0, 10);
        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => self::aplica((string) ($row['expirated_on'] ?? ''))
        ));

        $grouped = [];
        foreach ($rows as $index => $row) {
            $grouped[(string) ($row['student_id'] ?? '')][] = $index;
        }

        $marks = [];
        $lost = [];

        foreach ($grouped as $studentId => $indexes) {
            usort($indexes, function (int $a, int $b) use ($rows) {
                $byDate = strcmp((string) $rows[$a]['expirated_on'], (string) $rows[$b]['expirated_on']);
                if ($byDate !== 0) {
                    return $byDate;
                }

                return ((int) $rows[$a]['cuota_n']) <=> ((int) $rows[$b]['cuota_n']);
            });

            $streak = 0;
            $alreadyLost = false;
            foreach ($indexes as $index) {
                $situacion = self::situacion($rows[$index], $todayYmd);
                $perdio = false;

                if ($alreadyLost) {
                    if (self::esCaida($situacion)) {
                        $streak++;
                    }
                } elseif ($situacion === self::POR_VENCER) {
                    // Una cuota que todavía no vence no suma ni corta la racha.
                } elseif (self::esCaida($situacion)) {
                    $streak++;
                    if ($streak >= 3) {
                        $alreadyLost = true;
                        $perdio = true;
                        $lost[$studentId] = true;
                    }
                } else {
                    $streak = 0;
                }

                $marks[$index] = [
                    'situacion' => $situacion,
                    'situacion_label' => self::etiqueta($situacion),
                    'perdio_la_beca' => $perdio,
                ];
            }
        }

        $activas = [];
        $perdidas = [];
        foreach ($rows as $index => $row) {
            $row = array_merge($row, $marks[$index]);
            if (isset($lost[(string) ($row['student_id'] ?? '')])) {
                if (self::esCaida($row['situacion'])) {
                    $perdidas[] = $row;
                }

                continue;
            }

            $activas[] = $row;
        }

        return [
            'activas' => $activas,
            'perdidas' => $perdidas,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function situacion(array $row, string $todayYmd): string
    {
        $due = (string) $row['expirated_on'];
        $paidOn = $row['paid_on'] ?? null;
        $paidOn = is_string($paidOn) && $paidOn !== '' ? substr($paidOn, 0, 10) : null;

        if (! empty($row['pagada'])) {
            if ($paidOn !== null && $paidOn > $due) {
                return self::FUERA_DE_TERMINO;
            }

            return self::A_TERMINO;
        }

        if ($due < $todayYmd) {
            return self::VENCIDA_SIN_PAGAR;
        }

        return self::POR_VENCER;
    }

    /**
     * @return array{situacion: string, situacion_label: string, beca: string, perdio_la_beca: bool, baja_total: bool}
     */
    private static function marca(string $situacion, string $beca, bool $perdio, bool $bajaTotal): array
    {
        return [
            'situacion' => $situacion,
            'situacion_label' => self::etiqueta($situacion),
            'beca' => $beca,
            'perdio_la_beca' => $perdio,
            'baja_total' => $bajaTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function tieneVencimiento(array $row): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($row['expirated_on'] ?? ''));
    }

    private static function esCaida(string $situacion): bool
    {
        return $situacion === self::FUERA_DE_TERMINO || $situacion === self::VENCIDA_SIN_PAGAR;
    }

    private static function etiqueta(string $situacion): string
    {
        return match ($situacion) {
            self::A_TERMINO => 'A término',
            self::FUERA_DE_TERMINO => 'Fuera de término',
            self::VENCIDA_SIN_PAGAR => 'Vencida sin pagar',
            default => 'Por vencer',
        };
    }
}
