<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\VideoProduction\Models\ProductionRecord;
use Workdo\VideoProduction\Models\ProductionSetting;
use Workdo\Taskly\Models\Project;

class DashboardController extends Controller
{
    public function portal()
    {
        if (Auth::user()->type !== 'client') {
            return redirect()->route('project.index');
        }

        return redirect()->route('video-production.client.overview');
    }

    public function overview()
    {
        return $this->clientPortal('overview');
    }

    public function shootingLog()
    {
        return $this->clientPortal('shoot');
    }

    public function deliverables()
    {
        return $this->clientPortal('deliverable');
    }

    public function index(Project $project)
    {
        if (
            Auth::user()->type === 'client'
            && ! $project->clients()->where('users.id', Auth::id())->exists()
        ) {
            abort(403, __('You do not have access to this production project.'));
        }

        if (Auth::user()->type === 'client' && Auth::user()->hasRole('production-client')) {
            return redirect()->route('video-production.client.overview');
        }

        return $this->renderProject($project);
    }

    private function clientPortal(string $activeSection)
    {
        $user = Auth::user();
        abort_unless($user->type === 'client' && $user->hasRole('production-client'), 403);
        abort_unless(
            $activeSection === 'overview'
                ? $user->can('view-video-production-dashboard')
                : $user->can('view-video-production'),
            403
        );

        $projects = Project::query()
            ->where('created_by', creatorId())
            ->where('category', 'production')
            ->whereHas('clients', fn ($query) => $query->where('users.id', $user->id))
            ->limit(2)
            ->get();

        if ($projects->isEmpty()) {
            return redirect()->route('profile.edit')->with('error', __('No production project is assigned to this account.'));
        }

        abort_if($projects->count() > 1, 409, __('This production client is assigned to more than one project.'));

        return $this->renderProject($projects->first(), $activeSection);
    }

    private function renderProject(Project $project, ?string $activeSection = null)
    {

        if ($this->canViewProject($project)) {
            $canEdit = Auth::user()->can('manage-video-production');
            $canViewProduction = $canEdit || Auth::user()->can('view-video-production');
            $canViewDashboard = Auth::user()->can('view-video-production-dashboard');
            $settings = ProductionSetting::forProject(creatorId(), $project->id);
            $range = request('range') === 'monthly' ? 'monthly' : 'all';
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) request('month'))
                ? request('month')
                : now()->format('Y-m');
            $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $records = ProductionRecord::query()
                ->forCompany()
                ->where('project_id', $project->id)
                ->whereIn('type', self::recordTypes())
                ->latest('recorded_at')->latest('id')->get()->groupBy('type');
            $overviewRecords = $range === 'monthly'
                ? $records->map(fn ($items) => $items->filter(fn ($item) => $item->recorded_at?->betweenIncluded($monthStart, $monthEnd)))
                : $records;
            $shoots = $overviewRecords->get('shoot', collect());
            $deliverables = $overviewRecords->get('deliverable', collect());

            return Inertia::render('VideoProduction/Dashboard', [
                'companyName' => company_setting('company_name', creatorId()) ?: Auth::user()->name,
                'project' => $project->only(['id', 'name']),
                'isProductionClient' => Auth::user()->type === 'client' && Auth::user()->hasRole('production-client'),
                'activeSection' => $activeSection,
                'canEdit' => $canEdit,
                'canViewProduction' => $canViewProduction,
                'canViewDashboard' => $canViewDashboard,
                'canManageSettings' => Auth::user()->type !== 'client' && Auth::user()->can('manage-video-production-settings'),
                'unassignedRecordCount' => Auth::user()->can('manage-video-production')
                    ? ProductionRecord::query()->forCompany()->whereNull('project_id')->count()
                    : 0,
                'settings' => $settings,
                'range' => $range,
                'month' => $month,
                'records' => $canViewProduction
                    ? collect(self::recordTypes())->mapWithKeys(fn ($type) => [$type => ($records->get($type, collect()))->values()])
                    : collect(),
                'nextRecordKeys' => collect(self::recordTypes())->mapWithKeys(fn ($type) => [
                    $type => ProductionRecord::nextKey(creatorId(), $type, $project->id, $project->name),
                ]),
                'productionMetrics' => $canViewDashboard ? [
                    'shoots' => $shoots->count(),
                    'planned_shoots' => $shoots->where('data.shoot_type', 'Planned')->count(),
                    'urgent_shoots' => $shoots->filter(fn ($shoot) => in_array(data_get($shoot->data, 'shoot_type'), ['Unplanned', 'Urgent'], true))->count(),
                    'shooting_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'total_hours', 0)), 2),
                    'extra_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'extra_hours', 0)), 2),
                    'reels_delivered' => $deliverables
                        ->where('data.content_type', 'Reel')
                        ->whereNotNull('data.final_delivery_date')
                        ->sum(fn ($item) => max(1, (int) data_get($item->data, 'episodes_reels_count', 1))),
                    'static_delivered' => $deliverables->where('data.content_type', 'Static')->whereNotNull('data.final_delivery_date')->count(),
                    'waiting_for_client' => $deliverables->where('status', 'Waiting for DOC')->count(),
                    'work_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'total_hours', 0)), 2),
                ] : [],
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    private static function recordTypes(): array
    {
        return ['shoot', 'deliverable'];
    }

    public function claimUnassigned(Project $project)
    {
        abort_unless(Auth::user()->can('manage-video-production') && $project->created_by === creatorId(), 403);
        $count = ProductionRecord::query()->forCompany()->whereNull('project_id')->update(['project_id' => $project->id]);

        return back()->with('success', trans_choice(':count existing production record was moved to this project.|:count existing production records were moved to this project.', $count, ['count' => $count]));
    }

    private function canViewProject(Project $project): bool
    {
        $user = Auth::user();
        if (
            (
                ! $user->can('view-video-production-dashboard')
                && ! $user->can('view-video-production')
                && ! $user->can('manage-video-production')
                && ! $user->can('manage-video-production-settings')
            )
            || $project->category !== 'production'
            || $project->created_by !== creatorId()
        ) {
            return false;
        }

        if ($user->type === 'client' && ! $project->clients()->where('users.id', $user->id)->exists()) {
            return false;
        }

        return true;
    }
}
