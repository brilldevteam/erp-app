<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('sales_retainer_item_taxes'))
        {
            Schema::create('sales_retainer_item_taxes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->index();
                $table->string('tax_name');
                $table->decimal('tax_rate', 5, 2);

                $table->foreign('item_id')->references('id')->on('sales_retainer_items')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_retainer_item_taxes');
    }
};