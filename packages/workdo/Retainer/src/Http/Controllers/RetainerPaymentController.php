<?php

namespace Workdo\Retainer\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Retainer\Models\SalesRetainerPayment;
use Workdo\Retainer\Models\SalesRetainer;
use Workdo\Retainer\Models\RetainerPaymentAllocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\BankAccount;
use Workdo\Retainer\Events\CreateRetainerPayment;
use Workdo\Retainer\Events\UpdateRetainerPaymentStatus;
use Workdo\Retainer\Events\DestroyRetainerPayment;
use Workdo\Retainer\Http\Requests\StoreSalesRetainerPaymentRequest;
use Workdo\Account\Services\BankTransactionsService;

class RetainerPaymentController extends Controller
{
    protected $bankTransactionsService;

    public function __construct(BankTransactionsService $bankTransactionsService)
    {
        $this->bankTransactionsService = $bankTransactionsService;
    }
    public function index(Request $request)
    {
        if (Auth::user()->can('manage-retainer-payments')) {
            $query = SalesRetainerPayment::with(['retainer', 'customer', 'bankAccount', 'allocations.retainer'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-retainer-payments')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-retainer-payments')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

              // Apply filters
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->search) {
                $query->where('payment_number', 'like', '%' . $request->search . '%');
            }
            if ($request->date_from) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }
            if ($request->date_to) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }

            $sortField     = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $payments = $query->paginate($request->get('per_page', 10));

            $customers = User::where('type', 'client')
                ->where('created_by', creatorId())
                ->get();

            $bankAccounts = BankAccount::where('is_active', true)
                ->where('created_by', creatorId())
                ->get();

            return Inertia::render('Retainer/RetainerPayments/Index', [
                'payments'     => $payments,
                'customers'    => $customers,
                'bankAccounts' => $bankAccounts,
                'filters'      => $request->only(['customer_id', 'status', 'search', 'date_from', 'date_to', 'bank_account_id'])
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreSalesRetainerPaymentRequest $request)
    {
        if (Auth::user()->can('create-retainer-payments')) {
            if (!$request->allocations || count($request->allocations) === 0) {
                return back()->with('error', __('At least one retainer allocation is required to create a payment.'));
            }

            // Create payment
            $retainerPayment                   = new SalesRetainerPayment();
            $retainerPayment->payment_date     = $request->payment_date;
            $retainerPayment->customer_id      = $request->customer_id;
            $retainerPayment->bank_account_id  = $request->bank_account_id;
            $retainerPayment->reference_number = $request->reference_number;
            $retainerPayment->payment_amount   = $request->payment_amount;
            $retainerPayment->notes            = $request->notes;
            $retainerPayment->creator_id       = Auth::id();
            $retainerPayment->created_by       = creatorId();
            $retainerPayment->save();

            // Create allocations
            if ($request->allocations) {
                foreach ($request->allocations as $allocation) {
                    $paymentAllocation                   = new RetainerPaymentAllocation();
                    $paymentAllocation->payment_id       = $retainerPayment->id;
                    $paymentAllocation->retainer_id      = $allocation['retainer_id'];
                    $paymentAllocation->allocated_amount = $allocation['amount'];
                    $paymentAllocation->creator_id       = $retainerPayment->creator_id;
                    $paymentAllocation->created_by       = $retainerPayment->created_by;
                    $paymentAllocation->save();
                }
            }

            try {
                CreateRetainerPayment::dispatch($request, $retainerPayment);
            } catch (\Throwable $th) {
                return back()->with('error', $th->getMessage());
            }
            return redirect()->route('retainer-payments.index')->with('success', __('The retainer payment has been created successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }
    public function updateStatus(Request $request, SalesRetainerPayment $retainerPayment)
    {
        if (Auth::user()->can('cleared-retainer-payments') && $retainerPayment->created_by == creatorId()) {
            try {
                if ($request->status === 'cleared') {

                    try {
                        UpdateRetainerPaymentStatus::dispatch($request, $retainerPayment);
                    } catch (\Throwable $th) {
                        return back()->with('error', $th->getMessage());
                    }

                    // Update retainer balances for each allocation
                    foreach ($retainerPayment->allocations as $allocation) {
                        $retainer                  = $allocation->retainer;
                        $retainer->paid_amount    += $allocation->allocated_amount;
                        $retainer->balance_amount -= $allocation->allocated_amount;

                        if ($retainer->balance_amount <= 0) {
                            $retainer->status = 'paid';
                        } elseif ($retainer->paid_amount > 0) {
                            $retainer->status = 'partial';
                        }
                        $retainer->save();
                    }
                }

                $retainerPayment->update(['status' => $request->status]);

                return back()->with('success', __('The payment status has been updated successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(SalesRetainerPayment $retainerPayment)
    {
        if (Auth::user()->can('delete-retainer-payments') && $retainerPayment->created_by == creatorId() && $retainerPayment->status === 'pending') {

            DestroyRetainerPayment::dispatch($retainerPayment);

            $retainerPayment->delete();
            return back()->with('success', __('The retainer payment has been deleted.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function getOutstandingRetainers($customerId)
    {
        $retainers = SalesRetainer::where('customer_id', $customerId)
            ->where('created_by', creatorId())
            ->whereIn('status', ['accepted', 'partial'])
            ->where('balance_amount', '>', 0)
            ->select('id', 'retainer_number', 'total_amount', 'paid_amount', 'balance_amount', 'status')
            ->get();

        return response()->json(['retainers' => $retainers]);
    }
}
