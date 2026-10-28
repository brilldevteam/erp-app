<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xml;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

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

        if (!in_array($extension, ['csv', 'xlsx'], true) || !$path) {
            $fail('The :attribute must be a readable CSV or XLSX spreadsheet.');

            return;
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
        } catch (Throwable) {
            $fail('The :attribute must be a readable CSV or XLSX spreadsheet.');

            return;
        }

        $allowedReaders = $extension === 'csv'
            ? [Csv::class]
            : [Xlsx::class, Xls::class, Xml::class, Html::class, Ods::class];

        if (!in_array($reader::class, $allowedReaders, true) || !$reader->canRead($path)) {
            $fail('The :attribute must be a readable CSV or XLSX spreadsheet.');
        }
    }
}
