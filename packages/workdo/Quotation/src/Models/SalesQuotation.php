<?php

namespace Workdo\Quotation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Workdo\Account\Models\Customer;

class SalesQuotation extends Model
{
    use HasFactory;

    public const CONVERTIBLE_STATUSES = ['draft', 'sent', 'accepted'];

    protected $fillable = [
        'quotation_number',
        'revision_number',
        'parent_quotation_id',
        'quotation_date',
        'customer_id',
        'warehouse_id',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'template_key',
        'document_logo',
        'document_snapshot',
        'sent_at',
        'first_viewed_at',
        'last_viewed_at',
        'accepted_at',
        'rejected_at',
        'customer_action_name',
        'customer_action_comment',
        'converted_to_invoice',
        'invoice_id',
        'payment_terms',
        'notes',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'converted_to_invoice' => 'boolean',
            'document_snapshot' => 'array',
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesQuotationItem::class, 'quotation_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function customerDetails(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'user_id');
    }

    public function parentQuotation(): BelongsTo
    {
        return $this->belongsTo(SalesQuotation::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SalesQuotation::class, 'parent_quotation_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SalesInvoice::class, 'invoice_id');
    }

    public function canConvertToInvoice(): bool
    {
        return !$this->converted_to_invoice
            && !$this->invoice_id
            && in_array($this->status, self::CONVERTIBLE_STATUSES, true);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quotation) {
            if (empty($quotation->quotation_number)) {
                $quotation->quotation_number = static::generateQuotationNumber();
            }
        });
    }

    public static function generateQuotationNumber(): string
    {
        return app(\App\Services\Documents\DocumentNumberService::class)
            ->next('quotation', static::class, 'quotation_number');
    }
    public static function GivePermissionToRoles($role_id = null, $rolename = null)
    {
        $client_permission = [
            'manage-quotations',
            'manage-own-quotations',
            'view-quotations',
            'print-quotations',
            'approve-quotations',
            'reject-quotations'
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
