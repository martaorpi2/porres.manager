<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SupplierInvoice extends Model
{
    use CrudTrait;

    protected $table = 'supplier_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function accountingAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function getIdentifyingLabelAttribute(): string
    {
        $supplier = $this->supplier?->company_name ?? 'Proveedor';
        $date = $this->invoice_date?->format('d/m/Y');

        return $supplier.' — '.$this->invoice_number.($date ? ' ('.$date.')' : '');
    }

    /**
     * @return array<int, string>
     */
    public static function optionsForSelect(): array
    {
        return static::query()
            ->with('supplier')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(function (self $invoice) {
                return [$invoice->id => $invoice->identifying_label];
            })
            ->all();
    }

    public function paymentOrders(): BelongsToMany
    {
        return $this->belongsToMany(PaymentOrder::class, 'payment_order_invoice', 'supplier_invoice_id', 'payment_order_id')
            ->withPivot(['amount_applied', 'imputed_at'])
            ->withTimestamps();
    }

    /**
     * Suma imputada desde órdenes de pago no anuladas (pagos normales + anticipos aplicados).
     */
    public function allocatedAmountFromActiveOrders(): float
    {
        return (float) DB::table('payment_order_invoice as poi')
            ->join('payment_orders as po', 'po.id', '=', 'poi.payment_order_id')
            ->where('poi.supplier_invoice_id', $this->id)
            ->where('po.status', '!=', 'Anulada')
            ->sum('poi.amount_applied');
    }

    /**
     * Saldo pendiente de la factura: total menos lo imputado desde OP activas.
     */
    public function openBalance(): float
    {
        $open = (float) $this->total_amount - $this->allocatedAmountFromActiveOrders();

        return round(max(0, $open), 2);
    }
}
