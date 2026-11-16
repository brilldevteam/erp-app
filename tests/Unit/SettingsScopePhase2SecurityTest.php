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
        ]);

        // Pages other than Settings carry no AI provider, model or key.
        foreach (['ai_agent_provider', 'ai_agent_model', 'ai_agent_api_key', 'ai_provider', 'ai_model', 'ai_api_key'] as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }

        $settingsPage = app(BrowserSettingsPayloadService::class)->settingsPage([
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'agent-model',
            'ai_agent_api_key' => 'secret', 'ai_provider' => 'openai',
            'ai_model' => 'assistant-model', 'ai_api_key' => 'secret',
        ]);
        // The company's own AI settings are shown on its Settings page, with both keys masked.
        $this->assertSame('agent-model', $settingsPage['ai_agent_model']);
        $this->assertSame('assistant-model', $settingsPage['ai_model']);
        $this->assertTrue($settingsPage['ai_agent_api_key_configured']);
        $this->assertTrue($settingsPage['ai_api_key_configured']);
        foreach (['ai_agent_api_key', 'ai_api_key'] as $key) {
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
            ['ai_agent', ['ai_api_key' => 'Forbidden']],
        ] as [$section, $settings]) {
            try {
                $guard->invoke($controller, Request::create('/settings', 'POST', compact('settings')), $allowlists[$section]);
                $this->fail("{$section} accepted a platform-only key.");
            } catch (\Throwable $exception) {
                $this->assertInstanceOf(ValidationException::class, $exception->getPrevious() ?? $exception);
            }
        }
    }

    public function test_all_ai_readers_use_the_ai_settings_service(): void
    {
        foreach ([
            app_path('Http/Controllers/AIAgentChatPageController.php'),
            app_path('Services/AIAgentService.php'),
            base_path('packages/workdo/AIAssistant/src/Services/AIService.php'),
        ] as $path) {
            $source = file_get_contents($path);
            $this->assertStringContainsString('CentralAISettingsService', $source);
            $this->assertStringNotContainsString("admin_setting('ai_", $source);
        }

        // Companies use their own keys; the super admin's AI keys are never read for a company.
        $service = file_get_contents(app_path('Services/CentralAISettingsService.php'));
        $this->assertStringNotContainsString('admin_setting(', $service);
    }

    public function test_company_ai_endpoints_accept_only_their_own_keys(): void
    {
        $agent = file_get_contents(app_path('Http/Controllers/SettingController.php'));
        $assistant = file_get_contents(base_path('packages/workdo/AIAssistant/src/Http/Controllers/AIAssistantSettingsController.php'));

        $this->assertStringContainsString("'ai_agent' => ['ai_agent_provider', 'ai_agent_model', 'ai_agent_api_key', 'clear_ai_agent_api_key']", $agent);
        $this->assertStringContainsString("\$allowedKeys = ['ai_provider', 'ai_model', 'ai_api_key', 'clear_ai_api_key']", $assistant);
        $this->assertStringNotContainsString('central_ai', $agent);
    }
}
