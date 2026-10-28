<?php

namespace Tests\Unit;

require_once __DIR__ . '/../../app/Rules/SpreadsheetUpload.php';

use App\Rules\SpreadsheetUpload;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class SpreadsheetUploadRuleTest extends TestCase
{
    public function test_it_accepts_a_real_xlsx_even_when_detected_as_zip(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'spreadsheet-upload-');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(['Invoice Number', 'Customer Name'], null, 'A1');
        (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        $errors = $this->validate(new UploadedFile(
            $temporary,
            'Invoice.xlsx',
            'application/zip',
            null,
            true
        ));

        unlink($temporary);
        $this->assertSame([], $errors);
    }

    public function test_it_rejects_a_renamed_non_excel_file(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'spreadsheet-upload-');
        file_put_contents($temporary, 'not an Excel workbook');

        $errors = $this->validate(new UploadedFile(
            $temporary,
            'fake.xlsx',
            'application/zip',
            null,
            true
        ));

        unlink($temporary);
        $this->assertNotEmpty($errors);
    }

    public function test_it_accepts_excel_content_even_when_the_filename_uses_xlsx(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'spreadsheet-upload-');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(['Invoice Number', 'Customer Name'], null, 'A1');
        (new Xls($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        $errors = $this->validate(new UploadedFile(
            $temporary,
            'Invoice.xlsx',
            'application/vnd.ms-excel',
            null,
            true
        ));

        unlink($temporary);
        $this->assertSame([], $errors);
    }

    private function validate(UploadedFile $upload): array
    {
        $errors = [];
        (new SpreadsheetUpload())->validate('file', $upload, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        return $errors;
    }
}
