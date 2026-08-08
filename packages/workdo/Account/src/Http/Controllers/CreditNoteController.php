<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoiceReturn;
use App\Models\User;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteItem;
use Workdo\Account\Models\CreditNoteItemTax;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Events\ApproveCreditNote;
use Workdo\Account\Events\DestroyCreditNote;
use Workdo\Account\Http\Requests\StoreCreditNoteRequest;
use Workdo\Account\Services\JournalService;
use Workdo\ProductService\Models\ProductServiceItem;
use App\Models\EmailTemplate;

class CreditNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkCreditNoteAccess(CreditNote $creditNote)
    {
        if(Auth::user()->can('manage-any-credit-notes')) {
            return true;
        } elseif(Auth::user()->can('manage-own-credit-notes')) {
            if($creditNote->creator_id != Auth::id() && $creditNote->customer_id != Auth::id()) {
                return false;
            }
            if($creditNote->creator_id != Auth::id() && Auth::user()->type == 'client' && $creditNote->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-credit-notes')){
            $query = CreditNote::with(['customer', 'salesReturn', 'approvedBy'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-credit-notes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-credit-notes')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        if(Auth::user()->type == 'client') {
                            $q->where('status','!=', 'draft');
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where('credit_note_number', 'like', '%' . $request->search . '%');
            }

            if ($request->sales_return_id) {
                $query->where('return_id', $request->sales_return_id);
            }

            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $creditNotes = $query->paginate($request->per_page ?? 10)->withQueryString();

            $customers = User::where('type', 'client')->where('created_by', creatorId())->get(['id', 'name']);
            $salesReturns = SalesInvoiceReturn::where('created_by', creatorId())->get(['id', 'return_number']);

            return Inertia::render('Account/CreditNotes/Index', [
                'creditNotes' => $creditNotes,
                'customers' => $customers,
                'salesReturns' => $salesReturns,
                'filters' => $request->only(['customer_id', 'status', 'sales_return_id'])
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if(Auth::user()->can('create-credit-notes')){
            $customers = User::where('type', 'client')->where('created_by', creatorId())->get(['id', 'name']);
            $products = ProductServiceItem::where('created_by', creatorId())
                ->get(['id', 'name', 'sku', 'sale_price', 'unit', 'type', 'tax_ids'])
                ->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'sale_price' => $product->sale_price,
                    'unit' => $product->unit,
                    'type' => $product->type,
                    'taxes' => $product->taxes->map(fn($tax) => [
                        'id' => $tax->id,
                        'tax_name' => $tax->tax_name,
                        'rate' => $tax->rate,
                    ]),
                ]);

            return Inertia::render('Account/CreditNotes/Create', [
                'customers' => $customers,
                'products' => $products,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreCreditNoteRequest $request)
    {
        if(Auth::user()->can('create-credit-notes')){
            $validated = $request->validated();
            $tenantId = creatorId();

            $productIds = collect($validated['items'])->pluck('product_id')->unique();
            $products = ProductServiceItem::where('created_by', $tenantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $creditNote = DB::transaction(function () use ($validated, $products, $tenantId) {
                $subtotal = 0;
                $taxAmount = 0;
                $lineData = [];

                foreach ($validated['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    if (!$product) {
                        continue;
                    }

                    $lineSubtotal = $item['quantity'] * $item['unit_price'];
                    $taxRate = $product->taxes->sum('rate');
                    $lineTax = round($lineSubtotal * $taxRate / 100, 2);
                    $lineTotal = $lineSubtotal + $lineTax;

                    $subtotal += $lineSubtotal;
                    $taxAmount += $lineTax;

                    $lineData[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_percentage' => $taxRate,
                        'tax_amount' => $lineTax,
                        'total_amount' => $lineTotal,
                        'taxes' => $product->taxes,
                    ];
                }

                $totalAmount = $subtotal + $taxAmount;

                $creditNote = CreditNote::create([
                    'credit_note_date' => $validated['credit_note_date'],
                    'customer_id' => $validated['customer_id'],
                    'reason' => $validated['reason'],
                    'status' => 'draft',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'total_amount' => $totalAmount,
                    'applied_amount' => 0,
                    'balance_amount' => $totalAmount,
                    'notes' => $validated['notes'] ?? null,
                    'creator_id' => Auth::id(),
                    'created_by' => $tenantId,
                ]);

                foreach ($lineData as $line) {
                    $creditNoteItem = CreditNoteItem::create([
                        'credit_note_id' => $creditNote->id,
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'tax_percentage' => $line['tax_percentage'],
                        'tax_amount' => $line['tax_amount'],
                        'total_amount' => $line['total_amount'],
                    ]);

                    foreach ($line['taxes'] as $tax) {
                        CreditNoteItemTax::create([
                            'item_id' => $creditNoteItem->id,
                            'tax_name' => $tax->tax_name,
                            'tax_rate' => $tax->rate,
                        ]);
                    }
                }

                return $creditNote;
            });

            return redirect()->route('account.credit-notes.show', $creditNote->id)
                ->with('success', __('Credit note created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function show(CreditNote $creditNote)
    {
        if(Auth::user()->can('view-credit-notes') &&
           (Auth::user()->type == 'client' ? $creditNote->customer_id == Auth::id() : $creditNote->created_by == creatorId())){
            if(!$this->checkCreditNoteAccess($creditNote)) {
                return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
            }

            $creditNote->load(['customer', 'items.product', 'items.taxes', 'salesReturn', 'applications.payment']);

            return Inertia::render('Account/CreditNotes/View', [
                'creditNote' => $creditNote
            ]);
        }
        else{
            return redirect()->route('account.credit-notes.index')->with('error', __('Permission denied'));
        }
    }

    public function approve(CreditNote $creditNote)
    {
        if(Auth::user()->can('approve-credit-notes')){
            if ($creditNote->status !== 'draft') {
                return back()->with('error', __('Only draft credit notes can be approved.'));
            }
            try {
                // Create journal entries
                $this->journalService->createCreditNoteJournal($creditNote);
                $this->journalService->createCreditNoteCOGSJournal($creditNote);

                $creditNote->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id()
                ]);
                ApproveCreditNote::dispatch($creditNote);
                if(company_setting('Credit Note Approval') == 'on') {

                    $creditNote->load('customer', 'invoice', 'salesReturn');

                    $emailData = [
                        'credit_note_number' => $creditNote->credit_note_number ?? null,
                        'credit_note_date'   => $creditNote->credit_note_date ? \Carbon\Carbon::parse($creditNote->credit_note_date)->format('d M Y') : null,
                        'customer_name'      => $creditNote->customer->name ?? null,
                        'invoice_number'     => $creditNote->invoice->invoice_number ?? null,
                        'return_number'      => $creditNote->salesReturn->return_number ?? null,
                        'reason'             => $creditNote->reason ?? null,
                        'total_amount'       => number_format($creditNote->total_amount, 2),
                    ];
                    $message = EmailTemplate::sendEmailTemplate('Credit Note Approval', [$creditNote->customer->email ?? null], $emailData);
                    if($message['is_success'] == false && !empty($message['error'])) {
                        return back()
                            ->with('success', __('Credit note approved successfully.'))
                            ->with('error', $message['error']);
                    }
                }

                return back()->with('success', __('Credit note approved successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(CreditNote $creditNote)
    {
        if(Auth::user()->can('delete-credit-notes')){
            if ($creditNote->status !== 'draft') {
                return back()->with('error', __('Only draft credit notes can be deleted.'));
            }

            DestroyCreditNote::dispatch($creditNote);

            $creditNote->delete();
            return back()->with('success', __('Credit note deleted successfully.'));
        }
        else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
