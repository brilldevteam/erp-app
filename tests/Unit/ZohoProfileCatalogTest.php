<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoProfileCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZohoProfileCatalogTest extends TestCase
{
    #[DataProvider('knownProfiles')]
    public function test_it_detects_supported_profiles(array $headers, string $dataset): void
    {
        $result = (new ZohoProfileCatalog())->detect($headers);

        self::assertSame($dataset, $result['dataset_type']);
        self::assertSame(100, $result['confidence']);
        self::assertFalse($result['ambiguous']);
    }

    public function test_it_does_not_guess_unknown_profiles(): void
    {
        $result = (new ZohoProfileCatalog())->detect(['Something', 'Unrelated']);

        self::assertNull($result['profile_key']);
        self::assertSame(0, $result['confidence']);
    }

    public function test_control_reports_take_precedence_over_broader_invoice_profiles(): void
    {
        $result = (new ZohoProfileCatalog())->detect([
            'Invoice ID', 'Invoice Number', 'Customer Name', 'Total', 'Balance Due', 'Status',
        ]);

        self::assertSame('invoice_control', $result['dataset_type']);
        self::assertFalse($result['ambiguous']);
    }

    public function test_header_normalization_handles_zoho_spacing_and_separators(): void
    {
        $catalog = new ZohoProfileCatalog();

        self::assertSame('invoice number', $catalog->normalize("  Invoice_Number\u{200B} "));
    }

    public static function knownProfiles(): array
    {
        return [
            'invoice control' => [['Invoice Number', 'Total', 'Balance Due', 'Status'], 'invoice_control'],
            'payment allocation' => [['Payment ID', 'Invoice Number', 'Amount Applied'], 'customer_payment_allocations'],
            'vendor allocation' => [['Payment ID', 'Bill Number', 'Applied Amount'], 'vendor_payment_allocations'],
            'general ledger' => [['Transaction ID', 'Account', 'Debit', 'Credit'], 'general_ledger'],
        ];
    }
}
