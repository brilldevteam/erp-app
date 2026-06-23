<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceItemTax;
use App\Models\User;
use App\Models\Warehouse;
use App\Http\Requests\StoreSalesInvoiceRequest;
use App\Http\Requests\UpdateSalesInvoiceRequest;
use Workdo\ProductService\Models\ProductServiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Events\CreateSalesInvoice;
use App\Events\UpdateSalesInvoice;
use App\Events\DestroySalesInvoice;
use App\Events\PostSalesInvoice;
use App\Events\EditSalesInvoice;
use App\Models\EmailTemplate;
use App\Models\DocumentTemplate;
use App\Services\SalesInvoiceService;
use App\Services\DocumentTemplates\DocumentTemplateService;
use Workdo\Quotation\Events\ConvertSalesQuotation;

class SalesInvoiceController extends Controller
{
    private function invoiceCustomers()
    {
        return User::query()
            ->leftJoin('customers', 'customers.user_id', '=', 'users.id')
            ->where('users.type', 'client')
            ->where('users.created_by', creatorId())
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'customers.company_name',
                'customers.contact_person_name'
            )
            ->get();
    }

    private function checkInvoiceAccess(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('manage-any-sales-invoices')) {
            return true;
        } elseif(Auth::user()->can('manage-own-sales-invoices')) {
            if($salesInvoice->creator_id != Auth::id() && $salesInvoice->customer_id != Auth::id()) {
                return false;
            }
            if($salesInvoice->creator_id != Auth::id() && Auth::user()->type == 'client' && $salesInvoice->status == 'draft') {
                return false;
            }
            return true;
        }
        return false;
    }
    public function index(Request $request)
    {
        if(Auth::user()->can('manage-sales-invoices')){
            $query = SalesInvoice::with(['customer', 'customerDetails', 'items'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-sales-invoices')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-sales-invoices')) {
                        $q->where('creator_id', Auth::id())->orWhere('customer_id',Auth::id());
                        if(Auth::user()->type == 'client') {
                            $q->where('status','!=', 'draft');
                        }
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                });

            // Apply filters
            if ($request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }
            if ($request->warehouse_id) {
                $query->where('warehouse_id', $request->warehouse_id);
            }
            if ($request->status) {
                if ($request->status === 'overdue') {
                    $query->where('due_date', '<', now())
                    ->whereIn('status', ['posted', 'partial'])
                    ->where('balance_amount', '>', 0);
                } else {
                    $query->where('status', $request->status);
                }
            }
            if ($request->search) {
                $query->where('invoice_number', 'like', '%' . $request->search . '%');
            }
            if ($request->date_range) {
                $dates = explode(' - ', $request->date_range);
                if (count($dates) === 2) {
                    $query->whereBetween('invoice_date', [$dates[0], $dates[1]]);
                }
            }

        // Apply sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['invoice_number', 'invoice_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'balance_amount', 'status', 'created_at'];
        if (!in_array($sortField, $allowedSortFields) || empty($sortField)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 10);
        $invoices = $query->paginate($perPage);
        $customers = $this->invoiceCustomers();
        $warehouses = Warehouse::where('is_active', true)->select('id', 'name')->where('created_by', creatorId())->get();

            return Inertia::render('Sales/Index', [
                'invoices' => $invoices,
                'customers' => $customers,
                'warehouses' => $warehouses,
                'filters' => $request->only(['customer_id', 'warehouse_id', 'status', 'search', 'date_range'])
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if(Auth::user()->can('create-sales-invoices')){
            $customers = $this->invoiceCustomers();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();

            return Inertia::render('Sales/Create', [
                'customers' => $customers,
                'warehouses' => $warehouses,
                'documentTemplates' => $this->activeTemplates(DocumentTemplate::TYPE_INVOICE),
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreSalesInvoiceRequest $request, SalesInvoiceService $invoiceService)
    {
        if(Auth::user()->can('create-sales-invoices')){
            if (
                $request->filled('quotation_id')
                && !Auth::user()->can('convert-to-invoice-quotations')
            ) {
                return back()->with('error', __('Permission denied'));
            }

            $invoice = $invoiceService->create(
                $request->validated(),
                Auth::id(),
                creatorId(),
            );

            try {

                CreateSalesInvoice::dispatch($request, $invoice);
                if ($invoice->quotation) {
                    ConvertSalesQuotation::dispatch($invoice->quotation, $invoice);
                }
                // Send sales invoice mail
                if(company_setting('Sales Invoice') == 'on') {
                    $emailData = [
                        'invoice_number' => $invoice->invoice_number ?? null,
                        'sales_customer_name' => $invoice->customer->name ?? null,
                        'warehouse_name' => $invoice->warehouse->name ?? null,
                        'total_amount' => $invoice->total_amount,
                        'discount_amount' => $invoice->discount_amount,
                    ];
                    $message = EmailTemplate::sendEmailTemplate('Sales Invoice', [$invoice->customer->email], $emailData);
                    if($message['is_success'] == false && !empty($message['error'])) {
                        return back()
                            ->with('success', __('The sales invoice has been created successfully.'))
                            ->with('error', $message['error']);
                    }
                }
            } catch (\Throwable $th) {
                return back()->with('error', $th->getMessage());
            }


            return redirect()->route('sales-invoices.index')->with('success', __('The sales invoice has been created successfully.'));

        }
        else{
            return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
        }
    }

    public function show(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('view-sales-invoices') && $salesInvoice->created_by == creatorId()){
            if(!$this->checkInvoiceAccess($salesInvoice)) {
                return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
            }

            $salesInvoice->load(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse', 'quotation']);

            return Inertia::render('Sales/View', [
                'invoice' => $salesInvoice
            ]);
        }
        else{
            return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
        }
    }

    public function edit(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('edit-sales-invoices') && $salesInvoice->created_by == creatorId()){
            if(!$this->checkInvoiceAccess($salesInvoice)) {
                return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
            }

            if ($salesInvoice->status != 'draft') {
                return redirect()->route('sales-invoices.index')->with('error', __('Cannot update posted invoice.'));
            }

            $salesInvoice->load(['items.taxes']);

            EditSalesInvoice::dispatch($salesInvoice);

            $customers = $this->invoiceCustomers();
            $warehouses = Warehouse::where('is_active', true)->select('id', 'name', 'address')->where('created_by', creatorId())->get();

            return Inertia::render('Sales/Edit', [
                'invoice' => $salesInvoice,
                'customers' => $customers,
                'warehouses' => $warehouses,
                'documentTemplates' => $this->activeTemplates(DocumentTemplate::TYPE_INVOICE),
            ]);
        }
        else{
            return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateSalesInvoiceRequest $request, SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('edit-sales-invoices') && $salesInvoice->created_by == creatorId()){
            if ($salesInvoice->status != 'draft') {
                return redirect()->route('sales-invoices.index')->with('error', __('Cannot update posted invoice.'));
            }
            $totals = $this->calculateTotals($request->items);

            $salesInvoice->invoice_date = $request->invoice_date;
            $salesInvoice->due_date = $request->due_date;
            $salesInvoice->customer_id = $request->customer_id;
            $salesInvoice->document_template_id = app(DocumentTemplateService::class)
                ->resolveForDocument(DocumentTemplate::TYPE_INVOICE, creatorId(), $request->document_template_id)
                ->id;
            $salesInvoice->warehouse_id = $salesInvoice->type === 'product' && $request->filled('warehouse_id')
                ? $request->warehouse_id
                : null;
            $salesInvoice->payment_terms = $request->payment_terms;
            $salesInvoice->notes = $request->notes;
            $salesInvoice->subtotal = $totals['subtotal'];
            $salesInvoice->tax_amount = $totals['tax_amount'];
            $salesInvoice->discount_amount = $totals['discount_amount'];
            $salesInvoice->total_amount = $totals['total_amount'];
            $salesInvoice->balance_amount = $totals['total_amount'];
            $salesInvoice->save();

            // Delete existing items and recreate
            $salesInvoice->items()->delete();
            $this->createInvoiceItems($salesInvoice->id, $request->items);

            // Dispatch event for packages to handle their fields
            UpdateSalesInvoice::dispatch($request, $salesInvoice);

            return redirect()->route('sales-invoices.index')->with('success', __('The sales invoice details are updated successfully.'));
        }
        else{
            return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('delete-sales-invoices')){
            if ($salesInvoice->status === 'posted') {
                return back()->withErrors(['error' => __('Cannot delete posted invoice.')]);
            }

            // Dispatch event before deletion
            DestroySalesInvoice::dispatch($salesInvoice);

            $salesInvoice->delete();

            return redirect()->route('sales-invoices.index')->with('success', __('The sales invoice has been deleted.'));
        }
        else{
            return redirect()->route('sales-invoices.index')->with('error', __('Permission denied'));
        }
    }

    private function calculateTotals($items)
    {
        $subtotal = 0;
        $totalTax = 0;
        $totalDiscount = 0;

        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $discountAmount = ($lineTotal * ($item['discount_percentage'] ?? 0)) / 100;
            $afterDiscount = $lineTotal - $discountAmount;
            $taxAmount = ($afterDiscount * ($item['tax_percentage'] ?? 0)) / 100;

            $subtotal += $lineTotal;
            $totalDiscount += $discountAmount;
            $totalTax += $taxAmount;
        }

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $totalTax,
            'discount_amount' => $totalDiscount,
            'total_amount' => $subtotal + $totalTax - $totalDiscount
        ];
    }

    private function createInvoiceItems($invoiceId, $items)
    {
        foreach ($items as $itemData) {
            $item = new SalesInvoiceItem();
            $item->invoice_id = $invoiceId;
            $item->product_id = $itemData['product_id'];
            $item->quantity = $itemData['quantity'];
            $item->unit_price = $itemData['unit_price'];
            $item->discount_percentage = $itemData['discount_percentage'] ?? 0;
            $item->tax_percentage = $itemData['tax_percentage'] ?? 0;
            $item->save();

            // Store individual taxes
            if (isset($itemData['taxes']) && is_array($itemData['taxes'])) {
                foreach ($itemData['taxes'] as $tax) {
                    $salesInvoiceItemTax = new SalesInvoiceItemTax();
                    $salesInvoiceItemTax->item_id = $item->id;
                    $salesInvoiceItemTax->tax_name = $tax['tax_name'];
                    $salesInvoiceItemTax->tax_rate = $tax['tax_rate'] ?? $tax['rate'] ?? 0;
                    $salesInvoiceItemTax->save();
                }
            }
        }
    }

    private function activeTemplates(string $type)
    {
        app(DocumentTemplateService::class)->ensureDefault(creatorId(), $type);

        return DocumentTemplate::query()
            ->forCompany(creatorId())
            ->forType($type)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
    }

    public function post(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('post-sales-invoices')){
        if ($salesInvoice->status !== 'draft') {
            return back()->withErrors(['error' => __('Only draft invoices can be posted.')]);
        }

        try {
            PostSalesInvoice::dispatch($salesInvoice);
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }

        $salesInvoice->update(['status' => 'posted']);

        return back()->with('success', __('The sales invoice has been posted successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function getWarehouseProducts(Request $request)
    {
        if(Auth::user()->can('create-sales-invoices') || Auth::user()->can('edit-sales-invoices')){
            $validated = $request->validate([
                'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            ]);
            $warehouseId = $validated['warehouse_id'] ?? null;

            $productsQuery = ProductServiceItem::select('id', 'name', 'sku', 'description', 'sale_price', 'tax_ids', 'unit', 'type')
                ->where('is_active', true)
                ->where('created_by', creatorId());

            if ($warehouseId) {
                $productsQuery
                    ->whereHas('warehouseStocks', function($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId)
                      ->where('quantity', '>', 0);
                })
                    ->with(['warehouseStocks' => function($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }]);
            }

            $products = $productsQuery
                ->get()
                ->map(function ($product) use ($warehouseId) {
                    $productData = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'description' => $product->description,
                        'sale_price' => $product->sale_price,
                        'unit' => $product->unit,
                        'type' => $product->type,
                        'taxes' => $product->taxes->map(function ($tax) {
                            return [
                                'id' => $tax->id,
                                'tax_name' => $tax->tax_name,
                                'rate' => $tax->rate
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
        }
        else{
            return response()->json([], 403);
        }
    }

    public function getServices(Request $request)
    {
        if(Auth::user()->can('create-sales-invoices') || Auth::user()->can('edit-sales-invoices')){
            $services = ProductServiceItem::select('id', 'name', 'sku', 'description', 'sale_price', 'tax_ids', 'unit', 'type')
                ->where('is_active', true)
                ->where('type', 'service')
                ->where('created_by', creatorId())
                ->get()
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'sku' => $service->sku,
                        'description' => $service->description,
                        'sale_price' => $service->sale_price,
                        'unit' => $service->unit,
                        'type' => $service->type,
                        'taxes' => $service->taxes->map(function ($tax) {
                            return [
                                'id' => $tax->id,
                                'tax_name' => $tax->tax_name,
                                'rate' => $tax->rate
                            ];
                        })
                    ];
                });
            return response()->json($services);
        }
        else{
            return response()->json([], 403);
        }
    }

    public function print(SalesInvoice $salesInvoice)
    {
        if(Auth::user()->can('print-sales-invoices')){
            $salesInvoice->load(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse']);
            $templateService = app(DocumentTemplateService::class);
            $template = $templateService->resolveForDocument(
                DocumentTemplate::TYPE_INVOICE,
                creatorId(),
                $salesInvoice->document_template_id
            );

            return Inertia::render('Sales/Print', [
                'invoice' => $salesInvoice,
                'documentTemplate' => $template,
                'templateDocument' => $templateService->documentFromModel(DocumentTemplate::TYPE_INVOICE, $salesInvoice, $template),
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
