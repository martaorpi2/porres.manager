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

    public const PLAN = '11204000';

    public const PLAN_FINANCING_DEFERRED = '11205000';

    /**
     * Nombres del plan cargado. Si el código no coincide, se busca la cuenta por este nombre.
     *
     * @var array<string, string>
     */
    public const NAMES = [
        self::PLAN => 'PLAN DE PAGO DS X CUOTAS',
        self::PLAN_FINANCING_DEFERRED => 'INTERES FINANCIACION PLAN DE PAGO A DEVENGAR',
    ];

    public const PLAN_FINANCING_INCOME = '41209000';

    public const CARD_INTEREST = '41210000';

    public const OTHER_FINANCIAL_INCOME = '41208000';

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

    public static function normalizeName(string $name): string
    {
        $name = mb_strtoupper(trim($name), 'UTF-8');
        $name = strtr($name, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $name = preg_replace('/[^A-Z0-9]+/', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    public static function bankCode(?string $paymentType): ?string
    {
        $type = trim((string) $paymentType);

        return self::BY_PAYMENT_TYPE[$type] ?? null;
    }

    /**
     * Recargo que el medio suma a la cuota del plan.
     * Tarjetas van a Int. por cobro tarjeta. Mercado Pago tiene cuenta propia.
     * BSE y QR, si traen un resto, van a otros ingresos financieros.
     */
    public static function planSurchargeCode(?string $paymentType): string
    {
        $type = trim((string) $paymentType);
        if ($type === 'Mercado Pago') {
            return self::MP_SURCHARGE_INCOME;
        }
        if (str_starts_with($type, 'Tarjeta')) {
            return self::CARD_INTEREST;
        }

        return self::OTHER_FINANCIAL_INCOME;
    }
}
