<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class JournalEntry extends Model
{
    protected $fillable = [
        'journal_number',
        'journal_date',
        'entry_type',
        'reference_type',
        'reference_id',
        'description',
        'total_debit',
        'total_credit',
        'currency_code',
        'base_currency_code',
        'exchange_rate',
        'foreign_amount',
        'base_amount',
        'status',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'journal_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'foreign_amount' => 'decimal:2',
        'base_amount' => 'decimal:2'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JournalEntryAttachment::class);
    }

    public function isBalanced(): bool
    {
        return $this->total_debit == $this->total_credit;
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($journalEntry) {
            if (empty($journalEntry->journal_number)) {
                $journalEntry->journal_number = static::generateJournalNumber();
            }
        });
    }

    public static function generateJournalNumber(): string
    {
        $year = date('Y');
        $prefix = "JE-{$year}-";

        // Numbers are unique across all companies. Take the highest by its numeric part: sorting the text
        // ("JE-2026-999" > "JE-2026-1000") and reading only the last 3 digits made every number after 999
        // repeat JE-2026-1000, so every new journal failed.
        $lastNumber = (int) static::where('journal_number', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(journal_number, ?) AS UNSIGNED)) as last_number', [strlen($prefix) + 1])
            ->value('last_number');
        $nextNumber = $lastNumber + 1;

        return "JE-{$year}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
