<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\User;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteItem;
use Workdo\Account\Models\DebitNoteItemTax;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Events\ApproveDebitNote;
use Workdo\Account\Events\DestroyDebitNote;
use Workdo\Account\Http\Requests\StoreDebitNoteRequest;
use Workdo\Account\Services\JournalService;
use Workdo\ProductService\Models\ProductServiceItem;
use App\Models\EmailTemplate;

class DebitNoteController extends Controller
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    private function checkDebitNoteAccess(DebitNote $debitNote)
    {
        if(Auth::user()->can('manage-any-debit-notes')) {
            return true;
        } elseif(Auth::user()->can('manage-own-debit-notes')) {
            if($debitNote->creator_id != Auth::id() && $debitNote->vendor_id != Auth::id()) {
                return false;
            }
            if($debitNote->creator_id != Auth::id() && Auth::user()->type == 'vendor' && $debitNote->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-debit-notes')){
            $query = DebitNote::with(['vendor', 'purchaseReturn', 'approvedBy'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-debit-notes')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-debit-notes')) {
                        $q->where('creator_id', Auth::id())->orWhere('vendor_id', Auth::id());
                        if(Auth::user()->type == 'vendor') {
                            $q->where('status','!=', 'draft');
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            if ($request->vendor_id) {
                $query->where('vendor_id', $request->vendor_id);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->search) {
                $query->where('debit_note_number', 'like', '%' . $request->search . '%');
            }

            if ($request->purchase_return_id) {
                $query->where('return_id', $request->purchase_return_id);
            }

            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $debitNotes = $query->paginate($request->per_page ?? 10)->withQueryString();

            $vendors = User::where('type', 'vendor')->where('created_by', creatorId())->get(['id', 'name']);
            $purchaseReturns = PurchaseReturn::where('created_by', creatorId())->get(['id', 'return_number']);

            return Inertia::render('Account/DebitNotes/Index', [
                'debitNotes' => $debitNotes,
                'vendors' => $vendors,
                'purchaseReturns' => $purchaseReturns,
                'filters' => $request->only(['vendor_id', 'status', 'purchase_return_id'])
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if(Auth::user()->can('create-debit-notes')){
            $vendors = User::where('type', 'vendor')->where('created_by', creatorId())->get(['id', 'name']);
            $products = ProductServiceItem::where('created_by', creatorId())
                ->get(['id', 'name', 'sku', 'purchase_price', 'unit', 'type', 'tax_ids'])
                ->map(fn($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'purchase_price' => $product->purchase_price,
                    'unit' => $product->unit,
                    'type' => $product->type,
                    'taxes' => $product->taxes->map(fn($tax) => [
                        'id' => $tax->id,
                        'tax_name' => $tax->tax_name,
                        'rate' => $tax->rate,
                    ]),
                ]);

            return Inertia::render('Account/DebitNotes/Create', [
                'vendors' => $vendors,
                'products' => $products,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreDebitNoteRequest $request)
    {
        if(Auth::user()->can('create-debit-notes')){
            $validated = $request->validated();
            $tenantId = creatorId();

            $productIds = collect($validated['items'])->pluck('product_id')->unique();
            $products = ProductServiceItem::where('created_by', $tenantId)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $debitNote = DB::transaction(function () use ($validated, $products, $tenantId) {
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

                $debitNote = DebitNote::create([
                    'debit_note_date' => $validated['debit_note_date'],
                    'vendor_id' => $validated['vendor_id'],
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
                    $debitNoteItem = DebitNoteItem::create([
                        'debit_note_id' => $debitNote->id,
                        'product_id' => $line['product_id'],
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'tax_percentage' => $line['tax_percentage'],
                        'tax_amount' => $line['tax_amount'],
                        'total_amount' => $line['total_amount'],
                        'creator_id' => Auth::id(),
                        'created_by' => $tenantId,
                    ]);

                    foreach ($line['taxes'] as $tax) {
                        DebitNoteItemTax::create([
                            'item_id' => $debitNoteItem->id,
                            'tax_name' => $tax->tax_name,
                            'tax_rate' => $tax->rate,
                        ]);
                    }
                }

                return $debitNote;
            });

            return redirect()->route('account.debit-notes.show', $debitNote->id)
                ->with('success', __('Debit note created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function show(DebitNote $debitNote)
    {
        if(Auth::user()->can('view-debit-notes') &&
           (Auth::user()->type == 'vendor' ? $debitNote->vendor_id == Auth::id() : $debitNote->created_by == creatorId())){
            if(!$this->checkDebitNoteAccess($debitNote)) {
                return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
            }

            $debitNote->load(['vendor', 'items.product', 'items.taxes', 'purchaseReturn', 'applications.payment']);

            return Inertia::render('Account/DebitNotes/View', [
                'debitNote' => $debitNote
            ]);
        }
        else{
            return redirect()->route('account.debit-notes.index')->with('error', __('Permission denied'));
        }
    }

    public function approve(DebitNote $debitNote)
    {
        if(Auth::user()->can('approve-debit-notes')){
            if ($debitNote->status !== 'draft') {
                return back()->with('error', __('Only draft debit notes can be approved.'));
            }
            try {
                // Create journal entries
                $this->journalService->createDebitNoteJournal($debitNote);

                $debitNote->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id()
                ]);
                ApproveDebitNote::dispatch($debitNote);

                if(company_setting('Debit Note Approval') == 'on') {
                    $debitNote->load('vendor', 'invoice', 'purchaseReturn');
                    $emailData = [
                        'debit_note_number' => $debitNote->debit_note_number ?? null,
                        'debit_note_date'   => $debitNote->debit_note_date ? \Carbon\Carbon::parse($debitNote->debit_note_date)->format('d M Y') : null,
                        'vendor_name'       => $debitNote->vendor->name ?? null,
                        'invoice_number'    => $debitNote->invoice->invoice_number ?? null,
                        'return_number'     => $debitNote->purchaseReturn->return_number ?? null,
                        'reason'            => $debitNote->reason ?? null,
                        'total_amount'      => number_format($debitNote->total_amount, 2),
                    ];
                    $message = EmailTemplate::sendEmailTemplate('Debit Note Approval', [$debitNote->vendor->email ?? null], $emailData);
                    if($message['is_success'] == false && !empty($message['error'])) {
                        return back()
                            ->with('success', __('Debit note approved successfully.'))
                            ->with('error', $message['error']);
                    }
                }
                return back()->with('success', __('Debit note approved successfully.'));
            } catch (\Exception $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(DebitNote $debitNote)
    {
        if(Auth::user()->can('delete-debit-notes')){
            if ($debitNote->status !== 'draft') {
                return back()->with('error', __('Only draft debit notes can be deleted.'));
            }

            DestroyDebitNote::dispatch($debitNote);

            $debitNote->delete();
            return back()->with('success', __('Debit note deleted successfully.'));
        }
        else {
            return back()->with('error', __('Permission denied'));
        }
    }
}
