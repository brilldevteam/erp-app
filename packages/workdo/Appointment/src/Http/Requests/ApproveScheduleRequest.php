<?php

namespace Workdo\Appointment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
        ];
    }
}