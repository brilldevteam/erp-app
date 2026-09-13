<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\VideoProduction\Models\ProductionRecord;
use Workdo\VideoProduction\Models\ProductionSetting;

class DashboardController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage-video-production')) {
            $settings = ProductionSetting::forCompany(creatorId());
            $month = request('month', now()->format('Y-m'));
            $monthStart = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $records = ProductionRecord::query()
                ->forCompany()
                ->whereIn('type', self::recordTypes())
                ->latest('recorded_at')->latest('id')->get()->groupBy('type');
            $monthly = $records->map(fn ($items) => $items->filter(fn ($item) => $item->recorded_at?->betweenIncluded($monthStart, $monthEnd)));
            $shoots = $monthly->get('shoot', collect());
            $deliverables = $monthly->get('deliverable', collect());

            return Inertia::render('VideoProduction/Dashboard', [
                'companyName' => company_setting('company_name', creatorId()) ?: Auth::user()->name,
                'settings' => $settings,
                'month' => $month,
                'records' => collect(self::recordTypes())->mapWithKeys(fn ($type) => [$type => ($records->get($type, collect()))->values()]),
                'nextRecordKeys' => collect(self::recordTypes())->mapWithKeys(fn ($type) => [
                    $type => ProductionRecord::nextKey(creatorId(), $type),
                ]),
                'productionMetrics' => [
                    'shoots' => $shoots->count(),
                    'planned_shoots' => $shoots->where('data.shoot_type', 'Planned')->count(),
                    'urgent_shoots' => $shoots->filter(fn ($shoot) => in_array(data_get($shoot->data, 'shoot_type'), ['Unplanned', 'Urgent'], true))->count(),
                    'shooting_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'total_hours', 0)), 2),
                    'extra_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'extra_hours', 0)), 2),
                    'reels_delivered' => $deliverables->where('data.content_type', 'Reel')->whereNotNull('data.final_delivery_date')->count(),
                    'static_delivered' => $deliverables->where('data.content_type', 'Static')->whereNotNull('data.final_delivery_date')->count(),
                    'waiting_for_client' => ($records->get('deliverable', collect()))->where('status', 'Waiting for DOC')->count(),
                    'work_hours' => round($shoots->sum(fn ($item) => (float) data_get($item->data, 'total_hours', 0)), 2),
                ],
            ]);
        }

        return back()->with('error', __('Permission denied'));
    }

    private static function recordTypes(): array
    {
        return ['shoot', 'deliverable'];
    }
}
