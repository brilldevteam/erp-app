<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class PettyCashReimbursement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_number',
        'user_id',
        'category_id',
        'amount',
        'status',
        'description',
        'approved_date',
        'receipt_path',
        'approved_amount',
        'rejection_reason',
        'approved_by',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'category_id' => 'integer',
            'amount' => 'decimal:2',
            'request_date' => 'datetime',
            'approved_date' => 'datetime',
            'approved_by' => 'integer',
            'receipt_path' => 'string',
            'approved_amount' => 'decimal:2'
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(PettyCashCategory::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reimbursement) {
            if (empty($reimbursement->reimbursement_number)) {
                $reimbursement->reimbursement_number = static::generateReimbursementNumber();
            }
        });
    }

    public static function generateReimbursementNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastReimbursement = static::where('reimbursement_number', 'like', "Reimb-{$year}-{$month}-%")
            ->where('created_by', creatorId())
            ->orderBy('reimbursement_number', 'desc')
            ->first();

        if ($lastReimbursement) {
            $lastNumber = (int) substr($lastReimbursement->reimbursement_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "Reimb-{$year}-{$month}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
