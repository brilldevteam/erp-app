<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $transactionTables = [
        'sales_quotations', 'sales_invoices', 'sales_invoice_returns',
        'purchase_orders', 'purchase_invoices', 'purchase_returns',
        'customer_payments', 'vendor_payments', 'credit_notes', 'debit_notes',
        'revenues', 'expenses', 'bank_transactions', 'bank_transfers', 'journal_entries',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('company_currency_rates')) {
            Schema::create('company_currency_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->index();
                $table->string('currency_code', 3);
                $table->decimal('exchange_rate', 20, 8);
                $table->date('effective_date');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['created_by', 'currency_code', 'effective_date'], 'company_currency_rate_unique');
            });
        }

        foreach ($this->transactionTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'currency_code')) {
                    $table->string('currency_code', 3)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'exchange_rate')) {
                    $table->decimal('exchange_rate', 20, 8)->nullable();
                }
                if (!Schema::hasColumn($tableName, 'base_amount')) {
                    $table->decimal('base_amount', 20, 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('bank_accounts') && !Schema::hasColumn('bank_accounts', 'currency_code')) {
            Schema::table('bank_accounts', fn (Blueprint $table) => $table->string('currency_code', 3)->nullable());
        }

        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('journal_entries', 'foreign_amount')) {
                    $table->decimal('foreign_amount', 20, 2)->nullable();
                }
            });
        }

        foreach ($this->transactionTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            DB::table($tableName)->whereNull('exchange_rate')->update(['exchange_rate' => 1]);
        }
    }

    public function down(): void
    {
        foreach ($this->transactionTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $columns = array_values(array_filter(
                ['currency_code', 'exchange_rate', 'base_amount'],
                fn (string $column) => Schema::hasColumn($tableName, $column)
            ));
            if ($columns) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }

        if (Schema::hasTable('bank_accounts') && Schema::hasColumn('bank_accounts', 'currency_code')) {
            Schema::table('bank_accounts', fn (Blueprint $table) => $table->dropColumn('currency_code'));
        }
        if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', 'foreign_amount')) {
            Schema::table('journal_entries', fn (Blueprint $table) => $table->dropColumn('foreign_amount'));
        }
        Schema::dropIfExists('company_currency_rates');
    }
};
