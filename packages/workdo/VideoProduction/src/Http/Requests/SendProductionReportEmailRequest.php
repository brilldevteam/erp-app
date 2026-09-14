<?php

namespace Workdo\VideoProduction\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendProductionReportEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-video-production') ?? false;
    }

    public function rules(): array
    {
        return [
            'record_id' => ['required', 'integer'],
            'type' => ['required', 'in:shoot,deliverable'],
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc', 'distinct'],
            'cc' => ['nullable', 'array', 'max:10'],
            'cc.*' => ['required', 'email:rfc', 'distinct'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:5000'],
            'report' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ];
    }
}
