<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Workdo\PettyCashManagement\Models\PettyCashReimbursement;
use Workdo\PettyCashManagement\Http\Requests\StoreReimbursementRequest;
use Workdo\PettyCashManagement\Http\Requests\UpdateReimbursementRequest;
use Workdo\PettyCashManagement\Events\CreateReimbursement;
use Workdo\PettyCashManagement\Events\UpdateReimbursement;
use Workdo\PettyCashManagement\Events\UpdateStatusReimbursement;
use Workdo\PettyCashManagement\Events\DestroyReimbursement;
use Workdo\PettyCashManagement\Events\CreatePettyCashExpense;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\User;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Models\PettyCashExpense;

class ReimbursementController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-reimbursements')){
            $reimbursements = PettyCashReimbursement::query()
                ->with(['user', 'category', 'approver'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-reimbursements')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-reimbursements')) {
                        $q->where(function($subQ) {
                            $subQ->where('creator_id', Auth::id())->orwhere('user_id', Auth::id());
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('reimbursement_number'), function($q) {
                    $q->where('reimbursement_number', 'like', '%' . request('reimbursement_number') . '%');
                })
                ->when(request('user_id') && request('user_id') !== '', fn($q) => $q->where('user_id', request('user_id')))
                ->when(request('category_id') && request('category_id') !== '', fn($q) => $q->where('category_id', request('category_id')))
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('approved_by') && request('approved_by') !== '', fn($q) => $q->where('approved_by', request('approved_by')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            // Filter users based on permissions
            if(Auth::user()->can('manage-any-reimbursements')) {
                $users = User::where('created_by', creatorId())->emp()->select('id', 'name')->get();
            } elseif(Auth::user()->can('manage-own-reimbursements')) {
                $users = User::where('id', Auth::id())->select('id', 'name')->get();
            } else {
                $users = collect();
            }

            $categories = PettyCashCategory::where('created_by', creatorId())->select('id', 'name')->get();

            return Inertia::render('PettyCashManagement/Reimbursements/Index', [
                'reimbursements' => $reimbursements,
                'users'          => $users,
                'categories'     => $categories
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreReimbursementRequest $request)
    {
        if(Auth::user()->can('create-reimbursements')){
            $validated = $request->validated();

            $reimbursement               = new PettyCashReimbursement();
            $reimbursement->user_id      = $validated['user_id'];
            $reimbursement->category_id  = $validated['category_id'];
            $reimbursement->amount       = $validated['amount'];
            $reimbursement->status       = 0;
            $reimbursement->description  = $validated['description'];
            if ($request->hasFile('receipt_path')) {
                $filenameWithExt = $request->file('receipt_path')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('receipt_path')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $uplaod = upload_file($request,'receipt_path',$fileNameToStore,'petty_cach_reimbursement');
                if($uplaod['flag'] == 1)
                {
                    $reimbursement->receipt_path = $uplaod['url'];
                }
                else
                {
                    return redirect()->back()->with('error', $uplaod['msg']);
                }
            }
            $reimbursement->creator_id   = Auth::id();
            $reimbursement->created_by   = creatorId();
            $reimbursement->save();

            CreateReimbursement::dispatch($request, $reimbursement);

            return redirect()->route('petty-cash-management.reimbursements.index')->with('success', __('The reimbursement has been created successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.reimbursements.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateReimbursementRequest $request, PettyCashReimbursement $reimbursement)
    {
        if(Auth::user()->can('edit-reimbursements')){
            $validated = $request->validated();

            $reimbursement->user_id      = $validated['user_id'];
            $reimbursement->category_id  = $validated['category_id'];
            $reimbursement->amount       = $validated['amount'];
            $reimbursement->status       = 0;
            $reimbursement->description  = $validated['description'];
            if ($request->hasFile('receipt_path')) {
                $filenameWithExt = $request->file('receipt_path')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('receipt_path')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $uplaod = upload_file($request,'receipt_path',$fileNameToStore,'petty_cach_reimbursement');
                if($uplaod['flag'] == 1)
                {
                    $reimbursement->receipt_path = $uplaod['url'];
                }
                else
                {
                    return redirect()->back()->with('error', $uplaod['msg']);
                }
            }
            $reimbursement->save();

            UpdateReimbursement::dispatch($request, $reimbursement);

            return redirect()->back()->with('success', __('The reimbursement details are updated successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.reimbursements.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(PettyCashReimbursement $reimbursement)
    {
        if(Auth::user()->can('delete-reimbursements')){
            DestroyReimbursement::dispatch($reimbursement);

            $reimbursement->delete();

            return redirect()->back()->with('success', __('The reimbursement has been deleted.'));
        }
        else{
            return redirect()->route('petty-cash-management.reimbursements.index')->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(PettyCashReimbursement $reimbursement)
    {
        if(Auth::user()->can('approve-reimbursements')){
            $validated = request()->validate([
                'status'           => 'required|in:1,2',
                'approved_date'    => 'nullable|date',
                'approved_by'      => 'nullable|integer',
                'approved_amount'  => 'nullable|numeric|min:0',
                'rejection_reason' => 'nullable|string|max:1000'
            ]);

            $reimbursement->status = $validated['status'];

            if($validated['status'] == '1') {
                $pattyCash = PettyCash::latest()->where('created_by', creatorId())->first();
                if($pattyCash) {
                    if($pattyCash->status != 1) {
                        return redirect()->back()->with('error', __('Please approve petty cash first before processing reimbursement.'));
                    }
                    $closing_balance = $pattyCash->closing_balance - $validated['approved_amount'];
                    if($closing_balance < 0) {
                        return redirect()->back()->with('error', __('Insufficient petty cash balance! Available balance: :balance, Requested amount: :amount', [
                            'balance' => number_format($pattyCash->closing_balance, 2),
                            'amount'  => number_format($validated['approved_amount'], 2)
                        ]));
                    }

                    $reimbursement->approved_date    = $validated['approved_date'];
                    $reimbursement->approved_by      = $validated['approved_by'];
                    $reimbursement->approved_amount  = $validated['approved_amount'];
                    $reimbursement->rejection_reason = null;

                    $pattyCash->closing_balance = $closing_balance;
                    $pattyCash->total_expense  += $validated['approved_amount'];
                    $pattyCash->save();

                    $expense                   = new PettyCashExpense();
                    $expense->reimbursement_id = $reimbursement->id;
                    $expense->pettycash_id     = $pattyCash->id;
                    $expense->type             = 'reimbursement';
                    $expense->amount           = $validated['approved_amount'];
                    $expense->remarks          = $reimbursement->description;
                    $expense->status           = 1;
                    $expense->approved_at      = now();
                    $expense->approved_by      = Auth::id();
                    $expense->creator_id       = Auth::id();
                    $expense->created_by       = creatorId();
                    $expense->save();

                    CreatePettyCashExpense::dispatch($expense);

                } else {
                    return redirect()->back()->with('error', __('Petty cash not found!'));
                }
            } else {
                $reimbursement->approved_date    = null;
                $reimbursement->approved_by      = null;
                $reimbursement->approved_amount  = null;
                $reimbursement->rejection_reason = $validated['rejection_reason'];
            }

            $reimbursement->save();

            UpdateStatusReimbursement::dispatch($reimbursement);

            $message = $validated['status'] == '1' ? __('The reimbursement has been approved.') : __('The reimbursement has been rejected.');
            return redirect()->back()->with('success', $message);
        }
        else{
            return redirect()->route('petty-cash-management.reimbursements.index')->with('error', __('Permission denied'));
        }
    }

    public function getCategoriesByUser($userId)
    {
        if(Auth::user()->can('view-categories')){
            $categories = PettyCashCategory::where('user_id', $userId)
                ->where('created_by', creatorId())
                ->select('id', 'name')
                ->get();

            return response()->json($categories);
        }
        else{
            return response()->json([], 403);
        }
    }
}
