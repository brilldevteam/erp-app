<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpdeskReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'message',
        'attachments',
        'is_internal',
        'created_by',
        'sender_name',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'is_internal' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (HelpdeskReply $reply) {
            $reply->files->each->deleteStoredFile();
        });
    }

    /** Internal notes belong to the support side only. */
    public static function canSeeInternal(?User $user): bool
    {
        return $user !== null && $user->isSupportSide() && $user->can('view-helpdesk-internal-notes');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return self::canSeeInternal($user) ? $query : $query->where('is_internal', false);
    }

    public function files(): HasMany
    {
        return $this->hasMany(HelpdeskAttachment::class, 'reply_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSenderDisplayNameAttribute(): string
    {
        return $this->creator?->name ?: $this->sender_name ?: __('Deleted user');
    }
}
