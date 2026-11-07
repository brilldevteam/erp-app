<?php

namespace Workdo\Quotation\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\DocumentTemplate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Workdo\Account\Models\Customer;
use Workdo\ProductService\Models\ProductServiceItem;

class SalesQuotation extends Model
{
    use HasFactory;

    public const CONVERTIBLE_STATUSES = ['draft', 'sent', 'accepted'];
    public const AWAITING_STATUSES = ['draft', 'sent', 'expired'];

    protected $fillable = [
        'quotation_number',
        'revision_number',
        'parent_quotation_id',
        'quotation_date',
        'currency_code',
        'exchange_rate',
        'base_amount',
        'customer_id',
        'salesperson_id',
        'warehouse_id',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'type',
        'converted_to_invoice',
        'invoice_id',
        'document_template_id',
        'payment_terms',
        'subject',
        'notes',
        'terms',
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
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:2',
            'converted_to_invoice' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Older quotations have no stored type, so it comes from their catalog lines.
     * Null means mixed or manual lines only.
     */
    public function resolvedType(): ?string
    {
        if ($this->type) {
            return $this->type;
        }

        $itemTypes = ProductServiceItem::whereIn('id', $this->items->pluck('product_id')->filter())->pluck('type');

        if ($itemTypes->isEmpty()) {
            return null;
        }

        if ($itemTypes->every(fn ($type) => $type === 'service')) {
            return 'service';
        }

        return $itemTypes->contains('service') ? null : 'product';
    }

    /** Same rule as resolvedType(), for filtering a list. */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        $isService = fn ($product) => $product->where('type', 'service');
        $isNotService = fn ($product) => $product->where('type', '!=', 'service');

        return $query->where(fn ($quotations) => $quotations
            ->where('type', $type)
            ->orWhere(fn ($older) => $older
                ->whereNull('type')
                ->whereHas('items.product', $type === 'service' ? $isService : $isNotService)
                ->whereDoesntHave('items.product', $type === 'service' ? $isNotService : $isService)));
    }

    /** Still waiting on the customer after the due date. Accepted, rejected and converted quotations are finished. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('converted_to_invoice', false)
            ->whereIn('status', self::AWAITING_STATUSES)
            ->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return !$this->converted_to_invoice
            && in_array($this->status, self::AWAITING_STATUSES, true)
            && $this->due_date?->lt(today());
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(QuotationSalesperson::class, 'salesperson_id');
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

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
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
        $year = date('Y');
        $month = date('m');
        $lastQuotation = static::where('quotation_number', 'like', "QT-{$year}-{$month}-%")
            ->where('created_by', creatorId())
            ->orderBy('quotation_number', 'desc')
            ->first();

        if ($lastQuotation) {
            $lastNumber = (int) substr($lastQuotation->quotation_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "QT-{$year}-{$month}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
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
