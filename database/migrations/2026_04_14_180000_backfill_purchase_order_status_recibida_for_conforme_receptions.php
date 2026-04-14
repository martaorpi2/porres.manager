<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Las OC con recepción conforme debían figurar como Recibida; el campo quedaba en Pendiente por defecto.
     */
    public function up(): void
    {
        PurchaseOrder::query()
            ->where('status', '!=', 'Recibida')
            ->whereHas('receptions', function ($q) {
                $q->where('according', 'Si');
            })
            ->update(['status' => 'Recibida', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Sin reversión segura (no se guardaba el estado anterior).
    }
};
