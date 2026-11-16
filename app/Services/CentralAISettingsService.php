<?php

namespace App\Services;

class CentralAISettingsService
{
    /**
     * The AI Agent runs on the company's own provider and API key (the company pays for its usage).
     */
    public function agentConfig(): ?array
    {
        return $this->companyConfig('ai_agent');
    }

    /**
     * The AI Assistant runs on the company's own provider and API key (the company pays for its usage).
     */
    public function assistantConfig(): ?array
    {
        return $this->companyConfig('ai');
    }

    /**
     * Browser-safe availability for the signed-in user's company: booleans only, never credentials.
     */
    public function availability(): array
    {
        return [
            'ai_assistant_available' => $this->assistantConfig() !== null,
            'ai_agent_available' => $this->agentConfig() !== null,
        ];
    }

    /**
     * The signed-in company's own provider, model and key. The super admin's keys are never used for a company.
     */
    private function companyConfig(string $prefix): ?array
    {
        $provider = company_setting("{$prefix}_provider");
        $model = company_setting("{$prefix}_model");
        $apiKey = company_setting("{$prefix}_api_key");

        if (blank($provider) || blank($model) || blank($apiKey)) {
            return null;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
        ];
    }
}
