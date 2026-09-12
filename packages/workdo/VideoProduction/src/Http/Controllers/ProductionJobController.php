<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\VideoProduction\Http\Requests\StoreProductionJobRequest;
use Workdo\VideoProduction\Http\Requests\UpdateProductionJobRequest;
use Workdo\VideoProduction\Models\ProductionJob;

class ProductionJobController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('manage-video-production'), 403);

        $jobs = ProductionJob::query()
            ->forCompany()
            ->when(! Auth::user()->can('manage-any-video-production-job'), fn ($query) => $query->where('creator_id', Auth::id()))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($search) use ($request) {
                $term = $request->string('search');
                $search->where('name', 'like', '%'.$term.'%')->orWhere('reference', 'like', '%'.$term.'%');
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate($request->integer('per_page', 10))->withQueryString();

        return Inertia::render('VideoProduction/Jobs/Index', [
            'jobs' => $jobs,
            'nextJobReference' => ProductionJob::nextReference(),
            'statuses' => ProductionJob::STATUSES,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function store(StoreProductionJobRequest $request)
    {
        $data = $request->safe()->except(['reference']);
        ProductionJob::create($data + [
            'reference' => ProductionJob::nextReference(),
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Production job created successfully.'));
    }

    public function update(UpdateProductionJobRequest $request, ProductionJob $job)
    {
        $this->ensureCompanyOwnership($job);
        $job->update($request->validated());

        return back()->with('success', __('Production job updated successfully.'));
    }

    public function destroy(ProductionJob $job)
    {
        abort_unless(Auth::user()->can('delete-video-production-job'), 403);
        $this->ensureCompanyOwnership($job);
        $job->delete();

        return back()->with('success', __('Production job deleted successfully.'));
    }

    private function ensureCompanyOwnership(ProductionJob $job): void
    {
        abort_unless((int) $job->created_by === (int) creatorId(), 404);
        if (! Auth::user()->can('manage-any-video-production-job')) {
            abort_unless((int) $job->creator_id === (int) Auth::id(), 403);
        }
    }
}
