<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentPaymentTransaction extends Model
{
    protected $fillable = [
        'invoice_id', 'provider', 'provider_reference', 'amount', 'currency',
        'status', 'customer_payment_id', 'provider_payload', 'failure_reason',
        'created_by', 'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_payload' => 'array',
        'completed_at' => 'datetime',
    ];
}
