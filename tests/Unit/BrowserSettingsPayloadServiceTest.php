<?php

namespace Tests\Unit;

use App\Services\BrowserSettingsPayloadService;
use PHPUnit\Framework\TestCase;

class BrowserSettingsPayloadServiceTest extends TestCase
{
    public function test_shared_payload_contains_only_allowlisted_values_and_configuration_flags(): void
    {
        $payload = (new BrowserSettingsPayloadService())->shared([
            'titleText' => 'Wazely ERP',
            'defaultCurrency' => 'QAR',
            'email_password' => 'smtp-secret',
            'ai_api_key' => 'assistant-secret',
            'ai_agent_api_key' => 'agent-secret',
            'pusher_app_secret' => 'pusher-secret',
            'future_secret' => 'must-not-leak',
        ]);

        $this->assertSame('Wazely ERP', $payload['titleText']);
        $this->assertSame('QAR', $payload['defaultCurrency']);
        $this->assertTrue($payload['ai_api_key_configured']);
        $this->assertTrue($payload['ai_agent_api_key_configured']);
        $this->assertArrayNotHasKey('email_password', $payload);
        $this->assertArrayNotHasKey('ai_api_key', $payload);
        $this->assertArrayNotHasKey('ai_agent_api_key', $payload);
        $this->assertArrayNotHasKey('pusher_app_secret', $payload);
        $this->assertArrayNotHasKey('future_secret', $payload);
    }

    public function test_settings_page_masks_every_secret_and_reports_configuration_state(): void
    {
        $payload = (new BrowserSettingsPayloadService())->settingsPage([
            'email_host' => 'smtp.example.com',
            'email_password' => 'smtp-secret',
            'awsAccessKeyId' => 'visible-identifier',
            'awsSecretAccessKey' => 'storage-secret',
            'wasabiSecretKey' => '',
            'pusher_app_secret' => 'pusher-secret',
            'ai_api_key' => 'assistant-secret',
            'ai_agent_api_key' => null,
            'stripe_key' => 'pk_test_visible',
            'stripe_secret' => 'sk_test_secret',
            'stripe_enabled' => 'on',
            'paypal_client_id' => 'paypal-visible-id',
            'paypal_secret_key' => 'paypal-secret',
            'paypal_mode' => 'sandbox',
            'unknown_setting' => 'not-allowlisted',
        ]);

        $this->assertSame('smtp.example.com', $payload['email_host']);
        $this->assertSame('visible-identifier', $payload['awsAccessKeyId']);
        $this->assertTrue($payload['email_password_configured']);
        $this->assertTrue($payload['awsSecretAccessKey_configured']);
        $this->assertFalse($payload['wasabiSecretKey_configured']);
        $this->assertTrue($payload['pusher_app_secret_configured']);
        $this->assertTrue($payload['ai_api_key_configured']);
        $this->assertFalse($payload['ai_agent_api_key_configured']);
        $this->assertSame('pk_test_visible', $payload['stripe_key']);
        $this->assertSame('paypal-visible-id', $payload['paypal_client_id']);
        $this->assertTrue($payload['stripe_secret_configured']);
        $this->assertTrue($payload['paypal_secret_key_configured']);

        foreach ([
            'email_password',
            'awsSecretAccessKey',
            'wasabiSecretKey',
            'pusher_app_secret',
            'ai_api_key',
            'ai_agent_api_key',
            'stripe_secret',
            'paypal_secret_key',
            'unknown_setting',
        ] as $forbiddenKey) {
            $this->assertArrayNotHasKey($forbiddenKey, $payload);
        }
    }
}
