<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_accounts') && ! Schema::hasColumn('accounting_accounts', 'account_type')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                $table->string('account_type', 20)->nullable()->after('name');
            });
        }

        if (Schema::hasTable('payment_orders')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_orders', 'imputation_account_id')) {
                    $table->foreignId('imputation_account_id')
                        ->nullable()
                        ->after('bank')
                        ->constrained('accounting_accounts')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('payment_orders', 'funds_account_id')) {
                    $table->foreignId('funds_account_id')
                        ->nullable()
                        ->after('imputation_account_id')
                        ->constrained('accounting_accounts')
                        ->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('accounting_entries')) {
            Schema::create('accounting_entries', function (Blueprint $table) {
                $table->id();
                $table->string('entry_number', 32)->unique();
                $table->date('date');
                $table->string('kind', 24);
                $table->string('status', 16)->default('posted');
                $table->string('source_type');
                $table->unsignedBigInteger('source_id');
                $table->string('description');
                $table->foreignId('reversed_entry_id')->nullable()->constrained('accounting_entries')->nullOnDelete();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['source_type', 'source_id']);
                $table->index(['kind', 'status']);
            });
        }

        if (! Schema::hasTable('accounting_entry_lines')) {
            Schema::create('accounting_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_entry_id')->constrained('accounting_entries')->cascadeOnDelete();
                $table->foreignId('accounting_account_id')->constrained('accounting_accounts')->restrictOnDelete();
                $table->decimal('debit', 14, 2)->default(0);
                $table->decimal('credit', 14, 2)->default(0);
                $table->string('memo')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');

        if (Schema::hasTable('payment_orders')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                if (Schema::hasColumn('payment_orders', 'funds_account_id')) {
                    $table->dropConstrainedForeignId('funds_account_id');
                }
                if (Schema::hasColumn('payment_orders', 'imputation_account_id')) {
                    $table->dropConstrainedForeignId('imputation_account_id');
                }
            });
        }

        if (Schema::hasTable('accounting_accounts') && Schema::hasColumn('accounting_accounts', 'account_type')) {
            Schema::table('accounting_accounts', function (Blueprint $table) {
                $table->dropColumn('account_type');
            });
        }
    }
};
