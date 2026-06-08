<?php

namespace Workdo\PettyCashManagement\Http\Controllers;

use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Http\Requests\StorePettyCashCategoryRequest;
use Workdo\PettyCashManagement\Http\Requests\UpdatePettyCashCategoryRequest;
use Workdo\PettyCashManagement\Events\CreatePettyCashCategory;
use Workdo\PettyCashManagement\Events\UpdatePettyCashCategory;
use Workdo\PettyCashManagement\Events\DestroyPettyCashCategory;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;


class PettyCashCategoryController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-petty-cash-categories')){
            $pettycashcategories = PettyCashCategory::query()
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-petty-cash-categories')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-petty-cash-categories')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('name'), function($q) {
                    $q->where('name', 'like', '%' . request('name') . '%');
                })
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            return Inertia::render('PettyCashManagement/PettyCashCategories/Index', [
                'pettycashcategories' => $pettycashcategories,

            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StorePettyCashCategoryRequest $request)
    {
        if(Auth::user()->can('create-petty-cash-categories')){
            $validated = $request->validated();

            $pettycashcategory       = new PettyCashCategory();
            $pettycashcategory->name = $validated['name'];

            $pettycashcategory->creator_id = Auth::id();
            $pettycashcategory->created_by = creatorId();
            $pettycashcategory->save();

            CreatePettyCashCategory::dispatch($request, $pettycashcategory);

            return redirect()->route('petty-cash-management.petty-cash-categories.index')->with('success', __('The category has been created successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdatePettyCashCategoryRequest $request, PettyCashCategory $pettycashcategory)
    {
        if(Auth::user()->can('edit-petty-cash-categories')){
            $validated = $request->validated();

            $pettycashcategory->name = $validated['name'];
            $pettycashcategory->save();

            UpdatePettyCashCategory::dispatch($request, $pettycashcategory);

            return redirect()->back()->with('success', __('The category details are updated successfully.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-categories.index')->with('error', __('Permission denied'));
        }
    }

    public function destroy(PettyCashCategory $pettycashcategory)
    {
        if(Auth::user()->can('delete-petty-cash-categories')){
            DestroyPettyCashCategory::dispatch($pettycashcategory);
            
            $pettycashcategory->delete();

            return redirect()->back()->with('success', __('The category has been deleted.'));
        }
        else{
            return redirect()->route('petty-cash-management.petty-cash-categories.index')->with('error', __('Permission denied'));
        }
    }
}
