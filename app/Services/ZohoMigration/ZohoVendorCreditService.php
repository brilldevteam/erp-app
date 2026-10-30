<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteItem;

/**
 * Zoho vendor credits as ERP debit notes: debit payables 2000, credit each line's Zoho account.
 *
 * Debit note numbers are unique across all companies, so the number is VC-<Zoho number>, with the company id
 * appended only when another company already uses that number.
 */
class ZohoVendorCreditService extends ZohoCreditDocumentService
{
    protected function config(): array
    {
        return [
            'stage_key' => 'vendor_credits',
            'id' => ['vendor credit id'],
            'number' => 'vendor credit number',
            'date' => 'vendor credit date',
            'status' => 'vendor credit status',
            'partner' => 'vendor name',
            'linked' => 'associated bill number',
            'label' => 'vendor credit',
            'label_plural' => 'vendor credits',
            'number_label' => 'Vendor Credit Number',
            'partner_label' => 'Vendor',
            'partner_step' => 'Vendors',
            'linked_label' => 'bill',
            'control_code' => ZohoBillService::PAYABLE_ACCOUNT_CODE,
            'control_name' => 'Accounts Payable',
            'reference_type' => 'debit_note',
            'journal_label' => 'Vendor Credit',
            'number_field' => 'debit_note_number',
            'route' => 'account.debit-notes.index',
        ];
    }

    protected function partnerUser(string $name, int $tenantId): ?User
    {
        return $this->vendorUser($name, $tenantId);
    }

    protected function linkedDocument(string $number, int $partnerId, int $tenantId): ?Model
    {
        return PurchaseInvoice::where('created_by', $tenantId)->where('vendor_id', $partnerId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }

    /** The ERP debit note imported for this Zoho vendor credit number. */
    public function findImported(string $zohoNumber, int $tenantId): ?DebitNote
    {
        return $this->existing($zohoNumber, $tenantId);
    }

    protected function existing(string $number, int $tenantId): ?Model
    {
        return DebitNote::where('created_by', $tenantId)->whereIn('debit_note_number', $this->candidateNumbers($number, $tenantId))->first();
    }

    protected function erpNumber(string $zohoNumber, int $tenantId): string
    {
        [$plain, $scoped] = $this->candidateNumbers($zohoNumber, $tenantId);

        return DebitNote::where('debit_note_number', $plain)->where('created_by', '<>', $tenantId)->exists() ? $scoped : $plain;
    }

    protected function createDocument(array $header, array $lines, int $partnerId, ?Model $linked, int $productId, int $actorId, int $tenantId): Model
    {
        $values = $this->documentValues($header, $lines);
        $number = $this->erpNumber($header['number'], $tenantId);
        if ($number !== $this->candidateNumbers($header['number'], $tenantId)[0]) {
            $values['notes'] .= "\n\nNumbered {$number} because {$this->candidateNumbers($header['number'], $tenantId)[0]} is used by another company.";
        }
        $note = DebitNote::create([
            'debit_note_number' => $number,
            'debit_note_date' => $header['date'],
            'vendor_id' => $partnerId,
            'invoice_id' => $linked?->id,
            'status' => $header['action'] === 'post' ? 'approved' : 'draft',
            'approved_by' => $header['action'] === 'post' ? $actorId : null,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ] + array_diff_key($values, array_flip(['currency_code', 'exchange_rate', 'base_amount'])));
        // Not mass-assignable on the ERP model, so set explicitly.
        $note->forceFill(array_intersect_key($values, array_flip(['currency_code', 'exchange_rate', 'base_amount'])))->save();

        foreach ($lines as $line) {
            DebitNoteItem::create(['debit_note_id' => $note->id, 'creator_id' => $actorId, 'created_by' => $tenantId] + $this->lineValues($line, $productId));
        }

        return $note->fresh();
    }

    protected function journalLines(array $header, array $baseByAccount, ChartOfAccount $control, int $partnerId, float $discountBase = 0.0, ?ChartOfAccount $discountAccount = null): array
    {
        $lines = [['account' => $control, 'debit' => round(array_sum($baseByAccount), 2), 'credit' => 0.0, 'description' => 'Vendor Credit '.$header['number'].' - '.$header['partner'], 'contact_id' => $partnerId]];
        foreach ($baseByAccount as $accountId => $amount) {
            $lines[] = ['account' => ChartOfAccount::findOrFail($accountId), 'debit' => 0.0, 'credit' => $amount, 'description' => 'Vendor credit adjustment'];
        }

        return $lines;
    }

    /** [VC-<number>, VC-<number>-<company>] */
    private function candidateNumbers(string $zohoNumber, int $tenantId): array
    {
        $plain = 'VC-'.$zohoNumber;

        return [$plain, "{$plain}-{$tenantId}"];
    }
}
