<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('retainer_payment_allocations')) {
            Schema::create('retainer_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->unsignedBigInteger('retainer_id');
                $table->decimal('allocated_amount', 15, 2);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();
                $table->timestamps();

                $table->foreign('payment_id')->references('id')->on('sales_retainer_payments')->onDelete('cascade');
                $table->foreign('retainer_id')->references('id')->on('sales_retainers')->onDelete('cascade');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retainer_payment_allocations');
    }
};
