<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class PettyCashRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_number',
        'user_id',
        'categorie_id',
        'requested_amount',
        'status',
        'remarks',
        'approved_at',
        'approved_by',
        'approved_amount',
        'rejection_reason',
        'created_by',
        'creator_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'categorie_id' => 'integer',
            'requested_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
            'approved_amount' => 'decimal:2'
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(PettyCashCategory::class, 'categorie_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments()
    {
        return $this->hasMany(PettyCashAttachment::class, 'attachable_id')
            ->where('attachable_type', PettyCashAttachment::REQUEST)->latest('id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (empty($request->request_number)) {
                $request->request_number = static::generateRequestNumber();
            }
        });
    }

    public static function generateRequestNumber(): string
    {
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'request_number', 'Req-' . date('Y') . '-' . date('m') . '-', 3);
    }
}
