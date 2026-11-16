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

    public function test_agent_and_assistant_use_only_the_company_credentials(): void
    {
        $this->putSettings($this->admin->id, [
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'central-agent-model',
            'ai_agent_api_key' => 'test-central-key',
            'ai_provider' => 'openai', 'ai_model' => 'central-assistant-model',
            'ai_api_key' => 'test-central-assistant-key',
        ]);
        $this->putSettings($this->company->id, [
            'ai_agent_provider' => null, 'ai_agent_model' => null, 'ai_agent_api_key' => null,
            'ai_provider' => null, 'ai_model' => null, 'ai_api_key' => null,
        ]);

        $service = app(CentralAISettingsService::class);
        // The super admin's keys are never used for a company.
        $this->assertNull($service->agentConfig());
        $this->assertNull($service->assistantConfig());

        $this->putSettings($this->company->id, [
            'ai_agent_provider' => 'anthropic', 'ai_agent_model' => 'company-agent-model',
            'ai_agent_api_key' => 'test-company-agent-key',
            'ai_provider' => 'google', 'ai_model' => 'company-assistant-model',
            'ai_api_key' => 'test-company-assistant-key',
        ]);
        $this->assertSame('test-company-agent-key', $service->agentConfig()['api_key']);
        $this->assertSame('company-assistant-model', $service->assistantConfig()['model']);
        $this->assertSame('test-company-assistant-key', $service->assistantConfig()['api_key']);
    }

    public function test_company_saves_its_own_ai_agent_key_masked_and_audited(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'edit-ai-agent-settings', 'guard_name' => 'web'],
            ['module' => 'settings', 'label' => 'Edit AI Agent Settings', 'add_on' => 'general']
        );
        $this->company->givePermissionTo($permission);

        $this->post(route('settings.ai-agent.update'), ['settings' => [
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o',
            'ai_agent_api_key' => 'company-agent-key', 'clear_ai_agent_api_key' => false,
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['created_by' => $this->company->id, 'key' => 'ai_agent_model', 'value' => 'gpt-4o']);
        $this->assertDatabaseHas('sensitive_setting_audits', ['user_id' => $this->company->id, 'setting_key' => 'ai_agent_api_key']);

        $payload = app(\App\Services\BrowserSettingsPayloadService::class)->settingsPage(getCompanyAllSetting($this->company->id));
        $this->assertSame('gpt-4o', $payload['ai_agent_model']);
        $this->assertTrue($payload['ai_agent_api_key_configured']);
        $this->assertArrayNotHasKey('ai_agent_api_key', $payload);
    }

    public function test_company_saves_its_own_ai_assistant_key_masked_and_audited(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'edit-ai-assistant-settings', 'guard_name' => 'web'],
            ['module' => 'settings', 'label' => 'Edit AI Assistant Settings', 'add_on' => 'AIAssistant']
        );
        $this->company->givePermissionTo($permission);

        $this->post(route('ai-assistant.settings.store'), ['settings' => [
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4',
            'ai_api_key' => 'company-assistant-key', 'clear_ai_api_key' => false,
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['created_by' => $this->company->id, 'key' => 'ai_model', 'value' => 'gpt-4']);
        $this->assertDatabaseHas('sensitive_setting_audits', ['user_id' => $this->company->id, 'setting_key' => 'ai_api_key']);

        $payload = app(\App\Services\BrowserSettingsPayloadService::class)->settingsPage(getCompanyAllSetting($this->company->id));
        $this->assertSame('gpt-4', $payload['ai_model']);
        $this->assertTrue($payload['ai_api_key_configured']);
        $this->assertArrayNotHasKey('ai_api_key', $payload);

        // Unknown keys are refused.
        $this->post(route('ai-assistant.settings.store'), ['settings' => [
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4', 'ai_agent_api_key' => 'x',
        ]])->assertSessionHasErrors();
    }

    public function test_company_brand_and_system_reject_platform_fields_while_superadmin_accepts_them(): void
    {
        $companyBrand = $this->post(route('settings.brand.update'), ['settings' => [
            'logo_dark' => '', 'logo_light' => '', 'titleText' => 'Forbidden',
            'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain', 'layoutDirection' => 'ltr',
            'themeMode' => 'light', 'themeColor' => 'green', 'customColor' => '#10b981',
        ]]);
        $companyBrand->assertSessionHasErrors('settings');

        // Logos are platform branding too: a company may save its theme but not logos.
        $this->post(route('settings.brand.update'), ['settings' => [
            'logo_dark' => 'company-logo.png',
            'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain', 'layoutDirection' => 'ltr',
            'themeMode' => 'light', 'themeColor' => 'green', 'customColor' => '#10b981',
        ]])->assertSessionHasErrors('settings');
        $this->post(route('settings.brand.update'), ['settings' => [
            'sidebarVariant' => 'inset', 'sidebarStyle' => 'plain', 'layoutDirection' => 'ltr',
            'themeMode' => 'light', 'themeColor' => 'green', 'customColor' => '#10b981',
        ]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('settings', ['created_by' => $this->company->id, 'key' => 'logo_dark', 'value' => 'company-logo.png']);

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

        // SMTP values a company saved before are ignored: it still sends through the central mailer.
        Mail::fake();
        $this->putSettings($this->company->id, [
            'email_host' => 'smtp.tenant.test', 'email_fromAddress' => 'tenant@example.test',
        ]);
        $result = EmailTemplate::sendEmailTemplate($template->name, ['recipient@example.test'], [], $this->company->id);
        $this->assertTrue($result['is_success']);
        Mail::assertSent(CommonEmailTemplate::class, fn (CommonEmailTemplate $mail) =>
            $mail->fromAddress === 'central@example.test'
            && $mail->fromName === 'Tenant Company'
            && $mail->replyToAddress === 'reply@example.test'
        );
    }

    public function test_company_cannot_save_or_test_smtp_settings(): void
    {
        foreach (['edit-email-settings', 'test-email'] as $name) {
            $this->company->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['module' => 'settings', 'label' => $name, 'add_on' => 'general']
            ));
        }
        Mail::fake();

        $this->post(route('settings.email.update'), ['settings' => [
            'provider' => 'smtp', 'driver' => 'smtp', 'host' => 'smtp.tenant.test', 'port' => '587',
            'username' => 'u', 'password' => 'p', 'encryption' => 'tls', 'fromAddress' => 'tenant@example.test',
        ]])->assertSessionHas('error');
        $this->assertDatabaseMissing('settings', ['created_by' => $this->company->id, 'key' => 'email_host', 'value' => 'smtp.tenant.test']);

        $this->post(route('settings.email.test'), ['email' => 'recipient@example.test'])->assertSessionHas('error');
        Mail::assertNothingSent();

        // The company settings page carries no SMTP details.
        $payload = app(\App\Services\BrowserSettingsPayloadService::class)->settingsPage(['email_host' => 'smtp.tenant.test', 'email_password' => 'secret']);
        $this->assertArrayNotHasKey('email_host', $payload);
        $this->assertArrayNotHasKey('email_password_configured', $payload);
    }

    public function test_company_page_payload_reports_ai_availability_without_key_state(): void
    {
        $this->putSettings($this->company->id, [
            'ai_provider' => 'openai', 'ai_model' => 'gpt-4', 'ai_api_key' => 'test-company-assistant-key',
            'ai_agent_provider' => 'openai', 'ai_agent_model' => 'gpt-4o', 'ai_agent_api_key' => 'test-company-agent-key',
        ]);

        $shared = $this->get(route('settings.index'))->assertOk()->viewData('page')['props']['companyAllSetting'];
        $this->assertTrue($shared['ai_assistant_available']);
        $this->assertTrue($shared['ai_agent_available']);
        foreach (['ai_api_key', 'ai_agent_api_key', 'ai_api_key_configured', 'ai_agent_api_key_configured', 'ai_provider', 'ai_model', 'ai_agent_provider', 'ai_agent_model'] as $key) {
            $this->assertArrayNotHasKey($key, $shared);
        }

        $this->putSettings($this->company->id, ['ai_api_key' => null]);
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

        // Saved company SMTP values are ignored; the company's own sender name is kept.
        $this->putSettings($this->company->id, [
            'email_host' => 'smtp.tenant.test', 'email_fromAddress' => 'tenant@example.test',
            'company_email_from_name' => 'Tenant Sender',
        ]);
        $this->assertTrue(SetConfigEmail($this->company->id));
        $this->assertSame('smtp.central.test', config('mail.mailers.smtp.host'));
        $this->assertSame('central@example.test', config('mail.from.address'));
        $this->assertSame('Tenant Sender', config('mail.from.name'));
        $this->assertSame('reply@example.test', config('mail.reply_to.address'));

        // The super admin uses its own SMTP, with no reply-to.
        $this->assertTrue(SetConfigEmail($this->admin->id));
        $this->assertSame('smtp.central.test', config('mail.mailers.smtp.host'));
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
