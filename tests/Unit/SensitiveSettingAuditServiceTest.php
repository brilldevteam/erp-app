<?php

namespace Tests\Unit;

use App\Services\SensitiveSettingAuditService;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;

class SensitiveSettingAuditServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_records_an_explicit_clear_without_a_secret_value(): void
    {
        $service = $this->partialServiceExpecting('email', 'email_password', 'cleared');

        $service->recordSubmittedSecret('email', 'email_password', true, null, true);
        $this->addToAssertionCount(1);
    }

    public function test_it_records_replacement_and_initial_configuration_actions(): void
    {
        $replacement = $this->partialServiceExpecting('ai_agent', 'ai_agent_api_key', 'replaced');
        $replacement->recordSubmittedSecret('ai_agent', 'ai_agent_api_key', true, 'new-secret', false);

        $configuration = $this->partialServiceExpecting('storage', 'awsSecretAccessKey', 'configured');
        $configuration->recordSubmittedSecret('storage', 'awsSecretAccessKey', false, 'new-secret', false);
        $this->addToAssertionCount(2);
    }

    public function test_it_does_not_audit_a_blank_unchanged_secret(): void
    {
        $service = Mockery::mock(SensitiveSettingAuditService::class, [Mockery::mock(Request::class)])
            ->makePartial();
        $service->shouldNotReceive('record');

        $service->recordSubmittedSecret('email', 'email_password', true, '', false);
        $this->addToAssertionCount(1);
    }

    private function partialServiceExpecting(string $group, string $key, string $action): SensitiveSettingAuditService
    {
        $service = Mockery::mock(SensitiveSettingAuditService::class, [Mockery::mock(Request::class)])
            ->makePartial();
        $service->shouldReceive('record')
            ->once()
            ->with($group, $key, $action);

        return $service;
    }
}
