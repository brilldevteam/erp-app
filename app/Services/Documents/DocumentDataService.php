<?php

namespace App\Services\Documents;

use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Workdo\Quotation\Models\SalesQuotation;

class DocumentDataService
{
    public function __construct(
        private readonly DocumentSettingsService $settings,
        private readonly DocumentQrCodeService $qrCodes,
    ) {
    }

    public function find(string $type, int $id): Model
    {
        $model = $type === 'quotation'
            ? SalesQuotation::with(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse', 'invoice'])->findOrFail($id)
            : SalesInvoice::with(['customer', 'customerDetails', 'items.product', 'items.taxes', 'warehouse', 'quotation'])->findOrFail($id);

        return $model;
    }

    public function assertTenant(Model $document, int $tenantId): void
    {
        abort_unless((int) $document->created_by === $tenantId, 404);
    }

    public function snapshot(string $type, Model $document): array
    {
        $tenantId = (int) $document->created_by;
        $settings = $this->settings->get($tenantId, $type, $document->template_key);
        $templateKey = $document->template_key ?: $settings['template_key'];

        return [
            'settings' => array_merge($settings, ['template_key' => $templateKey]),
            'company' => [
                'name' => company_setting('company_name', $tenantId) ?: '',
                'address' => company_setting('company_address', $tenantId),
                'city' => company_setting('company_city', $tenantId),
                'state' => company_setting('company_state', $tenantId),
                'postal_code' => company_setting('company_zipcode', $tenantId),
                'country' => company_setting('company_country', $tenantId),
                'phone' => company_setting('company_telephone', $tenantId),
                'email' => company_setting('company_email', $tenantId),
                'website' => company_setting('company_website', $tenantId) ?: company_setting('company_url', $tenantId),
                'registration_number' => company_setting('registration_number', $tenantId),
                'tax_number' => company_setting('vat_gst_number', $tenantId),
                'logo' => $document->document_logo,
                'logo_dark' => $document->document_logo,
            ],
            'currency' => [
                'symbol' => company_setting('currencySymbol', $tenantId) ?: '$',
                'position' => company_setting('currencySymbolPosition', $tenantId) ?: 'pre',
            ],
            'customer' => [
                'name' => $document->customer?->name,
                'email' => $document->customer?->email,
                'company_name' => $document->customerDetails?->company_name,
                'tax_number' => $document->customerDetails?->tax_number,
                'billing_address' => $document->customerDetails?->billing_address,
                'shipping_address' => $document->customerDetails?->shipping_address,
            ],
            'qr' => $settings['show_qr']
                ? $this->qrCodes->for($type, $document, $tenantId)
                : null,
        ];
    }

    public function normalize(string $type, Model $document, bool $preferSnapshot = true): array
    {
        $snapshot = $preferSnapshot && $document->document_snapshot
            ? $document->document_snapshot
            : $this->snapshot($type, $document);
        $settings = array_merge(
            $this->settings->get((int) $document->created_by, $type, $document->template_key),
            $snapshot['settings'] ?? [],
            ['template_key' => $document->template_key ?: ($snapshot['settings']['template_key'] ?? 'zoho')],
        );
        $current = $this->snapshot($type, $document);
        $company = array_merge($current['company'], $snapshot['company'] ?? []);
        if (($company['name'] ?? '') === config('app.name') && !company_setting('company_name', (int) $document->created_by)) {
            $company['name'] = '';
        }
        $company['logo'] = $document->document_logo;
        $company['logo_dark'] = $document->document_logo;
        $currency = array_merge($current['currency'], $snapshot['currency'] ?? []);
        $customer = array_merge($current['customer'], $snapshot['customer'] ?? []);
        $snapshotQr = !empty($snapshot['qr']) ? $this->qrCodes->refresh($snapshot['qr']) : null;
        $qr = $settings['show_qr']
            ? ($snapshotQr ?: $this->qrCodes->for($type, $document, (int) $document->created_by))
            : null;

        if ($qr && $preferSnapshot && $document->document_snapshot && ($snapshot['qr'] ?? null) !== $qr) {
            $snapshot['qr'] = $qr;
            $document->forceFill(['document_snapshot' => $snapshot])->save();
        }

        return [
            'type' => $type,
            'label' => $settings['document_title'],
            'template_key' => $settings['template_key'],
            'settings' => $settings,
            'company' => $company,
            'currency' => $currency,
            'customer' => $customer,
            'qr' => $qr,
            'number' => $type === 'quotation' ? $document->quotation_number : $document->invoice_number,
            'date' => $type === 'quotation' ? $document->quotation_date : $document->invoice_date,
            'due_date' => $document->due_date,
            'status' => $document->display_status ?? $document->status,
            'reference' => $type === 'invoice' ? $document->quotation?->quotation_number : null,
            'items' => $document->items->map(fn ($item) => [
                'name' => $item->product?->name ?: __('Item'),
                'sku' => $item->product?->sku,
                'description' => $item->product?->description,
                'quantity' => (float) $item->quantity,
                'unit' => $item->product?->unit,
                'unit_price' => (float) $item->unit_price,
                'discount_percentage' => (float) $item->discount_percentage,
                'discount_amount' => (float) $item->discount_amount,
                'tax_amount' => (float) $item->tax_amount,
                'taxes' => $item->taxes->map(fn ($tax) => [
                    'name' => $tax->tax_name,
                    'rate' => (float) $tax->tax_rate,
                ])->values()->all(),
                'total' => (float) $item->total_amount,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (float) $document->subtotal,
                'discount' => (float) $document->discount_amount,
                'tax' => (float) $document->tax_amount,
                'total' => (float) $document->total_amount,
                'paid' => $type === 'invoice' ? (float) $document->paid_amount : 0,
                'balance' => $type === 'invoice' ? (float) $document->balance_amount : (float) $document->total_amount,
            ],
            'payment_terms' => $document->payment_terms,
            'notes' => $document->notes,
        ];
    }

    public function persistSnapshot(string $type, Model $document): void
    {
        if (!$document->document_snapshot) {
            $document->forceFill(['document_snapshot' => $this->snapshot($type, $document)])->save();
        }
    }
}
