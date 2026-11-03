<?php

namespace Workdo\ProductService\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\ProductService\Events\CreateProductServiceItem;
use Workdo\ProductService\Events\DestroyProductServiceItem;
use Workdo\ProductService\Events\UpdateProductServiceItem;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\WarehouseStock;
use Workdo\ProductService\Http\Requests\StoreProductServiceItemRequest;
use Workdo\ProductService\Http\Requests\UpdateProductServiceItemRequest;
use Workdo\ProductService\Models\ProductServiceUnit;

class ProductServiceItemController extends Controller
{
    public function index(Request $request)
    {
        $catalogType = $this->catalogType($request);
        if($this->canCatalog('manage', $catalogType)){
            $items = ProductServiceItem::select('id', 'name', 'sku', 'sale_price', 'purchase_price', 'tax_ids', 'category_id', 'unit', 'type', 'image', 'description', 'long_description', 'created_at')
                ->with(['category:id,name', 'unitRelation:id,unit_name', 'warehouseStocks:product_id,quantity'])
                ->where(function($q) use ($catalogType) {
                    if($this->canCatalog('manage-any', $catalogType)) {
                        $q->where('created_by', creatorId());
                    } elseif($this->canCatalog('manage-own', $catalogType)) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when($catalogType === 'service', fn($q) => $q->where('type', 'service'))
                ->when($catalogType === 'product', fn($q) => $q->where('type', '!=', 'service'))
                ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
                ->when(request('type'), fn($q) => $q->where('type', request('type')))
                ->when(request('category_id'), fn($q) => $q->where('category_id', request('category_id')))
                ->when(request('is_active') !== null, fn($q) => $q->where('is_active', request('is_active')))
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            // Add total quantity for each item
            $items->getCollection()->transform(function ($item) {
                $item->total_quantity = $item->warehouseStocks->sum('quantity');
                return $item;
            });

            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);

            return Inertia::render('ProductService/Items/Index', [
                'items' => $items,
                'categories' => $categories,
                'catalogType' => $catalogType,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function create(Request $request)
    {
        $catalogType = $this->catalogType($request);
        if($this->canCatalog('create', $catalogType)){
            $taxes = ProductServiceTax::where('created_by', creatorId())->get(['id', 'tax_name', 'rate']);
            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);
            $units = ProductServiceUnit::where('created_by', creatorId())->get(['id', 'unit_name']);
            $warehouses = Warehouse::where('is_active', true)->where('created_by', creatorId())->get(['id', 'name']);

            return Inertia::render('ProductService/Items/Create', [
                'taxes' => $taxes,
                'categories' => $categories,
                'units' => $units,
                'warehouses' => $warehouses,
                'catalogType' => $catalogType,
            ]);
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function store(StoreProductServiceItemRequest $request)
    {
        $catalogType = $this->catalogType($request);
        if($this->canCatalog('create', $catalogType)){
            $validated = $request->validated();
            $item = new ProductServiceItem();
            $item->name = $validated['name'];
            $item->sku = $validated['sku'];
            $item->tax_ids = (!empty($validated['tax_ids'])) ? array_map('intval', $validated['tax_ids']) : null;
            $item->category_id = $validated['category_id'] === 'none' ? null : $validated['category_id'];
            $item->description = $validated['description'] ?? null;
            $item->long_description = $validated['long_description'] ?? null;
            $item->sale_price = $validated['sale_price'];
            $item->purchase_price = $validated['purchase_price'];
            $item->unit = ($validated['unit'] ?? null) === 'none' ? null : ($validated['unit'] ?? null);
            $item->type = $catalogType ?: ($validated['type'] ?? 'product');
            $item->creator_id = Auth::id();
            $item->created_by = creatorId();

            // Handle image path from media library
            if (!empty($validated['image'])) {
                $item->image = basename($validated['image']);
            }

            // Handle multiple images
            if (!empty($validated['images'])) {
                $imageNames = array_map('basename', $validated['images']);
                $item->images = json_encode($imageNames);
            }

            $item->save();

            // Products always need a stock location, even when the warehouse field is omitted.
            $warehouseId = $this->resolveStockWarehouseId($validated['warehouse_id'] ?? null, $item->type);
            if ($warehouseId && isset($validated['quantity'])) {
                WarehouseStock::create([
                    'product_id' => $item->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $validated['quantity'] ?? 0,
                ]);
            }

            // Dispatch event for packages to handle their fields
            CreateProductServiceItem::dispatch($request, $item);

            if ($request->expectsJson()) {
                $item->load(['category:id,name', 'unitRelation:id,unit_name', 'warehouseStocks:product_id,quantity']);

                return response()->json([
                    'message' => __('The item has been created successfully.'),
                    'product' => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'description' => \App\Services\SalesLineAmounts::description($item->long_description ?: $item->description),
                        'sale_price' => (float) $item->sale_price,
                        'unit' => $item->unit,
                        'unit_name' => $item->unitRelation?->unit_name,
                        'type' => $item->type,
                        'category_id' => $item->category_id,
                        'category_name' => $item->category?->name,
                        'stock_quantity' => $item->type === 'service' ? null : $item->warehouseStocks->sum('quantity'),
                        'taxes' => $item->taxes->map(fn ($tax) => [
                            'id' => $tax->id,
                            'tax_name' => $tax->tax_name,
                            'rate' => (float) $tax->rate,
                        ])->values(),
                    ],
                ], 201);
            }

            return redirect()->route($this->catalogRoute('index', $item->type))->with('success', __('The item has been created successfully.'));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function show(Request $request, ProductServiceItem $item)
    {
        $catalogType = $this->itemCatalogType($item);
        if($this->routeMatchesItem($request, $catalogType) && $this->canCatalog('view', $catalogType)){
            $item->load(['category', 'unitRelation', 'warehouseStocks.warehouse:id,name']);

            // Load taxes if tax_ids exist
            $taxes = [];
            if ($item->tax_ids) {
                $taxIds = $item->tax_ids;
                if (!empty($taxIds) && is_array($taxIds) && count($taxIds) > 0) {
                    $taxes = ProductServiceTax::whereIn('id', $taxIds)
                        ->where('created_by', creatorId())
                        ->get(['id', 'tax_name', 'rate'])
                        ->toArray();
                }
            }

            $itemData = $item->toArray();
            $itemData['taxes'] = $taxes;
            $itemData['total_quantity'] = $item->warehouseStocks->sum('quantity');
            $itemData['warehouse_stocks'] = $item->warehouseStocks->map(function($stock) {
                return [
                    'warehouse_name' => $stock->warehouse->name,
                    'quantity' => $stock->quantity
                ];
            });

            return Inertia::render('ProductService/Items/Show', [
                'item' => $itemData,
                'catalogType' => $catalogType,
            ]);
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    public function edit(Request $request, ProductServiceItem $item)
    {
        $catalogType = $this->itemCatalogType($item);
        if($this->routeMatchesItem($request, $catalogType) && $this->canCatalog('edit', $catalogType)){
            $taxes = ProductServiceTax::where('created_by', creatorId())->get(['id', 'tax_name', 'rate']);
            $categories = ProductServiceCategory::where('created_by', creatorId())->get(['id', 'name']);
            $units = ProductServiceUnit::where('created_by', creatorId())->get(['id', 'unit_name']);
            $warehouses = Warehouse::where('is_active', true)->where('created_by', creatorId())->get(['id', 'name']);

            // Load the item with all necessary fields
            $item->load(['category', 'unitRelation', 'warehouseStocks:product_id,warehouse_id,quantity']);
            $firstStock = $item->warehouseStocks->first();
            $item->setAttribute('warehouse_id', $firstStock?->warehouse_id);
            $item->setAttribute('quantity', $firstStock?->quantity ?? 0);

            return Inertia::render('ProductService/Items/Edit', [
                'item' => $item,
                'taxes' => $taxes,
                'categories' => $categories,
                'units' => $units,
                'warehouses' => $warehouses,
                'catalogType' => $catalogType,
            ]);
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }



    public function update(UpdateProductServiceItemRequest $request, ProductServiceItem $item)
    {
        $catalogType = $this->itemCatalogType($item);
        if($this->routeMatchesItem($request, $catalogType) && $this->canCatalog('edit', $catalogType)){
            $validated = $request->validated();

            $item->name = $validated['name'];
            $item->sku = $validated['sku'];
            $item->tax_ids = (!empty($validated['tax_ids'])) ? array_map('intval', $validated['tax_ids']) : null;
            $item->category_id = $validated['category_id'] === 'none' ? null : $validated['category_id'];
            $item->description = $validated['description'];
            $item->long_description = $validated['long_description'] ?? null;
            $item->sale_price = $validated['sale_price'];
            $item->purchase_price = $validated['purchase_price'];
            $item->unit = ($validated['unit'] ?? null) === 'none' ? null : ($validated['unit'] ?? null);
            $item->type = $item->type === 'part' && $catalogType === 'product'
                ? 'part'
                : $catalogType;

            // Handle image path from media library
            if (isset($validated['image'])) {
                $item->image = !empty($validated['image']) ? basename($validated['image']) : null;
            }

            // Handle multiple images
            if (isset($validated['images'])) {
                if (!empty($validated['images'])) {
                    $imageNames = array_map('basename', $validated['images']);
                    $item->images = json_encode($imageNames);
                } else {
                    $item->images = null;
                }
            }

            $item->save();

            // Use the selected warehouse, or the company's default active warehouse when omitted.
            $warehouseId = $this->resolveStockWarehouseId($validated['warehouse_id'] ?? null, $item->type);

            if ($item->type !== 'service' && $warehouseId && isset($validated['quantity'])) {
                WarehouseStock::updateOrCreate(
                    [
                        'product_id' => $item->id,
                        'warehouse_id' => $warehouseId,
                    ],
                    [
                        'quantity' => $validated['quantity'],
                    ]
                );
            }

            // Dispatch event for packages to handle their fields
            UpdateProductServiceItem::dispatch($request, $item);

            return redirect()->route($this->catalogRoute('index', $catalogType))->with('success', __('The item details are updated successfully.'));
        }
        return redirect()->route('product-service.items.index')->with('error', __('Permission denied'));
    }

    private function resolveStockWarehouseId(mixed $warehouseId, ?string $itemType): ?int
    {
        if ($itemType === 'service') {
            return null;
        }

        if (!empty($warehouseId) && $warehouseId !== 'none') {
            return (int) $warehouseId;
        }

        $existingWarehouseId = Warehouse::where('created_by', creatorId())
            ->where('is_active', true)
            ->value('id');

        if ($existingWarehouseId) {
            return (int) $existingWarehouseId;
        }

        $warehouse = Warehouse::create([
            'name' => __('Main Warehouse'),
            'address' => company_setting('company_address', creatorId()) ?: '-',
            'city' => company_setting('company_city', creatorId()) ?: '-',
            'zip_code' => company_setting('company_zipcode', creatorId()) ?: '-',
            'phone' => company_setting('company_telephone', creatorId()) ?: null,
            'email' => company_setting('company_email', creatorId()) ?: null,
            'is_active' => true,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return (int) $warehouse->id;
    }

    public function destroy(Request $request, ProductServiceItem $item)
    {
        $catalogType = $this->itemCatalogType($item);
        if($this->routeMatchesItem($request, $catalogType) && $this->canCatalog('delete', $catalogType)){
            if ($item->image) {
                delete_file($item->image);
            }

            if ($item->images) {
                $images = json_decode($item->images, true);
                if (is_array($images)) {
                    foreach ($images as $image) {
                        delete_file($image);
                    }
                }
            }

            WarehouseStock::where('product_id', $item->id)->delete();

            DestroyProductServiceItem::dispatch($item);
            $item->delete();
            return back()->with('success', __('The item has been deleted.'));
        }
        return back()->with('error', __('Permission denied'));
    }

    private function catalogType(Request $request): ?string
    {
        $type = $request->route('catalogType');

        return in_array($type, ['product', 'service'], true) ? $type : null;
    }

    private function itemCatalogType(ProductServiceItem $item): string
    {
        return $item->type === 'service' ? 'service' : 'product';
    }

    private function canCatalog(string $action, ?string $catalogType): bool
    {
        if ($catalogType) {
            return Auth::user()->can($action.'-'.($catalogType === 'service' ? 'services' : 'products'));
        }

        return Auth::user()->can($action.'-product-service-item');
    }

    private function routeMatchesItem(Request $request, string $catalogType): bool
    {
        $routeType = $this->catalogType($request);

        return $routeType === null || $routeType === $catalogType;
    }

    private function catalogRoute(string $action, string $catalogType): string
    {
        return 'product-service.'.($catalogType === 'service' ? 'services' : 'products').'.'.$action;
    }

    public function stockIndex()
    {
        if(Auth::user()->can('manage-stock')){
            $stocks = ProductServiceItem::select('id', 'name', 'sku')
                ->with(['warehouseStocks:product_id,warehouse_id,quantity'])
                ->where('created_by', creatorId())
                ->where('type', '!=', 'service')
                ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
                ->when(request('sku'), fn($q) => $q->where('sku', 'like', '%' . request('sku') . '%'))
                ->latest()
                ->paginate(request('per_page', 10))
                ->withQueryString();

            // Add total quantity for each item
            $stocks->getCollection()->transform(function ($item) {
                $item->total_quantity = $item->warehouseStocks->sum('quantity');
                return $item;
            });

            $warehouses = \App\Models\Warehouse::where('created_by', creatorId())->where('is_active', true)->get(['id', 'name']);

            return Inertia::render('ProductService/Stock/Index', [
                'stocks' => $stocks,
                'warehouses' => $warehouses,
            ]);
        }
        return back()->with('error', __('Permission denied'));
    }

    public function apiIndex()
    {
        if(Auth::user()->can('manage-product-service-item') || Auth::user()->can('manage-any-product-service-item') || Auth::user()->can('manage-own-product-service-item')){
            try {
                $query = ProductServiceItem::query();

                if(Auth::user()->can('manage-any-product-service-item')) {
                   $query->where('created_by', creatorId());
                } elseif(Auth::user()->can('manage-own-product-service-item')) {
                    $query->where('creator_id', Auth::id());
                }
                $items = $query->get(['id', 'name', 'sku', 'sale_price', 'tax_ids', 'description']);

                $transformedItems = $items->map(function ($item) {
                    $taxes = [];
                    if ($item->tax_ids) {
                        $taxIds = is_string($item->tax_ids) ? json_decode($item->tax_ids, true) : $item->tax_ids;
                        if (!empty($taxIds) && is_array($taxIds)) {
                            $taxes = ProductServiceTax::whereIn('id', $taxIds)
                                ->get(['id', 'tax_name', 'rate'])
                                ->toArray();
                        }
                    }

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'sale_price' => $item->sale_price,
                        'description' => $item->description,
                        'taxes' => $taxes
                    ];
                });

                return response()->json($transformedItems);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Failed to fetch products'], 500);
            }
        }
        return response()->json(['error' => 'Permission denied'], 403);
    }

    public function stockStore(Request $request)
    {
        if(Auth::user()->can('create-stock')){
            $request->validate([
                'product_id' => 'required|exists:product_service_items,id',
                'warehouse_id' => 'required|exists:warehouses,id',
                'quantity' => 'required|numeric|min:0'
            ]);

            $existingStock = WarehouseStock::where('product_id', $request->product_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->first();

            if ($existingStock) {
                $existingStock->increment('quantity', $request->quantity);
            } else {
                WarehouseStock::create([
                    'product_id' => $request->product_id,
                    'warehouse_id' => $request->warehouse_id,
                    'quantity' => $request->quantity,
                ]);
            }

            return back()->with('success', __('Stock entry created successfully.'));
        }
        return back()->with('error', __('Permission denied'));
    }
}
