<?php

namespace Workdo\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;
use Workdo\Quotation\Models\SalesQuotationItemTax;
use Workdo\Quotation\Http\Requests\StoreQuotationRequest;
use Workdo\Quotation\Http\Requests\UpdateQuotationRequest;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;
use App\Models\SalesInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Quotation\Events\AcceptSalesQuotation;
use Workdo\Quotation\Events\CreateQuotation;
use Workdo\Quotation\Events\UpdateQuotation;
use Workdo\Quotation\Events\DestroyQuotation;
use Workdo\Quotation\Events\RejectSalesQuotation;
use Workdo\Quotation\Events\SentSalesQuotation;
use Workdo\Account\Models\Customer;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\DocumentSettingsService;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->can('manage-quotations')) {
            $query = SalesQuotation::with(['customer', 'items'])
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-quotations')) {
                        $q->where('created_by', creatorId());
                    } elseif (Auth::user()->can('manage-own-quotations')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        if (Auth::user()->type == 'client') {
                            $q->where('status', '!=', 'draft');
                        }
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
                $query->where('quotation_number', 'like', '%' . $request->search . '%');
            }
            if ($request->date_range) {
                $dates = explode(' - ', $request->date_range);
                if (count($dates) === 2) {
                    $query->whereBetween('quotation_date', [$dates[0], $dates[1]]);
                }
            }

              // Apply sorting
            $sortField     = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');

            $allowedSortFields = ['quotation_number', 'quotation_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'status', 'created_at'];
            if (!in_array($sortField, $allowedSortFields) || empty($sortField)) {
                $sortField = 'created_at';
            }

            $query->orderBy($sortField, $sortDirection);

            $perPage    = $request->get('per_page', 15);
            $quotations = $query->paginate($perPage);
            $customers  = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();

            return Inertia::render('Quotation/Quotations/Index', [
                'quotations' => $quotations,
                'customers'  => $customers,
                'filters'    => $request->only(['customer_id', 'status', 'search', 'date_range'])
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if (Auth::user()->can('create-quotations')) {
            $customers  = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();
            $customerUsers = User::where('type', 'client')
                ->where('created_by', creatorId())
                ->whereNotIn('id', Customer::whereNotNull('user_id')->pluck('user_id'))
                ->select('id', 'name', 'email', 'mobile_no')
                ->get();

            return Inertia::render('Quotation/Quotations/Create', [
                'customers'  => $customers,
                'warehouses' => $warehouses,
                'customerUsers' => $customerUsers,
                'documentTemplate' => company_setting('quotation_template', creatorId()) ?: 'zoho',
                'documentLogo' => company_setting('document_default_logo', creatorId()) ?: '',
                'templateProfiles' => $this->documentTemplateProfiles('quotation'),
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreQuotationRequest $request)
    {
        if (Auth::user()->can('create-quotations')) {
            $totals = $this->calculateTotals($request->items);

            $quotation                  = new SalesQuotation();
            $quotation->quotation_date  = $request->invoice_date;
            $quotation->due_date        = $request->due_date;
            $quotation->customer_id     = $request->customer_id;
            $quotation->warehouse_id    = $request->filled('warehouse_id') ? $request->warehouse_id : null;
            $quotation->payment_terms   = $request->payment_terms;
            $quotation->notes           = $request->notes;
            $quotation->template_key    = $request->template_key;
            $quotation->document_logo   = $request->filled('document_logo') ? basename($request->document_logo) : null;
            $quotation->subtotal        = $totals['subtotal'];
            $quotation->tax_amount      = $totals['tax_amount'];
            $quotation->discount_amount = $totals['discount_amount'];
            $quotation->total_amount    = $totals['total_amount'];
            $quotation->creator_id      = Auth::id();
            $quotation->created_by      = creatorId();
            $quotation->save();

              // Create quotation items
            $this->createQuotationItems($quotation->id, $request->items);

            try {
                CreateQuotation::dispatch($request, $quotation);
            } catch (\Throwable $th) {
                return back()->with('error', $th->getMessage());
            }

            return redirect()->route('quotations.index')->with('success', __('The quotation has been created successfully.'));
        } else {
            return redirect()->route('quotations.index')->with('error', __('Permission denied'));
        }
    }



    public function show(SalesQuotation $quotation)
    {
        if (Auth::user()->can('view-quotations')) {
            if (!$this->canAccessQuotation($quotation)) {
                return redirect()->route('quotations.index')->with('error', __('Access denied'));
            }

            $quotation->load(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse', 'parentQuotation', 'invoice']);

            return Inertia::render('Quotation/Quotations/View', [
                'quotation' => $quotation
            ]);
        } else {
            return redirect()->route('quotations.index')->with('error', __('Permission denied'));
        }
    }

    public function edit(SalesQuotation $quotation)
    {
        if (Auth::user()->can('edit-quotations')) {
            if (!$this->canAccessQuotation($quotation)) {
                return redirect()->route('quotations.index')->with('error', __('Access denied'));
            }

            if ($quotation->status != 'draft') {
                return redirect()->route('quotations.index')->with('error', __('Cannot update sent quotation.'));
            }

            $quotation->load(['items.taxes']);
            $customers  = User::where('type', 'client')->select('id', 'name', 'email')->where('created_by', creatorId())->get();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();

            return Inertia::render('Quotation/Quotations/Edit', [
                'quotation'  => $quotation,
                'customers'  => $customers,
                'warehouses' => $warehouses
                ,'templateProfiles' => $this->documentTemplateProfiles('quotation')
            ]);
        } else {
            return redirect()->route('quotations.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateQuotationRequest $request, SalesQuotation $quotation)
    {
        if (Auth::user()->can('edit-quotations') && $quotation->created_by == creatorId()) {
            if ($quotation->status != 'draft') {
                return redirect()->route('quotations.index')->with('error', __('Cannot update sent quotation.'));
            }

            $totals = $this->calculateTotals($request->items);

            $quotation->quotation_date  = $request->invoice_date;
            $quotation->due_date        = $request->due_date;
            $quotation->customer_id     = $request->customer_id;
            $quotation->warehouse_id    = $request->filled('warehouse_id') ? $request->warehouse_id : null;
            $quotation->payment_terms   = $request->payment_terms;
            $quotation->notes           = $request->notes;
            $quotation->template_key    = $request->template_key;
            $quotation->document_logo   = $request->filled('document_logo') ? basename($request->document_logo) : null;
            $quotation->document_snapshot = null;
            $quotation->subtotal        = $totals['subtotal'];
            $quotation->tax_amount      = $totals['tax_amount'];
            $quotation->discount_amount = $totals['discount_amount'];
            $quotation->total_amount    = $totals['total_amount'];
            $quotation->save();

            $quotation->items()->delete();
            $this->createQuotationItems($quotation->id, $request->items);

            UpdateQuotation::dispatch($request, $quotation);

            return redirect()->route('quotations.index')->with('success', __('The quotation details are updated successfully.'));
        } else {
            return redirect()->route('quotations.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(SalesQuotation $quotation)
    {
        if (Auth::user()->can('delete-quotations')) {
            if ($quotation->status === 'sent') {
                return back()->with('error', __('Cannot delete sent quotation.'));
            }

            DestroyQuotation::dispatch($quotation);

            $quotation->delete();

            return redirect()->route('quotations.index')->with('success', __('The quotation has been deleted.'));
        } else {
            return redirect()->route('quotations.index')->with('error', __('Permission denied'));
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

    private function createQuotationItems($quotationId, $items)
    {
        foreach ($items as $itemData) {
            $item                      = new SalesQuotationItem();
            $item->quotation_id        = $quotationId;
            $item->product_id          = $itemData['product_id'];
            $item->quantity            = $itemData['quantity'];
            $item->unit_price          = $itemData['unit_price'];
            $item->discount_percentage = $itemData['discount_percentage'] ?? 0;
            $item->tax_percentage      = $itemData['tax_percentage'] ?? 0;
            $item->save();

              // Store individual taxes
            if (isset($itemData['taxes']) && is_array($itemData['taxes'])) {
                foreach ($itemData['taxes'] as $tax) {
                    $quotationItemTax           = new SalesQuotationItemTax();
                    $quotationItemTax->item_id  = $item->id;
                    $quotationItemTax->tax_name = $tax['tax_name'];
                    $quotationItemTax->tax_rate = $tax['tax_rate'] ?? $tax['rate'] ?? 0;
                    $quotationItemTax->save();
                }
            }
        }
    }

    public function sent(SalesQuotation $quotation)
    {
        if (Auth::user()->can('sent-quotations') && $quotation->created_by == creatorId()) {
            if ($quotation->status !== 'draft') {
                return back()->with('error', __('Only draft quotations can be sent.'));
            }
            SentSalesQuotation::dispatch($quotation);

            $quotation->update(['status' => 'sent']);

            return back()->with('success', __('The quotation has been sent successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function approve(SalesQuotation $quotation)
    {
        if (Auth::user()->can('approve-quotations') && $quotation->created_by == creatorId()) {
            if ($quotation->status !== 'sent') {
                return back()->with('error', __('Only sent quotations can be approved.'));
            }
            AcceptSalesQuotation::dispatch($quotation);

            $quotation->update(['status' => 'accepted']);

            return back()->with('success', __('The quotation has been approved successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function reject(SalesQuotation $quotation)
    {
        if (Auth::user()->can('reject-quotations') && $quotation->created_by == creatorId()) {
            if ($quotation->status !== 'sent') {
                return back()->with('error', __('Only sent quotations can be rejected.'));
            }
            RejectSalesQuotation::dispatch($quotation);

            $quotation->update(['status' => 'rejected']);

            return back()->with('success', __('The quotation has been rejected successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function print(Request $request, SalesQuotation $quotation, DocumentRenderer $renderer)
    {
        if (Auth::user()->can('print-quotations') && $quotation->created_by == creatorId()) {
            $quotation->load(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse']);
            if ($request->boolean('download')) {
                return response($renderer->pdf('quotation', $quotation))
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="quotation-'.$quotation->quotation_number.'.pdf"');
            }

            return $renderer->preview('quotation', $quotation);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function createRevision(SalesQuotation $quotation)
    {
        if (Auth::user()->can('create-quotations-revision') && $quotation->created_by == creatorId()) {
            if ($quotation->status === 'draft') {
                return back()->with('error', __('Cannot create version of draft quotation.'));
            }

            $quotation->load(['items.taxes']);

            // Create new revision
            $newRevision = $quotation->replicate();
            $newRevision->parent_quotation_id = $quotation->id;
            $newRevision->revision_number = $quotation->revision_number + 1;
            $newRevision->status = 'draft';
            $newRevision->converted_to_invoice = false;
            $newRevision->invoice_id = null;
            $newRevision->quotation_number = null;
            $newRevision->save();

            // Copy items
            foreach ($quotation->items as $item) {
                $newItem = $item->replicate();
                $newItem->quotation_id = $newRevision->id;
                $newItem->save();

                // Copy taxes
                foreach ($item->taxes as $tax) {
                    $newTax = $tax->replicate();
                    $newTax->item_id = $newItem->id;
                    $newTax->save();
                }
            }

            return redirect()->route('quotations.edit', $newRevision);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function duplicate(SalesQuotation $quotation)
    {
        if (Auth::user()->can('duplicate-quotations')) {
            $quotation->load(['items.taxes']);

            // Create duplicate
            $duplicate = $quotation->replicate();
            $duplicate->status = 'draft';
            $duplicate->converted_to_invoice = false;
            $duplicate->invoice_id = null;
            $duplicate->quotation_number = null; // Will be auto-generated
            $duplicate->parent_quotation_id = null;
            $duplicate->revision_number = 1;
            $duplicate->save();

            // Copy items
            foreach ($quotation->items as $item) {
                $newItem = $item->replicate();
                $newItem->quotation_id = $duplicate->id;
                $newItem->save();

                // Copy taxes
                foreach ($item->taxes as $tax) {
                    $newTax = $tax->replicate();
                    $newTax->item_id = $newItem->id;
                    $newTax->save();
                }
            }
            return back()->with('success', __('Quotation duplicated successfully.'));
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function convertToInvoice(SalesQuotation $quotation)
    {
        if (
            !Auth::user()->can('convert-to-invoice-quotations')
            || !Auth::user()->can('create-sales-invoices')
            || $quotation->created_by != creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        if ($quotation->converted_to_invoice || $quotation->invoice_id) {
            return back()->with('error', __('This quotation has already been converted to invoice.'));
        }

        if (!$quotation->canConvertToInvoice()) {
            return back()->with('error', __('This quotation cannot be converted to invoice.'));
        }

        $quotation->load(['customer', 'warehouse', 'items.product', 'items.taxes']);

        if (!$quotation->customer) {
            return back()->with('error', __('A customer is required before converting this quotation.'));
        }

        if ($quotation->items->isEmpty()) {
            return back()->with('error', __('At least one line item is required before converting this quotation.'));
        }

        $type = $quotation->items->every(
            fn ($item) => $item->product?->type === 'service'
        ) ? 'service' : 'product';

        $customers = User::where('type', 'client')
            ->select('id', 'name', 'email')
            ->where('created_by', creatorId())
            ->get();
        $warehouses = Warehouse::where('is_active', true)
            ->select('id', 'name', 'address')
            ->where('created_by', creatorId())
            ->get();

        return Inertia::render('Sales/Create', [
            'customers' => $customers,
            'warehouses' => $warehouses,
            'initialProducts' => $quotation->items
                ->filter(fn ($item) => $item->product)
                ->unique('product_id')
                ->map(fn ($item) => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'description' => $item->product->description,
                    'sale_price' => (float) $item->product->sale_price,
                    'unit' => $item->product->unit,
                    'type' => $item->product->type,
                    'taxes' => $item->taxes->map(fn ($tax) => [
                        'id' => $tax->id,
                        'tax_name' => $tax->tax_name,
                        'rate' => (float) $tax->tax_rate,
                    ])->values(),
                ])
                ->values(),
            'initialInvoice' => [
                'invoice_number' => SalesInvoice::generateInvoiceNumber(),
                'quotation_id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'quotation_date' => $quotation->quotation_date->format('Y-m-d'),
                'invoice_date' => $quotation->quotation_date->format('Y-m-d'),
                'due_date' => $quotation->due_date->format('Y-m-d'),
                'customer_id' => (string) $quotation->customer_id,
                'warehouse_id' => $type === 'product' && $quotation->warehouse_id
                    ? (string) $quotation->warehouse_id
                    : '',
                'type' => $type,
                'payment_terms' => $quotation->payment_terms ?? '',
                'notes' => $quotation->notes ?? '',
                'template_key' => $quotation->template_key ?: 'classic',
                'document_logo' => $quotation->document_logo,
                'items' => $quotation->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percentage' => (float) $item->discount_percentage,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_percentage' => (float) $item->tax_percentage,
                    'tax_amount' => (float) $item->tax_amount,
                    'total_amount' => (float) $item->total_amount,
                    'taxes' => $item->taxes->map(fn ($tax) => [
                        'tax_name' => $tax->tax_name,
                        'tax_rate' => (float) $tax->tax_rate,
                    ])->values(),
                ])->values(),
            ],
            'templateProfiles' => $this->documentTemplateProfiles('invoice'),
        ]);
    }

    private function canAccessQuotation(SalesQuotation $quotation)
    {
        if (Auth::user()->can('manage-any-quotations')) {
            return $quotation->created_by == creatorId();
        } elseif (Auth::user()->can('manage-own-quotations')) {
            return $quotation->creator_id == Auth::id() || $quotation->customer_id == Auth::id();
        } else {
            return false;
        }
    }

    public function getWarehouseProducts(Request $request)
    {
        if (Auth::user()->can('create-quotations') || Auth::user()->can('edit-quotations')) {
            $validated = $request->validate([
                'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            ]);
            $warehouseId = $validated['warehouse_id'] ?? null;

            if ($warehouseId) {
                $warehouseExists = Warehouse::where('id', $warehouseId)
                    ->where('created_by', creatorId())
                    ->exists();

                if (!$warehouseExists) {
                    return response()->json([], 404);
                }
            }

            $productsQuery = ProductServiceItem::select('id', 'name', 'sku', 'sale_price', 'tax_ids', 'unit', 'type')
                ->where('is_active', true)
                ->where('created_by', creatorId());

            if ($warehouseId) {
                $productsQuery
                    ->whereHas('warehouseStocks', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)
                        ->where('quantity', '>', 0);
                })
                    ->with(['warehouseStocks' => function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }]);
            }

            $products = $productsQuery
                ->get()
                ->map(function ($product) use ($warehouseId) {
                    $productData = [
                        'id'             => $product->id,
                        'name'           => $product->name,
                        'sku'            => $product->sku,
                        'sale_price'     => $product->sale_price,
                        'unit'           => $product->unit,
                        'type'           => $product->type,
                        'taxes'          => $product->taxes->map(function ($tax) {
                            return [
                                'id'       => $tax->id,
                                'tax_name' => $tax->tax_name,
                                'rate'     => $tax->rate
                            ];
                        })
                    ];

                    if ($warehouseId) {
                        $stock = $product->warehouseStocks->first();
                        $productData['stock_quantity'] = $stock ? $stock->quantity : 0;
                    }

                    return $productData;
                });
            return response()->json($products);
        } else {
            return response()->json([], 403);
        }
    }

    private function documentTemplateProfiles(string $type): array
    {
        $settings = app(DocumentSettingsService::class);

        return collect(DocumentSettingsService::TEMPLATES)
            ->mapWithKeys(fn ($template) => [$template => $settings->get(creatorId(), $type, $template)])
            ->all();
    }
}
