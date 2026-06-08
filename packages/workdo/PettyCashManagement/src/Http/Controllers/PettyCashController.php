<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Http\Requests\StorePettyCashRequest;
use Workdo\PettyCashManagement\Http\Requests\UpdatePettyCashRequest;
use Workdo\PettyCashManagement\Events\CreatePettyCash;
use Workdo\PettyCashManagement\Events\UpdatePettyCash;
use Workdo\PettyCashManagement\Events\DestroyPettyCash;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\PettyCashManagement\Events\ApprovePettyCash;

class PettyCashController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-petty-cashes')){
            $pettycashes = PettyCash::query()
                ->with(['expenses.request.user', 'expenses.request.category', 'expenses.reimbursement.user', 'expenses.reimbursement.category', 'expenses.approver'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-petty-cashes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-petty-cashes')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('pettycash_number'), function($q) {
                    $q->where('pettycash_number', 'like', '%' . request('pettycash_number') . '%');
                })
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))

                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $latestEntryId = PettyCash::where('created_by', creatorId())->latest()->value('id');

            return Inertia::render('PettyCashManagement/PettyCashes/Index', [
                'pettycashes' => $pettycashes,
                'latestEntryId' => $latestEntryId,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StorePettyCashRequest $request)
    {
        if(Auth::user()->can('create-petty-cashes')){
            $validated    = $request->validated();
            $existingFund = PettyCash::where('date', $validated['date'])
                ->where('created_by', creatorId())
                ->first();

            if ($existingFund) {
                return back()->withErrors(['date' => __('Petty cash fund already exists for this date.')]);
            }

            $unapprovedEntries = PettyCash::where('created_by', creatorId())
                ->where('status', 0)
                ->exists();

            if ($unapprovedEntries) {
                return redirect()->route('petty-cash-management.petty-cashes.index')->with('error', __('Please approve all previous petty cash entries before creating a new one.'));
            }

            $previousEntry  = PettyCash::latest()->where('created_by', creatorId())->first();
            $addedAmount    = $validated['added_amount'] ?? 0;

            $openingBalance = $previousEntry ? $previousEntry->closing_balance : 0;
            $closingBalance = $openingBalance + $addedAmount;
            $totalBalance   = $closingBalance;

            $pettycash                  = new PettyCash();
            $pettycash->date            = $validated['date'];
            $pettycash->opening_balance = $openingBalance;
            $pettycash->added_amount    = $addedAmount;
            $pettycash->total_balance   = $totalBalance;
            $pettycash->total_expense   = 0;
            $pettycash->closing_balance = $closingBalance;
            $pettycash->remarks         = $validated['remarks'];
            $pettycash->status          = 0;
            $pettycash->creator_id      = Auth::id();
            $pettycash->created_by      = creatorId();
            $pettycash->save();

            CreatePettyCash::dispatch($request, $pettycash);

            return redirect()->route('petty-cash-management.petty-cashes.index')->with('success', __('Petty cash fund created successfully.'));
        }
        return redirect()->route('petty-cash-management.petty-cashes.index')->with('error', __('Permission denied'));
    }

    public function update(UpdatePettyCashRequest $request, PettyCash $pettycash)
    {
        if(Auth::user()->can('edit-petty-cashes')){
            $validated = $request->validated();

            if ($validated['date'] !== $pettycash->date) {
                $existingFund = PettyCash::where('date', $validated['date'])
                    ->where('created_by', creatorId())
                    ->where('id', '!=', $pettycash->id)
                    ->first();

                if ($existingFund) {
                    return back()->withErrors(['date' => __('Petty cash fund already exists for this date.')]);
                }
            }

            $addedAmount    = $validated['added_amount'] ?? 0;
            $openingBalance = $pettycash->opening_balance;
            $totalBalance   = $openingBalance + $addedAmount;
            $closingBalance = $totalBalance - $pettycash->total_expense;

            $pettycash->date            = $validated['date'];
            $pettycash->opening_balance = $openingBalance;
            $pettycash->added_amount    = $addedAmount;
            $pettycash->total_balance   = $totalBalance;
            $pettycash->closing_balance = $closingBalance;
            $pettycash->remarks         = $validated['remarks'];
            $pettycash->save();

            UpdatePettyCash::dispatch($request, $pettycash);

            return redirect()->back()->with('success', __('Petty cash fund updated successfully.'));
        }
        return redirect()->back()->with('error', __('Permission denied'));
    }

    public function destroy(PettyCash $pettycash)
    {
        if(Auth::user()->can('delete-petty-cashes')){
            DestroyPettyCash::dispatch($pettycash);

            $pettycash->delete();

            return redirect()->back()->with('success', __('The petty cash has been deleted.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cashes.index')->with('error', __('Permission denied'));
        }
    }

    public function approve(PettyCash $pettycash)
    {
        if(Auth::user()->can('approve-petty-cashes')){
            try {
                ApprovePettyCash::dispatch($pettycash);
            } catch (\Throwable $th) {
                return back()->with('error', $th->getMessage());
            }
            $pettycash->status = 1;
            $pettycash->save();

            return redirect()->back()->with('success', __('Petty cash approved successfully.'));
        }
        return redirect()->back()->with('error', __('Permission denied'));
    }
}
