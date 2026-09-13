<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_number');
            $table->foreignId('vendor_id')->constrained('users');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('vendor_reference')->nullable();
            $table->string('vendor_quotation_number')->nullable();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('currency_code', 3)->default('QAR');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->json('billing_address')->nullable();
            $table->json('delivery_address')->nullable();
            $table->string('order_status')->default('draft');
            $table->string('billing_status')->default('unbilled');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('line_discount_amount', 18, 2)->default(0);
            $table->decimal('document_discount_amount', 18, 2)->default(0);
            $table->decimal('document_discount_value', 18, 2)->default(0);
            $table->string('document_discount_type')->default('fixed');
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('shipping_amount', 18, 2)->default(0);
            $table->decimal('adjustment_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('creator_id')->constrained('users');
            $table->unsignedBigInteger('created_by');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['created_by', 'purchase_order_number']);
            $table->index(['created_by', 'order_status', 'billing_status']);
        });
        }

        if (!Schema::hasTable('purchase_order_items')) {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_value', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedInteger('line_order')->default(0);
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('purchase_order_item_taxes')) {
        Schema::create('purchase_order_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->string('tax_name');
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_amount', 18, 2);
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('purchase_order_approvals')) {
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('level')->default(1);
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('purchase_order_status_histories')) {
        Schema::create('purchase_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('purchase_order_attachments')) {
        Schema::create('purchase_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
        }

        if (!Schema::hasTable('purchase_order_invoice_links')) {
        Schema::create('purchase_order_invoice_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['purchase_order_id', 'purchase_invoice_id']);
        });
        }

        if (!Schema::hasTable('purchase_order_invoice_items')) {
        Schema::create('purchase_order_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_item_id')->nullable()->constrained('purchase_invoice_items')->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->unique(['purchase_order_item_id', 'purchase_invoice_item_id'], 'po_invoice_item_unique');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_invoice_items');
        Schema::dropIfExists('purchase_order_invoice_links');
        Schema::dropIfExists('purchase_order_attachments');
        Schema::dropIfExists('purchase_order_status_histories');
        Schema::dropIfExists('purchase_order_approvals');
        Schema::dropIfExists('purchase_order_item_taxes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
