<?php

namespace Tests\Unit;

use App\Http\Controllers\SettingController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class SettingsFormPayloadCompatibilityTest extends TestCase
{
    #[DataProvider('formPayloads')]
    public function test_settings_form_post_payload_is_accepted_by_its_allowlist(
        string $section,
        array $payload,
        array $roles
    ): void {
        $request = Request::create("/settings/{$section}", 'POST', ['settings' => $payload]);
        $controller = new SettingController();
        $reflection = new ReflectionClass($controller);
        $allowlists = $reflection->getConstant('SETTING_KEYS');
        $guard = new ReflectionMethod($controller, 'rejectUnknownSettingKeys');
        $guard->setAccessible(true);

        foreach ($roles as $role) {
            $guard->invoke($controller, $request, $allowlists[$section]);
            $this->addToAssertionCount(1);
        }
    }

    public static function formPayloads(): array
    {
        $shared = ['superadmin', 'company'];
        $platform = ['superadmin'];

        return [
            'superadmin brand' => ['brand_platform', [
                'logo_dark' => '', 'logo_light' => '', 'favicon' => '',
                'titleText' => 'Wazely ERP', 'footerText' => 'Copyright',
                'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain',
                'layoutDirection' => 'ltr', 'themeMode' => 'light',
                'themeColor' => 'green', 'customColor' => '#10b981',
            ], $platform],
            'company brand' => ['brand_company', [
                'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain',
                'layoutDirection' => 'ltr', 'themeMode' => 'light',
                'themeColor' => 'green', 'customColor' => '#10b981',
            ], ['company']],
            'company' => ['company', [
                'company_name' => 'Example Company', 'company_address' => '',
                'company_city' => '', 'company_state' => '', 'company_country' => '',
                'company_zipcode' => '', 'company_telephone' => '',
                'company_email_from_name' => '', 'registration_number' => '',
                'company_email' => 'company@example.test',
                'vat_gst_number_switch' => 'off', 'tax_type' => '', 'tax_number' => '',
            ], $shared],
            'superadmin system' => ['system_platform', [
                'defaultLanguage' => 'en', 'dateFormat' => 'Y-m-d',
                'timeFormat' => 'H:i', 'calendarStartDay' => '0',
                'enableRegistration' => 'off', 'enableEmailVerification' => 'off',
                'landingPageEnabled' => 'on', 'termsConditionsUrl' => '',
            ], $platform],
            'company system' => ['system_company', [
                'defaultLanguage' => 'en', 'dateFormat' => 'Y-m-d',
                'timeFormat' => 'H:i', 'calendarStartDay' => '0',
            ], ['company']],
            'currency' => ['currency', [
                'decimalFormat' => '2', 'defaultCurrency' => 'QAR',
                'decimalSeparator' => '.', 'thousandsSeparator' => ',',
                'floatNumber' => true, 'currencySymbolSpace' => true,
                'currencySymbolPosition' => 'postfix', 'currencySymbol' => 'QR',
                'currencyName' => 'Qatari Riyal',
            ], $shared],
            'cookie' => ['cookie', [
                'enableCookiePopup' => true, 'enableLogging' => true,
                'strictlyNecessaryCookies' => true, 'cookieTitle' => 'Cookies',
                'strictlyCookieTitle' => 'Necessary cookies',
                'cookieDescription' => 'Cookie details',
                'strictlyCookieDescription' => 'Required cookie details',
                'contactUsDescription' => 'Contact us',
                'contactUsUrl' => 'https://example.test/contact',
            ], $platform],
            'email' => ['email', [
                'provider' => 'smtp', 'driver' => 'smtp', 'host' => 'smtp.example.test',
                'port' => '587', 'username' => 'mailer@example.test', 'password' => '',
                'clear_password' => false, 'encryption' => 'tls',
                'fromAddress' => 'noreply@example.test',
            ], $platform],
            'seo' => ['seo', [
                'metaKeywords' => 'erp', 'metaTitle' => 'Wazely ERP',
                'metaDescription' => 'ERP software', 'metaImage' => '',
            ], $platform],
            'storage' => ['storage', [
                'storageType' => 'local', 'allowedFileTypes' => 'jpg,png',
                'maxUploadSize' => '2048', 'awsAccessKeyId' => '',
                'awsSecretAccessKey' => '', 'clearAwsSecretAccessKey' => false,
                'awsDefaultRegion' => 'us-east-1', 'awsBucket' => '', 'awsUrl' => '',
                'awsEndpoint' => '', 'wasabiAccessKey' => '', 'wasabiSecretKey' => '',
                'clearWasabiSecretKey' => false, 'wasabiRegion' => 'us-east-1',
                'wasabiBucket' => '', 'wasabiUrl' => '', 'wasabiRoot' => '',
            ], $platform],
            'bank transfer' => ['bank_transfer', [
                'bankTransferEnabled' => 'off', 'instructions' => '',
            ], $platform],
            'pusher' => ['pusher', [
                'app_id' => '123', 'app_key' => 'key', 'app_secret' => '',
                'clear_app_secret' => false, 'app_cluster' => 'mt1', 'enabled' => false,
            ], $platform],
            'social login' => ['social_login', [
                'social_login_enabled' => false, 'google_login_enabled' => false,
                'google_client_id' => '', 'google_client_secret' => '',
                'clear_google_client_secret' => false,
                'microsoft_login_enabled' => false, 'microsoft_client_id' => '',
                'microsoft_client_secret' => '', 'clear_microsoft_client_secret' => false,
                'microsoft_tenant_id' => 'common',
            ], $platform],
            'AI agent' => ['ai_agent', [
                'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o',
                'ai_agent_api_key' => '', 'clear_ai_agent_api_key' => false,
            ], ['company']],
        ];
    }

    public function test_ai_assistant_form_payload_matches_its_controller_allowlist(): void
    {
        $payload = [
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4',
            'ai_api_key' => '', 'clear_ai_api_key' => false,
        ];
        $source = file_get_contents(base_path(
            'packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php'
        ));

        foreach (['company'] as $role) {
            foreach (array_keys($payload) as $key) {
                $this->assertStringContainsString("'{$key}'", $source, "{$role}: {$key}");
            }
        }
    }

    public function test_follow_up_fields_have_endpoint_validation_and_currency_scope(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SettingController.php'));

        foreach ([
            'settings.vat_gst_number_switch', 'settings.tax_type', 'settings.tax_number',
            'settings.currencySymbol', 'settings.currencyName',
            'settings.enableCookiePopup', 'settings.enabled',
        ] as $field) {
            $this->assertStringContainsString("'{$field}' =>", $source);
        }

        $this->assertStringContainsString(
            "setSetting(\$key, \$value, null, Auth::user()->type === 'superadmin');",
            $this->methodSource($source, 'updateCurrencySettings', 'clearCache')
        );
        $this->assertStringContainsString("setSetting('pusher_' . \$key", $source);
    }

    private function methodSource(string $source, string $startMethod, string $nextMethod): string
    {
        $start = strpos($source, "public function {$startMethod}");
        $end = strpos($source, "public function {$nextMethod}", $start ?: 0);

        return $start === false ? '' : substr($source, $start, $end === false ? null : $end - $start);
    }
}
