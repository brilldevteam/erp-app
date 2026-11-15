<?php

namespace Tests\Unit;

use App\Http\Controllers\SettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class SettingsScopeSecurityTest extends TestCase
{
    public function test_guest_branding_uses_only_allowlisted_superadmin_settings(): void
    {
        $source = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('Arr::only(getAdminAllSetting(true), $brandKeys)', $source);
        $this->assertStringNotContainsString('getCompanyAllSetting($latestBrandSetting->created_by', $source);

        foreach (['company_name', 'company_address', 'company_email', 'registration_number', 'tax_number'] as $privateKey) {
            $this->assertStringNotContainsString("'{$privateKey}'", $this->loginBrandMethodSource($source));
        }
    }

    public function test_unknown_setting_keys_are_rejected_with_validation_error(): void
    {
        $request = Request::create('/settings/brand', 'POST', [
            'settings' => [
                'titleText' => 'Allowed',
                'email_password' => 'must-not-be-written',
            ],
        ]);

        $method = new ReflectionMethod(SettingController::class, 'rejectUnknownSettingKeys');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);
        $method->invoke(new SettingController(), $request, ['titleText']);
    }

    public function test_all_core_setting_writers_apply_an_explicit_key_allowlist(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SettingController.php'));

        foreach ([
            'brand', 'company', 'system', 'currency', 'cookie', 'email', 'seo',
            'storage', 'bank_transfer', 'pusher', 'social_login', 'ai_agent',
        ] as $section) {
            $this->assertStringContainsString(
                "rejectUnknownSettingKeys(\$request, self::SETTING_KEYS['{$section}'])",
                $source,
                "Missing submitted-key validation for {$section}."
            );
        }

        $assistantSource = file_get_contents(
            base_path('packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php')
        );
        $this->assertStringContainsString('array_diff(array_keys($submittedSettings), $allowedKeys)', $assistantSource);
    }

    public function test_company_accounts_cannot_pass_the_platform_setting_role_guard(): void
    {
        $company = new class {
            public string $type = 'company';
            public function can(string $permission): bool { return true; }
        };
        Auth::shouldReceive('user')->once()->andReturn($company);

        $method = new ReflectionMethod(SettingController::class, 'canManagePlatformSetting');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(new SettingController(), 'clear-cache'));
    }

    public function test_company_smtp_rejects_private_hosts_but_superadmin_may_use_them(): void
    {
        $method = new ReflectionMethod(SettingController::class, 'rejectUnsafeCompanySmtpHost');
        $method->setAccessible(true);

        $company = (object) ['type' => 'company'];
        Auth::shouldReceive('user')->once()->andReturn($company);

        try {
            $method->invoke(new SettingController(), '127.0.0.1');
            $this->fail('A company SMTP configuration accepted a loopback host.');
        } catch (\ReflectionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(ValidationException::class, $exception->getPrevious() ?? $exception);
        }

        Auth::shouldReceive('user')->once()->andReturn((object) ['type' => 'superadmin']);
        $method->invoke(new SettingController(), '127.0.0.1');
        $this->addToAssertionCount(1);
    }

    public function test_test_email_error_does_not_return_raw_exception_details(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SettingController.php'));

        $this->assertStringContainsString('Failed to send test email. Please verify the email configuration.', $source);
        $this->assertStringNotContainsString("Failed to send test email: :error", $source);
        $this->assertStringNotContainsString("'error' => \$e->getMessage()", $source);
    }

    private function loginBrandMethodSource(string $source): string
    {
        $start = strpos($source, 'private function getPublicLoginBrandSetting');

        return $start === false ? '' : substr($source, $start);
    }
}
