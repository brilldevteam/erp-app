<?php

namespace App\Services\ZohoMigration;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteItem;

/**
 * Zoho credit notes as ERP credit notes: debit each line's Zoho revenue account, credit receivables 1100.
 */
class ZohoCustomerCreditService extends ZohoCreditDocumentService
{
    protected function config(): array
    {
        return [
            'stage_key' => 'credit_notes',
            'id' => ['creditnotes id', 'credit note id'],
            'number' => 'credit note number',
            'date' => 'credit note date',
            'status' => 'credit note status',
            'partner' => 'customer name',
            'linked' => 'applied invoice number',
            'label' => 'credit note',
            'label_plural' => 'credit notes',
            'number_label' => 'Credit Note Number',
            'partner_label' => 'Customer',
            'partner_step' => 'Customers',
            'linked_label' => 'invoice',
            'control_code' => ZohoSalesInvoiceService::RECEIVABLE_ACCOUNT_CODE,
            'control_name' => 'Accounts Receivable',
            'reference_type' => 'credit_note',
            'journal_label' => 'Credit Note',
            'number_field' => 'credit_note_number',
            'route' => 'account.credit-notes.index',
        ];
    }

    protected function partnerUser(string $name, int $tenantId): ?User
    {
        return $this->customerUser($name, $tenantId);
    }

    protected function linkedDocument(string $number, int $partnerId, int $tenantId): ?Model
    {
        return SalesInvoice::where('created_by', $tenantId)->where('customer_id', $partnerId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }

    protected function existing(string $number, int $tenantId): ?Model
    {
        return CreditNote::where('created_by', $tenantId)->whereRaw('LOWER(credit_note_number) = ?', [mb_strtolower($number)])->first();
    }

    protected function erpNumber(string $zohoNumber, int $tenantId): string
    {
        return $zohoNumber;
    }

    protected function createDocument(array $header, array $lines, int $partnerId, ?Model $linked, int $productId, int $actorId, int $tenantId): Model
    {
        $values = $this->documentValues($header, $lines);
        $note = CreditNote::create([
            'credit_note_number' => $this->erpNumber($header['number'], $tenantId),
            'credit_note_date' => $header['date'],
            'customer_id' => $partnerId,
            'invoice_id' => $linked?->id,
            'status' => $header['action'] === 'post' ? 'approved' : 'draft',
            'approved_by' => $header['action'] === 'post' ? $actorId : null,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ] + array_diff_key($values, array_flip(['currency_code', 'exchange_rate', 'base_amount'])));
        // Not mass-assignable on the ERP model, so set explicitly.
        $note->forceFill(array_intersect_key($values, array_flip(['currency_code', 'exchange_rate', 'base_amount'])))->save();

        foreach ($lines as $line) {
            CreditNoteItem::create(['credit_note_id' => $note->id] + $this->lineValues($line, $productId));
        }

        return $note->fresh();
    }

    protected function postsDiscounts(): bool
    {
        return true;
    }

    protected function journalLines(array $header, array $baseByAccount, ChartOfAccount $control, int $partnerId, float $discountBase = 0.0, ?ChartOfAccount $discountAccount = null): array
    {
        $lines = [];
        foreach ($baseByAccount as $accountId => $amount) {
            $lines[] = ['account' => ChartOfAccount::findOrFail($accountId), 'debit' => $amount, 'credit' => 0.0, 'description' => 'Credit note adjustment'];
        }
        if ($discountBase > 0) {
            $lines[] = ['account' => $discountAccount, 'debit' => 0.0, 'credit' => $discountBase, 'description' => 'Item discounts on credit note '.$header['number']];
        }
        $lines[] = ['account' => $control, 'debit' => 0.0, 'credit' => round(array_sum($baseByAccount) - $discountBase, 2), 'description' => 'Credit Note '.$header['number'].' - '.$header['partner'], 'contact_id' => $partnerId];

        return $lines;
    }
}
