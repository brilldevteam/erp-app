<?php

namespace App\Http\Controllers;

use App\Services\Documents\DocumentQrCodeService;
use App\Services\Documents\DocumentSettingsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentSettingsController extends Controller
{
    public function __construct(
        private readonly DocumentSettingsService $settings,
        private readonly DocumentQrCodeService $qrCodes,
    ) {
    }

    public function index(string $type = 'invoice'): Response
    {
        abort_unless(in_array($type, ['invoice', 'quotation'], true), 404);
        $tenantId = creatorId();
        $documentSettings = $this->settings->get($tenantId, $type);

        return Inertia::render('documents/settings', [
            'settings' => array_merge(
                $documentSettings,
                [
                    'invoice_template' => company_setting('invoice_template', $tenantId) ?: 'zoho',
                    'quotation_template' => company_setting('quotation_template', $tenantId) ?: 'zoho',
                    'invoice_document_title' => $type === 'invoice' ? $documentSettings['document_title'] : (company_setting('invoice_document_title', $tenantId) ?: 'INVOICE'),
                    'quotation_document_title' => $type === 'quotation' ? $documentSettings['document_title'] : (company_setting('quotation_document_title', $tenantId) ?: 'QUOTATION'),
                    'document_footer' => $documentSettings['footer'],
                    'document_payment_instructions' => $documentSettings['payment_instructions'],
                    'invoice_number_prefix' => company_setting('invoice_number_prefix', $tenantId) ?: 'SI',
                    'quotation_number_prefix' => company_setting('quotation_number_prefix', $tenantId) ?: 'QT',
                    'invoice_next_number' => (int) (company_setting('invoice_next_number', $tenantId) ?: 1),
                    'quotation_next_number' => (int) (company_setting('quotation_next_number', $tenantId) ?: 1),
                    'document_number_padding' => (int) (company_setting('document_number_padding', $tenantId) ?: 3),
                    'document_number_reset' => company_setting('document_number_reset', $tenantId) ?: 'monthly',
                    'invoice_reminder_offsets' => company_setting('invoice_reminder_offsets', $tenantId) ?: '-3,0,3,7',
                    'document_default_logo' => $documentSettings['document_default_logo'],
                    'document_signature_image' => $documentSettings['signature_image'],
                ]
            ),
            'templates' => DocumentSettingsService::TEMPLATES,
            'documentType' => $type,
            'templateProfiles' => collect(DocumentSettingsService::TEMPLATES)
                ->mapWithKeys(fn ($template) => [$template => $this->settings->get($tenantId, $type, $template)]),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'invoice_template' => ['required', Rule::in(DocumentSettingsService::TEMPLATES)],
            'quotation_template' => ['required', Rule::in(DocumentSettingsService::TEMPLATES)],
            'document_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_document_title' => ['required', 'string', 'max:50'],
            'quotation_document_title' => ['required', 'string', 'max:50'],
            'document_footer' => ['nullable', 'string', 'max:500'],
            'document_payment_instructions' => ['nullable', 'string', 'max:2000'],
            'invoice_number_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]+$/'],
            'quotation_number_prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]+$/'],
            'invoice_next_number' => ['required', 'integer', 'min:1'],
            'quotation_next_number' => ['required', 'integer', 'min:1'],
            'document_number_padding' => ['required', 'integer', 'between:2,8'],
            'document_number_reset' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
            'invoice_reminder_offsets' => ['nullable', 'regex:/^-?\d+(,-?\d+)*$/'],
            'document_default_logo' => ['nullable', 'string', 'max:500'],
            'document_signature_image' => ['nullable', 'string', 'max:500'],
            'document_type' => ['required', Rule::in(['invoice', 'quotation'])],
            'profile_template' => ['required', Rule::in(DocumentSettingsService::TEMPLATES)],
            'document_show_sku' => ['boolean'],
            'document_show_description' => ['boolean'],
            'document_show_quantity' => ['boolean'],
            'document_show_discount' => ['boolean'],
            'document_show_tax' => ['boolean'],
            'document_show_shipping' => ['boolean'],
            'document_show_signature' => ['boolean'],
            'document_show_qr' => ['boolean'],
        ]);

        $this->settings->update(creatorId(), $validated);
        $this->settings->updateProfile(creatorId(), $validated['document_type'], $validated['profile_template'], $validated);

        return back()->with('success', __('Template, logo, footer, and payment instructions saved successfully.'));
    }

    public function sample(string $type, string $template)
    {
        abort_unless(in_array($type, ['invoice', 'quotation'], true), 404);
        abort_unless(in_array($template, DocumentSettingsService::TEMPLATES, true), 404);

        $settings = $this->settings->get(creatorId(), $type, $template);

        return view('documents.show', [
            'document' => $this->sampleDocument($type, $settings),
            'publicMode' => false,
        ]);
    }

    private function sampleDocument(string $type, array $settings): array
    {
        return [
            'type' => $type,
            'label' => $settings['document_title'],
            'template_key' => $settings['template_key'],
            'settings' => $settings,
            'company' => [
                'name' => company_setting('company_name', creatorId()) ?: __('Your Company'),
                'address' => '120 Business Avenue', 'city' => 'Colombo', 'state' => null,
                'postal_code' => '00300', 'country' => 'Sri Lanka', 'phone' => '+94 11 234 5678',
                'email' => 'accounts@example.com', 'website' => 'www.example.com', 'registration_number' => 'PV 12345',
                'tax_number' => 'VAT 987654', 'logo' => $settings['document_default_logo'],
                'logo_dark' => $settings['document_default_logo'],
            ],
            'currency' => ['symbol' => company_setting('currencySymbol', creatorId()) ?: '$', 'position' => 'pre'],
            'customer' => [
                'name' => 'Sample Customer', 'email' => 'customer@example.com', 'company_name' => 'Northstar Trading',
                'tax_number' => 'TAX-10020',
                'billing_address' => ['address_line_1' => '48 Lake Road', 'city' => 'Colombo 05', 'country' => 'Sri Lanka'],
                'shipping_address' => ['address_line_1' => '22 Warehouse Lane', 'city' => 'Colombo 03', 'country' => 'Sri Lanka'],
            ],
            'number' => $type === 'invoice' ? 'SI-2026-06-001' : 'QT-2026-06-001',
            'date' => Carbon::today(), 'due_date' => Carbon::today()->addDays(14), 'status' => 'draft', 'reference' => null,
            'items' => [
                ['name' => 'Consulting services', 'sku' => 'SERV-01', 'description' => 'Implementation and configuration', 'quantity' => 8, 'unit' => 'hour', 'unit_price' => 125, 'discount_percentage' => 5, 'discount_amount' => 50, 'tax_amount' => 142.5, 'taxes' => [['name' => 'VAT', 'rate' => 15]], 'total' => 1092.5],
                ['name' => 'Annual support', 'sku' => 'SUP-12', 'description' => 'Priority support subscription', 'quantity' => 1, 'unit' => 'year', 'unit_price' => 600, 'discount_percentage' => 0, 'discount_amount' => 0, 'tax_amount' => 90, 'taxes' => [['name' => 'VAT', 'rate' => 15]], 'total' => 690],
            ],
            'totals' => ['subtotal' => 1600, 'discount' => 50, 'tax' => 232.5, 'total' => 1782.5, 'paid' => 0, 'balance' => 1782.5],
            'payment_terms' => 'Payment is due within 14 days.',
            'notes' => 'Thank you for choosing our company.',
            'qr' => $settings['show_qr'] ? [
                'url' => route('documents.settings.type', ['type' => $type]),
                'image' => $this->qrCodes->image(route('documents.settings.type', ['type' => $type])),
            ] : null,
        ];
    }
}
