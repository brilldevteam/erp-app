<?php

namespace Workdo\Retainer\Models;

use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Workdo\Account\Models\Customer;

class SalesRetainer extends Model
{
    protected $fillable = [
        'retainer_number',
        'retainer_date',
        'due_date',
        'customer_id',
        'warehouse_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'converted_to_invoice',
        'invoice_id',
        'payment_terms',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'retainer_date' => 'date',
        'due_date' => 'date',
        'converted_to_invoice' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($retainer) {
            if (empty($retainer->retainer_number)) {
                $retainer->retainer_number = self::generateRetainerNumber();
            }
        });
    }

    public static function generateRetainerNumber()
    {
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'retainer_number', 'RET-' . date('Y') . '-' . date('m') . '-', 3);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function customerDetails(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesRetainerItem::class, 'retainer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesRetainerPayment::class, 'retainer_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $client_permission = [
            'manage-retainer',
            'manage-own-retainer',
            'view-retainer',
            'print-retainer',
            'accept-retainer',
            'reject-retainer',
            'manage-retainer-payments',
            'manage-own-retainer-payments',
            'view-retainer-payments'
            
        ];

        if ($rolename == 'client') {
            $roles_v = Role::where('name', 'client')->where('id', $role_id)->first();
            foreach ($client_permission as $permission_v) {
                $permission = Permission::where('name', $permission_v)->first();
                if (!empty($permission)) {
                    if (!$roles_v->hasPermissionTo($permission_v)) {
                        $roles_v->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}