<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(!Schema::hasTable('petty_cashes'))
        {
            Schema::create('petty_cashes', function (Blueprint $table) {
                $table->id();
                $table->string('pettycash_number');
                $table->date('date')->nullable();
                $table->decimal('opening_balance', 10, 2);
                $table->decimal('added_amount', 10, 2)->default(0);
                $table->decimal('total_balance', 10, 2)->default(0);
                $table->decimal('total_expense', 10, 2)->default(0);
                $table->decimal('closing_balance', 10, 2);
                $table->string('status')->default(0);
                $table->longText('remarks')->nullable();
                $table->foreignId('bank_account_id')->nullable();

                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cashes');
    }
};
