<?php

namespace Tests\Smoke;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\Account\Models\JournalEntry;

/** Journal numbers must keep counting past 999 (they are unique across all companies). */
class JournalNumberTest extends TestCase
{
    use DatabaseTransactions;

    private function journal(string $number): JournalEntry
    {
        return JournalEntry::create([
            'journal_number' => $number, 'journal_date' => now()->toDateString(), 'entry_type' => 'manual', 'reference_type' => 'manual', 'reference_id' => 0,
            'description' => 'Number test', 'total_debit' => 0, 'total_credit' => 0, 'status' => 'draft',
            'creator_id' => 950, 'created_by' => 950,
        ]);
    }

    public function test_next_number_after_999_and_1000_is_unique(): void
    {
        $year = date('Y');
        $prefix = "JE-{$year}-";
        $highest = (int) JournalEntry::where('journal_number', 'like', $prefix . '%')->get()
            ->map(fn ($j) => (int) substr($j->journal_number, strlen($prefix)))->max();
        $base = max($highest, 1000) + 1;

        // Text order puts "...-999" after "...-1000"; the old code then kept generating the same number.
        if (!JournalEntry::where('journal_number', $prefix . '999')->exists()) {
            $this->journal($prefix . '999');
        }
        $this->journal($prefix . $base);

        $next = JournalEntry::generateJournalNumber();
        $this->assertSame($prefix . ($base + 1), $next);
        $this->assertFalse(JournalEntry::where('journal_number', $next)->exists());

        // Creating one without a number uses it, and the one after keeps counting.
        $created = $this->journal('');
        $this->assertSame($prefix . ($base + 1), $created->journal_number);
        $this->assertSame($prefix . ($base + 2), JournalEntry::generateJournalNumber());
    }
}
