<?php

namespace Workdo\VideoProduction\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\VideoProduction\Http\Requests\UpdateProductionSettingsRequest;
use Workdo\VideoProduction\Models\ProductionSetting;

class ProductionSettingsController extends Controller
{
    public function edit()
    {
        abort_unless(Auth::user()->can('manage-video-production-settings'), 403);

        return Inertia::render('VideoProduction/Settings/Edit', ['settings' => ProductionSetting::forCompany(creatorId())]);
    }

    public function update(UpdateProductionSettingsRequest $request)
    {
        ProductionSetting::forCompany(creatorId())->update($request->validated());

        return back()->with('success', __('Video production settings updated successfully.'));
    }
}
