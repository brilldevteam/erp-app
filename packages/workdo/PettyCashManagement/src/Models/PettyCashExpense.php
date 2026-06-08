<?php

namespace Workdo\PettyCashManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Workdo\PettyCashManagement\Models\PettyCashReimbursement;

class PettyCashExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'pettycash_id',
        'request_id',
        'reimbursement_id',
        'type',
        'amount',
        'remarks',
        'status',
        'approved_at',
        'approved_by',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pettycash_id' => 'integer',
            'request_id'  => 'integer',
            'reimbursement_id' => 'integer',
            'type'        => 'string',
            'status'      => 'string',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
            'created_by'  => 'integer'
        ];
    }

    public function request()
    {
        return $this->belongsTo(PettyCashRequest::class, 'request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pettyCash()
    {
        return $this->belongsTo(PettyCash::class, 'pettycash_id');
    }

    public function reimbursement()
    {
        return $this->belongsTo(PettyCashReimbursement::class, 'reimbursement_id');
    }
}
