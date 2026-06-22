<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentShareLink extends Model
{
    protected $fillable = [
        'document_type', 'document_id', 'token_hash', 'encrypted_token', 'created_by', 'expires_at',
        'revoked_at', 'first_viewed_at', 'last_viewed_at', 'view_count',
        'last_ip', 'last_user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'encrypted_token' => 'encrypted',
    ];

    public function isUsable(): bool
    {
        return !$this->revoked_at && (!$this->expires_at || $this->expires_at->isFuture());
    }
}
