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
        $year = date('Y');
        $month = date('m');
        $lastRequest = static::where('request_number', 'like', "Req-{$year}-{$month}-%")
            ->where('created_by', creatorId())
            ->orderBy('request_number', 'desc')
            ->first();

        if ($lastRequest) {
            $lastNumber = (int) substr($lastRequest->request_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "Req-{$year}-{$month}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
