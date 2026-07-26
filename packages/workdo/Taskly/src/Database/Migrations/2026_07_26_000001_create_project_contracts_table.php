<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_contracts')) {
            Schema::create('project_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
                $table->enum('type', ['main', 'subcontractor'])->index();
                $table->foreignId('parent_contract_id')->nullable()->constrained('project_contracts')->nullOnDelete();
                $table->string('scope_of_work');
                $table->decimal('contract_value', 15, 2);
                $table->date('work_start_date');
                $table->date('completion_date');
                $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['created_by', 'project_id']);
                $table->index(['project_id', 'vendor_id']);
            });
        }

        if (Schema::hasTable('purchase_invoices') && !Schema::hasColumn('purchase_invoices', 'project_contract_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->foreignId('project_contract_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('project_contracts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'project_contract_id')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_contract_id');
            });
        }

        Schema::dropIfExists('project_contracts');
    }
};
