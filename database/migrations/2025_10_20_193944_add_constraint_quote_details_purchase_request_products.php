<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear una vista que valide que los productos en quote_details 
        // solo sean aquellos que están en purchase_request_details
        DB::statement("
            CREATE VIEW quote_details_validation AS
            SELECT 
                qd.id,
                qd.market_rate_id,
                qd.product_id,
                qd.quantity,
                qd.unit_price,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM purchase_request_details prd 
                        WHERE prd.product_id = qd.product_id
                    ) THEN 'VALID'
                    ELSE 'INVALID'
                END as validation_status
            FROM quote_details qd
        ");

        // Crear un trigger que valide antes de insertar en quote_details
        DB::statement("
            CREATE TRIGGER validate_quote_detail_product
            BEFORE INSERT ON quote_details
            FOR EACH ROW
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM purchase_request_details 
                    WHERE product_id = NEW.product_id
                ) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Product must exist in purchase request details before creating quote detail';
                END IF;
            END
        ");

        // Crear un trigger que valide antes de actualizar en quote_details
        DB::statement("
            CREATE TRIGGER validate_quote_detail_product_update
            BEFORE UPDATE ON quote_details
            FOR EACH ROW
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM purchase_request_details 
                    WHERE product_id = NEW.product_id
                ) THEN
                    SIGNAL SQLSTATE '45000' 
                    SET MESSAGE_TEXT = 'Product must exist in purchase request details before updating quote detail';
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar los triggers
        DB::statement("DROP TRIGGER IF EXISTS validate_quote_detail_product");
        DB::statement("DROP TRIGGER IF EXISTS validate_quote_detail_product_update");
        
        // Eliminar la vista
        DB::statement("DROP VIEW IF EXISTS quote_details_validation");
    }
};