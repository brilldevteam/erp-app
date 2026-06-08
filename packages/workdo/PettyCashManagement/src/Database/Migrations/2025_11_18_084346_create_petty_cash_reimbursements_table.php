<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('petty_cash_reimbursements'))
        {
            Schema::create('petty_cash_reimbursements', function (Blueprint $table) {
                $table->id();
                $table->string('reimbursement_number');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('category_id')->nullable()->constrained('petty_cash_categories')->onDelete('set null');
                $table->decimal('amount', 10, 2)->nullable();
                $table->string('status')->default('0');
                $table->longText('description')->nullable();
                $table->string('receipt_path')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->decimal('approved_amount', 10, 2)->nullable();
                $table->timestamp('approved_date')->nullable();
                $table->longText('rejection_reason')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_reimbursements');
    }
};
