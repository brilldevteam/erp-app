<?php

namespace App\Services\Documents;

class DocumentSettingsService
{
    public const TEMPLATES = ['classic', 'modern', 'minimal', 'zoho'];

    public function get(int $tenantId, string $type, ?string $templateKey = null): array
    {
        $prefix = $type === 'quotation' ? 'quotation' : 'invoice';
        $template = $this->validTemplate($templateKey ?: (company_setting("{$prefix}_template", $tenantId) ?: 'zoho'));
        $profile = "document_{$prefix}_{$template}";

        return [
            'template_key' => $template,
            'accent_color' => company_setting("{$profile}_accent_color", $tenantId) ?: company_setting('document_accent_color', $tenantId) ?: '#0f766e',
            'document_title' => company_setting("{$profile}_title", $tenantId) ?: company_setting("{$prefix}_document_title", $tenantId)
                ?: ($type === 'quotation' ? __('QUOTATION') : __('INVOICE')),
            'footer' => company_setting("{$profile}_footer", $tenantId) ?: company_setting('document_footer', $tenantId) ?: __('Thank you for your business!'),
            'payment_instructions' => $type === 'invoice' ? (company_setting("{$profile}_payment_instructions", $tenantId) ?: company_setting('document_payment_instructions', $tenantId) ?: '') : '',
            'document_default_logo' => company_setting("{$profile}_logo", $tenantId) ?: company_setting('document_default_logo', $tenantId) ?: '',
            'signature_image' => company_setting("{$profile}_signature_image", $tenantId) ?: '',
            'show_sku' => $this->profileToggle($profile, 'show_sku', $tenantId, true),
            'show_description' => $this->profileToggle($profile, 'show_description', $tenantId, true),
            'show_quantity' => $this->profileToggle($profile, 'show_quantity', $tenantId, true),
            'show_discount' => $this->profileToggle($profile, 'show_discount', $tenantId, true),
            'show_tax' => $this->profileToggle($profile, 'show_tax', $tenantId, true),
            'show_shipping' => $this->profileToggle($profile, 'show_shipping', $tenantId, true),
            'show_signature' => $this->profileToggle($profile, 'show_signature', $tenantId, true),
            'show_qr' => $this->profileToggle($profile, 'show_qr', $tenantId, true),
        ];
    }

    public function updateProfile(int $tenantId, string $type, string $template, array $input): void
    {
        $prefix = $type === 'quotation' ? 'quotation' : 'invoice';
        $profile = "document_{$prefix}_{$this->validTemplate($template)}";
        $values = [
            'accent_color' => $input['document_accent_color'] ?? '#0f766e',
            'title' => $input[$type === 'quotation' ? 'quotation_document_title' : 'invoice_document_title'] ?? '',
            'footer' => $input['document_footer'] ?? '',
            'logo' => $input['document_default_logo'] ?? '',
            'signature_image' => $input['document_signature_image'] ?? '',
            'show_sku' => !empty($input['document_show_sku']) ? 'on' : 'off',
            'show_description' => !empty($input['document_show_description']) ? 'on' : 'off',
            'show_quantity' => !empty($input['document_show_quantity']) ? 'on' : 'off',
            'show_discount' => !empty($input['document_show_discount']) ? 'on' : 'off',
            'show_tax' => !empty($input['document_show_tax']) ? 'on' : 'off',
            'show_shipping' => !empty($input['document_show_shipping']) ? 'on' : 'off',
            'show_signature' => !empty($input['document_show_signature']) ? 'on' : 'off',
            'show_qr' => !empty($input['document_show_qr']) ? 'on' : 'off',
        ];
        if ($type === 'invoice') {
            $values['payment_instructions'] = $input['document_payment_instructions'] ?? '';
        }

        foreach ($values as $key => $value) {
            setSetting("{$profile}_{$key}", $value, $tenantId, false);
        }
    }

    public function update(int $tenantId, array $input): void
    {
        $allowed = [
            'invoice_template', 'quotation_template', 'invoice_number_prefix',
            'quotation_number_prefix', 'document_number_padding', 'document_number_reset',
            'invoice_next_number', 'quotation_next_number', 'invoice_reminder_offsets',
        ];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                setSetting($key, is_bool($input[$key]) ? ($input[$key] ? 'on' : 'off') : (string) $input[$key], $tenantId, false);
            }
        }
    }

    private function validTemplate(string $template): string
    {
        return in_array($template, self::TEMPLATES, true) ? $template : 'zoho';
    }

    private function enabled(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array($value, [true, 1, '1', 'on', 'yes'], true);
    }

    private function profileToggle(string $profile, string $key, int $tenantId, bool $default): bool
    {
        $value = company_setting("{$profile}_{$key}", $tenantId);

        return $value === null || $value === ''
            ? $this->enabled(company_setting("document_{$key}", $tenantId), $default)
            : $this->enabled($value, $default);
    }
}
