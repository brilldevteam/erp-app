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
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'budget' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:Ongoing,Onhold,Finished',
        ] + $this->projectPropertyInformationRules();
    }
}
