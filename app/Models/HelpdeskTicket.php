<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HelpdeskTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'title',
        'description',
        'status',
        'priority',
        'category_id',
        'created_by',
        'requester_user_id',
        'assigned_agent_id',
        'resolved_at',
        'first_response_at',
        'closed_at',
        'last_reply_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'first_response_at' => 'datetime',
            'closed_at' => 'datetime',
            'last_reply_at' => 'datetime',
        ];
    }
    
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_id)) {
                do {
                    $ticket->ticket_id = mt_rand(10000000, 99999999);
                } while (self::where('ticket_id', $ticket->ticket_id)->exists());
            }
        });

        static::deleting(function ($ticket) {
            if ($ticket->isForceDeleting()) {
                HelpdeskAttachment::where('ticket_id', $ticket->id)->get()->each->deleteStoredFile();
            }
        });
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('view-helpdesk-tickets') && $this->isVisibleTo($user);
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->type === 'superadmin' || ($user->isPlatformSupport() && $user->can('view-all-support-tickets'))) {
            return $query;
        }

        if ($user->type === 'client') {
            return $query->where('requester_user_id', $user->id);
        }

        if ($user->type === 'company') {
            return $query->where('created_by', $user->id);
        }

        return $query->where(function (Builder $visible) use ($user) {
            $visible->where('assigned_agent_id', $user->id);

            if ($user->can('manage-own-helpdesk-tickets') && $user->created_by) {
                $visible->orWhere('created_by', $user->created_by);
            }
        });
    }

    /** Email templates render as raw HTML, so anything a user typed must be plain, escaped text. */
    public static function emailText(?string $value): string
    {
        return e(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpdeskCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(HelpdeskReply::class, 'ticket_id');
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(HelpdeskAssignmentHistory::class, 'ticket_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(HelpdeskStatusHistory::class, 'ticket_id');
    }
}
