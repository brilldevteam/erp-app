<?php

namespace Workdo\Appointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_name' => 'required|string|max:255',
            'appointment_type' => 'required|string',
            'week_day' => 'required|array',
            'duration' => 'required|numeric|min:1',
            'phone_enabled' => 'boolean',
            'question_ids' => ['array', function ($attribute, $value, $fail) {
                $requiredQuestions = \Workdo\Appointment\Models\Question::where('required_answer', true)
                    ->where('enabled', true)
                    ->where('created_by', creatorId())
                    ->get();
                foreach ($requiredQuestions as $question) {
                    if (!in_array($question->id, $value ?? [])) {
                        $fail("The {$question->question_name} field is required.");
                    }
                }
            }],
            'enabled' => 'boolean',
        ];
    }
}