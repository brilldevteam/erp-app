<?php

namespace Tests\Unit;

use App\Http\Controllers\SettingController;
use App\Services\BrowserSettingsPayloadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class SettingsScopePhase2SecurityTest extends TestCase
{
    public function test_company_browser_payload_contains_no_ai_credentials(): void
    {
        $payload = app(BrowserSettingsPayloadService::class)->shared([
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'model',
            'ai_agent_api_key' => 'secret', 'ai_provider' => 'openai',
            'ai_model' => 'model', 'ai_api_key' => 'secret',
            'ai_agent_enabled' => '1', 'ai_assistant_enabled' => '1',
        ]);

        foreach (['ai_agent_provider', 'ai_agent_model', 'ai_agent_api_key', 'ai_provider', 'ai_model', 'ai_api_key'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
        $this->assertSame('1', $payload['ai_agent_enabled']);
        $this->assertSame('1', $payload['ai_assistant_enabled']);

        $settingsPage = app(BrowserSettingsPayloadService::class)->settingsPage([
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'model',
            'ai_agent_api_key' => 'secret', 'ai_provider' => 'openai',
            'ai_model' => 'model', 'ai_api_key' => 'secret',
        ]);
        foreach (['ai_agent_provider', 'ai_agent_model', 'ai_agent_api_key_configured', 'ai_provider', 'ai_model', 'ai_api_key_configured'] as $key) {
            $this->assertArrayNotHasKey($key, $settingsPage);
        }
    }

    public function test_company_allowlists_reject_platform_brand_system_and_ai_keys(): void
    {
        $controller = new SettingController();
        $reflection = new ReflectionClass($controller);
        $allowlists = $reflection->getConstant('SETTING_KEYS');
        $guard = new ReflectionMethod($controller, 'rejectUnknownSettingKeys');
        $guard->setAccessible(true);

        foreach ([
            ['brand_company', ['titleText' => 'Forbidden']],
            ['system_company', ['landingPageEnabled' => 'on']],
            ['ai_agent', ['ai_agent_api_key' => 'Forbidden']],
        ] as [$section, $settings]) {
            try {
                $guard->invoke($controller, Request::create('/settings', 'POST', compact('settings')), $allowlists[$section]);
                $this->fail("{$section} accepted a platform-only key.");
            } catch (\Throwable $exception) {
                $this->assertInstanceOf(ValidationException::class, $exception->getPrevious() ?? $exception);
            }
        }
    }

    public function test_all_ai_readers_use_the_central_settings_service(): void
    {
        foreach ([
            app_path('Http/Controllers/AIAgentChatPageController.php'),
            app_path('Services/AIAgentService.php'),
            base_path('packages/workdo/AIAssistant/src/Services/AIService.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertStringContainsString('CentralAISettingsService', $source);
            $this->assertStringNotContainsString("company_setting('ai_agent_api_key')", $source);
            $this->assertStringNotContainsString("company_setting('ai_api_key')", $source);
        }
    }

    public function test_company_ai_endpoints_accept_only_feature_switches(): void
    {
        $agent = file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $assistant = file_get_contents(base_path('packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php'));

        $this->assertStringContainsString("'ai_agent' => ['ai_agent_enabled']", $agent);
        $this->assertStringContainsString("\$allowedKeys = ['ai_assistant_enabled']", $assistant);
    }
}
