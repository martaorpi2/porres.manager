<?php

namespace App\Services;

/**
 * Medio de pago de ePorres → cuenta del plan.
 * Cada medio genera la cobranza el día del cobro, en su cuenta a cobrar.
 * La acreditación del archivo cancela esa cuenta cuando se sube.
 * Tarjeta de crédito no tiene cuenta imputable en el plan cargado.
 */
final class QuotaPaymentAccounts
{
    public const DEBTORS = '11201000';

    public const QUOTAS_INCOME = '41102000';

    public const SCHOLARSHIP = '52302000';

    public const LATE_INTEREST = '41201000';

    public const MP_RECEIVABLE = '11202005';

    public const QR_RECEIVABLE = '11202006';

    public const MP_AVAILABLE = '11104000';

    public const MP_COMMISSION = '52309000';

    public const MP_SURCHARGE_INCOME = '41303000';

    /** @var array<string, string> */
    public const BY_PAYMENT_TYPE = [
        'BSE' => '11102002',
        'QR' => self::QR_RECEIVABLE,
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
