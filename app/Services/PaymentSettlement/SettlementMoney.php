<?php

namespace App\Services\PaymentSettlement;

use RuntimeException;

final class SettlementMoney
{
    public static function cents(string $value): int
    {
        $value = str_replace(['$', ' ', "\xc2\xa0"], '', trim($value));
        if ($value === '' || $value === '-') {
            return 0;
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $value) === 1 || preg_match('/^\d+,\d{2}$/', $value) === 1) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }
        if (! is_numeric($value)) {
            throw new RuntimeException('Hay un importe que no se pudo leer.');
        }

        $cents = (int) round(((float) $value) * 100);

        return $negative ? -$cents : $cents;
    }

    public static function isMoney(string $value): bool
    {
        return preg_match('/\$\s*-?[\d.,]+/', $value) === 1;
    }

    public static function date(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $match) === 1) {
            return $match[3].'-'.$match[2].'-'.$match[1];
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $value, $match) === 1) {
            $year = (int) $match[3];
            $year += $year >= 70 ? 1900 : 2000;

            return $year.'-'.$match[2].'-'.$match[1];
        }
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $match) === 1) {
            return $match[3].'-'.$match[2].'-'.$match[1];
        }

        return null;
    }

    public static function fold(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $value) ?? '';
    }
}
