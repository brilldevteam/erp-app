<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('type', 32);
            $table->string('status', 24)->default('active');
            $table->boolean('is_default')->default(false);
            $table->string('primary_color', 16)->default('#10b981');
            $table->string('logo_url', 500)->nullable();
            $table->json('config_json');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->text('bank_details')->nullable();
            $table->string('signature_text')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'type', 'is_default']);
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('document_template_id')->nullable()->after('quotation_id');
        });

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('document_template_id')->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->dropColumn('document_template_id');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn('document_template_id');
        });

        Schema::dropIfExists('document_templates');
    }
};
