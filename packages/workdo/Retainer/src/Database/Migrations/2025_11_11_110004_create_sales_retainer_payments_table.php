<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('sales_retainer_payments'))
        {
            Schema::create('sales_retainer_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number');
                $table->date('payment_date');
                $table->foreignId('customer_id')->index();
                $table->foreignId('bank_account_id')->index();
                $table->decimal('payment_amount', 15, 2);
                $table->string('reference_number')->nullable();
                $table->enum('status', ['pending', 'partial','cleared', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->index();

                $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_retainer_payments');
    }
};
