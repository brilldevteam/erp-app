<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoExportTemplate;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use App\Services\ZohoMigration\ZohoStageCatalog;
use App\Services\ZohoMigration\ZohoUploadGuide;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
}
