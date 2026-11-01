<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoExportTemplate;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use App\Services\ZohoMigration\ZohoStageCatalog;
use App\Services\ZohoMigration\ZohoUploadGuide;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ZohoExportTemplateTest extends TestCase
{
    #[DataProvider('offeredTemplates')]
    public function test_each_offered_template_has_zoho_columns_and_is_recognised_by_its_step(string $stageKey, string $template): void
    {
        self::assertTrue(ZohoExportTemplate::exists($template));
        $stage = (new ZohoStageCatalog())->find($stageKey);

        foreach (['xlsx', 'csv'] as $format) {
            $path = (new ZohoExportTemplate())->write($template, $format);
            try {
                $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
            } finally {
                @unlink($path);
            }

            $title = ZohoExportTemplate::title($template);
            if ($title !== null) {
                self::assertSame($title, str_replace("\r\n", "\n", (string) $rows[0][0]), "{$template} {$format} title");
                // The reconciliation reads the period from this title.
                self::assertMatchesRegularExpression('/From\s+\d{1,2}\s+\w{3}\s+\d{4}\s+To\s+\d{1,2}\s+\w{3}\s+\d{4}/', $title);
            }
            $headers = array_slice(array_map(fn ($value) => (string) $value, $rows[$title === null ? 0 : 1]), 0, count(ZohoExportTemplate::headers($template)));
            self::assertSame(ZohoExportTemplate::headers($template), $headers, "{$template} {$format} headers");

            $detected = (new ZohoProfileCatalog())->detect(array_map('trim', $headers));
            self::assertContains($detected['dataset_type'], $stage['datasets'], "{$template} {$format} is accepted by {$stageKey}");
        }
    }

    public static function offeredTemplates(): array
    {
        $cases = [];
        foreach ((new ZohoStageCatalog())->all() as $stage) {
            foreach (ZohoUploadGuide::for($stage['key'])['files'] ?? [] as $entry) {
                if (isset($entry['template'])) {
                    $cases["{$stage['key']} {$entry['template']}"] = [$stage['key'], $entry['template']];
                }
            }
        }

        return $cases;
    }

    public function test_every_file_based_upload_guide_offers_a_template_and_visible_columns(): void
    {
        foreach ((new ZohoStageCatalog())->all() as $stage) {
            foreach (ZohoUploadGuide::for($stage['key'])['files'] ?? [] as $entry) {
                self::assertArrayHasKey('template', $entry, "{$stage['key']} {$entry['title']} template");
                self::assertArrayHasKey('columns', $entry, "{$stage['key']} {$entry['title']} columns");
                self::assertArrayHasKey('column_usage', $entry, "{$stage['key']} {$entry['title']} usage");
                self::assertNotEmpty($entry['columns']);
                $classified = array_merge(...array_values($entry['column_usage']));
                sort($classified);
                $columns = $entry['columns'];
                sort($columns);
                self::assertSame($columns, $classified);
            }
        }
    }

    public function test_expense_xlsx_marks_only_ignored_columns_red_and_required_columns_green(): void
    {
        $path = (new ZohoExportTemplate())->write('expenses', 'xlsx');
        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $headers = array_flip(ZohoExportTemplate::headers('expenses'));
            $cell = fn (string $header): string => Coordinate::stringFromColumnIndex($headers[$header] + 1).'1';
            $fill = fn (string $header): string => $sheet->getStyle($cell($header))->getFill()->getStartColor()->getARGB();

            self::assertSame('FFF4CCCC', $fill('Vehicle Name'));
            self::assertSame('FFD9EAD3', $fill('Expense Date'));
            self::assertSame(Fill::FILL_NONE, $sheet->getStyle($cell('Expense Description'))->getFill()->getFillType());
        } finally {
            @unlink($path);
        }
    }

    public function test_manual_journal_template_contains_fields_needed_to_import_rows(): void
    {
        self::assertContains('Status', ZohoExportTemplate::headers('manual_journals'));
        self::assertContains('Total', ZohoExportTemplate::headers('manual_journals'));
        self::assertContains('Currency', ZohoExportTemplate::headers('manual_journals'));
        self::assertContains('Contact Type', ZohoExportTemplate::headers('manual_journals'));
        self::assertNotContains('Status', ZohoExportTemplate::usage('manual_journals')['ignored']);
    }

    public function test_audited_import_dependencies_have_the_correct_usage(): void
    {
        self::assertContains('Total', ZohoExportTemplate::usage('expenses')['required']);
        self::assertContains('Account Code', ZohoExportTemplate::usage('manual_journals')['required']);
        self::assertNotContains('Account # ', ZohoExportTemplate::usage('chart_of_accounts')['ignored']);
        self::assertNotContains('Currency', ZohoExportTemplate::usage('chart_of_accounts')['ignored']);
    }

    public function test_vendor_bill_allocations_are_conditional_and_alternatives_are_explained(): void
    {
        self::assertContains('Bill Number', ZohoExportTemplate::usage('vendor_payments')['optional']);
        self::assertContains('Bill Amount', ZohoExportTemplate::usage('vendor_payments')['optional']);
        self::assertStringContainsString('unapplied advance', ZohoExportTemplate::columnNotes('vendor_payments')['Bill Number']);
        self::assertStringContainsString('code is used first', mb_strtolower(ZohoExportTemplate::columnNotes('sales_invoices')['Account']));
        self::assertStringContainsString('treated as customer', ZohoExportTemplate::columnNotes('manual_journals')['Contact Type']);
    }
}
