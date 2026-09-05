<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_orders') && Schema::hasColumn('payment_orders', 'purchase_order_id')) {
            $this->dropForeignKeys('payment_orders', 'purchase_order_id');
            DB::statement('ALTER TABLE `payment_orders` MODIFY `purchase_order_id` BIGINT UNSIGNED NULL');
            $this->ensureForeign('payment_orders', 'purchase_order_id', 'purchase_orders', true);
        }

        if (Schema::hasTable('payment_orders') && ! Schema::hasColumn('payment_orders', 'supplier_id')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('purchase_order_id')
                    ->constrained('suppliers')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('fund_movements')) {
            $hasVouchers = Schema::hasTable('internal_vouchers');
            $hasInvoices = Schema::hasTable('supplier_invoices');

            Schema::create('fund_movements', function (Blueprint $table) use ($hasVouchers, $hasInvoices) {
                $table->id();
                $table->string('number', 32)->unique();
                $table->date('date');
                $table->string('type', 20)->default('egreso');
                $table->string('status', 20)->default('Pendiente');
                $table->string('beneficiary');
                $table->decimal('amount', 14, 2);
                $table->string('currency_code', 3)->default('ARS');
                $table->string('payment_method')->nullable();
                $table->foreignId('funds_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
                $table->foreignId('payment_order_id')->nullable()->constrained('payment_orders')->nullOnDelete();
                if ($hasVouchers) {
                    $table->foreignId('internal_voucher_id')->nullable()->constrained('internal_vouchers')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('internal_voucher_id')->nullable();
                }
                if ($hasInvoices) {
                    $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('supplier_invoice_id')->nullable();
                }
                $table->text('observations')->nullable();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('annulled_at')->nullable();
                $table->text('annulment_reason')->nullable();
                $table->foreignId('annulled_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['type', 'status']);
                $table->index('date');
            });
        }

        if (! Schema::hasTable('fund_movement_imputations')) {
            Schema::create('fund_movement_imputations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fund_movement_id')->constrained('fund_movements')->cascadeOnDelete();
                $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('memo')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_movement_imputations');
        Schema::dropIfExists('fund_movements');

        if (Schema::hasTable('payment_orders') && Schema::hasColumn('payment_orders', 'supplier_id')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_id');
            });
        }
    }

    protected function dropForeignKeys(string $table, string $column): void
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $rows = $conn->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$db, $table, $column]
        );
        foreach ($rows as $row) {
            $name = str_replace('`', '``', (string) $row->CONSTRAINT_NAME);
            DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$name.'`');
        }
    }

    protected function ensureForeign(string $table, string $column, string $references, bool $nullOnDelete): void
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        $count = (int) ($conn->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$db, $table, $column, $references]
        )->c ?? 0);
        if ($count > 0) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $nullOnDelete) {
            $fk = $blueprint->foreign($column)->references('id')->on($references);
            if ($nullOnDelete) {
                $fk->nullOnDelete();
            }
        });
    }
};
