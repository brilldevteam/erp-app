<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SensitiveSettingsAuthorizationTest extends TestCase
{
    #[DataProvider('protectedControllerProvider')]
    public function test_sensitive_setting_updates_enforce_the_expected_permission(
        string $path,
        string $permission
    ): void {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);

        $this->assertIsString($source);
        $this->assertStringContainsString("can('{$permission}')", $source);
        $this->assertStringContainsString("__('Permission denied')", $source);
    }

    public static function protectedControllerProvider(): array
    {
        return [
            'email settings' => ['app/Http/Controllers/SettingController.php', 'edit-email-settings'],
            'storage settings' => ['app/Http/Controllers/SettingController.php', 'edit-storage-settings'],
            'Pusher settings' => ['app/Http/Controllers/SettingController.php', 'edit-pusher-settings'],
            'social login settings' => ['app/Http/Controllers/SettingController.php', 'edit-social-login-settings'],
            'AI Agent settings' => ['app/Http/Controllers/SettingController.php', 'edit-ai-agent-settings'],
            'Stripe settings' => ['packages/workdo/Stripe/src/Http/Controllers/StripeSettingsController.php', 'edit-stripe-settings'],
            'PayPal settings' => ['packages/workdo/Paypal/src/Http/Controllers/PaypalSettingsController.php', 'edit-paypal-settings'],
            'AI Assistant settings' => ['packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php', 'edit-ai-assistant-settings'],
        ];
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_sensitive_setting_routes_require_authentication_and_verification(string $path): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);

        $this->assertIsString($source);
        $this->assertStringContainsString("'auth'", $source);
        $this->assertStringContainsString("'verified'", $source);
    }

    public static function protectedRouteProvider(): array
    {
        return [
            'core settings routes' => ['routes/web.php'],
            'Stripe settings routes' => ['packages/workdo/Stripe/src/Routes/web.php'],
            'PayPal settings routes' => ['packages/workdo/Paypal/src/Routes/web.php'],
            'AI Assistant settings routes' => ['packages/workdo/AIAssistant/src/Routes/web.php'],
        ];
    }

    #[DataProvider('protectedRequestProvider')]
    public function test_payment_setting_requests_reject_users_without_edit_permission(
        string $path,
        string $permission
    ): void {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);

        $this->assertIsString($source);
        $this->assertStringContainsString('Auth::check()', $source);
        $this->assertStringContainsString("can('{$permission}')", $source);
    }

    public static function protectedRequestProvider(): array
    {
        return [
            'Stripe request' => ['packages/workdo/Stripe/src/Http/Requests/UpdateStripeSettingsRequest.php', 'edit-stripe-settings'],
            'PayPal request' => ['packages/workdo/Paypal/src/Http/Requests/UpdatePaypalSettingsRequest.php', 'edit-paypal-settings'],
        ];
    }
}
