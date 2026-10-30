<?php

namespace App\Services\ZohoMigration;

use App\Models\DocumentTemplate;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\DocumentTemplates\DocumentTemplateService;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Quotation\Models\SalesQuotation;
use Workdo\Quotation\Models\SalesQuotationItem;

/**
 * Imports a Zoho Estimates export (one row per estimate line) as ERP quotations.
 *
 * Every line is placed on one service, "Zoho free-text line", with the Zoho item name and description kept
 * on the line. Line amounts are carried as a fixed discount equal to Zoho's line subtotal minus its line
 * total, so each line — including Zoho's estimate-level discounts, which Zoho spreads over the lines — and
 * each quotation total match Zoho to the cent. Zoho statuses map onto the ERP's four, the original status is
 * kept in the notes, and invoiced estimates are marked converted so they cannot be invoiced a second time.
 */
class ZohoQuotationService
{
    use ReadsZohoDocuments;

    public const LINE_PRODUCT_SKU = ZohoFreeTextLineProduct::SKU;
    public const LINE_PRODUCT_NAME = ZohoFreeTextLineProduct::NAME;

    /** Zoho estimate status => [ERP status, converted to invoice]. */
    private const STATUSES = [
        'draft' => ['draft', false],
        'pending' => ['draft', false],
        'pending approval' => ['draft', false],
        'sent' => ['sent', false],
        'expired' => ['sent', false],
        'approved' => ['accepted', false],
        'accepted' => ['accepted', false],
        'invoiced' => ['accepted', true],
        'declined' => ['rejected', false],
        'rejected' => ['rejected', false],
    ];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoFreeTextLineProduct $lineProduct,
    ) {
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);

        $zohoStatus = mb_strtolower($get('estimate status'));
        [$status, $converted] = self::STATUSES[$zohoStatus] ?? [null, false];
        $quotationDate = $this->date($get('estimate date'), (string) $batch->source_date_format);
        $expiry = $get('expiry date');
        $quantity = (float) str_replace(',', '', $get('quantity'));
        $unitPrice = $amount('item price');
        $lineTotal = $amount('item total');
        $currency = strtoupper($get('currency code') ?: (string) $batch->base_currency);

        return [
            'quotation_number' => $get('estimate number'),
            'zoho_estimate_id' => $get('estimate id'),
            'quotation_date' => $quotationDate,
            'quotation_date_source' => $get('estimate date'),
            'due_date' => $expiry === '' ? $quotationDate : $this->date($expiry, (string) $batch->source_date_format),
            'due_date_source' => $expiry,
            'customer' => $get('customer name'),
            'zoho_status' => $get('estimate status'),
            'status' => $status,
            'converted_to_invoice' => $converted,
            'subject' => $get('subject') ?: null,
            'notes' => $this->notes($get('notes'), $get('terms & conditions'), $get('estimate status'), $status, $expiry === ''),
            'currency_code' => $currency,
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'quotation_total' => $amount('total'),
            'description' => trim(implode("\n", array_filter([$get('item name'), $get('item desc')]))) ?: null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            // Zoho's line total already includes its item discount and its share of any estimate-level discount.
            'discount_value' => round(round($quantity * $unitPrice, 2) - $lineTotal, 2),
        ];
    }

    /**
     * Per-estimate facts the row checks need: each estimate's first row, line-total sum, Zoho total and header.
     */
    public function context(iterable $preparedRows): array
    {
        $quotes = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = mb_strtolower($row['quotation_number']);
            $quotes[$key] ??= ['first_row' => $rowNumber, 'line_sum' => 0.0, 'total' => $row['quotation_total'], 'header' => $this->header($row)];
            $quotes[$key]['line_sum'] = round($quotes[$key]['line_sum'] + $row['line_total'], 2);
        }

        return $quotes;
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $quote = $context[mb_strtolower($row['quotation_number'])] ?? null;

        if ($row['quotation_number'] === '') {
            $errors[] = 'Estimate Number is required.';
        }
        if (!$row['quotation_date']) {
            $errors[] = "Estimate Date '{$row['quotation_date_source']}' is not a valid date.";
        }
        if (!$row['due_date']) {
            $errors[] = "Expiry Date '{$row['due_date_source']}' is not a valid date.";
        } elseif ($row['quotation_date'] && $row['due_date'] < $row['quotation_date']) {
            $errors[] = 'Expiry Date is before the Estimate Date.';
        }
        if ($row['status'] === null) {
            $errors[] = "Estimate Status '{$row['zoho_status']}' is not a known Zoho status.";
        }
        if ($row['customer'] === '') {
            $errors[] = 'Customer Name is required.';
        } elseif (!$this->customerUser($row['customer'], $batch->tenant_id)) {
            $errors[] = "Customer '{$row['customer']}' was not found in ERP. Complete the Customers step first.";
        }
        if ($row['quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        } elseif (floor($row['quantity']) !== $row['quantity']) {
            $errors[] = "Quantity {$row['quantity']} is not a whole number; ERP quotation lines store whole quantities only.";
        }
        if ($row['unit_price'] < 0) {
            $errors[] = 'Item Price must be zero or greater.';
        }
        if ($row['discount_value'] < 0 || $row['discount_value'] > round($row['quantity'] * $row['unit_price'], 2)) {
            $errors[] = 'Item Total '.number_format($row['line_total'], 2).' does not fit Quantity × Item Price ('.number_format($row['quantity'] * $row['unit_price'], 2).').';
        }
        if (mb_strlen((string) $row['subject']) > 500) {
            $errors[] = 'Subject is longer than 500 characters.';
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency estimate.';
        }

        // Estimate-level checks are reported once, on the estimate's first row.
        if ($quote && $quote['first_row'] === $sourceRowNumber) {
            if (abs($quote['line_sum'] - $quote['total']) > 0.01) {
                $errors[] = 'Line totals add up to '.number_format($quote['line_sum'], 2).' but the Zoho estimate total is '.number_format($quote['total'], 2).'.';
            }
            if ($batch->duplicate_strategy === 'block' && $this->existing($row['quotation_number'], $batch->tenant_id)) {
                $errors[] = 'A quotation with this number already exists in ERP and this migration is configured to block duplicates.';
            }
        } elseif ($quote && $this->header($row) !== $quote['header']) {
            $errors[] = "This line's estimate details (date, customer, status or currency) differ from row {$quote['first_row']} of the same estimate.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_quotation',
            'field' => 'estimate_number',
            'source_value' => $row['quotation_number'],
            'message' => $message,
            'resolution_url' => Route::has('account.customers.index') ? route('account.customers.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'quotations')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing quotations.');
        }

        $rows = $batch->files()->where('stage_key', 'quotations')->where('status', 'staged')
            ->with(['stagedRows' => fn ($query) => $query->where('status', 'validated')->orderBy('source_row_number')])
            ->get()->flatMap->stagedRows;
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated estimate rows are ready to import.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $quotes = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $quotes[mb_strtolower($data['quotation_number'])][] = [$row, $data];
        }

        return DB::transaction(function () use ($quotes, $batch, $actorId, $stage): array {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'lines' => 0, 'converted' => 0];
            $product = $this->lineProduct->for($batch->tenant_id, $actorId);
            $templateId = app(DocumentTemplateService::class)->resolveForDocument(DocumentTemplate::TYPE_QUOTATION, $batch->tenant_id, null)->id;

            foreach ($quotes as $lines) {
                $header = $lines[0][1];
                $quotation = $this->existing($header['quotation_number'], $batch->tenant_id);
                if ($quotation && $batch->duplicate_strategy !== 'update') {
                    $counts['skipped']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $values = [
                    'quotation_number' => $header['quotation_number'],
                    'quotation_date' => $header['quotation_date'],
                    'due_date' => $header['due_date'],
                    'customer_id' => $this->customerUser($header['customer'], $batch->tenant_id)->id,
                    'currency_code' => $header['currency_code'],
                    'exchange_rate' => $header['exchange_rate'],
                    'status' => $header['status'],
                    'converted_to_invoice' => $header['converted_to_invoice'],
                    'subject' => $header['subject'],
                    'notes' => $header['notes'],
                    'document_template_id' => $templateId,
                ];
                if ($quotation) {
                    $quotation->items()->delete();
                    $quotation->update($values);
                    $result = 'updated';
                } else {
                    $quotation = SalesQuotation::create($values + [
                        'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0,
                        'creator_id' => $actorId, 'created_by' => $batch->tenant_id,
                    ]);
                    $result = 'imported';
                }

                foreach ($lines as [$row, $data]) {
                    SalesQuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'product_id' => $product->id,
                        'description' => $data['description'],
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                        'discount_type' => 'fixed',
                        'discount_value' => $data['discount_value'],
                        'tax_percentage' => 0,
                    ]);
                    $row->update(['status' => $result, 'normalized_values' => $data]);
                    $counts['lines']++;
                }

                $items = $quotation->items()->get();
                $total = round((float) $items->sum('total_amount'), 2);
                // Guard: the ERP's own line maths must land exactly on the Zoho total, or nothing is kept.
                if (abs($total - $header['quotation_total']) > 0.01) {
                    throw new RuntimeException("Estimate {$header['quotation_number']}: ERP total {$total} does not match the Zoho total {$header['quotation_total']}. Nothing was imported.");
                }
                $quotation->update([
                    'subtotal' => round((float) $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price), 2),
                    'discount_amount' => round((float) $items->sum('discount_amount'), 2),
                    'tax_amount' => round((float) $items->sum('tax_amount'), 2),
                    'total_amount' => $total,
                    'base_amount' => round($total * $header['exchange_rate'], 2),
                ]);
                $counts[$result]++;
                $counts['converted'] += $header['converted_to_invoice'] ? 1 : 0;
            }

            $stage->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $actorId,
                'summary' => $counts,
            ]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'quotations',
                'event_type' => 'stage_imported',
                'message' => "Quotations import completed: {$counts['imported']} created, {$counts['updated']} updated, {$counts['skipped']} already existed ({$counts['lines']} lines; {$counts['converted']} marked converted to invoice).",
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    private function header(array $row): array
    {
        return [$row['quotation_date'], $row['due_date'], mb_strtolower($row['customer']), $row['status'], $row['currency_code'], $row['quotation_total']];
    }

    private function notes(string $notes, string $terms, string $zohoStatus, ?string $status, bool $noExpiry): ?string
    {
        $parts = array_filter([
            $notes,
            $terms !== '' ? "Terms & Conditions:\n{$terms}" : '',
            $status !== null && mb_strtolower($zohoStatus) !== $status ? 'Zoho status: '.ucfirst(mb_strtolower($zohoStatus)) : '',
            $noExpiry ? 'No expiry date in Zoho; expiry set to the estimate date.' : '',
        ]);

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function existing(string $number, int $tenantId): ?SalesQuotation
    {
        return SalesQuotation::where('created_by', $tenantId)->whereRaw('LOWER(quotation_number) = ?', [mb_strtolower($number)])->first();
    }
}
