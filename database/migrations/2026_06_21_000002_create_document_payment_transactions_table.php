<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending');
            $table->foreignId('customer_payment_id')->nullable()->constrained('customer_payments')->nullOnDelete();
            $table->json('provider_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payment_transactions');
    }
};
