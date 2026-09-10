<?php

namespace Tests\Unit;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\DoubleEntry\Services\GeneralLedgerExcelExportService;

class GeneralLedgerExcelExportServiceTest extends TestCase
{
    public function test_it_exports_account_transactions_and_balances(): void
    {
        $account = new ChartOfAccount([
            'account_code' => '1000',
            'account_name' => 'Cash',
        ]);
        $data = [
            'opening_balance' => 125,
            'transactions' => [[
                'date' => '2026-09-01',
                'description' => 'Receipt',
                'reference_type' => 'customer_payment',
                'reference_id' => 42,
                'debit' => 75,
                'credit' => 0,
                'balance' => 200,
            ]],
            'closing_balance' => 200,
        ];

        $path = app(GeneralLedgerExcelExportService::class)->create($data, $account, [
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
        ]);

        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('1000 - Cash', $sheet->getCell('B2')->getValue());
        $this->assertSame('Opening Balance', $sheet->getCell('B6')->getValue());
        $this->assertSame('Receipt', $sheet->getCell('B7')->getValue());
        $this->assertSame('customer_payment #42', $sheet->getCell('C7')->getValue());
        $this->assertSame(75.0, (float) $sheet->getCell('D7')->getValue());
        $this->assertSame(200.0, (float) $sheet->getCell('F8')->getValue());

        unlink($path);
    }
}
