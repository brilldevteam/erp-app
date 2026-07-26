<?php

namespace Workdo\Taskly\Models;

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Models\Vendor;

class ProjectContract extends Model
{
    protected $fillable = [
        'project_id',
        'vendor_id',
        'type',
        'parent_contract_id',
        'scope_of_work',
        'contract_value',
        'work_start_date',
        'completion_date',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'work_start_date' => 'date',
            'completion_date' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function parentContract()
    {
        return $this->belongsTo(self::class, 'parent_contract_id');
    }

    public function subcontractors()
    {
        return $this->hasMany(self::class, 'parent_contract_id');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function financialSummary(): array
    {
        $contractValue = (float) $this->contract_value;
        $amountPaid = (float) ($this->getAttribute('amount_paid') ?? 0);

        return [
            'contract_value' => $contractValue,
            'amount_paid' => $amountPaid,
            'remaining_balance' => $contractValue - $amountPaid,
        ];
    }
}
