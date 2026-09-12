<?php

namespace Workdo\VideoProduction\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\VideoProduction\Models\ProductionJob;

class StoreProductionJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create-video-production-job') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(ProductionJob::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
