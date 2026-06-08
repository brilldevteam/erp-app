<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('petty_cash_expenses'))
        {
            Schema::create('petty_cash_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pettycash_id')->nullable()->index();
                $table->foreignId('request_id')->nullable()->index();
                $table->foreignId('reimbursement_id')->nullable()->index();
                $table->string('type')->default('0');
                $table->string('amount');
                $table->longText('remarks')->nullable();
                $table->string('status')->default('0');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->index();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('pettycash_id')->references('id')->on('petty_cashes')->onDelete('set null');
                $table->foreign('request_id')->references('id')->on('petty_cash_requests')->onDelete('set null');
                $table->foreign('reimbursement_id')->references('id')->on('petty_cash_reimbursements')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_expenses');
    }
};
