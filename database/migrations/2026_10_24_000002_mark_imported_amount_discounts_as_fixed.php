<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imported sales lines (e.g. from Zoho) carry a discount amount with a 0% discount and the default
     * "percentage" type. Mark them as fixed-amount discounts of the same amount so they display as an
     * amount and keep their discount when the document is edited or returned. Amounts and totals are unchanged.
     */
    public function up(): void
    {
        foreach (['sales_invoice_items', 'sales_quotation_items', 'purchase_invoice_items'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'discount_type') || !Schema::hasColumn($table, 'discount_value')) {
                continue;
            }

            DB::table($table)
                ->where('discount_amount', '>', 0)
                ->where(fn ($query) => $query->whereNull('discount_percentage')->orWhere('discount_percentage', 0))
                ->where(fn ($query) => $query->whereNull('discount_type')->orWhere('discount_type', '!=', 'fixed'))
                ->whereRaw('discount_amount <= ROUND(quantity * unit_price, 2)')
                ->update(['discount_type' => 'fixed', 'discount_value' => DB::raw('discount_amount')]);
        }
    }

    public function down(): void
    {
        // Data correction only; the previous type carried no extra information.
    }
};
