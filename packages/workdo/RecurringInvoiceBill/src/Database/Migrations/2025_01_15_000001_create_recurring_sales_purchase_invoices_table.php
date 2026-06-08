<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('recurring_sales_purchase_invoices')) {
            Schema::create('recurring_sales_purchase_invoices', function (Blueprint $table) {
                $table->id();
                $table->integer('invoice_id');
                $table->enum('invoice_type', ['sales', 'purchase']);
                $table->string('recurring_duration');
                $table->integer('cycles');
                $table->string('day_type')->nullable();
                $table->integer('count')->nullable();
                $table->integer('pending_cycle');
                $table->date('modify_date');
                $table->date('modify_due_date');
                $table->text('duplicate_invoices')->nullable();
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('recurring_sales_purchase_invoices');
    }
};