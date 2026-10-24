<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bill lines: the account each line posts to, and a percentage or fixed-amount discount like sales invoices.
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_invoice_items', 'account_id')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('product_id')->index();
            });
        }

        if (!Schema::hasColumn('purchase_invoice_items', 'discount_type')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->string('discount_type', 16)->default('percentage')->after('unit_price');
                $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_invoice_items', 'discount_type')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->dropColumn(['discount_type', 'discount_value']);
            });
        }

        if (Schema::hasColumn('purchase_invoice_items', 'account_id')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            });
        }
    }
};
