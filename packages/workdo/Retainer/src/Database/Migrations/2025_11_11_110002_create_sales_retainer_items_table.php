<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('sales_retainer_items'))
        {
            Schema::create('sales_retainer_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('retainer_id');
                $table->unsignedBigInteger('product_id');
                $table->decimal('quantity', 10, 2);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_percentage', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2);
                $table->timestamps();
                
                $table->foreign('retainer_id')->references('id')->on('sales_retainers')->onDelete('cascade');

                $table->index('retainer_id');
                $table->index('product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_retainer_items');
    }
};