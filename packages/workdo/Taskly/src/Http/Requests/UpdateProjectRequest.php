<?php

namespace Workdo\Taskly\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Taskly\Http\Requests\Concerns\ValidatesProjectPropertyInformation;

class UpdateProjectRequest extends FormRequest
{
    use ValidatesProjectPropertyInformation;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isProduction = $this->input('category') === 'production';

        return [
            'name' => 'required|string|max:255',
            'category' => 'required|in:general,production,property',
            'description' => 'nullable|string',
            'budget' => $isProduction ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'start_date' => $isProduction ? 'nullable|date' : 'required|date',
            'end_date' => $isProduction ? 'nullable|date|after_or_equal:start_date' : 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:Ongoing,Onhold,Finished',
        ] + $this->projectPropertyInformationRules();
    }
}
