<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\VideoProduction\Http\Requests\UpdateProductionSettingsRequest;
use Workdo\VideoProduction\Models\ProductionSetting;
use Workdo\Taskly\Models\Project;

class ProductionSettingsController extends Controller
{
    public function update(UpdateProductionSettingsRequest $request, Project $project)
    {
        abort_unless(Auth::user()->can('manage-video-production-settings') && $project->created_by === creatorId(), 403);
        ProductionSetting::forProject(creatorId(), $project->id)->update($request->validated());

        return back()->with('success', __('Video production settings updated successfully.'));
    }
}
