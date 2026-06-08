<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Workdo\PettyCashManagement\Http\Requests\StorePettyCashRequestRequest;
use Workdo\PettyCashManagement\Http\Requests\UpdatePettyCashRequestRequest;
use Workdo\PettyCashManagement\Events\CreatePettyCashRequest;
use Workdo\PettyCashManagement\Events\UpdatePettyCashRequest;
use Workdo\PettyCashManagement\Events\UpdateStatusPettyCashRequest;
use Workdo\PettyCashManagement\Events\DestroyPettyCashRequest;
use Workdo\PettyCashManagement\Events\CreatePettyCashExpense;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use App\Models\User;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashExpense;

class PettyCashRequestController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-petty-cash-requests')){
            $pettycashrequests = PettyCashRequest::query()
                ->with(['user', 'category', 'approver'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-petty-cash-requests')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-petty-cash-requests')) {
                        $q->where(function($subQ) {
                            $subQ->where('creator_id', Auth::id())->orwhere('user_id', Auth::id());
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('request_number'), function($q) {
                    $q->where('request_number', 'like', '%' . request('request_number') . '%');
                })
                ->when(request('user_id') && request('user_id') !== '', fn($q) => $q->where('user_id', request('user_id')))
                ->when(request('categorie_id') && request('categorie_id') !== '', fn($q) => $q->where('categorie_id', request('categorie_id')))
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            // Filter users based on permissions
            if(Auth::user()->can('manage-any-petty-cash-requests')) {
                $users = User::where('created_by', creatorId())->emp()->select('id', 'name')->get();
            } elseif(Auth::user()->can('manage-own-petty-cash-requests')) {
                $users = User::where('id', Auth::id())->select('id', 'name')->get();
            } else {
                $users = collect();
            }

            $categories = PettyCashCategory::where('created_by', creatorId())->select('id', 'name')->get();

            return Inertia::render('PettyCashManagement/PettyCashRequests/Index', [
                'pettycashrequests'   => $pettycashrequests,
                'users'               => $users,
                'pettycashcategories' => $categories,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StorePettyCashRequestRequest $request)
    {
        if(Auth::user()->can('create-petty-cash-requests')){
            $validated = $request->validated();

            $pettycashrequest                   = new PettyCashRequest();
            $pettycashrequest->user_id          = $validated['user_id'];
            $pettycashrequest->categorie_id     = $validated['categorie_id'];
            $pettycashrequest->requested_amount = $validated['requested_amount'];
            $pettycashrequest->status           = 0;
            $pettycashrequest->remarks          = $validated['remarks'];
            $pettycashrequest->creator_id       = Auth::id();
            $pettycashrequest->created_by       = creatorId();
            $pettycashrequest->save();

            CreatePettyCashRequest::dispatch($request, $pettycashrequest);

            return redirect()->route('petty-cash-management.petty-cash-requests.index')->with('success', __('The petty cash request has been created successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-requests.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdatePettyCashRequestRequest $request, PettyCashRequest $pettycashrequest)
    {
        if(Auth::user()->can('edit-petty-cash-requests')){
            $validated = $request->validated();

            $pettycashrequest->user_id          = $validated['user_id'];
            $pettycashrequest->categorie_id     = $validated['categorie_id'];
            $pettycashrequest->requested_amount = $validated['requested_amount'];
            $pettycashrequest->status           = '0';
            $pettycashrequest->remarks          = $validated['remarks'];
            $pettycashrequest->save();

            UpdatePettyCashRequest::dispatch($request, $pettycashrequest);

            return redirect()->back()->with('success', __('The petty cash request details are updated successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-requests.index')->with('error', __('Permission denied'));
        }
    }

    public function updateStatus(PettyCashRequest $pettycashrequest)
    {
        if(Auth::user()->can('approve-petty-cash-requests')){
            $validated = request()->validate([
                'status'           => 'required|in:1,2',
                'approved_at'      => 'nullable|date',
                'approved_by'      => 'nullable|integer',
                'approved_amount'  => 'nullable|numeric|min:0',
                'rejection_reason' => 'nullable|string|max:1000'
            ]);

            $pettycashrequest->status = $validated['status'];

            if($validated['status'] == '1') {
                // Approval
                $pattyCash = PettyCash::latest()->where('created_by', creatorId())->first();
                if ($pattyCash->status != 1) {
                    return redirect()->back()->with('error', __('Please approve petty cash first before processing request.'));
                }
                if($pattyCash) {
                    $closing_balance = $pattyCash->closing_balance - $validated['approved_amount'];
                    if($closing_balance < 0) {
                        return redirect()->back()->with('error', __('Insufficient petty cash balance! Available balance: :balance, Requested amount: :amount', [
                            'balance' => number_format($pattyCash->closing_balance, 2),
                            'amount' => number_format($validated['approved_amount'], 2)
                        ]));
                    }

                    $pettycashrequest->approved_at      = $validated['approved_at'];
                    $pettycashrequest->approved_by      = $validated['approved_by'];
                    $pettycashrequest->approved_amount  = $validated['approved_amount'];
                    $pettycashrequest->rejection_reason = null;

                    $pattyCash->closing_balance = $closing_balance;
                    $pattyCash->total_expense  += $validated['approved_amount'];
                    $pattyCash->save();

                    $expense               = new PettyCashExpense();
                    $expense->request_id   = $pettycashrequest->id;
                    $expense->pettycash_id = $pattyCash->id;
                    $expense->type         = 'pettycash';
                    $expense->amount       = $validated['approved_amount'];
                    $expense->remarks      = $pettycashrequest->remarks;
                    $expense->status       = 1;
                    $expense->approved_at  = now();
                    $expense->approved_by  = Auth::id();
                    $expense->creator_id   = Auth::id();
                    $expense->created_by   = creatorId();
                    $expense->save();

                    CreatePettyCashExpense::dispatch($expense);
                } else {
                    return redirect()->back()->with('error', __('No petty cash record found!'));
                }
            } else {
                // Rejection
                $pettycashrequest->approved_at      = null;
                $pettycashrequest->approved_by      = null;
                $pettycashrequest->approved_amount  = null;
                $pettycashrequest->rejection_reason = $validated['rejection_reason'];
            }

            $pettycashrequest->save();

            UpdateStatusPettyCashRequest::dispatch($pettycashrequest);

            $message = $validated['status'] == '1' ? __('The petty cash request has been approved.') : __('The petty cash request has been rejected.');
            return redirect()->back()->with('success', $message);
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-requests.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(PettyCashRequest $pettycashrequest)
    {
        if(Auth::user()->can('delete-petty-cash-requests')){
            DestroyPettyCashRequest::dispatch($pettycashrequest);

            $pettycashrequest->delete();

            return redirect()->back()->with('success', __('The petty cash request has been deleted.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-requests.index')->with('error', __('Permission denied'));
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
