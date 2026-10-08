<?php

namespace App\Services\PaymentSettlement;

use App\Models\AccountingEntry;
use App\Models\QuotaAccountingBatch;
use App\Services\QuotaPaymentAccounts;
use RuntimeException;

final class PaymentSettlementChannels
{
    public const NARANJA = 'naranja';

    public const SOL = 'sol';

    public const QR = 'qr';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::NARANJA, self::SOL, self::QR];
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
     * @return array<string, array<string, mixed>>
     */
    private static function definitions(): array
    {
        return [
            self::NARANJA => [
                'title' => 'Liquidación Naranja X',
                'menu' => 'Naranja X',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [
                    'En Naranja X cada fila es un día, con los totales de ese día.',
                ],
                'entry_lines' => [
                    ['Banco BSE', 'por el neto acreditado.'],
                    ['Comisiones Tarjeta Naranja', 'por el arancel y el IVA.'],
                    ['Intereses pagados', 'por el interés de los planes.'],
                    ['Tarjeta Naranja a cobrar', 'por el importe bruto.'],
                ],
                'mode' => 'receivable',
                'payment_type' => 'Tarjeta Naranja',
                'batch_kind' => QuotaAccountingBatch::KIND_NX_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_NX_SETTLEMENT,
                'description' => 'LIQUIDACION COBRANZA NARANJA X',
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
                'title' => 'Liquidación Sol Pago',
                'menu' => 'Sol Pago',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [
                    'Cada fila es un cupón. Las filas de la misma fecha forman un asiento.',
                ],
                'entry_lines' => [
                    ['Banco BSE', 'por el neto de la fecha.'],
                    ['Comisiones Tarjeta Sol', 'por el arancel, el IVA y las otras deducciones.'],
                    ['Intereses pagados', 'por el costo financiero.'],
                    ['Tarjeta Sol a cobrar', 'por el monto presentado.'],
                ],
                'mode' => 'receivable',
                'payment_type' => 'Tarjeta Sol',
                'batch_kind' => QuotaAccountingBatch::KIND_SOL_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_SOL_SETTLEMENT,
                'description' => 'LIQUIDACION COBRANZA SOL PAGO',
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
                'title' => 'Liquidación QR',
                'menu' => 'QR',
                'accept' => '.xlsx,.xls',
                'extensions' => ['xlsx', 'xls'],
                'file_label' => 'Excel de acreditación',
                'extension_error' => 'El archivo tiene que ser un Excel (.xlsx).',
                'empty_error' => 'Elegí el Excel de la acreditación.',
                'paragraphs' => [
                    'Cada fila es un cupón. Las filas de la misma fecha forman un asiento.',
                ],
                'entry_lines' => [
                    ['Banco BSE', 'por el neto depositado.'],
                    ['Comisiones cobranzas QR', 'por el arancel y el IVA.'],
                    ['Deudores por cuotas', 'por el importe bruto.'],
                ],
                'mode' => 'receivable',
                'payment_type' => 'QR',
                'batch_kind' => QuotaAccountingBatch::KIND_QR_SETTLEMENT,
                'entry_kind' => AccountingEntry::KIND_QUOTA_QR_SETTLEMENT,
                'description' => 'LIQUIDACION COBRANZA QR',
                'bank' => '11102002',
                'receivable' => QuotaPaymentAccounts::DEBTORS,
                'commission' => '52320000',
                'interest' => null,
                'bank_memo' => 'Banco BSE',
                'receivable_memo' => 'Deudores por cuotas',
                'commission_memo' => 'Comisión e IVA QR',
                'interest_memo' => null,
            ],
        ];
    }
}
