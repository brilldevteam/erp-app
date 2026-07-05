<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Http\Requests\StoreJournalEntryRequest;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

class JournalEntryController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-journal-entries')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = JournalEntry::query()
            ->with(['items.account'])
            ->where('created_by', creatorId())
            ->where('entry_type', 'manual')
            ->when(request('search'), function ($q) {
                $search = request('search');
                $q->where(function ($query) use ($search) {
                    $query->where('journal_number', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(request('status') && request('status') !== 'all', fn ($q) => $q->where('status', request('status')))
            ->when(request('sort'), function ($q) {
                $allowed = ['journal_number', 'journal_date', 'total_debit', 'total_credit', 'status', 'created_at'];
                $sort = in_array(request('sort'), $allowed, true) ? request('sort') : 'created_at';
                $q->orderBy($sort, request('direction', 'desc'));
            }, fn ($q) => $q->latest());

        return Inertia::render('Account/JournalEntries/Index', [
            'journalEntries' => $query->paginate(request('per_page', 10))->withQueryString(),
            'filters' => request()->only(['search', 'status', 'sort', 'direction', 'per_page']),
        ]);
    }

    public function create()
    {
        if (!Auth::user()->can('create-journal-entries')) {
            return back()->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/JournalEntries/Create', [
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function store(StoreJournalEntryRequest $request)
    {
        if (!Auth::user()->can('create-journal-entries')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $items = collect($validated['items'])
            ->map(function ($item) use ($validated) {
                return [
                    'account_id' => $item['account_id'],
                    'description' => $item['description'] ?: $validated['description'],
                    'debit_amount' => round((float) ($item['debit_amount'] ?? 0), 2),
                    'credit_amount' => round((float) ($item['credit_amount'] ?? 0), 2),
                    'creator_id' => Auth::id(),
                    'created_by' => creatorId(),
                ];
            });

        $totalDebit = round($items->sum('debit_amount'), 2);
        $totalCredit = round($items->sum('credit_amount'), 2);

        DB::transaction(function () use ($validated, $items, $totalDebit, $totalCredit) {
            $journalEntry = JournalEntry::create([
                'journal_date' => $validated['journal_date'],
                'entry_type' => 'manual',
                'reference_type' => $validated['reference_type'] ?: 'Manual Journal',
                'reference_id' => null,
                'description' => $validated['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'posted',
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            $journalEntry->items()->createMany($items->toArray());
        });

        return redirect()->route('account.journal-entries.index')->with('success', __('Journal entry recorded successfully.'));
    }

    public function show(JournalEntry $journalEntry)
    {
        if (!Auth::user()->can('view-journal-entries')) {
            return back()->with('error', __('Permission denied'));
        }

        if ($journalEntry->created_by !== creatorId() || $journalEntry->entry_type !== 'manual') {
            return redirect()->route('account.journal-entries.index')->with('error', __('Permission denied'));
        }

        return Inertia::render('Account/JournalEntries/View', [
            'journalEntry' => $journalEntry->load(['items.account']),
        ]);
    }

    public function destroy(JournalEntry $journalEntry)
    {
        if (!Auth::user()->can('delete-journal-entries')) {
            return back()->with('error', __('Permission denied'));
        }

        if ($journalEntry->created_by !== creatorId() || $journalEntry->entry_type !== 'manual') {
            return redirect()->route('account.journal-entries.index')->with('error', __('Permission denied'));
        }

        $journalEntry->delete();

        return redirect()->route('account.journal-entries.index')->with('success', __('Journal entry deleted successfully.'));
    }

    private function accountOptions()
    {
        return ChartOfAccount::query()
            ->where('created_by', creatorId())
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name', 'normal_balance']);
    }
}
