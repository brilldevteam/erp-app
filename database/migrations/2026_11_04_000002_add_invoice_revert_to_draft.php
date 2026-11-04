<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'revert-sales-invoices-to-draft' => ['sales-invoices', 'Revert Sales Invoices to Draft'],
        'revert-purchase-invoices-to-draft' => ['purchase-invoices', 'Revert Purchase Invoices to Draft'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('invoice_status_histories')) {
            Schema::create('invoice_status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('action', 64);
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['document_type', 'document_id']);
            $table->index(['created_by', 'created_at']);
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($this->permissions as $name => [$module, $label]) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => $module, 'label' => $label, 'add_on' => 'general']
            );
            Role::whereIn('name', ['company', 'super admin'])->where('guard_name', 'web')->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_status_histories');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::whereIn('name', array_keys($this->permissions))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
