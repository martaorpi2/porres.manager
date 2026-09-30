<?php

namespace App\Services;

/**
 * Arma las líneas de un asiento editado a mano y exige que cierre.
 */
final class AccountingEntryEditor
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{lines: list<array{accounting_account_id: int, debit: string, credit: string, source_id: int|null}>, error: string|null}
     */
    public static function prepare(array $rows): array
    {
        $lines = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $account = (int) ($row['accounting_account_id'] ?? 0);
            $debit = self::cents($row['debit'] ?? 0);
            $credit = self::cents($row['credit'] ?? 0);
            $sourceId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($debit === null || $credit === null) {
                return self::fail('El importe tiene que ser un número mayor o igual a cero.');
            }
            if ($account < 1 && $debit < 1 && $credit < 1) {
                continue;
            }
            if ($account < 1) {
                return self::fail('Cada línea tiene que tener una cuenta.');
            }
            if ($debit > 0 && $credit > 0) {
                return self::fail('Una línea no puede tener importe en el debe y en el haber.');
            }
            if ($debit < 1 && $credit < 1) {
                return self::fail('Cada línea tiene que tener importe en el debe o en el haber.');
            }

            $lines[] = [
                'accounting_account_id' => $account,
                'debit' => self::money($debit),
                'credit' => self::money($credit),
                'source_id' => $sourceId,
            ];
        }

        if (count($lines) < 2) {
            return self::fail('El asiento necesita al menos dos líneas.');
        }

        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $debit += self::cents($line['debit']);
            $credit += self::cents($line['credit']);
        }
        if ($debit !== $credit) {
            return self::fail('El debe ('.self::pesos($debit).') y el haber ('.self::pesos($credit).') no coinciden. No se guardaron los cambios.');
        }

        return ['lines' => $lines, 'error' => null];
    }

    /**
     * @return array{lines: list<array{accounting_account_id: int, debit: string, credit: string, source_id: int|null}>, error: string}
     */
    private static function fail(string $error): array
    {
        return ['lines' => [], 'error' => $error];
    }

    private static function cents(mixed $amount): ?int
    {
        if (is_int($amount) || is_float($amount)) {
            if ($amount < 0) {
                return null;
            }

            return (int) round($amount * 100);
        }

        $value = trim(str_replace(' ', '', (string) $amount));
        if ($value === '') {
            return 0;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function pesos(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }
}
