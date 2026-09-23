<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->string('cr_number')->nullable()->after('tax_number'));
        Schema::table('vendors', fn (Blueprint $table) => $table->string('cr_number')->nullable()->after('tax_number'));

        Schema::create('party_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_attachments');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('cr_number'));
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn('cr_number'));
    }
};
