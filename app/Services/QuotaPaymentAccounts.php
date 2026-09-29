<?php

namespace App\Services;

/**
 * Medio de pago de ePorres → cuenta de fondos del plan.
 * QR acredita en Banco BSE, igual que la cobranza del libro diario.
 * Tarjeta de crédito no tiene cuenta imputable en el plan cargado.
 */
final class QuotaPaymentAccounts
{
    public const DEBTORS = '11201000';

    public const QUOTAS_INCOME = '41102000';

    public const LATE_INTEREST = '41201000';

    /** @var array<string, string> */
    public const BY_PAYMENT_TYPE = [
        'BSE' => '11102002',
        'QR' => '11102002',
        'Mercado Pago' => '11104000',
        'Tarjeta de Débito' => '11202004',
        'Tarjeta Naranja Débito' => '11202004',
        'Tarjeta Naranja' => '11202003',
        'Tarjeta Sol' => '11202001',
    ];

    public static function bankCode(?string $paymentType): ?string
    {
        $type = trim((string) $paymentType);

        return self::BY_PAYMENT_TYPE[$type] ?? null;
    }
}
