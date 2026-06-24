<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_return_items', function (Blueprint $table) {
            $table->decimal('return_quantity', 15, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_return_items', function (Blueprint $table) {
            $table->integer('return_quantity')->change();
        });
    }
};
