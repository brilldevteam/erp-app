<?php

namespace App\Services;

use Illuminate\Support\Arr;

class BrowserSettingsPayloadService
{
    private const SHARED_KEYS = [
        'logo_dark',
        'logo_light',
        'favicon',
        'titleText',
        'footerText',
        'sidebarVariant',
        'sidebarStyle',
        'layoutDirection',
        'themeMode',
        'themeColor',
        'customColor',
        'metaTitle',
        'metaKeywords',
        'metaDescription',
        'metaImage',
        'defaultLanguage',
        'dateFormat',
        'timeFormat',
        'calendarStartDay',
        'decimalFormat',
        'defaultCurrency',
        'decimalSeparator',
        'thousandsSeparator',
        'floatNumber',
        'currencySymbolSpace',
        'currencySymbolPosition',
        'currencySymbol',
        'enableCookiePopup',
        'strictlyNecessaryCookies',
        'cookieTitle',
        'strictlyCookieTitle',
        'cookieDescription',
        'strictlyCookieDescription',
        'contactUsDescription',
        'contactUsUrl',
        'company_name',
        'company_address',
        'company_city',
        'company_state',
        'company_country',
        'company_zipcode',
        'company_telephone',
        'company_email_from_name',
        'registration_number',
        'company_email',
        'vat_gst_number_switch',
        'tax_type',
        'tax_number',
        'ai_provider',
        'ai_model',
        'ai_agent_provider',
        'ai_agent_model',
    ];

    private const SETTINGS_PAGE_KEYS = [
        ...self::SHARED_KEYS,
        'enableRegistration',
        'enableEmailVerification',
        'landingPageEnabled',
        'termsConditionsUrl',
        'enableLogging',
        'email_provider',
        'email_driver',
        'email_host',
        'email_port',
        'email_username',
        'email_encryption',
        'email_fromAddress',
        'storageType',
        'allowedFileTypes',
        'maxUploadSize',
        'awsAccessKeyId',
        'awsDefaultRegion',
        'awsBucket',
        'awsUrl',
        'awsEndpoint',
        'wasabiAccessKey',
        'wasabiRegion',
        'wasabiBucket',
        'wasabiUrl',
        'wasabiRoot',
        'pusher_app_id',
        'pusher_app_key',
        'pusher_app_cluster',
        'pusher_enabled',
        'bankTransferEnabled',
        'instructions',
        'stripe_key',
        'stripe_enabled',
        'paypal_client_id',
        'paypal_enabled',
        'paypal_mode',
        'contract_prefix',
    ];

    private const SECRET_CONFIGURATION_FLAGS = [
        'email_password' => 'email_password_configured',
        'ai_api_key' => 'ai_api_key_configured',
        'ai_agent_api_key' => 'ai_agent_api_key_configured',
        'pusher_app_secret' => 'pusher_app_secret_configured',
        'awsSecretAccessKey' => 'awsSecretAccessKey_configured',
        'wasabiSecretKey' => 'wasabiSecretKey_configured',
        'google_client_secret' => 'google_client_secret_configured',
        'microsoft_client_secret' => 'microsoft_client_secret_configured',
        'stripe_secret' => 'stripe_secret_configured',
        'paypal_secret_key' => 'paypal_secret_key_configured',
    ];

    public function shared(array $settings): array
    {
        $payload = Arr::only($settings, self::SHARED_KEYS);
        $payload['ai_api_key_configured'] = filled($settings['ai_api_key'] ?? null);
        $payload['ai_agent_api_key_configured'] = filled($settings['ai_agent_api_key'] ?? null);

        return $payload;
    }

    public function settingsPage(array $settings): array
    {
        $payload = Arr::only($settings, self::SETTINGS_PAGE_KEYS);

        foreach (self::SECRET_CONFIGURATION_FLAGS as $secretKey => $flagKey) {
            $payload[$flagKey] = filled($settings[$secretKey] ?? null);
        }

        return $payload;
    }
}
