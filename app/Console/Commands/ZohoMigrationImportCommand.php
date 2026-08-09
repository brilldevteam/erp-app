<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ZohoMigration\ZohoMigrationImportService;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CLI-only entry point for the one-time Zoho Books historical data migration.
 * Deliberately separate from the generic bulk-import UI/API (BulkImportRegistry,
 * app/Services/BulkImport/Definitions/*) — this command and the service it
 * drives are the only callers of ZohoMigrationImportService.
 */
class ZohoMigrationImportCommand extends Command
{
    protected $signature = 'zoho:migrate-import
        {type : expenses|customer-payments|vendor-payments}
        {file : Absolute path to the .xlsx file}
        {--tenant= : Tenant (company) user ID the records belong to}
        {--actor= : User ID recorded as creator; defaults to --tenant}';

    protected $description = 'One-time import of a Zoho Books export (expenses, customer payments, or vendor payments) with relaxed bank-account requirements.';

    public function handle(ZohoMigrationImportService $service): int
    {
        $type = $this->argument('type');
        $path = $this->argument('file');

        if (!in_array($type, ['expenses', 'customer-payments', 'vendor-payments'], true)) {
            $this->error("Unknown type '{$type}'. Expected: expenses, customer-payments, vendor-payments.");
            return self::FAILURE;
        }

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $tenantId = (int) $this->option('tenant');
        if (!$tenantId || !User::find($tenantId)) {
            $this->error('Pass a valid --tenant=<user id> for the company these records belong to.');
            return self::FAILURE;
        }
        $actorId = (int) ($this->option('actor') ?: $tenantId);

        $rows = $this->readRows($path);
        if (!$rows) {
            $this->error('No data rows found in the file.');
            return self::FAILURE;
        }

        $this->info(sprintf('Importing %d row(s) of %s for tenant #%d...', count($rows), $type, $tenantId));

        $summary = match ($type) {
            'expenses' => $service->importExpenses($rows, $tenantId, $actorId),
            'customer-payments' => $service->importCustomerPayments($rows, $tenantId, $actorId),
            'vendor-payments' => $service->importVendorPayments($rows, $tenantId, $actorId),
        };

        $this->info("Imported: {$summary['imported']}, Skipped (duplicate): {$summary['skipped']}");
        if ($summary['errors']) {
            $this->warn(count($summary['errors']).' row(s) failed:');
            foreach ($summary['errors'] as $error) {
                $this->line('  - '.$error);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Reads the sheet using its header row verbatim as array keys — these
     * files are expected to already match the target entity's field names
     * (expense_number, bank_account, etc.), no alias/fuzzy mapping needed.
     */
    private function readRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $headers = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0];
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false)[0];
            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $value = $values[$i] ?? null;
                $data[$header] = is_string($value) ? trim($value) : $value;
            }
            $rows[] = $data;
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }
}
