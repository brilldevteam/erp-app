<?php

namespace Workdo\Retainer\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Retainer\Models\SalesRetainer;
use Workdo\Retainer\Models\SalesRetainerItem;
use Workdo\Retainer\Models\SalesRetainerItemTax;
use Workdo\Retainer\Http\Requests\StoreRetainerRequest;
use Workdo\Retainer\Http\Requests\UpdateRetainerRequest;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Retainer\Events\CreateRetainer;
use Workdo\Retainer\Events\UpdateRetainer;
use Workdo\Retainer\Events\DestroyRetainer;
use Workdo\Retainer\Events\SentSalesRetainer;
use Workdo\Retainer\Events\AcceptSalesRetainer;
use Workdo\Retainer\Events\RejectSalesRetainer;
use Workdo\Retainer\Events\ConvertSalesRetainer;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceItemTax;

class RetainerController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->can('manage-retainer')) {
            $query = SalesRetainer::with(['customer', 'items'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-retainer')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-retainer')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        if (Auth::user()->type == 'client') {
                            $q->where('status', '!=', 'draft');
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
                $query->where('retainer_number', 'like', '%' . $request->search . '%');
            }
            if ($request->date_range) {
                $dates = explode(' - ', $request->date_range);
                if (count($dates) === 2) {
                    $query->whereBetween('retainer_date', [$dates[0], $dates[1]]);
                }
            }

            $sortField     = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');

            $allowedSortFields = ['retainer_number', 'retainer_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'status', 'created_at'];
            if (!in_array($sortField, $allowedSortFields) || empty($sortField)) {
                $sortField = 'created_at';
            }

            $query->orderBy($sortField, $sortDirection);

            $perPage   = $request->get('per_page', 15);
            $retainers = $query->paginate($perPage);
            $customers = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();

            return Inertia::render('Retainer/Retainers/Index', [
                'retainers' => $retainers,
                'customers' => $customers,
                'filters'   => $request->only(['customer_id', 'status', 'search', 'date_range'])
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('create-retainer')) {
            $customers  = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();

            return Inertia::render('Retainer/Retainers/Create', [
                'customers'  => $customers,
                'warehouses' => $warehouses
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreRetainerRequest $request)
    {
        if (Auth::user()->can('create-retainer')) {
            $totals = $this->calculateTotals($request->items);

            $retainer                  = new SalesRetainer();
            $retainer->retainer_date   = $request->retainer_date;
            $retainer->due_date        = $request->due_date;
            $retainer->customer_id     = $request->customer_id;
            $retainer->warehouse_id    = $request->warehouse_id;
            $retainer->payment_terms   = $request->payment_terms;
            $retainer->notes           = $request->notes;
            $retainer->subtotal        = $totals['subtotal'];
            $retainer->tax_amount      = $totals['tax_amount'];
            $retainer->discount_amount = $totals['discount_amount'];
            $retainer->total_amount    = $totals['total_amount'];
            $retainer->balance_amount  = $totals['total_amount'];
            $retainer->creator_id      = Auth::id();
            $retainer->created_by      = creatorId();
            $retainer->save();

            $this->createRetainerItems($retainer->id, $request->items);

            try {
                CreateRetainer::dispatch($request, $retainer);
            } catch (\Throwable $th) {
                return back()->with('error', $th->getMessage());
            }

            return redirect()->route('retainers.index')->with('success', __('The retainer has been created successfully.'));
        } else {
            return redirect()->route('retainers.index')->with('error', __('Permission denied'));
        }
    }

    public function show(SalesRetainer $retainer)
    {
        if (Auth::user()->can('view-retainer')) {
            if (!$this->canAccessRetainer($retainer)) {
                return redirect()->route('retainers.index')->with('error', __('Permission denied'));
            }

            $retainer->load(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse']);

            return Inertia::render('Retainer/Retainers/View', [
                'retainer' => $retainer
            ]);
        } else {
            return redirect()->route('retainers.index')->with('error', __('Permission denied'));
        }
    }

    public function edit(SalesRetainer $retainer)
    {
        if (Auth::user()->can('edit-retainer')) {
            if (!$this->canAccessRetainer($retainer)) {
                return redirect()->route('retainers.index')->with('error', __('Permission denied'));
            }

            if ($retainer->status != 'draft') {
                return redirect()->route('retainers.index')->with('error', __('Cannot update sent retainer.'));
            }

            $retainer->load(['items.taxes']);
            $customers  = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();

            return Inertia::render('Retainer/Retainers/Edit', [
                'retainer'   => $retainer,
                'customers'  => $customers,
                'warehouses' => $warehouses
            ]);
        } else {
            return redirect()->route('retainers.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateRetainerRequest $request, SalesRetainer $retainer)
    {
        if (Auth::user()->can('edit-retainer') && $retainer->created_by == creatorId()) {
            if ($retainer->status != 'draft') {
                return redirect()->route('retainers.index')->with('error', __('Cannot update sent retainer.'));
            }

            $totals = $this->calculateTotals($request->items);

            $retainer->retainer_date   = $request->retainer_date;
            $retainer->due_date        = $request->due_date;
            $retainer->customer_id     = $request->customer_id;
            $retainer->warehouse_id    = $request->warehouse_id;
            $retainer->payment_terms   = $request->payment_terms;
            $retainer->notes           = $request->notes;
            $retainer->subtotal        = $totals['subtotal'];
            $retainer->tax_amount      = $totals['tax_amount'];
            $retainer->discount_amount = $totals['discount_amount'];
            $retainer->total_amount    = $totals['total_amount'];
            $retainer->balance_amount  = $totals['total_amount'] - $retainer->paid_amount;
            $retainer->save();

            $retainer->items()->delete();
            $this->createRetainerItems($retainer->id, $request->items);

            UpdateRetainer::dispatch($request, $retainer);

            return redirect()->route('retainers.index')->with('success', __('The retainer details are updated successfully.'));
        } else {
            return redirect()->route('retainers.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(SalesRetainer $retainer)
    {
        if (Auth::user()->can('delete-retainer')) {
            if ($retainer->status === 'sent') {
                return back()->with('error', __('Cannot delete sent retainer.'));
            }

            DestroyRetainer::dispatch($retainer);

            $retainer->delete();

            return back()->with('success', __('The retainer has been deleted.'));
        } else {
            return redirect()->route('retainers.index')->with('error', __('Permission denied'));
        }
    }

    private function calculateTotals($items)
    {
        $subtotal      = 0;
        $totalTax      = 0;
        $totalDiscount = 0;

        foreach ($items as $item) {
            $lineTotal      = $item['quantity'] * $item['unit_price'];
            $discountAmount = ($lineTotal * ($item['discount_percentage'] ?? 0)) / 100;
            $afterDiscount  = $lineTotal - $discountAmount;
            $taxAmount      = ($afterDiscount * ($item['tax_percentage'] ?? 0)) / 100;

            $subtotal      += $lineTotal;
            $totalDiscount += $discountAmount;
            $totalTax      += $taxAmount;
        }

        return [
            'subtotal'        => $subtotal,
            'tax_amount'      => $totalTax,
            'discount_amount' => $totalDiscount,
            'total_amount'    => $subtotal + $totalTax - $totalDiscount
        ];
    }

    private function createRetainerItems($retainerId, $items)
    {
        foreach ($items as $itemData) {
            $item                      = new SalesRetainerItem();
            $item->retainer_id         = $retainerId;
            $item->product_id          = $itemData['product_id'];
            $item->quantity            = $itemData['quantity'];
            $item->unit_price          = $itemData['unit_price'];
            $item->discount_percentage = $itemData['discount_percentage'] ?? 0;
            $item->tax_percentage      = $itemData['tax_percentage'] ?? 0;

            $lineTotal             = $item->quantity * $item->unit_price;
            $item->discount_amount = ($lineTotal * $item->discount_percentage) / 100;
            $afterDiscount         = $lineTotal - $item->discount_amount;
            $item->tax_amount      = ($afterDiscount * $item->tax_percentage) / 100;
            $item->total_amount    = $afterDiscount + $item->tax_amount;

            $item->save();

            if (isset($itemData['taxes']) && is_array($itemData['taxes'])) {
                foreach ($itemData['taxes'] as $tax) {
                    $retainerItemTax           = new SalesRetainerItemTax();
                    $retainerItemTax->item_id  = $item->id;
                    $retainerItemTax->tax_name = $tax['tax_name'];
                    $retainerItemTax->tax_rate = $tax['tax_rate'] ?? $tax['rate'] ?? 0;
                    $retainerItemTax->save();
                }
            }
        }
    }

    public function sent(SalesRetainer $retainer)
    {
        if (Auth::user()->can('sent-retainer') && $retainer->created_by == creatorId()) {
            if ($retainer->status !== 'draft') {
                return back()->with('error', __('Only draft retainers can be sent.'));
            }

            SentSalesRetainer::dispatch($retainer);

            $retainer->update(['status' => 'sent']);

            return back()->with('success', __('The retainer has been sent successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function accept(SalesRetainer $retainer)
    {
        if (Auth::user()->can('accept-retainer') && $retainer->created_by == creatorId()) {
            if ($retainer->status !== 'sent') {
                return back()->with('error', __('Only sent retainers can be accepted.'));
            }

            AcceptSalesRetainer::dispatch($retainer);

            $retainer->update(['status' => 'accepted']);

            return back()->with('success', __('The retainer has been accepted successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function reject(SalesRetainer $retainer)
    {
        if (Auth::user()->can('reject-retainer') && $retainer->created_by == creatorId()) {
            if ($retainer->status !== 'sent') {
                return back()->with('error', __('Only sent retainers can be rejected.'));
            }

            RejectSalesRetainer::dispatch($retainer);

            $retainer->update(['status' => 'rejected']);

            return back()->with('success', __('The retainer has been rejected successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function print(SalesRetainer $retainer)
    {
        if (Auth::user()->can('print-retainer')) {
            $retainer->load(['customer', 'items.product', 'items.taxes', 'warehouse']);

            return Inertia::render('Retainer/Retainers/Print', [
                'retainer' => $retainer
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function duplicate(SalesRetainer $retainer)
    {
        if (Auth::user()->can('duplicate-retainer')) {
            $retainer->load(['items.taxes']);

            $duplicate                       = $retainer->replicate();
            $duplicate->status               = 'draft';
            $duplicate->converted_to_invoice = false;
            $duplicate->invoice_id           = null;
            $duplicate->retainer_number      = null;
            $duplicate->paid_amount          = 0;
            $duplicate->balance_amount       = $duplicate->total_amount;
            $duplicate->save();

            foreach ($retainer->items as $item) {
                $newItem              = $item->replicate();
                $newItem->retainer_id = $duplicate->id;
                $newItem->save();

                foreach ($item->taxes as $tax) {
                    $newTax          = $tax->replicate();
                    $newTax->item_id = $newItem->id;
                    $newTax->save();
                }
            }

            return back()->with('success', __('Retainer duplicated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function convertToInvoice(SalesRetainer $retainer)
    {
        if (Auth::user()->can('convert-to-invoice-retainer') && $retainer->created_by == creatorId()) {
            if ($retainer->converted_to_invoice) {
                return back()->with('error', __('Retainer already converted to invoice.'));
            }

            if ($retainer->paid_amount <= 0) {
                return back()->with('error', __('Retainer must have at least one payment to be converted.'));
            }

            $retainer->load(['items.taxes']);

            // Create sales invoice from retainer
            $invoice                  = new SalesInvoice();
            $invoice->customer_id     = $retainer->customer_id;
            $invoice->warehouse_id    = $retainer->warehouse_id ?? 1;
            $invoice->invoice_date    = now();
            $invoice->due_date        = $retainer->due_date;
            $invoice->subtotal        = $retainer->subtotal;
            $invoice->tax_amount      = $retainer->tax_amount;
            $invoice->discount_amount = $retainer->discount_amount;
            $invoice->total_amount    = $retainer->total_amount;
            $invoice->paid_amount     = $retainer->paid_amount;
            $invoice->balance_amount  = $retainer->total_amount - $retainer->paid_amount;
            $invoice->status          = $retainer->status;
            $invoice->payment_terms   = $retainer->payment_terms;
            $invoice->notes           = $retainer->notes;
            $invoice->creator_id      = Auth::id();
            $invoice->created_by      = creatorId();
            $invoice->save();

            // Copy retainer items to invoice items
            foreach ($retainer->items as $retainerItem) {
                $invoiceItem                      = new SalesInvoiceItem();
                $invoiceItem->invoice_id          = $invoice->id;
                $invoiceItem->product_id          = $retainerItem->product_id;
                $invoiceItem->quantity            = $retainerItem->quantity;
                $invoiceItem->unit_price          = $retainerItem->unit_price;
                $invoiceItem->discount_percentage = $retainerItem->discount_percentage;
                $invoiceItem->discount_amount     = $retainerItem->discount_amount;
                $invoiceItem->tax_percentage      = $retainerItem->tax_percentage;
                $invoiceItem->tax_amount          = $retainerItem->tax_amount;
                $invoiceItem->total_amount        = $retainerItem->total_amount;
                $invoiceItem->save();

                // Copy tax details
                foreach ($retainerItem->taxes as $tax) {
                    $invoiceTax           = new SalesInvoiceItemTax();
                    $invoiceTax->item_id  = $invoiceItem->id;
                    $invoiceTax->tax_name = $tax->tax_name;
                    $invoiceTax->tax_rate = $tax->tax_rate;
                    $invoiceTax->save();
                }
            }

            try {
                ConvertSalesRetainer::dispatch($retainer, $invoice);
            } catch (\Throwable $th) {
                $invoice->delete();
                return back()->with('error', $th->getMessage());
            }

            // Mark retainer as converted
            $retainer->converted_to_invoice = true;
            $retainer->invoice_id           = $invoice->id;
            $retainer->status               = 'converted';
            $retainer->save();

            return back()->with('success', __('Retainer converted to invoice successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    private function canAccessRetainer(SalesRetainer $retainer)
    {
        if (Auth::user()->can('manage-any-retainer')) {
            return $retainer->created_by == creatorId();
        } elseif (Auth::user()->can('manage-own-retainer')) {
            return $retainer->creator_id == Auth::id() || $retainer->customer_id == Auth::id();
        } else {
            return false;
        }
    }

    public function getWarehouseProducts(Request $request)
    {
        if (Auth::user()->can('create-retainer') || Auth::user()->can('edit-retainer')) {
            $warehouseId = $request->warehouse_id;

            if (!$warehouseId) {
                return response()->json([]);
            }

            $products = ProductServiceItem::select('id', 'name', 'sku', 'sale_price', 'tax_ids', 'unit', 'type')
                ->where('is_active', true)
                ->where('created_by', creatorId())
                ->whereHas('warehouseStocks', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)
                        ->where('quantity', '>', 0);
                })
                ->with(['warehouseStocks' => function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }])
                ->get()
                ->map(function ($product) {
                    $stock = $product->warehouseStocks->first();
                    return [
                        'id'             => $product->id,
                        'name'           => $product->name,
                        'sku'            => $product->sku,
                        'sale_price'     => $product->sale_price,
                        'unit'           => $product->unit,
                        'type'           => $product->type,
                        'stock_quantity' => $stock ? $stock->quantity : 0,
                        'taxes'          => $product->taxes->map(function ($tax) {
                            return [
                                'id'       => $tax->id,
                                'tax_name' => $tax->tax_name,
                                'rate'     => $tax->rate
                            ];
                        })
                    ];
                });
            return response()->json($products);
        } else {
            return response()->json([], 403);
        }
    }
}
