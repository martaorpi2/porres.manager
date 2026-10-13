<?php

namespace App\Services\PaymentSettlement;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\QuotaPaymentAccounts;
use RuntimeException;

final class PaymentSettlementChannels
{
    public const MERCADOPAGO = 'mercadopago';

    public const NARANJA = 'naranja';

    public const SOL = 'sol';

    public const QR = 'qr';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::MERCADOPAGO, self::NARANJA, self::SOL, self::QR];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $channel): array
    {
        $found = self::definitions()[$channel] ?? null;
        if ($found === null) {
            throw new RuntimeException('Medio de pago desconocido.');
        }

        return $found;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forBatchKind(string $kind): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['batch_kind'] === $kind) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        return [
            self::MERCADOPAGO => [
                'title' => 'Acreditación Mercado Pago',
                'menu' => 'Mercado Pago',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [],
                'mode' => 'receivable',
                'payment_type' => 'Mercado Pago',
                'batch_kind' => QuotaAccountingBatch::KIND_MP_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_MP_SETTLEMENT,
                'description' => 'ACREDITACION COBRANZA MERCADO PAGO',
                'bank' => QuotaPaymentAccounts::MP_AVAILABLE,
                'receivable' => QuotaPaymentAccounts::MP_RECEIVABLE,
                'commission' => QuotaPaymentAccounts::MP_COMMISSION,
                'interest' => '52312000',
                'bank_memo' => 'Mercado Pago',
                'receivable_memo' => 'Mercado Pago a cobrar',
                'commission_memo' => 'Comisión Mercado Pago',
                'interest_memo' => 'Intereses Mercado Pago',
            ],
            self::NARANJA => [
                'title' => 'Acreditación Naranja X',
                'menu' => 'Naranja X',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [
                    'En Naranja X cada fila es un día, con los totales de ese día.',
                ],
                'mode' => 'receivable',
                'payment_type' => 'Tarjeta Naranja',
                'batch_kind' => QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_NX_SETTLEMENT,
                'description' => 'ACREDITACION COBRANZA NARANJA X',
                'bank' => '11102002',
                'receivable' => '11202003',
                'commission' => '52306000',
                'interest' => '52312000',
                'bank_memo' => 'Banco BSE',
                'receivable_memo' => 'Tarjeta Naranja a cobrar',
                'commission_memo' => 'Arancel e IVA Naranja X',
                'interest_memo' => 'Intereses plan Naranja X',
            ],
            self::SOL => [
                'title' => 'Acreditación Sol Pago',
                'menu' => 'Sol Pago',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [],
                'mode' => 'receivable',
                'payment_type' => 'Tarjeta Sol',
                'batch_kind' => QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT,
                'description' => 'ACREDITACION COBRANZA SOL PAGO',
                'bank' => '11102002',
                'receivable' => '11202001',
                'commission' => '52307000',
                'interest' => '52312000',
                'bank_memo' => 'Banco BSE',
                'receivable_memo' => 'Tarjeta Sol a cobrar',
                'commission_memo' => 'Arancel e IVA Sol Pago',
                'interest_memo' => 'Costo financiero Sol Pago',
            ],
            self::QR => [
                'title' => 'Acreditación QR',
                'menu' => 'QR',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [],
                'mode' => 'receivable',
                'payment_type' => 'QR',
                'batch_kind' => QuotaAccountingBatch::KIND_QR_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_QR_SETTLEMENT,
                'description' => 'ACREDITACION COBRANZA QR',
                'bank' => '11102002',
                'receivable' => QuotaPaymentAccounts::QR_RECEIVABLE,
                'commission' => '52320000',
                'interest' => '52312000',
                'bank_memo' => 'Banco BSE',
                'receivable_memo' => 'QR a cobrar',
                'commission_memo' => 'Comisión e IVA QR',
                'interest_memo' => 'Intereses QR',
            ],
        ];
    }
}
