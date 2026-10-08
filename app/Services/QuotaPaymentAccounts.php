<?php

namespace App\Services;

/**
 * Medio de pago de ePorres → cuenta del plan.
 * QR no entra en la cobranza: se cobra al registrar el informe de First Data.
 * Mercado Pago queda en Mercado Pago a cobrar hasta que MP libera el dinero.
 * Tarjeta de crédito no tiene cuenta imputable en el plan cargado.
 */
final class QuotaPaymentAccounts
{
    public const DEBTORS = '11201000';

    public const QUOTAS_INCOME = '41102000';

    public const SCHOLARSHIP = '52302000';

    public const LATE_INTEREST = '41201000';

    public const MP_RECEIVABLE = '11204000';

    public const MP_AVAILABLE = '11104000';

    public const MP_COMMISSION = '52309000';

    public const MP_SURCHARGE_INCOME = '41303000';

    /** @var array<string, string> */
    public const BY_PAYMENT_TYPE = [
        'BSE' => '11102002',
        'QR' => '11102002',
        'Mercado Pago' => self::MP_RECEIVABLE,
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
