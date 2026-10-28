<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class SpreadsheetUpload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid()) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $path = $value->getRealPath();

        $reader = match ($extension) {
            'csv' => new Csv(),
            'xlsx' => new Xlsx(),
            default => null,
        };

        if (!$reader || !$path || !$reader->canRead($path)) {
            $fail('The :attribute must be a readable CSV or XLSX spreadsheet.');
        }
    }
}
