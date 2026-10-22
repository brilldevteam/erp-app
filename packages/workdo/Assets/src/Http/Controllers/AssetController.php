<?php

namespace Workdo\Assets\Http\Controllers;

use Workdo\Assets\Models\Asset;
use Workdo\Assets\Http\Requests\StoreAssetRequest;
use Workdo\Assets\Http\Requests\UpdateAssetRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Assets\Models\AssetsCategory;
use Workdo\Assets\Models\Category;
use Illuminate\Http\Request;
use Workdo\Assets\Events\CreateAsset;
use Workdo\Assets\Events\DestroyAsset;
use Workdo\Assets\Events\UpdateAsset;
use Workdo\Assets\Models\AssetLocation;

class AssetController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-assets')){
            $assets = Asset::query()
                ->with(['category', 'location'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-assets')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-assets')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), function($q) {
                    $search = '%' . request('name') . '%';
                    $q->where(function($query) use ($search) {
                        $query->where('name', 'like', $search)
                            ->orWhere('serial_code', 'like', $search)
                            ->orWhereHas('location', fn($location) => $location->where('name', 'like', $search))
                            ->orWhereHas('category', fn($category) => $category->where('name', 'like', $search));
                    });
                })
                ->when(request('category_id') && request('category_id') !== 'all', fn($q) => $q->where('category_id', request('category_id')))
                ->tap(function($q) {
                    // Sort only by known columns; the id tie-breaker keeps pages stable when values are equal
                    // (e.g. many assets imported in the same second).
                    $sortable = [
                        'name' => 'name',
                        'purchase_date' => 'purchase_date',
                        'serial_code' => 'serial_code',
                        'quantity' => 'quantity',
                        'unit_price' => 'unit_price',
                        'purchase_cost' => 'purchase_cost',
                        'category.name' => AssetsCategory::select('name')->whereColumn('assets_categories.id', 'assets.category_id'),
                    ];
                    $direction = request('direction') === 'desc' ? 'desc' : 'asc';
                    if (isset($sortable[request('sort')])) {
                        $q->orderBy($sortable[request('sort')], $direction)->orderBy('id', $direction);
                    } else {
                        $q->orderByDesc('purchase_date')->orderByDesc('id');
                    }
                })
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('Assets/Assets/Index', [
                'assets' => $assets,
                'categories' => AssetsCategory::where('created_by', creatorId())->select('id', 'name')->get(),
                'locations' => AssetLocation::where('created_by', creatorId())->where('is_active', true)->select('id', 'name', 'code')->get(),
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreAssetRequest $request)
    {
        if(Auth::user()->can('create-assets')){
            $validated = $request->validated();

            $asset = new Asset();
            $asset->name = $validated['name'];
            $asset->purchase_date = $validated['purchase_date'];
            $asset->supported_date = $validated['supported_date'];
            $asset->serial_code = $validated['serial_code'];
            $asset->quantity = $validated['quantity'];
            $asset->unit_price = $validated['unit_price'];
            $asset->purchase_cost = $validated['purchase_cost'];
            $asset->warranty_period = $validated['warranty_period'];            
            $asset->location_id = $validated['location_id'];
            $asset->description = $validated['description'];
            $asset->image = $validated['image'] ? basename($validated['image']) : null;
            $asset->category_id = $validated['category_id'];

            $asset->creator_id = Auth::id();
            $asset->created_by = creatorId();
            $asset->save();

            CreateAsset::dispatch($request, $asset);

            return redirect()->route('assets.assets.index')->with('success', __('The asset has been created successfully.'));
        }
        else{
            return redirect()->route('assets.assets.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        if(Auth::user()->can('edit-assets')){
            $validated = $request->validated();

            $asset->name = $validated['name'];
            $asset->purchase_date = $validated['purchase_date'];
            $asset->supported_date = $validated['supported_date'];
            $asset->serial_code = $validated['serial_code'];
            $asset->quantity = $validated['quantity'];
            $asset->unit_price = $validated['unit_price'];
            $asset->purchase_cost = $validated['purchase_cost'];
            $asset->warranty_period = $validated['warranty_period'];            
            $asset->location_id = $validated['location_id'];
            $asset->description = $validated['description'];
            $asset->image = $validated['image'] ? basename($validated['image']) : null;
            $asset->category_id = $validated['category_id'];

            $asset->save();

            UpdateAsset::dispatch($request, $asset);

            return redirect()->back()->with('success', __('The asset details are updated successfully.'));
        }
        else{
            return redirect()->route('assets.assets.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(Asset $asset)
    {
        if(Auth::user()->can('delete-assets')){

            DestroyAsset::dispatch($asset);

            $asset->delete();

            return redirect()->back()->with('success', __('The asset has been deleted.'));
        }
        else{
            return redirect()->route('assets.assets.index')->with('error', __('Permission denied'));
        }
    }
}
