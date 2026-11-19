<?php

namespace Workdo\Timesheet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()->can('create-timesheet');
    }

    public function rules(): array
    {
        $rules = [
            'date' => 'required|date',
            'hours' => 'required|integer|min:0|max:12',
            'minutes' => 'required|integer|min:0|max:60',
            'notes' => 'nullable|string|max:1000',
            'type' => 'required|in:clock_in_out,project,manual',
        ];



        // User field only for managers
        if(Auth::user()->can('manage-any-timesheet')) {
            $rules['user_id'] = 'required|exists:users,id';
        } else {
            // Staff can only create for themselves
            $this->merge(['user_id' => Auth::id()]);
        }

        // Project and task fields for project type
        if($this->type === 'project') {
            $rules['project_id'] = 'required|integer';
            $rules['task_id'] = 'nullable|integer';
        }

        return $rules;
    }

    /**
     * Whole hours are allowed (e.g. 4 h 0 min), but the total time must be more than zero.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('hours') || $validator->errors()->has('minutes')) {
                return;
            }
            if (((int) $this->input('hours') * 60) + (int) $this->input('minutes') <= 0) {
                $validator->errors()->add('minutes', __('Enter a time greater than 0.'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'minutes.min' => __('Minutes cannot be negative.'),
            'minutes.max' => __('Minutes cannot exceed 60.'),
            'hours.max' => __('Hours cannot exceed 12.'),
        ];
    }
}