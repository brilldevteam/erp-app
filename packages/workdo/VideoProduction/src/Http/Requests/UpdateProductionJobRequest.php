<?php

namespace Workdo\VideoProduction\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductionJobRequest extends StoreProductionJobRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit-video-production-job') ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['reference'] = [
            'nullable', 'string', 'max:100',
            Rule::unique('video_production_jobs')->where('created_by', creatorId())->ignore($this->route('job')),
        ];

        return $rules;
    }
}
