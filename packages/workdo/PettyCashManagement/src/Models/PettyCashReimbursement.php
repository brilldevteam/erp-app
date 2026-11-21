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
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'reimbursement_number', 'Reimb-' . date('Y') . '-' . date('m') . '-', 3);
    }
}
