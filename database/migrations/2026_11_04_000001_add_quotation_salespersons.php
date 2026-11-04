<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_salespersons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['created_by', 'is_active']);
            $table->unique(['created_by', 'name']);
        });

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->foreignId('salesperson_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('quotation_salespersons')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
        });
        Schema::dropIfExists('quotation_salespersons');
    }
};
