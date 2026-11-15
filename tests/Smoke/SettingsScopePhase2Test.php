<?php

namespace Tests\Smoke;

use App\Mail\CommonEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\CentralAISettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsScopePhase2Test extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('type', 'superadmin')->orderBy('id')->firstOrFail();
        $this->company = User::where('type', 'company')->orderBy('id')->firstOrFail();
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
    }

    public function test_company_ai_switches_use_central_credentials_and_fail_closed(): void
    {
        $this->putSettings($this->admin->id, [
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o',
            'ai_agent_api_key' => 'test-central-key',
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4',
            'ai_api_key' => 'test-central-assistant-key',
        ]);
        $this->putSettings($this->company->id, [
            'ai_agent_enabled' => '1', 'ai_assistant_enabled' => '1',
        ]);

        $service = app(CentralAISettingsService::class);
        $this->assertSame('gpt-4o', $service->agentConfig()['model']);
        $this->assertSame('gpt-4', $service->assistantConfig()['model']);

        $this->putSettings($this->company->id, ['ai_agent_enabled' => '0']);
        $this->assertNull($service->agentConfig());

        $this->putSettings($this->admin->id, ['ai_api_key' => null]);
        $this->assertNull($service->assistantConfig());
    }

    public function test_superadmin_saves_and_audits_masked_central_ai_credentials(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'edit-platform-ai-settings', 'guard_name' => 'web'],
            ['module' => 'settings', 'label' => 'Edit Platform AI Settings', 'add_on' => 'general']
        );
        $this->admin->givePermissionTo($permission);
        $this->actingAs($this->admin)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->admin->security_version ?? 1)),
        ]);

        $response = $this->post(route('settings.platform-ai.update'), ['settings' => [
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o',
            'ai_agent_api_key' => 'replacement-agent-key', 'clear_ai_agent_api_key' => false,
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4',
            'ai_api_key' => 'replacement-assistant-key', 'clear_ai_api_key' => false,
        ]]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('settings', ['created_by' => $this->admin->id, 'key' => 'ai_agent_model', 'value' => 'gpt-4o']);
        $this->assertDatabaseHas('sensitive_setting_audits', ['user_id' => $this->admin->id, 'setting_key' => 'ai_agent_api_key']);
        $this->assertDatabaseHas('sensitive_setting_audits', ['user_id' => $this->admin->id, 'setting_key' => 'ai_api_key']);

        $payload = app(\App\Services\BrowserSettingsPayloadService::class)->settingsPage(getCompanyAllSetting($this->admin->id), true);
        $this->assertTrue($payload['ai_agent_api_key_configured']);
        $this->assertTrue($payload['ai_api_key_configured']);
        $this->assertArrayNotHasKey('ai_agent_api_key', $payload);
        $this->assertArrayNotHasKey('ai_api_key', $payload);
    }

    public function test_company_brand_and_system_reject_platform_fields_while_superadmin_accepts_them(): void
    {
        $companyBrand = $this->post(route('settings.brand.update'), ['settings' => [
            'logo_dark' => '', 'logo_light' => '', 'titleText' => 'Forbidden',
            'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain', 'layoutDirection' => 'ltr',
            'themeMode' => 'light', 'themeColor' => 'green', 'customColor' => '#10b981',
        ]]);
        $companyBrand->assertSessionHasErrors('settings');

        $companySystem = $this->post(route('settings.system.update'), ['settings' => [
            'defaultLanguage' => 'en', 'dateFormat' => 'Y-m-d', 'timeFormat' => 'H:i',
            'calendarStartDay' => '0', 'landingPageEnabled' => 'on',
        ]]);
        $companySystem->assertSessionHasErrors('settings');

        $this->actingAs($this->admin)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->admin->security_version ?? 1)),
        ]);
        $this->post(route('settings.brand.update'), ['settings' => [
            'logo_dark' => '', 'logo_light' => '', 'favicon' => '',
            'titleText' => 'Platform', 'footerText' => 'Footer',
            'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain', 'layoutDirection' => 'ltr',
            'themeMode' => 'light', 'themeColor' => 'green', 'customColor' => '#10b981',
        ]])->assertSessionHasNoErrors();
        $this->post(route('settings.system.update'), ['settings' => [
            'defaultLanguage' => 'en', 'dateFormat' => 'Y-m-d', 'timeFormat' => 'H:i',
            'calendarStartDay' => '0', 'enableRegistration' => 'off',
            'enableEmailVerification' => 'off', 'landingPageEnabled' => 'on',
            'termsConditionsUrl' => '',
        ]])->assertSessionHasNoErrors();
    }

    public function test_company_mail_uses_its_complete_smtp_or_central_fallback_envelope(): void
    {
        $template = EmailTemplate::whereHas('templateLangs', fn ($query) => $query->where('lang', 'en'))->firstOrFail();
        $this->putSettings($this->admin->id, [
            'email_host' => 'smtp.central.test', 'email_fromAddress' => 'central@example.test',
        ]);
        $this->putSettings($this->company->id, [
            'defaultLanguage' => 'en', 'company_name' => 'Tenant Company',
            'company_email' => 'reply@example.test', 'email_host' => null,
            'email_fromAddress' => null,
        ]);

        Mail::fake();
        $result = EmailTemplate::sendEmailTemplate($template->name, ['recipient@example.test'], [], $this->company->id);
        $this->assertTrue($result['is_success']);
        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) =>
            $mail->fromAddress === 'central@example.test'
            && $mail->fromName === 'Tenant Company'
            && $mail->replyToAddress === 'reply@example.test'
        );

        Mail::fake();
        $this->putSettings($this->company->id, [
            'email_host' => 'smtp.tenant.test', 'email_fromAddress' => 'tenant@example.test',
            'company_email_from_name' => 'Tenant Sender',
        ]);
        $result = EmailTemplate::sendEmailTemplate($template->name, ['recipient@example.test'], [], $this->company->id);
        $this->assertTrue($result['is_success']);
        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) =>
            $mail->fromAddress === 'tenant@example.test'
            && $mail->fromName === 'Tenant Sender'
            && $mail->replyToAddress === null
        );
    }

    public function test_company_page_payload_reports_central_ai_availability_without_key_state(): void
    {
        $this->putSettings($this->admin->id, [
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o', 'ai_agent_api_key' => 'test-central-key',
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4', 'ai_api_key' => 'test-central-assistant-key',
        ]);
        $this->putSettings($this->company->id, ['ai_agent_enabled' => '1', 'ai_assistant_enabled' => '1']);

        $shared = $this->get(route('settings.index'))->assertOk()->viewData('page')['props']['companyAllSetting'];
        $this->assertTrue($shared['ai_assistant_available']);
        $this->assertTrue($shared['ai_agent_available']);
        foreach (['ai_api_key', 'ai_agent_api_key', 'ai_api_key_configured', 'ai_agent_api_key_configured', 'ai_provider', 'ai_model'] as $key) {
            $this->assertArrayNotHasKey($key, $shared);
        }

        $this->putSettings($this->company->id, ['ai_assistant_enabled' => '0']);
        $shared = $this->get(route('settings.index'))->assertOk()->viewData('page')['props']['companyAllSetting'];
        $this->assertFalse($shared['ai_assistant_available']);
        $this->assertTrue($shared['ai_agent_available']);
    }

    public function test_direct_senders_use_central_mailer_when_company_smtp_is_missing(): void
    {
        $this->putSettings($this->admin->id, [
            'email_host' => 'smtp.central.test', 'email_fromAddress' => 'central@example.test',
        ]);
        $this->putSettings($this->company->id, [
            'company_name' => 'Tenant Company', 'company_email' => 'reply@example.test',
            'company_email_from_name' => null, 'email_host' => null, 'email_fromAddress' => null,
        ]);

        $this->assertTrue(SetConfigEmail($this->company->id));
        $this->assertSame('smtp.central.test', config('mail.mailers.smtp.host'));
        $this->assertSame('central@example.test', config('mail.from.address'));
        $this->assertSame('Tenant Company', config('mail.from.name'));
        $this->assertSame('reply@example.test', config('mail.reply_to.address'));

        $this->putSettings($this->company->id, [
            'email_host' => 'smtp.tenant.test', 'email_fromAddress' => 'tenant@example.test',
            'company_email_from_name' => 'Tenant Sender',
        ]);
        $this->assertTrue(SetConfigEmail($this->company->id));
        $this->assertSame('smtp.tenant.test', config('mail.mailers.smtp.host'));
        $this->assertSame('tenant@example.test', config('mail.from.address'));
        $this->assertSame('Tenant Sender', config('mail.from.name'));
        $this->assertNull(config('mail.reply_to'));
    }

    public function test_reminder_email_no_longer_requires_company_smtp(): void
    {
        $this->putSettings($this->admin->id, [
            'email_host' => 'smtp.central.test', 'email_fromAddress' => 'central@example.test',
        ]);
        $this->putSettings($this->company->id, [
            'company_name' => 'Tenant Company', 'company_email' => 'reply@example.test',
            'email_host' => null, 'email_fromAddress' => null,
        ]);

        Mail::fake();
        $reminder = (object) [
            'created_by' => $this->company->id,
            'name' => 'Follow up',
            'actions' => 'email',
            'action_config' => ['email' => ['email' => 'recipient@example.test', 'message' => 'Reminder body']],
        ];
        EmailTemplate::reminderSendEmail($reminder, null);

        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) => $mail->hasTo('recipient@example.test'));
    }

    public function test_helpdesk_job_keeps_the_central_mail_owner(): void
    {
        $source = file_get_contents(app_path('Jobs/SendHelpdeskEmail.php'));

        $this->assertStringContainsString("User::where('type', 'superadmin')", $source);
        $this->assertStringContainsString('SetConfigEmail($ownerId)', $source);
        $this->assertStringContainsString('$ownerId', $source);
    }

    private function putSettings(int $userId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'created_by' => $userId],
                ['value' => $value, 'is_public' => false]
            );
        }

        Cache::flush();
    }
}
