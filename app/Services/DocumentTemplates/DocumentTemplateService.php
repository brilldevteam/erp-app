<?php

namespace App\Services\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workdo\Quotation\Models\SalesQuotation;

class DocumentTemplateService
{
    public const TYPES = [
        DocumentTemplate::TYPE_QUOTATION,
        DocumentTemplate::TYPE_INVOICE,
    ];

    public const DEFAULT_CONFIG = [
        'header' => [
            'showLogo' => true,
            'showCompanyName' => true,
            'showCompanyAddress' => true,
            'showContactDetails' => true,
            'alignment' => 'left',
        ],
        'customerBlock' => [
            'showBillingAddress' => true,
            'showShippingAddress' => true,
            'showContactPerson' => true,
        ],
        'documentDetails' => [
            'showDocumentNumber' => true,
            'showDocumentDate' => true,
            'showDueDate' => true,
        ],
        'itemsTable' => [
            'columns' => ['item', 'description', 'quantity', 'rate', 'tax', 'total'],
        ],
        'totals' => [
            'showSubtotal' => true,
            'showDiscount' => true,
            'showTax' => true,
            'showGrandTotal' => true,
        ],
        'footer' => [
            'showTerms' => true,
            'showNotes' => true,
            'showBankDetails' => true,
            'showSignature' => true,
            'footerText' => 'Thank you for your business.',
        ],
    ];

    public function normalizeConfig(?array $config): array
    {
        $config = $config ?: [];

        return array_replace_recursive(self::DEFAULT_CONFIG, $config);
    }

    public function create(int $companyId, int $userId, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($companyId, $userId, $data) {
            $template = new DocumentTemplate($this->attributes($data));
            $template->company_id = $companyId;
            $template->created_by = $userId;
            $template->updated_by = $userId;
            $template->config_json = $this->normalizeConfig($data['config_json'] ?? []);

            $this->validateDefaultStatus($template);

            if ($template->is_default) {
                $this->clearDefault($companyId, $template->type);
            } elseif (!$this->hasDefault($companyId, $template->type)) {
                $template->is_default = true;
            }

            $template->save();

            return $template;
        });
    }

    public function update(DocumentTemplate $template, int $userId, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($template, $userId, $data) {
            $originalType = $template->type;
            $template->fill($this->attributes($data));
            $template->updated_by = $userId;
            $template->config_json = $this->normalizeConfig($data['config_json'] ?? $template->config_json);

            $this->validateDefaultStatus($template);

            if ($template->is_default) {
                $this->clearDefault($template->company_id, $template->type, $template->id);
            }

            $template->save();

            if ($originalType !== $template->type && !$this->hasDefault($template->company_id, $originalType)) {
                $this->firstActive($template->company_id, $originalType)?->update(['is_default' => true]);
            }

            if (!$this->hasDefault($template->company_id, $template->type)) {
                $template->forceFill(['is_default' => true])->save();
            }

            return $template->refresh();
        });
    }

    public function duplicate(DocumentTemplate $template, int $userId): DocumentTemplate
    {
        return DB::transaction(function () use ($template, $userId) {
            $copy = $template->replicate();
            $copy->name = $this->copyName($template);
            $copy->is_default = false;
            $copy->status = DocumentTemplate::STATUS_INACTIVE;
            $copy->created_by = $userId;
            $copy->updated_by = $userId;
            $copy->save();

            return $copy;
        });
    }

    public function delete(DocumentTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $wasDefault = $template->is_default;
            $companyId = $template->company_id;
            $type = $template->type;
            $template->delete();

            if ($wasDefault) {
                $this->firstActive($companyId, $type)?->update(['is_default' => true]);
            }
        });
    }

    public function setDefault(DocumentTemplate $template): DocumentTemplate
    {
        if ($template->status !== DocumentTemplate::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'status' => __('Only active templates can be used as default.'),
            ]);
        }

        return DB::transaction(function () use ($template) {
            $this->clearDefault($template->company_id, $template->type, $template->id);
            $template->forceFill(['is_default' => true])->save();

            return $template->refresh();
        });
    }

    public function resolveForDocument(string $type, int $companyId, ?int $templateId): DocumentTemplate
    {
        if ($templateId) {
            $template = DocumentTemplate::query()
                ->forCompany($companyId)
                ->forType($type)
                ->active()
                ->find($templateId);

            if ($template) {
                return $template;
            }

            throw ValidationException::withMessages([
                'document_template_id' => __('The selected template is not available for this document type.'),
            ]);
        }

        $template = DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->active()
            ->where('is_default', true)
            ->first();

        return $template ?: $this->ensureDefault($companyId, $type);
    }

    public function ensureDefault(int $companyId, string $type): DocumentTemplate
    {
        $template = DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->active()
            ->where('is_default', true)
            ->first();

        if ($template) {
            return $template;
        }

        $template = DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->active()
            ->first();

        if ($template) {
            $this->setDefault($template);
            return $template->refresh();
        }

        return DocumentTemplate::create([
            'company_id' => $companyId,
            'name' => $type === DocumentTemplate::TYPE_INVOICE ? __('Standard Invoice') : __('Standard Quotation'),
            'type' => $type,
            'status' => DocumentTemplate::STATUS_ACTIVE,
            'is_default' => true,
            'primary_color' => company_setting('theme_color', $companyId) ?: '#10b981',
            'logo_url' => company_setting('logo_dark', $companyId) ?: company_setting('logo_light', $companyId),
            'config_json' => self::DEFAULT_CONFIG,
            'terms' => '',
            'notes' => '',
            'bank_details' => '',
            'signature_text' => __('Authorized Signature'),
            'created_by' => auth()->id() ?: $companyId,
            'updated_by' => auth()->id() ?: $companyId,
        ]);
    }

    public function sampleDocument(string $type, ?DocumentTemplate $template = null): array
    {
        $template = $template ?: new DocumentTemplate([
            'name' => __('Sample Template'),
            'type' => $type,
            'primary_color' => '#10b981',
            'config_json' => self::DEFAULT_CONFIG,
            'terms' => __('Payment is due according to the agreed terms.'),
            'notes' => __('This is a sample preview.'),
            'bank_details' => __('Bank: Sample Bank') . "\n" . __('Account: 000-123456'),
            'signature_text' => __('Authorized Signature'),
        ]);

        return [
            'type' => $type,
            'template' => $template->toArray(),
            'company' => [
                'name' => company_setting('company_name', creatorId()) ?: __('Your Company'),
                'address' => company_setting('company_address', creatorId()) ?: '120 Business Avenue',
                'city' => company_setting('company_city', creatorId()) ?: 'Colombo',
                'country' => company_setting('company_country', creatorId()) ?: 'Sri Lanka',
                'phone' => company_setting('company_telephone', creatorId()) ?: '+94 11 234 5678',
                'email' => company_setting('company_email', creatorId()) ?: 'accounts@example.com',
                'logo' => $template->logo_url,
            ],
            'customer' => [
                'name' => 'Northstar Trading',
                'contact_person' => 'Sample Customer',
                'email' => 'customer@example.com',
                'billing_address' => ['48 Lake Road', 'Colombo 05', 'Sri Lanka'],
                'shipping_address' => ['22 Warehouse Lane', 'Colombo 03', 'Sri Lanka'],
            ],
            'number' => $type === DocumentTemplate::TYPE_INVOICE ? 'SI-2026-06-001' : 'QT-2026-06-001',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'items' => [
                ['item' => 'Consulting services', 'description' => 'Implementation and configuration', 'quantity' => 8, 'rate' => 125, 'tax' => 142.50, 'total' => 1092.50],
                ['item' => 'Annual support', 'description' => 'Priority support subscription', 'quantity' => 1, 'rate' => 600, 'tax' => 90, 'total' => 690],
            ],
            'totals' => ['subtotal' => 1600, 'discount' => 50, 'tax' => 232.50, 'grand_total' => 1782.50],
        ];
    }

    public function documentFromModel(string $type, Model $document, DocumentTemplate $template): array
    {
        $companyId = (int) $document->created_by;
        $customerDetails = $document->customerDetails;
        $billing = $this->addressLines($customerDetails?->billing_address);
        $shipping = $this->addressLines($customerDetails?->shipping_address);

        return [
            'type' => $type,
            'template' => $template->toArray(),
            'company' => [
                'name' => company_setting('company_name', $companyId) ?: '',
                'address' => company_setting('company_address', $companyId),
                'city' => company_setting('company_city', $companyId),
                'country' => company_setting('company_country', $companyId),
                'phone' => company_setting('company_telephone', $companyId),
                'email' => company_setting('company_email', $companyId),
                'logo' => $template->logo_url,
            ],
            'customer' => [
                'name' => $customerDetails?->company_name ?: $document->customer?->name,
                'contact_person' => $customerDetails?->contact_person_name ?: $document->customer?->name,
                'email' => $document->customer?->email,
                'billing_address' => $billing,
                'shipping_address' => $shipping,
            ],
            'number' => $type === DocumentTemplate::TYPE_INVOICE
                ? ($document instanceof SalesInvoice ? $document->invoice_number : '')
                : ($document instanceof SalesQuotation ? $document->quotation_number : ''),
            'date' => $type === DocumentTemplate::TYPE_INVOICE
                ? optional($document->invoice_date)->format('Y-m-d')
                : optional($document->quotation_date)->format('Y-m-d'),
            'due_date' => optional($document->due_date)->format('Y-m-d'),
            'items' => $document->items->map(fn ($item) => [
                'item' => $item->product?->name ?: __('Item'),
                'description' => $item->product?->description,
                'quantity' => (float) $item->quantity,
                'rate' => (float) $item->unit_price,
                'tax' => (float) $item->tax_amount,
                'has_tax' => $item->taxes->isNotEmpty() || (float) $item->tax_percentage > 0,
                'total' => (float) $item->total_amount,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (float) $document->subtotal,
                'discount' => (float) $document->discount_amount,
                'tax' => (float) $document->tax_amount,
                'grand_total' => (float) $document->total_amount,
            ],
        ];
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'status' => $data['status'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'primary_color' => $data['primary_color'] ?? '#10b981',
            'logo_url' => $data['logo_url'] ?? null,
            'terms' => $data['terms'] ?? null,
            'notes' => $data['notes'] ?? null,
            'bank_details' => $data['bank_details'] ?? null,
            'signature_text' => $data['signature_text'] ?? null,
        ];
    }

    private function clearDefault(int $companyId, string $type, ?int $exceptId = null): void
    {
        DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->update(['is_default' => false]);
    }

    private function validateDefaultStatus(DocumentTemplate $template): void
    {
        if ($template->is_default && $template->status !== DocumentTemplate::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'is_default' => __('Only active templates can be set as default.'),
            ]);
        }
    }

    private function hasDefault(int $companyId, string $type): bool
    {
        return DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->where('is_default', true)
            ->exists();
    }

    private function firstActive(int $companyId, string $type): ?DocumentTemplate
    {
        return DocumentTemplate::query()
            ->forCompany($companyId)
            ->forType($type)
            ->active()
            ->oldest()
            ->first();
    }

    private function copyName(DocumentTemplate $template): string
    {
        $base = $template->name . ' Copy';
        $name = $base;
        $count = 2;

        while (DocumentTemplate::where('company_id', $template->company_id)->where('name', $name)->exists()) {
            $name = "{$base} {$count}";
            $count++;
        }

        return $name;
    }

    private function addressLines(mixed $address): array
    {
        if (is_string($address)) {
            $address = json_decode($address, true) ?: [];
        }

        if (!is_array($address)) {
            return [];
        }

        return array_values(array_filter([
            $address['name'] ?? null,
            $address['address_line_1'] ?? null,
            $address['address_line_2'] ?? null,
            trim(implode(', ', array_filter([$address['city'] ?? null, $address['state'] ?? null]))) ?: null,
            trim(implode(' ', array_filter([$address['zip_code'] ?? null, $address['country'] ?? null]))) ?: null,
        ]));
    }
}
