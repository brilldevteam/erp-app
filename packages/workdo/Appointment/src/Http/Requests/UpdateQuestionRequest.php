<?php

namespace Workdo\Appointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'question_name' => 'required|string|max:255',
            'question_type' => 'required',
            'required_answer' => 'boolean',
            'enabled' => 'boolean'
        ];

        if ($this->question_type !== '2') {
            $rules['available_answers'] = 'required|string';
        } else {
            $rules['available_answers'] = 'nullable|string';
        }

        return $rules;
    }
}