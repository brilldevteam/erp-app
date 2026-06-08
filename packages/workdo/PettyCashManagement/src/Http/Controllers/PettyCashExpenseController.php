<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Workdo\PettyCashManagement\Models\PettyCashExpense;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Workdo\PettyCashManagement\Models\PettyCash;
use App\Models\User;
use Workdo\PettyCashManagement\Models\PettyCashReimbursement;

class PettyCashExpenseController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-petty-cash-expenses')){
            $pettycashexpenses = PettyCashExpense::query()
                ->with(['request.user', 'request.category', 'reimbursement.user', 'reimbursement.category', 'approver', 'pettyCash'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-petty-cash-expenses')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-petty-cash-expenses')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('user_name') && request('user_name') !== '', function($q) {
                    $q->where(function($query) {
                        $query->whereHas('request.user', fn($userQuery) => $userQuery->where('name', 'like', '%' . request('user_name') . '%'))
                              ->orWhereHas('reimbursement.user', fn($userQuery) => $userQuery->where('name', 'like', '%' . request('user_name') . '%'));
                    });
                })
                ->when(request('type') !== null && request('type') !== '', fn($q) => $q->where('type', request('type')))
                ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('status', request('status')))
                ->when(request('request_number') && request('request_number') !== '', fn($q) => $q->whereHas('request', fn($query) => $query->where('request_number', request('request_number'))))
                ->when(request('reimbursement_number') && request('reimbursement_number') !== '', fn($q) => $q->whereHas('reimbursement', fn($query) => $query->where('reimbursement_number', request('reimbursement_number'))))
                ->when(request('pettycash_number') && request('pettycash_number') !== '', fn($q) => $q->whereHas('pettyCash', fn($query) => $query->where('pettycash_number', request('pettycash_number'))))
                ->when(request('pettycash_id') && request('pettycash_id') !== '', fn($q) => $q->where('pettycash_id', request('pettycash_id')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

                $pettyCashRequest = PettyCashRequest::where('created_by', creatorId())->select('id', 'request_number')->whereNotNull('request_number')->get();
                $reimbursement    = PettyCashReimbursement::where('created_by', creatorId())->select('id', 'reimbursement_number')->whereNotNull('reimbursement_number')->get();
                $pettyCash        = PettyCash::where('created_by', creatorId())->select('id', 'pettycash_number')->whereNotNull('pettycash_number')->get();
                $users            = User::where('created_by', creatorId())->select('id', 'name')->emp()->get();

            return Inertia::render('PettyCashManagement/PettyCashExpenses/Index', [
                'pettycashexpenses' => $pettycashexpenses,
                'pettycashrequests' => $pettyCashRequest,
                'reimbursements'    => $reimbursement,
                'pettycashes'       => $pettyCash,
                'users'             => $users,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
