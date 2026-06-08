<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('sales_retainers'))
        {
            Schema::create('sales_retainers', function (Blueprint $table) {
                $table->id();
                $table->string('retainer_number');
                $table->date('retainer_date');
                $table->date('due_date');
                $table->foreignId('customer_id')->index();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('balance_amount', 15, 2)->default(0);
                $table->enum('status', ['draft', 'sent', 'accepted','partial', 'paid','converted','rejected'])->default('draft');
                $table->boolean('converted_to_invoice')->default(false);
                $table->foreignId('invoice_id')->nullable()->index();
                $table->text('payment_terms')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->index();

                $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('invoice_id')->references('id')->on('sales_invoices')->onDelete('cascade');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_retainers');
    }
};