<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

/**
 * Shared import for Zoho credit documents — customer credit notes and vendor credits (ERP debit notes).
 *
 * ERP credit/debit note lines need a product and have no description or account, so lines use the
 * "Zoho free-text line" service, each line's Zoho item name and description is kept in the document notes, and
 * each line's Zoho account is used in the journal entry the import writes itself (the ERP's own posting is
 * hard-wired to default accounts). Totals match Zoho to the cent. Open and closed credits import approved with
 * their full balance open: applying them to invoices and bills is the Credit applications stage. Draft credits
 * import as drafts with no journal; void credits are skipped and listed.
 */
abstract class ZohoCreditDocumentService
{
    use ReadsZohoDocuments;

    private const ACTIONS = ['open' => 'post', 'closed' => 'post', 'draft' => 'draft', 'void' => 'skip'];

    public function __construct(
        protected readonly ZohoProfileCatalog $profiles,
        protected readonly ZohoFreeTextLineProduct $lineProduct,
        protected readonly ZohoJournalWriter $journals,
    ) {
    }

    /**
     * Stage key, Zoho column names (id, number, date, status, partner, linked document), labels, the ERP
     * control account code and the journal reference type.
     */
    abstract protected function config(): array;

    /** The ERP user of the customer or vendor. */
    abstract protected function partnerUser(string $name, int $tenantId): ?\App\Models\User;

    /** The ERP invoice or bill a single linked Zoho document number refers to, for this partner. */
    abstract protected function linkedDocument(string $number, int $partnerId, int $tenantId): ?Model;

    /** An already imported credit with this number for this company. */
    abstract protected function existing(string $number, int $tenantId): ?Model;

    /** The ERP document number to use: Zoho's own, made unique across companies when the ERP requires it. */
    abstract protected function erpNumber(string $zohoNumber, int $tenantId): string;

    /** Creates the ERP credit document and its lines; returns the document. */
    abstract protected function createDocument(array $header, array $lines, int $partnerId, ?Model $linked, int $productId, int $actorId, int $tenantId): Model;

    /** Journal lines for the document: the control account and each Zoho line account, in base currency. */
    /** $baseByAccount holds full-price line amounts when the document books its item discounts to Zoho's Discount account. */
    abstract protected function journalLines(array $header, array $baseByAccount, ChartOfAccount $control, int $partnerId, float $discountBase = 0.0, ?ChartOfAccount $discountAccount = null): array;

    /** Whether Zoho books this document's lines at full price with the item discounts on its Discount account. */
    protected function postsDiscounts(): bool
    {
        return false;
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $c = $this->config();
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] ??= is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);

        $zohoStatus = mb_strtolower($get($c['status']));
        $action = self::ACTIONS[$zohoStatus] ?? null;
        $quantity = round((float) str_replace(',', '', $get('quantity')), 2);
        $unitPrice = $amount('item price');
        $lineTotal = $amount('item total');
        $id = '';
        foreach ($c['id'] as $idColumn) {
            $id = $id !== '' ? $id : $get($idColumn);
        }

        return [
            'include' => $action !== 'skip',
            'action' => $action,
            'skip_reason' => $action === 'skip' ? 'Void in Zoho' : null,
            'number' => $get($c['number']),
            'zoho_id' => $id,
            'date' => $this->date($get($c['date']), (string) $batch->source_date_format),
            'date_source' => $get($c['date']),
            'partner' => $get($c['partner']),
            'zoho_status' => $get($c['status']),
            'linked_numbers' => array_values(array_filter(array_map('trim', explode(',', $get($c['linked']))))),
            'zoho_notes' => $get('notes'),
            'terms' => $get('terms & conditions'),
            'subject' => $get('subject'),
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'document_total' => $amount('total'),
            'tax_amount' => $amount('item tax amount'),
            'description' => trim(implode(': ', array_filter([$get('item name'), $get('item desc')]))) ?: null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'discount_amount' => round(round($quantity * $unitPrice, 2) - $lineTotal, 2),
            'account_code' => $get('account code'),
            'account_name' => $get('account'),
        ];
    }

    /** Per-document facts the row checks need: first row, line-total sum, Zoho total and header. */
    public function context(iterable $preparedRows): array
    {
        $documents = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = $this->key($row);
            $documents[$key] ??= ['first_row' => $rowNumber, 'line_sum' => 0.0, 'total' => $row['document_total'], 'header' => $this->header($row)];
            $documents[$key]['line_sum'] = round($documents[$key]['line_sum'] + $row['line_total'], 2);
        }

        return $documents;
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $c = $this->config();
        $errors = [];
        $document = $context[$this->key($row)] ?? null;

        if ($row['number'] === '') {
            $errors[] = "{$c['number_label']} is required.";
        }
        if ($row['action'] === null) {
            $errors[] = "{$c['label']} status '{$row['zoho_status']}' is not a known Zoho status.";
        }
        if (!$row['date']) {
            $errors[] = "Date '{$row['date_source']}' is not a valid date.";
        }
        $partner = $row['partner'] !== '' ? $this->partnerUser($row['partner'], $batch->tenant_id) : null;
        if ($row['partner'] === '') {
            $errors[] = "{$c['partner_label']} Name is required.";
        } elseif (!$partner) {
            $errors[] = "{$c['partner_label']} '{$row['partner']}' was not found in ERP. Complete the {$c['partner_step']} step first.";
        }
        if ($row['quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if ($row['unit_price'] < 0) {
            $errors[] = 'Item Price must be zero or greater.';
        }
        if ($row['discount_amount'] < 0 || $row['discount_amount'] > round($row['quantity'] * $row['unit_price'], 2)) {
            $errors[] = 'Item Total '.number_format($row['line_total'], 2).' does not fit Quantity × Item Price ('.number_format($row['quantity'] * $row['unit_price'], 2).').';
        }
        if ($row['tax_amount'] != 0.0) {
            $errors[] = "This line has Zoho tax (".number_format($row['tax_amount'], 2)."); taxed {$c['label_plural']} are not supported by this import yet.";
        }
        if (!$this->lineAccount($row, $batch->tenant_id)) {
            $errors[] = "Account {$row['account_code']} ({$row['account_name']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }
        if ($this->postsDiscounts() && $this->lineDiscount($row) > 0 && !$this->discountAccount($batch->tenant_id)) {
            $errors[] = 'Zoho posts item discounts to its Discount account, and ERP has no single account named Discount. Complete the Chart of Accounts step first.';
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = "Exchange Rate is required for a foreign-currency {$c['label']}.";
        }

        // Document-level checks are reported once, on the document's first row.
        if ($document && $document['first_row'] === $sourceRowNumber) {
            if (abs($document['line_sum'] - $document['total']) > 0.01) {
                $errors[] = 'Line totals add up to '.number_format($document['line_sum'], 2)." but the Zoho {$c['label']} total is ".number_format($document['total'], 2).'.';
            }
            if ($existing = $this->existing($row['number'], $batch->tenant_id)) {
                if ($batch->duplicate_strategy === 'block') {
                    $errors[] = "A {$c['label']} with this number already exists in ERP and this migration is configured to block duplicates.";
                } elseif ($batch->duplicate_strategy === 'update') {
                    $errors[] = "A {$c['label']} with this number already exists in ERP. Imported {$c['label_plural']} are not updated in place because they may already be posted; remove it or use the link duplicate setting.";
                }
            }
            if ($row['action'] === 'post') {
                if (!$this->controlAccount($batch->tenant_id)) {
                    $errors[] = "Account {$c['control_code']} ({$c['control_name']}) was not found in ERP.";
                }
                if (!$existing && JournalEntry::where('journal_number', $this->journalNumber($row, $batch->tenant_id))->exists()) {
                    $errors[] = 'Journal number '.$this->journalNumber($row, $batch->tenant_id)." is already used; this {$c['label']} may have been imported before.";
                }
            }
        } elseif ($document && $this->header($row) !== $document['header']) {
            $errors[] = "This line's {$c['label']} details (date, {$c['partner_label']}, status or currency) differ from row {$document['first_row']} of the same {$c['label']}.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_'.$c['stage_key'],
            'field' => 'number',
            'source_value' => $row['number'],
            'message' => $message,
            'resolution_url' => Route::has($c['route']) ? route($c['route']) : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $c = $this->config();
        $stage = $batch->stages()->where('stage_key', $c['stage_key'])->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException("Resolve all validation issues before importing {$c['label_plural']}.");
        }

        $files = $batch->files()->where('stage_key', $c['stage_key'])->where('status', 'staged')->get();
        $rows = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException("No validated {$c['label']} rows are ready to import.");
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $documents = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if (!$data['include']) {
                throw new RuntimeException('Row '.$row->source_row_number.' ('.$data['number'].') is now '.$data['zoho_status'].' in the file. Upload the file again to re-check it.');
            }
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $documents[$this->key($data)][] = [$row, $data];
        }

        $skippedInZoho = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'skipped')->get())
            ->map(fn ($row) => $this->prepare($row->original_values, $batch))
            ->unique(fn (array $data) => $this->key($data))
            ->map(fn (array $data) => "{$data['number']} ({$data['skip_reason']})")
            ->values()->all();

        return DB::transaction(function () use ($documents, $batch, $actorId, $stage, $skippedInZoho, $c): array {
            $counts = ['posted' => 0, 'drafts' => 0, 'skipped_existing' => 0, 'lines' => 0, 'journal_entries' => 0, 'linked' => 0, 'skipped_in_zoho' => count($skippedInZoho)];
            $productId = $this->lineProduct->for($batch->tenant_id, $actorId)->id;
            $control = $this->controlAccount($batch->tenant_id);

            foreach ($documents as $lines) {
                $header = $lines[0][1];
                if ($this->existing($header['number'], $batch->tenant_id)) {
                    // Only reachable with the "link" duplicate setting: the existing ERP credit is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $partner = $this->partnerUser($header['partner'], $batch->tenant_id);
                $linked = count($header['linked_numbers']) === 1 ? $this->linkedDocument($header['linked_numbers'][0], $partner->id, $batch->tenant_id) : null;
                $document = $this->createDocument($header, array_column($lines, 1), $partner->id, $linked, $productId, $actorId, $batch->tenant_id);
                foreach ($lines as [$row, $data]) {
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                    $counts['lines']++;
                }
                $counts['linked'] += $linked ? 1 : 0;

                // Guard: the stored lines must land exactly on the Zoho total, or nothing is kept.
                if (abs((float) $document->total_amount - $header['document_total']) > 0.01) {
                    throw new RuntimeException("{$c['label']} {$header['number']}: ERP total {$document->total_amount} does not match the Zoho total {$header['document_total']}. Nothing was imported.");
                }

                if ($header['action'] === 'post') {
                    $byAccount = [];
                    $discount = 0.0;
                    foreach ($lines as [, $data]) {
                        $accountId = $this->lineAccount($data, $batch->tenant_id)->id;
                        $lineDiscount = $this->postsDiscounts() ? $this->lineDiscount($data) : 0.0;
                        $byAccount[$accountId] = round(($byAccount[$accountId] ?? 0) + $data['line_total'] + $lineDiscount, 2);
                        $discount = round($discount + $lineDiscount, 2);
                    }
                    arsort($byAccount);
                    $rate = (float) $header['exchange_rate'] ?: 1.0;
                    $base = ZohoJournalWriter::toBase($byAccount, $rate, (float) $document->total_amount + $discount);
                    $discountBase = round(array_sum($base) - round((float) $document->total_amount * $rate, 2), 2);
                    $this->journals->post([
                        'journal_number' => $this->journalNumber($header, $batch->tenant_id),
                        'journal_date' => $header['date'],
                        'reference_type' => $c['reference_type'],
                        'reference_id' => $document->id,
                        'description' => "{$c['journal_label']} #".$document->{$c['number_field']}.' (imported from Zoho)',
                        'currency_code' => $header['currency_code'],
                        'exchange_rate' => $rate,
                        'foreign_amount' => (float) $document->total_amount,
                    ], $this->journalLines($header, $base, $control, $partner->id, $discountBase, $discountBase > 0 ? $this->discountAccount($batch->tenant_id) : null), $actorId, $batch->tenant_id);
                    $counts['journal_entries']++;
                    $counts['posted']++;
                } else {
                    $counts['drafts']++;
                }
            }

            $summary = $counts + ['skipped_in_zoho_documents' => $skippedInZoho];
            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $summary]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => $c['stage_key'],
                'event_type' => 'stage_imported',
                'message' => ucfirst($c['label_plural'])." import completed: {$counts['posted']} approved with journal entries (balances open until the Credit applications step), {$counts['drafts']} drafts, {$counts['lines']} lines, {$counts['linked']} linked to their {$c['linked_label']}"
                    .($counts['skipped_existing'] ? ", {$counts['skipped_existing']} already in ERP" : '')
                    .($skippedInZoho ? '. Not imported: '.implode(', ', $skippedInZoho) : '').'.',
                'metadata' => $summary,
            ]);

            return $summary;
        });
    }

    /** Line amounts as stored on ERP credit/debit note lines, which do not calculate themselves. */
    protected function lineValues(array $data, int $productId): array
    {
        $subtotal = round($data['quantity'] * $data['unit_price'], 2);

        return [
            'product_id' => $productId,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'discount_percentage' => $subtotal > 0 ? round($data['discount_amount'] / $subtotal * 100, 2) : 0,
            'discount_amount' => $data['discount_amount'],
            'tax_percentage' => 0,
            'tax_amount' => 0,
            'total_amount' => $data['line_total'],
        ];
    }

    /** Document totals, plus notes that keep what the ERP lines cannot hold (Zoho line text) and Zoho context. */
    protected function documentValues(array $header, array $lines): array
    {
        $c = $this->config();
        $subtotal = round(array_sum(array_map(fn (array $line) => round($line['quantity'] * $line['unit_price'], 2), $lines)), 2);
        $discount = round(array_sum(array_column($lines, 'discount_amount')), 2);
        $total = round(array_sum(array_column($lines, 'line_total')), 2);
        $lineText = array_map(fn (array $line) => '- '.($line['description'] ?? '(no description in Zoho)').' — '.number_format($line['line_total'], 2), $lines);
        $notes = array_filter([
            $header['zoho_notes'],
            "Zoho lines:\n".implode("\n", $lineText),
            $header['terms'] !== '' ? "Terms & Conditions:\n{$header['terms']}" : '',
            $header['linked_numbers'] ? "Applied in Zoho to: ".implode(', ', $header['linked_numbers']) : '',
            'Zoho status: '.ucfirst(mb_strtolower($header['zoho_status'])),
        ]);

        return [
            'reason' => mb_substr($header['zoho_notes'] ?: ($header['subject'] ?: "{$c['journal_label']} imported from Zoho"), 0, 255),
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => 0,
            'total_amount' => $total,
            'applied_amount' => 0,
            'balance_amount' => $total,
            'notes' => implode("\n\n", $notes),
            'currency_code' => $header['currency_code'],
            'exchange_rate' => $header['exchange_rate'],
            'base_amount' => round($total * $header['exchange_rate'], 2),
        ];
    }

    protected function controlAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $this->config()['control_code'])->first();
    }

    private function journalNumber(array $row, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['zoho_id'], $this->config()['stage_key'].'-'.$row['number'], $row['date']);
    }

    private function key(array $row): string
    {
        return mb_strtolower($row['number']);
    }

    private function lineAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        $query = ChartOfAccount::where('created_by', $tenantId);

        return $row['account_code'] !== ''
            ? (clone $query)->where('account_code', $row['account_code'])->first()
            : ($row['account_name'] !== '' ? (clone $query)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($row['account_name'])])->first() : null);
    }

    private function header(array $row): array
    {
        return [$row['date'], mb_strtolower($row['partner']), $row['action'], $row['currency_code'], $row['exchange_rate'], $row['document_total']];
    }

    /** The line's item discount: full price less Zoho's Item Total. */
    protected function lineDiscount(array $row): float
    {
        return max(0.0, round(round($row['quantity'] * $row['unit_price'], 2) - $row['line_total'], 2));
    }
}
