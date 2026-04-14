<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseOrderDetail extends Model
{
    use CrudTrait;
    use HasFactory;

    protected $table = 'oc_details';
    protected $guarded = ['id'];

    protected $fillable = [
        'purchase_order_id',
        'supplier_id',
        'input_id',
        'quantity',
        'unit_price',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class, 'supplier_id');
    }

    /**
     * Asegura supplier_id en oc_details (BD sin migración 2026_02_13).
     */
    public static function ensureSupplierIdColumnExists(): void
    {
        $tableName = (new static)->getTable();
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'supplier_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) {
            $blueprint->foreignId('supplier_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('suppliers')
                ->onDelete('restrict');
        });

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'supplier_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement(
                    "UPDATE {$tableName} d INNER JOIN purchase_orders po ON d.purchase_order_id = po.id SET d.supplier_id = po.supplier_id WHERE d.supplier_id IS NULL AND po.supplier_id IS NOT NULL"
                );
            } else {
                foreach (DB::table('purchase_orders')->select('id', 'supplier_id')->get() as $po) {
                    if ($po->supplier_id) {
                        DB::table($tableName)->where('purchase_order_id', $po->id)->whereNull('supplier_id')->update(['supplier_id' => $po->supplier_id]);
                    }
                }
            }
        }
    }

    public function input()
    {
        return $this->belongsTo(\App\Models\Input::class, 'input_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getSubtotalAttribute()
    {
        return (float) $this->quantity * (float) ($this->attributes['unit_price'] ?? 0);
    }
}
