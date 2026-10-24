<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class JournalEntryItem extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'contact_id',
        'description',
        'debit_amount',
        'credit_amount',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2'
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /** The vendor or customer this line relates to, if any. */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    /**
     * Vendors and customers of a company that a journal line can name, with the display name used on invoices and bills.
     */
    public static function contactOptions($creatorId, ?array $ids = null)
    {
        return User::query()
            ->leftJoin('vendors', 'vendors.user_id', '=', 'users.id')
            ->leftJoin('customers', 'customers.user_id', '=', 'users.id')
            ->where('users.created_by', $creatorId)
            ->whereIn('users.type', ['vendor', 'client'])
            ->when($ids !== null, fn ($query) => $query->whereIn('users.id', $ids))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.type', 'vendors.company_name as vendor_company', 'customers.company_name as customer_company'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'company_name' => ($user->type === 'vendor' ? $user->vendor_company : $user->customer_company) ?: null,
                'type' => $user->type === 'vendor' ? 'vendor' : 'customer',
            ])
            ->unique('id')
            ->values();
    }

    /**
     * Sets each line's contact display name (company name when there is one) and type on the given lines.
     */
    public static function attachContactNames($items, $creatorId): void
    {
        $ids = collect($items)->pluck('contact_id')->filter()->unique()->values()->all();
        $contacts = $ids ? static::contactOptions($creatorId, $ids)->keyBy('id') : collect();

        foreach ($items as $item) {
            $contact = $item->contact_id ? $contacts->get($item->contact_id) : null;
            $item->setAttribute('contact_name', $contact ? ($contact['company_name'] ?: $contact['name']) : null);
            $item->setAttribute('contact_type', $contact['type'] ?? null);
        }
    }

    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    public function getAmount(): float
    {
        return $this->isDebit() ? $this->debit_amount : $this->credit_amount;
    }

    public function getType(): string
    {
        return $this->isDebit() ? 'debit' : 'credit';
    }
}