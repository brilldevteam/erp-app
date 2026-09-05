<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales_invoice_items', 'sales_quotation_items'] as $name) {
            if (!Schema::hasTable($name)) {
                continue;
            }
            Schema::table($name, function (Blueprint $table) {
                $table->text('description')->nullable();
                $table->string('discount_type', 16)->default('percentage');
                $table->decimal('discount_value', 15, 2)->default(0);
            });
            // Snapshot the short description previously displayed on existing documents.
            if (Schema::hasTable('product_service_items')) {
                \Illuminate\Support\Facades\DB::table($name)->orderBy('id')->chunkById(500, function ($rows) use ($name) {
                    $descriptions = \Illuminate\Support\Facades\DB::table('product_service_items')
                        ->whereIn('id', $rows->pluck('product_id'))->pluck('description', 'id');
                    foreach ($rows as $row) {
                        \Illuminate\Support\Facades\DB::table($name)->where('id', $row->id)->update([
                            'description' => \App\Services\SalesLineAmounts::description($descriptions[$row->product_id] ?? ''),
                        ]);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['sales_invoice_items', 'sales_quotation_items'] as $name) {
            if (Schema::hasTable($name)) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['description', 'discount_type', 'discount_value']));
            }
        }
    }
};
