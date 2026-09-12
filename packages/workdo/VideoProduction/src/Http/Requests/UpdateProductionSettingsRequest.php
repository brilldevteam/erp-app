<?php

namespace Workdo\VideoProduction\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-video-production-settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'monthly_reel_target' => ['required', 'integer', 'min:0', 'max:10000'],
            'monthly_static_target' => ['required', 'integer', 'min:0', 'max:10000'],
            'minimum_shoots' => ['required', 'integer', 'min:0', 'max:1000'],
            'maximum_shoots' => ['required', 'integer', 'gte:minimum_shoots', 'max:1000'],
            'included_hours_per_shoot' => ['required', 'numeric', 'min:0', 'max:168'],
            'required_lead_days' => ['required', 'integer', 'min:0', 'max:365'],
            'included_revisions' => ['required', 'integer', 'min:0', 'max:100'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'workflow_effective_date' => ['nullable', 'date'],
        ];
    }
}
