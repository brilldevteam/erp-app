<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Shared Zoho contact-export import for the Customers and Vendors stages. Each contact becomes an ERP
 * record keyed by its Zoho Display Name, the name Zoho documents refer to, and is written through the
 * ERP bulk-import definition for that record type.
 */
abstract class ZohoContactImportService
{
    private const SHIPPING_FIELDS = ['shipping_name', 'shipping_address_line_1', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_zip_code'];

    public function __construct(protected readonly ZohoProfileCatalog $profiles)
    {
    }

    abstract protected function definition(): EntityDefinition;

    /** Stage key, Zoho contact type, singular/plural nouns, the other contact step, and the ERP list route. */
    abstract protected function labels(): array;

    /** Fills the definition's name and email fields from the Zoho display name, contact person and email. */
    abstract protected function names(string $displayName, string $person, string $email): array;

    /** The definition's "<name field> is required." message, reported as a missing Zoho Display Name. */
    abstract protected function requiredNameMessage(): string;

    /** Whether the ERP definition matches existing records by email, so emails must be unique per file. */
    protected function matchesByEmail(): bool
    {
        return false;
    }

    public function prepare(array $values, string $baseCurrency): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        // Zoho multi-line addresses become one line, as the ERP address lines are single-line fields.
        $line = fn (string $key): string => trim((string) preg_replace('/\s*[\r\n]+\s*/', ', ', $get($key)), ', ');

        $displayName = $get('display name') ?: $get('contact name');
        $person = trim(implode(' ', array_filter([$get('salutation'), $get('first name'), $get('last name')])));
        $shipping = [
            'shipping_name' => $get('shipping attention'),
            'shipping_address_line_1' => $line('shipping address'),
            'shipping_address_line_2' => $line('shipping street2'),
            'shipping_city' => $get('shipping city'),
            'shipping_state' => $get('shipping state'),
            'shipping_country' => $get('shipping country'),
            'shipping_zip_code' => $get('shipping code'),
        ];
        // The ERP requires a complete separate shipping address; a partial Zoho one is kept in the notes instead.
        $shippingComplete = !in_array('', array_intersect_key($shipping, array_flip(self::SHIPPING_FIELDS)), true);
        $partialShipping = !$shippingComplete ? implode(', ', array_filter($shipping)) : '';
        $currency = strtoupper($get('currency code'));

        $notes = array_filter([
            $get('notes'),
            $currency !== '' && $currency !== strtoupper($baseCurrency) ? "Zoho currency: {$currency}" : '',
            mb_strtolower($get('status')) === 'inactive' ? 'Zoho status: Inactive' : '',
            $get('website') !== '' ? 'Website: '.$get('website') : '',
            $partialShipping !== '' ? "Zoho shipping address: {$partialShipping}" : '',
        ]);

        $row = [
            'zoho_contact_id' => $get('contact id'),
            'contact_type' => mb_strtolower($get('contact type')),
            ...$this->names($displayName, $person, $get('emailid')),
            'mobile_no' => $get('mobilephone') ?: ($get('phone') ?: $get('billing phone')),
            'company_name' => $displayName,
            'tax_number' => '',
            'payment_terms' => $get('payment terms label'),
            'billing_name' => $get('billing attention'),
            'billing_address_line_1' => $line('billing address'),
            'billing_address_line_2' => $line('billing street2'),
            'billing_city' => $get('billing city'),
            'billing_state' => $get('billing state'),
            'billing_country' => $get('billing country'),
            'billing_zip_code' => $get('billing code'),
            'same_as_billing' => $shippingComplete ? 'no' : 'yes',
            ...($shippingComplete ? $shipping : array_fill_keys(array_keys($shipping), '')),
            'notes' => implode("\n", $notes),
        ];

        return $this->definition()->prepare($row) + ['_mapped_fields' => $this->definition()->headers()];
    }

    /**
     * @param  array<string, array{row: int, name: string}>  $seen  Names (and emails, when matched by email) already seen in this pass.
     */
    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array &$seen): array
    {
        $labels = $this->labels();
        $errors = [];
        if (($row['contact_type'] ?? '') !== '' && $row['contact_type'] !== $labels['contact_type']) {
            $errors[] = 'This Zoho contact is a '.$row['contact_type'].", not a {$labels['singular']}. Import it in the {$labels['other_step']} step instead.";
        }
        $errors = [...$errors, ...array_map(
            fn (string $message) => $message === $this->requiredNameMessage() ? 'Display Name is required.' : $message,
            $this->definition()->validate($row, $batch->tenant_id)
        )];

        $name = (string) ($row['company_name'] ?? '');
        $key = 'name:'.mb_strtolower($name);
        if ($name !== '' && isset($seen[$key])) {
            $errors[] = $seen[$key]['name'] === $name
                ? "Display Name appears more than once in this file (rows {$seen[$key]['row']} and {$sourceRowNumber})."
                : "Display Name '{$name}' matches row {$seen[$key]['row']} ('{$seen[$key]['name']}') apart from letter case. ERP treats them as one {$labels['singular']}: rename or remove one of them in the file, then upload it again.";
        } elseif ($name !== '') {
            $seen[$key] = ['row' => $sourceRowNumber, 'name' => $name];
        }

        $email = $this->matchesByEmail() ? mb_strtolower((string) ($row[$labels['email_field']] ?? '')) : '';
        if ($email !== '' && isset($seen['email:'.$email])) {
            $errors[] = "Email {$email} is also used by row {$seen['email:'.$email]['row']} ('{$seen['email:'.$email]['name']}'). ERP matches {$labels['plural']} by email, so they would merge into one: give each its own email, or clear one, then upload the file again.";
        } elseif ($email !== '') {
            $seen['email:'.$email] = ['row' => $sourceRowNumber, 'name' => $name];
        }

        if ($name !== '' && $batch->duplicate_strategy === 'block' && $this->definition()->duplicate($row, $batch->tenant_id)) {
            $errors[] = "A {$labels['singular']} with this name".($this->matchesByEmail() ? ' or email' : '').' already exists in ERP and this migration is configured to block duplicates.';
        }

        return array_map(fn (string $message) => [
            'type' => "invalid_{$labels['singular']}",
            'field' => 'display_name',
            'source_value' => $name,
            'message' => $message,
            'resolution_url' => Route::has($labels['route']) ? route($labels['route']) : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $labels = $this->labels();
        $stageKey = $labels['stage_key'];
        $stage = $batch->stages()->where('stage_key', $stageKey)->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException("Resolve all validation issues before importing {$labels['plural']}.");
        }

        $rows = $batch->files()->where('stage_key', $stageKey)->where('status', 'staged')
            ->with(['stagedRows' => fn ($query) => $query->where('status', 'validated')->orderBy('source_row_number')])
            ->get()->flatMap->stagedRows;
        if ($rows->isEmpty()) {
            throw new RuntimeException("No validated {$labels['singular']} rows are ready to import.");
        }

        // Rebuild every row from the Zoho values and re-run the checks, so rows validated by an
        // older rule set (or edited since) can never be imported in a stale shape.
        $seen = [];
        $prepared = [];
        foreach ($rows as $row) {
            $data = $this->prepare($row->original_values, (string) $batch->base_currency);
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $seen)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $prepared[] = [$row, $data];
        }

        // Duplicates are rejected during validation when blocked, so only "update" changes existing records.
        $strategy = $batch->duplicate_strategy === 'update' ? 'update' : 'skip';

        return DB::transaction(function () use ($prepared, $batch, $actorId, $stage, $strategy, $labels, $stageKey): array {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($prepared as [$row, $data]) {
                $result = $this->definition()->import($data, $strategy, $batch->tenant_id, $actorId);
                $counts[$result]++;
                $row->update(['status' => $result, 'normalized_values' => $data]);
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
                'stage_key' => $stageKey,
                'event_type' => 'stage_imported',
                'message' => ucfirst($labels['plural'])." import completed: {$counts['imported']} created, {$counts['updated']} updated, {$counts['skipped']} already existed.",
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }
}
